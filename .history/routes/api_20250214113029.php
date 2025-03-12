<?php

use App\Http\Controllers\AuthController;
use App\Services\Scrapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/data', [App\Http\Controllers\DataController::class, 'getFeedsData']);
Route::get('/data/{id}', [App\Http\Controllers\DataController::class, 'getSpeceficData']);

Route::post('/article/publish/{id}', [App\Http\Controllers\DataController::class, 'publishArticle']);


/*************  ✨ Codeium Command 🌟  *************/
Route::post('/data', function () {
    (new Scrapping())->scrappe();
});
Route::post('/data',function(){
    new Scrapping()->scrappe();
})
/******  aa013462-e9a7-425a-8ff1-18834aa4a719  *******/

// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
