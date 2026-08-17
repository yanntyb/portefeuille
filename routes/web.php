<?php

use App\Contexts\InstrumentView\Http\InstrumentDetailController;
use App\Contexts\Portfolio\Http\DashboardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/pwa.php';

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/instruments/{id}', InstrumentDetailController::class)->name('instruments.show');
