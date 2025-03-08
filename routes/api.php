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
use App\Http\Controllers\DataController;








Route::middleware(['auth.api:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::get('/data', [DataController::class, 'getFeedsData']);
    Route::get('/ready_feeds', [DataController::class, 'getReadyFeeds']);
});

Route::get('/data/{id}', [DataController::class, 'getSpeceficData']);
Route::post('/article/{id}/publish', [DataController::class, 'publishArticle']);


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
        
        // Find records with duplicate titles and descriptions
        $duplicates = RssFeedModel::select('title', 'description')
            ->groupBy('title', 'description')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        $deletedCount = 0;
        
        foreach ($duplicates as $duplicate) {
            // For each set of duplicates, keep only the oldest record (lowest ID)
            $recordsToDelete = RssFeedModel::where('title', $duplicate->title)
                ->where('description', $duplicate->description)
                ->orderBy('id')
                ->get();
            
            // Skip the first record (lowest ID) and delete the rest
            if ($recordsToDelete->count() > 1) {
                $keepId = $recordsToDelete->first()->id;
                
                $deletedCount += RssFeedModel::where('title', $duplicate->title)
                    ->where('description', $duplicate->description)
                    ->where('id', '!=', $keepId)
                    ->delete();
            }
        }
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'message' => 'Duplicates removed successfully',
            'deleted_count' => $deletedCount
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error removing duplicates: ' . $e->getMessage()
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


Route::get('/data/ready',function () {
    return response()->json(ReadyFeed::all());
});


Route::get('/publishedArticles', [App\Http\Controllers\DataController::class, 'getReadyData']);