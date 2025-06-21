<?php

use Illuminate\Support\Facades\Route;
// If you have a specific WebApp controller, use it.
// For simplicity, using a closure here.
// use App\Containers\AppSection\WebApp\UI\WEB\Controllers\WebAppController;

Route::get('/', function () {
    return view('index'); // Assumes 'index.blade.php' is in 'resources/views/'
})->name('webapp.home');

// If you prefer a controller:
// Route::get('/', [WebAppController::class, 'showHomePage'])->name('webapp.home');
