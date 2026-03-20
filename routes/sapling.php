<?php

use hexa_package_sapling\Http\Controllers\SaplingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sapling Package Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {
    // Raw dev view
    Route::get('/raw-sapling', [SaplingController::class, 'raw'])->name('sapling.index');

    // API endpoints
    Route::post('/sapling/detect', [SaplingController::class, 'detect'])->name('sapling.detect');
});
