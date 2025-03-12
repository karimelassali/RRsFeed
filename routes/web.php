<?php

use App\Http\Controllers\DataController;
use Illuminate\Support\Facades\Route;

// Define a route that calls the getFeedsData method from DataController
Route::get('/', function () {
    return '<center><h1>Accesso non consentito</h1><p>Per favore, visita <a href="https://rssfeed-frontend.vercel.app/">https://rssfeed-frontend.vercel.app/</a></p><script>setTimeout(function(){ window.location.href = "https://rssfeed-frontend.vercel.app/"; }, 1500);</script></center>';
});
