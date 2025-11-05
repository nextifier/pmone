<?php

use App\Http\Controllers\ShortLinkRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Short link redirect route
Route::get('/{slug}', ShortLinkRedirectController::class)
    ->where('slug', '[a-zA-Z0-9_-]+')
    ->name('short-link.redirect');

// Include Fortify authentication routes
require __DIR__.'/auth.php';
