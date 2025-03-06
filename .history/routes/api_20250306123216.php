<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\RequestRole;
use App\Models\ReadyFeed;
use App\Services\Scrapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use App\Models\RssFeedModel;
use App\Http\Controllers\FavoritesourceController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;






Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/data', [App\Http\Controllers\DataController::class, 'getFeedsData']);

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

Route::post('/sign-in',[AuthController::class,'signIn']);
Route::post('/user/create',[AuthController::class,'store']);



// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);




Route::post('/rssfeeds/remove-duplicates', function () {
    try {
        DB::beginTransaction();

        // Normalize content for better duplicate detection
        $normalizeContent = function ($text) {
            return Str::lower(
                trim(
                    preg_replace(
                        '/[^\p{L}\p{N}\s]/u', // Remove special characters, keep letters and numbers
                        '',
                        strip_tags($text) // Remove HTML tags
                    )
                )
            );
        };

        // Use a more efficient query with subquery to get duplicates
        $duplicates = DB::table('rss_feeds') // Replace with your actual table name if different
            ->select('title', 'description', DB::raw('MIN(id) as keep_id'))
            ->groupBy('title', 'description')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedCount = 0;
        $processedTitles = []; // Track processed normalized titles

        foreach ($duplicates as $duplicate) {
            $normalizedTitle = $normalizeContent($duplicate->title);
            $normalizedDesc = $normalizeContent($duplicate->description);

            // Skip if we've already processed this content combination
            $contentKey = md5($normalizedTitle . $normalizedDesc);
            if (in_array($contentKey, $processedTitles)) {
                continue;
            }

            // Get all records for this duplicate set
            $records = RssFeedModel::where('title', $duplicate->title)
                ->where('description', $duplicate->description)
                ->where('id', '!=', $duplicate->keep_id)
                ->get();

            if ($records->count() > 0) {
                // Delete duplicates in chunks for better memory management
                $recordIds = $records->pluck('id')->toArray();
                $chunks = array_chunk($recordIds, 100); // Process 100 at a time

                foreach ($chunks as $chunk) {
                    $deletedCount += RssFeedModel::whereIn('id', $chunk)->delete();
                }

                // Log the deletion
                Log::info('Removed duplicate RSS feed entries', [
                    'title' => $duplicate->title,
                    'count' => count($recordIds),
                    'kept_id' => $duplicate->keep_id
                ]);

                $processedTitles[] = $contentKey;
            }
        }

        DB::commit();

        // Calculate execution time
        $executionTime = microtime(true) - LARAVEL_START;

        return response()->json([
            'success' => true,
            'message' => 'Duplicate removal completed successfully',
            'deleted_count' => $deletedCount,
            'execution_time' => round($executionTime, 3) . ' seconds',
            'processed_unique_entries' => count($processedTitles)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        // Log the error with more context
        Log::error('Failed to remove duplicate RSS feeds', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to remove duplicates',
            'error' => $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
});

Route::post('/favorite_sources/store', [FavoriteSourceController::class, 'store']);

Route::get('/ready_feeds', function () {
    return response()->json(ReadyFeed::all());
});


Route::post('/favorite_sources/fetch', [FavoriteSourceController::class, 'fecth']);

//temporary route
Route::get('/create-favorite-sources', function () {
    if (!Schema::hasTable('favorite_sources')) {
        Schema::create('favorite_sources', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->text('source');
            $table->timestamps();
        });
        return response()->json(['message' => 'Table favorite_sources created successfully.']);
    }
    return response()->json(['message' => 'Table already exists.']);
});


//tempo
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


Route::get('/published', [App\Http\Controllers\DataController::class, 'getReadyData']);
