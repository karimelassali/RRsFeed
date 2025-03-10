<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FavoriteSource;
use Illuminate\Support\Facades\Log;

class FavoritesourceController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'source'=> 'required',
        ]);

        try {
            $favorite = FavoriteSource::where('user_id', $validatedData['user_id'])
            ->where('source', $validatedData['source'])->first();
            if ($favorite) {
                return response()->json(['message' => 'This source is already a favorite source']);
            }else{
                $source = parse_url($validatedData['source'])['host'];
                $validatedData['source'] = $source;
                FavoriteSource::create($validatedData);
                return response()->json(['message' => $validatedData['source'] . ' added as a favorite source successfully']);
            }
            
        } catch (\Exception $e) {
            Log::error('Error storing favorite source: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to store favorite source'], 500);
        }
        return response()->json(['message' => 'Something went wrong'], 500);

    }

    public function fecth(Request $request)
    {
        try{
            $userId = $request->user_id;
            $favorite = FavoriteSource::where('user_id', $userId)->get();
            return response()->json([
                'message' =>  "{$favorite->count()} favorite sources found",
                'sources' => $favorite  ]);
        }catch(\Exception $e){
            Log::error('Error fetching favorite sources: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch favorite sources'], 500);
        }
    }

}

?>