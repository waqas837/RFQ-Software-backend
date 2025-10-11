<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Add login route for API authentication redirects
Route::get('/login', function () {
    return response()->json([
        'message' => 'Authentication required',
        'error' => 'Please login via API'
    ], 401);
})->name('login');
