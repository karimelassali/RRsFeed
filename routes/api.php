<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\FavoriteSourceController;
use App\Http\Middleware\RequestRole;
use App\Models\ReadyFeed;
use App\Models\RssFeedModel;
use App\Services\Scrapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Authentication Routes
 */
Route::post('/sign-in', [AuthController::class, 'signIn']);
Route::post('/user/create', [AuthController::class, 'store']);

/**
 * Protected User Route (Requires Authentication)
 */
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = Auth::guard('sanctum')->user();
    if (!$user) {
        return response()->json(['message' => 'No authenticated user'], 401);
    }
    return response()->json($user);
});

/**
 * Data Fetching Routes (Protected)
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/data', [DataController::class, 'getFeedsData']);
    Route::get('/data/{id}', [DataController::class, 'getSpeceficData']);
    Route::get('/publishedArticles', [DataController::class, 'getReadyData']);
    Route::post('/article/publish/{id}', [DataController::class, 'publishArticle']);
    Route::get('/ready_feeds', fn() => response()->json(ReadyFeed::all()));
});

/**
 * Scraping Route
 */
Route::post('/data', function (Request $request) {
    $scrapping = new Scrapping();
    $result = $scrapping->scrappe();
    return response()->json($result);
});

/**
 * Favorite Sources Routes (Protected)
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/favorite_sources/store', [FavoriteSourceController::class, 'store']);
    Route::post('/favorite_sources/fetch', [FavoriteSourceController::class, 'fecth']);
});

/**
 * Duplicate Removal Route for RSS Feeds
 */
Route::post('/rssfeeds/remove-duplicates', function () {
    try {
        DB::beginTransaction();
        
        $normalizeContent = fn($text) => Str::lower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', strip_tags($text))));
        
        $duplicates = DB::table('rss_feeds')
            ->select('title', 'description', DB::raw('MIN(id) as keep_id'))
            ->groupBy('title', 'description')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedCount = 0;
        $processedTitles = [];

        foreach ($duplicates as $duplicate) {
            $contentKey = md5($normalizeContent($duplicate->title) . $normalizeContent($duplicate->description));
            if (in_array($contentKey, $processedTitles)) continue;

            $records = RssFeedModel::where('title', $duplicate->title)
                ->where('description', $duplicate->description)
                ->where('id', '!=', $duplicate->keep_id)
                ->pluck('id')->toArray();

            foreach (array_chunk($records, 100) as $chunk) {
                $deletedCount += RssFeedModel::whereIn('id', $chunk)->delete();
            }
            
            Log::info('Removed duplicate RSS feed entries', ['title' => $duplicate->title, 'count' => count($records), 'kept_id' => $duplicate->keep_id]);
            $processedTitles[] = $contentKey;
        }

        DB::commit();
        return response()->json(['success' => true, 'deleted_count' => $deletedCount]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to remove duplicates', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => 'Failed to remove duplicates'], 500);
    }
});

/**
 * Temporary Routes (For Development Only)
 */
Route::get('/create-favorite-sources', function () {
    if (!Schema::hasTable('favorite_sources')) {
        Schema::create('favorite_sources', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->text('source');
            $table->timestamps();
        });
        return response()->json(['message' => 'Table created successfully.']);
    }
    return response()->json(['message' => 'Table already exists.']);
});

Route::get('/reset-users-table', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('role');
        $table->string('password');
        $table->string('api_token')->nullable();
        $table->rememberToken();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    return response()->json(['message' => 'Users table reset successfully.']);
});
