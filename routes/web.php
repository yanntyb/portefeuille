<?php

use App\Livewire\InvitationRegistration;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitation/{token}', InvitationRegistration::class)
    ->name('invitation.register')
    ->middleware('guest');

require __DIR__.'/pwa.php';
