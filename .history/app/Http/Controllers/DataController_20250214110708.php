<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RssFeedModel;


class DataController extends Controller
{
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
                $data['image'] ?? 'https://imgs.search.brave.com/msQNvh8YagNsZYKF5ZNNsxh9fIJahOTipF7UcsmRg6Q/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly90My5m/dGNkbi5uZXQvanBn/LzAyLzQxLzQ1LzY4/LzM2MF9GXzI0MTQ1/Njg5OF9OYnZ5N3Vk/VXh1RENzOHdJbTNj/eGZLdUZiM0p3VnEx/aS5qcGc'            // Image URL
            );
        } catch (\Exception $e) {
            // Handle notification failure
            Log::error('Notification failed for item ' . $data['title'] . ': ' . $e->getMessage());
        }
        return response()->json($data);
    }
}
