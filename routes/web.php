<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/pwa.php';

Route::get('/', function () {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
