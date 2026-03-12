<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Generic /dashboard → redirect to role-based dashboard
    Route::get('dashboard', function () {
        return redirect(auth()->user()->dashboardRoute());
    })->name('dashboard');

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('dashboard', 'Admin/Dashboard')->name('dashboard');
    });

    // Guru routes
    Route::middleware('role:guru')->prefix('guru')->name('guru.')->group(function () {
        Route::inertia('dashboard', 'Guru/Dashboard')->name('dashboard');
    });

    // Siswa routes
    Route::middleware('role:siswa')->prefix('siswa')->name('siswa.')->group(function () {
        Route::inertia('dashboard', 'Siswa/Dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
