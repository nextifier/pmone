<?php

use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storage Route (Development & CORS)
|--------------------------------------------------------------------------
|
| Serve storage files through Laravel to enable CORS headers. This is
| necessary because files served via symlink bypass middleware stack.
| The storage symlink still exists for direct file serving when CORS
| is not required.
|
*/
Route::get('/storage/{path}', [StorageController::class, 'serve'])
    ->where('path', '.*')
    ->name('storage.serve');

Route::get('/', function () {
    return view('welcome');
});

// Include Fortify authentication routes
require __DIR__.'/auth.php';
