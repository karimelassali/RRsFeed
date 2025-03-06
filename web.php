<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return '<div style="display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column; text-align: center;"><h1>This is an API Laravel project, please do not visit this route.</h1><p>To see the full app visit <a href="https://rssfeed-frontend.vercel.app/">https://rssfeed-frontend.vercel.app/</a></p></div>';
});


