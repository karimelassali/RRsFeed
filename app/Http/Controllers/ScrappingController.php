<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;
use App\Jobs\NotifyingWithNeewFeedJob;
use App\Models\RssFeedModel;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Berkayk\OneSignal\OneSignalFacade as OneSignal;
class ScrappingController extends Controller
{

        // $cacheKey = 'rss_feed_data';
        // $cacheDuration = 60; //In munites

        // $feedData =  RssFeedModel::all();

        // return response()->json([
        //     'length'=> count($feedData),
        //     'data' => $feedData,
        // ]);


public function scrappe(array $customConfigs = [])
{
    $rssLinks = [
        'https://www.ansa.it/valledaosta/notizie/valledaosta_rss.xml',
        'https://www.comune.aosta.it/it/events/feed',
        'https://www.comune.aosta.it/it/news/feed',
        'https://pressevda.regione.vda.it/it/events/feed',
        'https://pressevda.regione.vda.it/it/news/feed',
    ];

    $htmlLinks = [
        'https://appweb.regione.vda.it/dbweb/Comunicati.nsf/ElencoNotizie?OpenForm&l=ita',
    ];

    $newItems = [];

    // Process RSS feeds
    $rssItems = $this->processRssFeeds($rssLinks);

    // Process HTML pages
    $htmlItems = $this->processHtmlPages($htmlLinks);

    // Process custom URLs
    $customItems = $this->processCustomUrls($customConfigs);

    // Combine results
    $newItems = array_merge($rssItems, $htmlItems, $customItems);

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

        $payload = [
            'app_id' => config('services.onesignal.app_id'),
            'headings' => ['en' => $item['title']],
            'contents' => ['en' => $item['description']],
            'url' => $item['link'],
            'included_segments' => ['All'], // Send to all users
        ];

        try {
            // Initialize Guzzle client
            $client = new Client();

            // Send notification via OneSignal REST API
            $response = $client->post('https://onesignal.com/api/v1/notifications', [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Basic ' . config('services.onesignal.rest_api_key'),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                Log::info("Notification sent successfully for item: " . $item['title']);
            } else {
                Log::error("Failed to send notification for item: " . $item['title'] . ". Response: " . $response->getBody());
            }
        } catch (\Exception $e) {
            Log::error("Error sending notification for item: " . $item['title'] . ". Exception: " . $e->getMessage());
        }
    }


    return response()->json([
        'totalData' => count($newItems),
        'data' => $newItems
    ]);
}

private function processRssFeeds(array $links): array
{
    $items = [];

    foreach ($links as $sourceUrl) {
        try {
            // Make single requests instead of using pool to better handle errors
            $response = Http::timeout(30)->get($sourceUrl);

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
                        if (str_contains($sourceUrl, 'ansa.it')) {
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
            $pubDate = $pubDateSelector ? $this->extractContent($crawler, $pubDateSelector) : date('Y-m-d H:i:s');

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



    /**
     * Retrieves a specific item from the RSS feed based on the provided number.
     *
     * @param Request $request The incoming request containing the 'number' parameter.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the specified item and the total count of items,
     *                                       or an error message if the number is invalid or an exception occurs.
     */



    public function news(Request $request)
    {
        $crawler = new Crawler();
        $request = file_get_contents('https://appweb.regione.vda.it/dbweb/Comunicati.nsf/ElencoNotizie_ita/744ED6607E2535CCC1258C11003D0C98?OpenDocument&l=ita&');
        $crawler->addHtmlContent($request);
        $title = $crawler->filter('.testi')->text();
        $data = $crawler->filter('p');
        $result = [];

        foreach ($data as $item) {

            $text = $item->textContent;
            $result[] = [
                'title' => trim(preg_replace('/\n|\t/','', $title)),
                'description' => trim(preg_replace('/\n|\t/', '', preg_replace('/Riproduzione riservata \x{a9} Copyright ANSA/', '', $text)))
            ];
        }

        return response()->json(['data' => $result]);
    }
}
