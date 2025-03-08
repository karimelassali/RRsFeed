<?php

namespace App\Http\Controllers;

use App\Models\ReadyFeed;
use Illuminate\Http\Request;
use App\Models\RssFeedModel;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;


class DataController extends Controller
{
    /**
     * Returns a paginated list of feeds.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeedsData(Request $request)
    {
        try {
            $page = $request->query('page', 1);
            $perPage = $request->query('pageSize', 10);
    
            $query = RssFeedModel::select('id', 'title', 'description', 'source', 'pubDate', 'isPublished')
                ->latest('pubDate');
    
            // Apply search if provided
            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('source', 'like', "%{$search}%");
                });
            }
    
            // Apply filters if provided
            if ($filtersJson = $request->query('activeFilters')) {
                $filters = json_decode($filtersJson, true);
                if (is_array($filters) && !empty($filters)) {
                    // If filters is an object like { "source": "value" }, extract the source value
                    if (isset($filters['source'])) {
                        $query->where('source', $filters['source']);
                    } else {
                        // If filters is an array of sources, use whereIn
                        $sources = array_values($filters);
                        $query->whereIn('source', $sources);
                    }
                }
            }
    
            $feeds = $query->paginate($perPage, ['*'], 'page', $page);
    
            return response()->json([
                'data' => $feeds->items(),
                'pagination' => [
                    'current_page' => $feeds->currentPage(),
                    'total_pages' => $feeds->lastPage(),
                    'total_items' => $feeds->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error in getFeedsData: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getSpeceficData(Request $request, $id)
    {
        $data = RssFeedModel::find($id);
        return response()->json($data);
    }



    public function publishArticle(Request $request, $id)
    {
        try {
            Log::info('Publishing article with ID: ' . $id, [
                'title' => $request->get('title'),
                'description' => $request->get('description'),
                'image' => $request->input('image'),
                'category' => $request->input('category'),
                'showInHomePage' => $request->get('showInHomePage'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging article publish: ' . $e->getMessage());
        }
        $data = RssFeedModel::find($id);
        $data->isPublished = true;
        $data->save();
        $readyFeed = new ReadyFeed();
        $readyFeed->title = $request->get('title') ?? '';
        $readyFeed->description = $request->description ?? '';
        $readyFeed->image = $request->input('image') ?? '';
        $readyFeed->category = $request->input('category') ?? '';
        $readyFeed->showInHomePage = $request->get('showInHomePage') ?? true;
        $readyFeed->publishType = $request->get('publishType') ?? 'now';
        $readyFeed->date = $request->input('date') ?? (new \DateTime())->format('Y-m-d');
        $readyFeed->time = $request->input('time') ?? (new \DateTime())->format('H:i');
        $readyFeed->scheduledTime = $request->input('scheduledTime') ?? '';
        $readyFeed->save();

        try {
            // Pass the API URL directly in the notification function
            $this->sendNotification(
                'New update: ' . $readyFeed['title'],  // Notification title
                $readyFeed['description'],             // Notification message
                $readyFeed['link'],                    // URL for the notification
                'https://api.onesignal.com',           // Direct REST API URL for OneSignal
            );
        } catch (\Exception $e) {
            // Handle notification failure
            Log::error('Notification failed for item ' . $readyFeed['title'] . ': ' . $e->getMessage());
        }
        return response()->json($data);
    }


    public function getReadyData(Request $request)
    {
        $data = ReadyFeed::all();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    public function getReadyFeeds(Request $request)
    {
        try {
            $data = ReadyFeed::all();
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getReadyData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendNotification($title, $message, $url, $restApiUrl)
    {
        $appId = config('services.onesignal.app_id','e8dd6f91-e21d-4a9c-bab4-f8440b7d63b0');
        $restApiKey = config('services.onesignal.rest_api_key','os_v2_app_5dow7epcdvfjzovu7bcaw7ldwcijmaimzf7unvet2utpbxyy7yfqxkfrlpi4wk4xezcifgjkoo4w4hlq6hqcm5swzffepffe66ztclq');

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

}
