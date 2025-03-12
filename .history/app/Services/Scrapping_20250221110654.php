<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\RssFeedModel;
use Illuminate\Http\Client\Pool;

use Symfony\Component\DomCrawler\Crawler;
use App\Jobs\NotifyingWithNeewFeedJob;

use Berkayk\OneSignal\OneSignalFacade as OneSignal;

use Carbon\Carbon;


class Scrapping {
    public function index()
    {
        try {
            $currentDate = Carbon::now();
            return RssFeedModel::orderBy('id', 'desc')->paginate();
        } catch (\Exception $e) {
            Log::error('An error occurred while fetching the RSS feeds: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching the RSS feeds.'], 500);
        }
    }
    public function readMore($id){
        return RssFeedModel::find($id);
    }


public function scrappe(array $customConfigs = [])
{
    $newItems = [];

    // If custom configurations are provided, skip default sources
    if (!empty($customConfigs)) {
        $newItems = $this->processCustomUrls($customConfigs);
    } else {
        // Default RSS and HTML links
        $rssLinks = config('rssfee_sources.links');

        $htmlLinks = [
            'https://appweb.regione.vda.it/dbweb/Comunicati.nsf/ElencoNotizie?OpenForm&l=ita',
        ];

        // Process RSS feeds
        $rssItems = $this->processRssFeeds($rssLinks);

        // Process HTML pages
        $htmlItems = $this->processHtmlPages($htmlLinks);

        // Combine results
        $newItems = array_merge($rssItems, $htmlItems);
    }

    // If no new items found, return early
    if (empty($newItems)) {
        return response()->json(['totalData' => 0, 'data' => []]);
    }

    // Store all items and send notifications
    foreach ($newItems as $item) {
        RssFeedModel::create([
            'source' => $item['source'],
            'title' => $item['title'],
            'link' => $item['link'],
            'description' => str_replace('ANSA', 'Riproduzione riservata  Copyright Digival', $item['description']),
            'pubDate' => $item['pubDate'],
            'isPublished' => 0,
        ]);

        try {
            // Pass the API URL directly in the notification function
            $this->sendNotification(
                'New update: ' . $item['title'],  // Notification title
                $item['description'],             // Notification message
                $item['link'],                    // URL for the notification
                'https://api.onesignal.com',      // Direct REST API URL for OneSignal
                $item['image'] ?? 'https://imgs.search.brave.com/msQNvh8YagNsZYKF5ZNNsxh9fIJahOTipF7UcsmRg6Q/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly90My5m/dGNkbi5uZXQvanBn/LzAyLzQxLzQ1LzY4/LzM2MF9GXzI0MTQ1/Njg5OF9OYnZ5N3Vk/VXh1RENzOHdJbTNj/eGZLdUZiM0p3VnEx/aS5qcGc'            // Image URL
            );
        } catch (\Exception $e) {
            // Handle notification failure
            Log::error('Notification failed for item ' . $item['title'] . ': ' . $e->getMessage());
        }

        // dispatch(new NotifyingWithNeewFeedJob([$item['title']], [$item['description']]));
    }

    return response()->json([
        'totalData' => count($newItems),
        'data' => $newItems
    ]);
}

    public function sendNotification($title, $message, $url, $restApiUrl)
    {
        $appId = config('services.onesignal.app_id');
        $restApiKey = config('services.onesignal.rest_api_key');

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $restApiKey,
        ])->post($restApiUrl . '/notifications', [
            'app_id' => $appId,
            'contents' => ['en' => $message],
            'headings' => ['en' => $title],
            'url' => $url,
            'included_segments' => ['All'],  // Send to all users
        ]);

        if ($response->successful()) {
            Log::info("Notification sent successfully!");
        } else {
            Log::error("Notification failed: " . $response->body());
        }
    }


    private function processRssFeeds(array $links): array
    {
        $items = [];

        foreach ($links as $sourceUrl) {
            try {
                // Make single requests instead of using pool to better handle errors
                $response = Http::timeout(30)->withOptions(['verify' => false])->get($sourceUrl);

                if (!$response->successful()) {
                    Log::error("Failed to fetch RSS feed {$sourceUrl}: HTTP {$response->status()}");
                    continue;
                }

                $crawler = new Crawler();
                $crawler->addXmlContent($response->body());

                foreach ($crawler->filter('item') as $item) {
                    try {
                        $itemCrawler = new Crawler($item);

                        // Safely extract link
                        if (!$itemCrawler->filterXPath('.//link')->count()) {
                            Log::warning("No link found for item in {$sourceUrl}");
                            continue;
                        }

                        $link = $itemCrawler->filterXPath('.//link')->text();

                        // Skip if already exists
                        if (RssFeedModel::where('link', $link)->exists()) {
                            continue;
                        }

                        // Get content with retry logic
                        $maxRetries = 3;
                        $attempt = 0;
                        $itemResponse = null;

                        while ($attempt < $maxRetries) {
                            try {
                                $itemResponse = Http::timeout(15)->get($link);
                                if ($itemResponse->successful()) {
                                    break;
                                }
                                $attempt++;
                                sleep(1); // Wait before retry
                            } catch (\Exception $e) {
                                Log::error("Attempt {$attempt} failed for {$link}: " . $e->getMessage());
                                $attempt++;
                                if ($attempt >= $maxRetries) {
                                    throw $e;
                                }
                                sleep(1);
                            }
                        }

                        if (!$itemResponse || !$itemResponse->successful()) {
                            Log::error("Failed to fetch content after {$maxRetries} attempts: {$link}");
                            continue;
                        }

                        $itemContentCrawler = new Crawler($itemResponse->body());

                        // Extract content based on source with null coalescing
                        $title = null;
                        $description = null;

                        try {
                            Log::info('This source is ' . $sourceUrl);
                            if(str_contains($sourceUrl,'appweb.regione.vda.it')){
                                $title = $this->extractContent($itemContentCrawler,'.testi');
                                $crawler->filter('table.noborder p:first-child');
                            }
                            elseif (str_contains($sourceUrl, 'ansa.it')) {
                                $title = $this->extractContent($itemContentCrawler, '.post-single-title');
                                $description = $this->extractContent($itemContentCrawler, '.post-single-text');
                            } elseif (str_contains($sourceUrl, 'comune.aosta.it')) {
                                $title = $this->extractContent($itemContentCrawler, '[data-element="news-title"]');
                                $description = $this->extractContent($itemContentCrawler, '.page-content.paragraph');
                            } elseif (str_contains($sourceUrl, 'pressevda.regione.vda.it')) {
                                $title = $this->extractContent($itemContentCrawler, 'h1');
                                $description = $this->extractContent($itemContentCrawler, '.content');
                            }
                        } catch (\Exception $e) {
                            Log::error("Error extracting content from {$link}: " . $e->getMessage());
                            continue;
                        }

                        if (!$title || !$description) {
                            Log::warning("Missing title or description for {$link}");
                            continue;
                        }

                        $description = str_replace(
                            "Riproduzione riservata  Copyright ANSA",
                            'Riproduzione riservata  Copyright Digival',
                            $description
                        );

                        // Safely get pubDate with fallback
                        $pubDate = date('Y-m-d H:i:s');
                        if ($itemCrawler->filterXPath('.//pubDate')->count()) {
                            try {
                                $pubDate = $itemCrawler->filterXPath('.//pubDate')->text();
                            } catch (\Exception $e) {
                                Log::warning("Error extracting pubDate from {$link}, using current date");
                            }
                        }

                        $items[] = [
                            'source' => $sourceUrl,
                            'title' => $title,
                            'link' => $link,
                            'description' => $description,
                            'pubDate' => $pubDate,
                        ];

                    } catch (\Exception $e) {
                        Log::error("Error processing item from {$sourceUrl}: " . $e->getMessage());
                        continue;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error processing RSS feed {$sourceUrl}: " . $e->getMessage());
                continue;
            }
        }

        return $items;
    }

    private function processHtmlPages(array $links): array
    {
        $items = [];

        // Fetch HTML pages concurrently
        $responses = Http::pool(fn (\Illuminate\Http\Client\Pool $pool) =>
            array_map(fn ($link) => $pool->get($link), $links)
        );

        foreach ($responses as $index => $response) {
            $sourceUrl = $links[$index];

            try {
                $crawler = new Crawler($response->body());

                // Extract news items
                $crawler->filter('')->each(function ($node) use (&$items, $sourceUrl) {
                    $link = $node->attr('href');

                    // Handle relative URLs
                    if (!filter_var($link, FILTER_VALIDATE_URL)) {
                        $parsedUrl = parse_url($sourceUrl);
                        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                        $link = $baseUrl . ($link[0] === '/' ? '' : '/') . $link;
                    }

                    // Skip if already exists
                    if (RssFeedModel::where('link', $link)->exists()) {
                        return;
                    }

                    // Get content
                    $itemResponse = Http::get($link);
                    $itemContentCrawler = new Crawler($itemResponse->body());

                    $title = $this->extractContent($itemContentCrawler, '#News1');
                    $description = $this->extractContent($itemContentCrawler, '#News1');

                    $items[] = [
                        'source' => $sourceUrl,
                        'title' => $title,
                        'link' => $link,
                        'description' => $description,
                        'pubDate' => date('Y-m-d H:i:s'),
                    ];
                });
            } catch (\Exception $e) {
                Log::error("Error processing HTML page {$sourceUrl}: " . $e->getMessage());
                continue;
            }
        }

        return $items;
    }

    private function processCustomUrls(array $customConfigs): array
    {
        $items = [];

        foreach ($customConfigs as $config) {
            $sourceUrl = $config['url'];
            $titleSelector = $config['title_selector'] ?? 'h1';
            $descriptionSelector = $config['description_selector'] ?? 'p';
            $pubDateSelector = $config['pub_date_selector'] ?? null;

            try {
                $response = Http::timeout(30)->get($sourceUrl);

                if (!$response->successful()) {
                    Log::error("Failed to fetch custom URL {$sourceUrl}: HTTP {$response->status()}");
                    continue;
                }

                $crawler = new Crawler($response->body());

                // Extract title
                $title = $this->extractContent($crawler, $titleSelector);

                // Extract description
                $description = $this->extractContent($crawler, $descriptionSelector);

                // Extract publication date if selector is provided
                $pubDate = $pubDateSelector ? $this->extractContent($crawler, $pubDateSelector) : null;

                // Parse the publication date into a real date format
                if ($pubDate) {
                    try {
                        // Use Carbon to parse the date (supports multiple formats)
                        $pubDate = \Carbon\Carbon::parse($pubDate)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        Log::warning("Failed to parse pubDate from {$sourceUrl}: " . $e->getMessage());
                        $pubDate = date('Y-m-d H:i:s'); // Fallback to current date
                    }
                } else {
                    $pubDate = date('Y-m-d H:i:s'); // Fallback to current date
                }

                if (!$title || !$description) {
                    Log::warning("Missing title or description for {$sourceUrl}");
                    continue;
                }

                $items[] = [
                    'source' => $sourceUrl,
                    'title' => $title,
                    'link' => $sourceUrl, // Assuming the URL itself is the link
                    'description' => $description,
                    'pubDate' => $pubDate,
                ];

            } catch (\Exception $e) {
                Log::error("Error processing custom URL {$sourceUrl}: " . $e->getMessage());
                continue;
            }
        }

        return $items;
    }

    private function extractContent(Crawler $crawler, string $selector): ?string
    {
        try {
            return $crawler->filter($selector)->count() ? trim($crawler->filter($selector)->text()) : null;
        } catch (\Exception $e) {
            Log::error("Error extracting content with selector {$selector}: " . $e->getMessage());
            return null;
        }
    }
}
