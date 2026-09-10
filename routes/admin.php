<?php

use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/reports');

    Route::livewire('reports', 'pages::admin.reports')->name('reports');
    Route::livewire('users', 'pages::admin.users')->name('users');
});
