<?php

use App\Shared\Pwa\Http\SnapshotController;
use App\Shared\Pwa\ServiceWorkerScript;
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

Route::get('sw.js', function (ServiceWorkerScript $script) {
    return response()
        ->view('pwa.sw', ['script' => $script->render()])
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'no-cache');
})->name('pwa.sw');

/** Repli du service worker : une page jamais visitée n'a rien en cache à servir. */
Route::view('hors-ligne', 'pwa.offline')->name('pwa.offline');

/** Instantané hors-ligne : le worker le laisse passer, le store côté client s'en occupe seul. */
Route::get('instantane', SnapshotController::class)->name('pwa.snapshot');
