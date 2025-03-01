<?php

use App\Http\Controllers\AuthController;
use App\Models\ReadyFeed;
use App\Services\Scrapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/data', [App\Http\Controllers\DataController::class, 'getFeedsData'])->middleware(['auth:sanctum');

Route::get('/data/{id}', [App\Http\Controllers\DataController::class, 'getSpeceficData']);

Route::post('/article/publish/{id}', [App\Http\Controllers\DataController::class, 'publishArticle']);



Route::post('/data', function (Request $request) {
    // Instantiate the Scrapping class
    $scrapping = new Scrapping();

    // Get custom configurations from the request (if any)
    $customConfigs = $request->input('custom_configs', []);

    // Call the scrape method
    $result = $scrapping->scrappe();

    // Return the result as a JSON response
    return response()->json($result);
});



// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
