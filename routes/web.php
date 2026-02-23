<?php

use App\Support\PythonScriptCaller;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/python-test', function () {
    return response()->json(
        PythonScriptCaller::call('test.py', ['hello' => 'from Laravel'])
    );
});

require __DIR__.'/pwa.php';
