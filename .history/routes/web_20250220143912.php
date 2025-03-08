<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => [DataController::class,'getFeedsData']
});


