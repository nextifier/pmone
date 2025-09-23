<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Include Fortify authentication routes
require __DIR__.'/auth.php';
