<?php

use Illuminate\Support\Facades\Route;

Route::get('manifest.json', function () {
    return response()->json([
        'name' => config('pwa.name'),
        'short_name' => config('pwa.short_name'),
        'description' => config('pwa.description'),
        'start_url' => config('pwa.start_url'),
        'scope' => config('pwa.scope'),
        'display' => config('pwa.display'),
        'theme_color' => config('pwa.theme_color'),
        'background_color' => config('pwa.background_color'),
        'icons' => config('pwa.icons'),
    ]);
})->name('pwa.manifest');

Route::get('sw.js', function () {
    return response()
        ->view('pwa.sw')
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'no-cache');
})->name('pwa.sw');
