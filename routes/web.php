<?php

use App\Http\Controllers\Platform\HealthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Platform/Demo'))
    ->name('platform.demo');

Route::get('/up', HealthController::class)
    ->name('health');
