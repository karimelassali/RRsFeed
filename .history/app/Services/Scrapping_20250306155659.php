<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\RssFeedModel;
use Symfony\Component\DomCrawler\Crawler;
use App\Jobs\NotifyingWithNeewFeedJob;
use Berkayk\OneSignal\OneSignalFacade as OneSignal;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;

class Scrapping
{
    public function index()
    {
        try {
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
            Log::info("Processing source: {$sourceUrl}");

            try {
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

                        if (!$itemCrawler->filterXPath('.//link')->count()) {
                            Log::warning("No link found for item in {$sourceUrl}");
                            continue;
                        }

                        $link = $itemCrawler->filterXPath('.//link')->text();

                        if (RssFeedModel::where('link', $link)->exists()) {
                            continue;
                        }

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
                                sleep(1);
                            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                                Log::error("Connection error for {$link}: " . $e->getMessage());
                                $attempt++;
                                if ($attempt >= $maxRetries) {
                                    continue 2;
                                }
                                sleep(1);
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

                        $html = null;
                        $itemContentCrawler = null;
                        if (str_contains($sourceUrl, 'pressevda.regione.vda.it')) {
                            $html = Browsershot::url($link)
                                ->waitUntilNetworkIdle()
                                ->timeout(30000)
                                ->bodyHtml();
                            $itemContentCrawler = new Crawler($html);
                            Log::debug("Raw HTML length for {$link}: " . strlen($html));
                            Log::debug("Raw HTML snippet for {$link}: " . substr($html, 0, 2000) . '...');
                        } else {
                            $html = $itemResponse->body();
                            $itemContentCrawler = new Crawler($html);
                            Log::debug("Raw HTML length for {$link}: " . strlen($html));
                            Log::debug("Raw HTML snippet for {$link}: " . substr($html, 0, 2000) . '...');
                        }

                        $title = null;
                        $description = null;

                        if (str_contains($sourceUrl, 'ansa.it')) {
                            $title = $this->extractContentWithFallback($itemContentCrawler, ['.post-single-title']);
                            $description = $this->extractContentWithFallback($itemContentCrawler, ['.post-single-text']);
                        } elseif (str_contains($sourceUrl, 'comune.aosta.it')) {
                            $title = $this->extractContentWithFallback($itemContentCrawler, ['[data-element="news-title"]']);
                            $description = $this->extractContentWithFallback($itemContentCrawler, ['.page-content.paragraph']);
                        } elseif (str_contains($sourceUrl, 'pressevda.regione.vda.it')) {
                            $titleSelectors = [
                                'h1.testi',            // Primary title
                                'h1',                  // Fallback
                                '#bread ul li:last-child', // Breadcrumb fallback
                            ];
                            $descriptionSelectors = [
                                '#contentgc p',        // Primary content paragraphs
                                '#contentgc',          // Entire content div
                                'p',                   // Any paragraph
                            ];

                            $title = $this->extractContentWithFallback($itemContentCrawler, $titleSelectors);
                            Log::debug("Title extracted for {$link}: " . ($title ?? 'null'));

                            // Extract all text from #contentgc p tags and join them
                            $descriptionNodes = $itemContentCrawler->filter('#contentgc p');
                            if ($descriptionNodes->count()) {
                                $description = $descriptionNodes->each(function (Crawler $node) {
                                    return trim($node->text());
                                });
                                $description = implode("\n", array_filter($description));
                            }
                            if (empty($description)) {
                                $description = $this->extractContentWithFallback($itemContentCrawler, $descriptionSelectors);
                            }
                            Log::debug("Description extracted for {$link}: " . ($description ? substr($description, 0, 100) . '...' : 'null'));

                            // Fallback to raw HTML parsing if Crawler fails
                            if (!$title && $html) {
                                if (preg_match('/<h1 class="testi">(.*?)<\/h1>/i', $html, $match)) {
                                    $title = trim($match[1]);
                                    Log::debug("Fallback title extracted from raw HTML for {$link}: " . $title);
                                }
                            }
                            if (!$description && $html) {
                                if (preg_match('/<div id="contentgc">.*?<p>(.*?)(?:<div align="right">|<br>)/is', $html, $match)) {
                                    $description = trim(strip_tags($match[1]));
                                    Log::debug("Fallback description extracted from raw HTML for {$link}: " . substr($description, 0, 100) . '...');
                                }
                            }
                        }

                        if (!$title) {
                            Log::warning("No title found for {$link} after trying all selectors and raw HTML fallback");
                        }
                        if (!$description) {
                            Log::warning("No description found for {$link} after trying all selectors and raw HTML fallback");
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
                                $pubDate = Carbon::parse($itemCrawler->filterXPath('.//pubDate')->text())->format('Y-m-d H:i:s');
                            } catch (\Exception $e) {
                                Log::warning("Error parsing pubDate from {$link}, using current date: " . $e->getMessage());
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

    private function extractContentWithFallback(Crawler $crawler, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if ($nodes->count()) {
                    $text = trim($nodes->text());
                    if (!empty($text)) {
                        Log::debug("Extracted content with selector '{$selector}': " . substr($text, 0, 100) . '...');
                        return $text;
                    } else {
                        Log::debug("Selector '{$selector}' found but text is empty");
                    }
                } else {
                    Log::debug("No elements found for selector '{$selector}'");
                }
            } catch (\Exception $e) {
                Log::error("Error extracting content with selector {$selector}: " . $e->getMessage());
            }
        }
        return null;
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

                $title = $this->extractContentWithFallback($crawler, [$titleSelector]);
                $description = $this->extractContentWithFallback($crawler, [$descriptionSelector]);
                $pubDate = $pubDateSelector ? $this->extractContentWithFallback($crawler, [$pubDateSelector]) : null;

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
}
