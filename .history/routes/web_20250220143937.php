<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return ['Laravel' => [Data::class,'getFeedsData']];
});


