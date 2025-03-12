<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/data', [App\Http\Controllers\DataController::class, 'getFeedsData']);
Route::get('/data/{id}', [App\Http\Controllers\DataController::class, 'getSpeceficData']);

Route::post('/article/publish/{id}', [App\Http\Controllers\DataController::class, 'publishArticle']);


/*************  ✨ Codeium Command 🌟  *************/
Route::post('/data', [App\Services\Scrapping::class, 'scrappe']);
Route::post('/data',function(){

})
/******  a2972fc8-0bc8-4888-bb08-f3c8d3fc2302  *******/

// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
