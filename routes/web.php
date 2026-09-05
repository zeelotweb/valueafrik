<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CommunityPhotoController;
use App\Http\Controllers\ProfileShowController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('guide', 'guide')->name('guide');
Route::view('legal/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('legal/terms', 'legal.terms')->name('legal.terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('profile', fn () => redirect()->route('profile.show', Auth::user()));
    Route::get('u/{user}', ProfileShowController::class)->name('profile.show');

    Route::livewire('messages', 'pages::messages.inbox')->name('messages.index');
    Route::livewire('messages/{conversation}', 'pages::messages.show')->name('messages.show');

    Route::livewire('notifications', 'pages::notifications.index')->name('notifications.index');

    Route::livewire('bookmarks', 'pages::bookmarks.index')->name('bookmarks.index');

    Route::livewire('discover', 'pages::discover.index')->name('discover.index');

    Route::livewire('communities', 'pages::communities.index')->name('communities.index');
    Route::livewire('communities/create', 'pages::communities.create')->name('communities.create');
    Route::livewire('communities/{community:slug}', 'pages::communities.show')->name('communities.show');
    Route::livewire('communities/{community:slug}/edit', 'pages::communities.edit')->name('communities.edit');

    Route::post('communities/{community:slug}/avatar', [CommunityPhotoController::class, 'updateAvatar'])->name('communities.avatar');
    Route::post('communities/{community:slug}/cover', [CommunityPhotoController::class, 'updateCover'])->name('communities.cover');

    Route::livewire('live', 'pages::live.index')->name('live.index');
    Route::livewire('live/{liveSession}', 'pages::live.show')->name('live.show');
});

Route::post('logout', Logout::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware(['guest'])->group(function () {
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');
});

require __DIR__.'/settings.php';
