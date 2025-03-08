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
        return response()->json($data);
    }
}
