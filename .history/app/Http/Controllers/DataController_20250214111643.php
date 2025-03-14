<?php

namespace App\Http\Controllers;

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
        $perPage = $request->query('pageSize', 10);

        $feeds = RssFeedModel::select('id', 'title', 'description', 'source', 'pubDate', 'isPublished')
            ->latest()
            ->paginate($perPage);

        return response()->json($feeds);

    } catch (\Exception $e) {
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
        $data = RssFeedModel::find($id);
        $data->isPublished = true;
        $data->save();
        try {
            // Pass the API URL directly in the notification function
            $this->sendNotification(
                'New update: ' . $data['title'],  // Notification title
                $data['description'],             // Notification message
                $data['link'],                    // URL for the notification
                'https://api.onesignal.com',      // Direct REST API URL for OneSignal
            );
        } catch (\Exception $e) {
            // Handle notification failure
            Log::error('Notification failed for item ' . $data['title'] . ': ' . $e->getMessage());
        }
        return response()->json($data);
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

}
