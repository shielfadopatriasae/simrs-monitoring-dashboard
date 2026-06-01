<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [MonitoringController::class, 'index'])->name('dashboard');
Route::get('/api/ping', [MonitoringController::class, 'ping'])->name('api.ping');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
