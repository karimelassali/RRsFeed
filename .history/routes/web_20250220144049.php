<?php

use App\Http\Controllers\DataController;
use Illuminate\Support\Facades\Route;

// Define a route that calls the getFeedsData method from DataController
Route::get('/', [DataController::class, 'getFeedsData']);
