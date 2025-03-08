<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

use App\Services\Scrapping;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Schedule::call(function () {
    $customConfigs = [
        [
            'url' => 'https://aostanews24.it/saint-vincent-47enne-denunciato-per-favoreggiamento-della-prostituzione/',
            'title_selector' => '.elementor-heading-title',
            'description_selector' => '.elementor-widget-container',
            'pub_date_selector' => '.elementor-widget-container',
        ],
    ];
    (new Scrapping())->scrappe();
})->everyFiveSeconds();

