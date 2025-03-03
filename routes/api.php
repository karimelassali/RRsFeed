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
    // Group by 'link' to find duplicates (adjust the field if needed)
    $duplicates = RssFeedModel::select('link')
        ->groupBy('link')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    foreach ($duplicates as $duplicate) {
        // Get all records with the duplicate link, ordered by ID
        $records = RssFeedModel::where('link', $duplicate->link)
            ->orderBy('id')
            ->get();

        // Delete all except the first record
        if ($records->count() > 1) {
            $records->slice(1)->each->delete();
        }
    }

    return response()->json(['message' => 'Duplicates removed successfully']);
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