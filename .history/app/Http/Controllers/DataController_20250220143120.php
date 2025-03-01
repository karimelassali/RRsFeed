<?php

namespace App\Http\Controllers;

use App\Models\ReadyFeed;
use App\Models\RssFeedModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DataController extends Controller
{
    public function getFeedsData(Request $request)
    {
        try {
            $page = $request->query('page', 1);
            $pageSize = $request->query('pageSize', 10);
            $searchQuery = $request->query('searchQuery');
            $activeFilters = $request->query('activeFilters', []);

            $cacheKey = "feeds_p{$page}_s{$pageSize}_" . md5($searchQuery . json_encode($activeFilters));

            return Cache::remember($cacheKey, 300, function () use ($page, $pageSize, $searchQuery, $activeFilters) {
                $query = RssFeedModel::select('id', 'title', 'description', 'source', 'pubDate', 'isPublished')
                    ->latest();

                // Apply search if provided
                if ($searchQuery) {
                    $query->where(function($q) use ($searchQuery) {
                        $q->where('title', 'LIKE', "%{$searchQuery}%")
                          ->orWhere('description', 'LIKE', "%{$searchQuery}%");
                    });
                }

                // Apply filters if provided
                if (!empty($activeFilters)) {
                    $query->whereIn('source', $activeFilters);
                }

                // Get total count before pagination
                $totalCount = $query->count();

                // Get paginated results
                $feeds = $query->skip(($page - 1) * $pageSize)
                             ->take($pageSize + 1)
                             ->get();

                // Check if there are more items
                $hasMore = $feeds->count() > $pageSize;

                // Remove the extra item if exists
                if ($hasMore) {
                    $feeds = $feeds->slice(0, $pageSize);
                }

                return response()->json([
                    'data' => $feeds,
                    'meta' => [
                        'currentPage' => (int)$page,
                        'pageSize' => (int)$pageSize,
                        'totalCount' => $totalCount,
                        'hasMore' => $hasMore
                    ]
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Feed fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSpeceficData(Request $request, $id)
    {
        try {
            $data = Cache::remember("feed_{$id}", 300, function () use ($id) {
                return RssFeedModel::findOrFail($id);
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Specific feed fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Article not found or error occurred.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function publishArticle(Request $request, $id)
    {
        try {
            $data = RssFeedModel::findOrFail($id);
            $data->isPublished = true;
            $data->save();

            // Clear the cache for this article
            Cache::forget("feed_{$id}");

            // Optional: Send notification
            if ($request->input('sendNotification', false)) {
                $this->sendNotification(
                    'New Article Published: ' . $data->title,
                    substr($data->description, 0, 100) . '...',
                    $request->input('articleUrl', ''),
                    'https://api.onesignal.com'
                );
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Article published successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Article publish error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish article.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function sendNotification($title, $message, $url, $restApiUrl)
    {
        try {
            $appId = config('services.onesignal.app_id');
            $restApiKey = config('services.onesignal.rest_api_key');

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $restApiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ])->post($restApiUrl . '/notifications', [
                'app_id' => $appId,
                'contents' => ['en' => $message],
                'headings' => ['en' => $title],
                'url' => $url,
                'included_segments' => ['All']
            ]);

            if (!$response->successful()) {
                Log::error("OneSignal API error: " . $response->body());
                throw new \Exception('Failed to send notification');
            }

            Log::info("Notification sent successfully!");
            return true;

        } catch (\Exception $e) {
            Log::error("Notification error: " . $e->getMessage());
            throw $e;
        }
    }
}
