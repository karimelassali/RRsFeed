<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\RssFeedModel;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;

class Scrapping
{
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

    public function readMore($id)
    {
        return RssFeedModel::find($id);
    }

    public function scrappe(array $customConfigs = [])
    {
        $newItems = [];

        if (!empty($customConfigs)) {
            $newItems = $this->processCustomUrls($customConfigs);
        } else {
            $rssLinks = config('rssfee_sources.links');
            $rssItems = $this->processRssFeeds($rssLinks);
            $newItems = array_merge($rssItems);
        }

        if (empty($newItems)) {
            return response()->json(['totalData' => 0, 'data' => []]);
        }

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
                $this->sendNotification(
                    'New update: ' . $item['title'],
                    $item['description'],
                    $item['link'],
                    'https://api.onesignal.com'
                );
            } catch (\Exception $e) {
                Log::error('Notification failed for item ' . $item['title'] . ': ' . $e->getMessage());
            }
        }

        return response()->json([
            'totalData' => count($newItems),
            'data' => $newItems
        ]);
    }

    public function sendNotification($title, $message, $url, $restApiUrl)
    {
        $appId = 'e8dd6f91-e21d-4a9c-bab4-f8440b7d63b0';
        $restApiKey = 'os_v2_app_5dow7epcdvfjzovu7bcaw7ldwcijmaimzf7unvet2utpbxyy7yfqxkfrlpi4wk4xezcifgjkoo4w4hlq6hqcm5swzffepffe66ztclq';

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $restApiKey,
        ])->post($restApiUrl . '/notifications', [
            'app_id' => $appId,
            'contents' => ['en' => $message],
            'headings' => ['en' => $title],
            'url' => $url,
            'included_segments' => ['All'],
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
            Log::info('Processing RSS feed: ' . $sourceUrl);
    
            try {
                // Explicitly set cURL options to override defaults
                $response = Http::withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_CONNECTTIMEOUT => 30, // 30 seconds to connect
                        CURLOPT_TIMEOUT => 60,        // 60 seconds total timeout
                    ],
                ])
                ->retry(5, 2000) // 5 retries, 2-second delay
                ->get($sourceUrl);
    
                if (!$response->successful()) {
                    Log::error("Failed to fetch RSS feed {$sourceUrl}: HTTP {$response->status()}");
                    continue;
                }
    
                $crawler = new Crawler();
                $crawler->addXmlContent($response->body());
    
                foreach ($crawler->filter('item') as $item) {
                    try {
                        $itemCrawler = new Crawler($item);
    
                        if (!$itemCrawler->filterXPath('.//link')->count()) {
                            Log::warning("No link found for item in {$sourceUrl}");
                            continue;
                        }
    
                        $link = $itemCrawler->filterXPath('.//link')->text();
    
                        if (RssFeedModel::where('link', $link)->exists()) {
                            continue;
                        }
    
                        $itemResponse = Http::withOptions([
                            'verify' => false,
                            'curl' => [
                                CURLOPT_CONNECTTIMEOUT => 30,
                                CURLOPT_TIMEOUT => 60,
                            ],
                        ])
                        ->retry(3, 1000)
                        ->get($link);
    
                        if (!$itemResponse->successful()) {
                            Log::error("Failed to fetch content for {$link}: HTTP {$itemResponse->status()}");
                            continue;
                        }
    
                        $itemContentCrawler = new Crawler($itemResponse->body());
    
                        $title = null;
                        $description = null;
    
                        try {
                            if (str_contains($sourceUrl, 'ansa.it')) {
                                $title = $this->extractContent($itemContentCrawler, '.post-single-title');
                                $description = $this->extractContent($itemContentCrawler, '.post-single-text');
                            } elseif (str_contains($sourceUrl, 'comune.aosta.it')) {
                                $title = $this->extractContent($itemContentCrawler, '[data-element="news-title"]');
                                $description = $this->extractContent($itemContentCrawler, '.page-content.paragraph');
                            } elseif (str_contains($sourceUrl, 'pressevda.regione.vda.it') || str_contains($sourceUrl, 'appweb.regione.vda.it')) {
                                $rawTitle = $this->extractContent($itemContentCrawler, 'h1.testi');
                                $title = $this->removeDateFromTitle($rawTitle);
                                $description = $this->extractContent($itemContentCrawler, '#contentgc');
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

    private function removeDateFromTitle(?string $title): ?string
    {
        if (!$title) {
            return null;
        }

        $pattern = '/^\d{2}\/\d{2}\/\d{4}\s*-\s*\d{2}:\d{2}\s*-\s*/';
        $cleanedTitle = preg_replace($pattern, '', $title);

        return trim($cleanedTitle);
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

                $title = $this->extractContent($crawler, $titleSelector);
                $description = $this->extractContent($crawler, $descriptionSelector);

                $pubDate = $pubDateSelector ? $this->extractContent($crawler, $pubDateSelector) : null;

                if ($pubDate) {
                    try {
                        $pubDate = Carbon::parse($pubDate)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        Log::warning("Failed to parse pubDate from {$sourceUrl}: " . $e->getMessage());
                        $pubDate = date('Y-m-d H:i:s');
                    }
                } else {
                    $pubDate = date('Y-m-d H:i:s');
                }

                if (!$title || !$description) {
                    Log::warning("Missing title or description for {$sourceUrl}");
                    continue;
                }

                $items[] = [
                    'source' => $sourceUrl,
                    'title' => $title,
                    'link' => $sourceUrl,
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