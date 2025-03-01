<?php



namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\RssFeedModel;
use Illuminate\Http\Client\Pool;

use Symfony\Component\DomCrawler\Crawler;
use App\Jobs\NotifyingWithNeewFeedJob;

use Carbon\Carbon;
use Illuminate\Support\Arr;

use GuzzleHttp\Promise;

class AiService
{

    public function aiRefactoredDescription( $article ,  $prompt )
    {
        try {


            $apiKey = env('GEMINI_API_KEY');
            if (empty($apiKey)) {
                return response()->json([
                    'error' => 'API key not configured'
                ], 500);
            }

            // Define prompts for title and description
            $titlePrompt = $prompt['title']. ":\n\n" . $article['title'];
            $descriptionPrompt = $prompt['description']. ":\n\n" . $article['description'];

            // Create a pool of asynchronous requests
            $client = new \GuzzleHttp\Client();
            $promises = [
                'title' => $client->postAsync(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey,
                    [
                        'json' => [
                            'contents' => [
                                [
                                    'parts' => [
                                        ['text' => $titlePrompt]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ),
                'description' => $client->postAsync(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey,
                    [
                        'json' => [
                            'contents' => [
                                [
                                    'parts' => [
                                        ['text' => $descriptionPrompt]
                                    ]
                                ]
                            ]
                        ]
                    ]
                )
            ];

            // Wait for both requests to complete
            $results = \GuzzleHttp\Promise\Utils::unwrap($promises);

            // Check if the title request was successful
            if ($results['title']->getStatusCode() !== 200) {
                return response()->json([
                    'error' => 'Title API request failed: ' . $results['title']->getBody()
                ], $results['title']->getStatusCode());
            }

            // Check if the description request was successful
            if ($results['description']->getStatusCode() !== 200) {
                return response()->json([
                    'error' => 'Description API request failed: ' . $results['description']->getBody()
                ], $results['description']->getStatusCode());
            }

            $titleData = json_decode($results['title']->getBody(), true);
            $descriptionData = json_decode($results['description']->getBody(), true);

            // Validate response structure for title
            if (!isset($titleData['candidates'][0]['content']['parts'][0]['text'])) {
                return response()->json([
                    'error' => 'Invalid title API response structure'
                ], 500);
            }

            // Validate response structure for description
            if (!isset($descriptionData['candidates'][0]['content']['parts'][0]['text'])) {
                return response()->json([
                    'error' => 'Invalid description API response structure'
                ], 500);
            }

            $aiTitle = trim($titleData['candidates'][0]['content']['parts'][0]['text']);
            $aiDescription = trim($descriptionData['candidates'][0]['content']['parts'][0]['text']);

            // Clean up the AI responses
            $aiTitle = preg_replace('/^.*Suitable Title:\n\n\*\*|\*\*$/', '', $aiTitle);
            $aiDescription = preg_replace('/^.*\n\n/', '', $aiDescription);

            return response()->json([
                'aiTitle' => $aiTitle,
                'aiDescription' => $aiDescription,
                'titlePrompt' => $titlePrompt,
                'descriptionPrompt' => $descriptionPrompt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
