<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CommunityPhotoController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MentionSearchController;
use App\Http\Controllers\ProfileShowController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update')->middleware('throttle:60,1');
Route::view('guide', 'guide')->middleware('feature:guide')->name('guide');
Route::view('roadmap', 'roadmap')->name('roadmap');
Route::view('legal/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('legal/terms', 'legal.terms')->middleware('feature:terms')->name('legal.terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('onboarding', 'pages::onboarding.index')->name('onboarding.index');
});

Route::middleware(['auth', 'verified', EnsureOnboardingComplete::class])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('profile', fn () => redirect()->route('profile.show', Auth::user()));
    Route::get('u/{user}', ProfileShowController::class)->name('profile.show');
    Route::livewire('u/{user}/connections', 'pages::profile.connections')->name('profile.connections');

    // Both routes render the same component — messages.show just arrives
    // with a conversation pre-selected (see Conversation $conversation on
    // pages::messages.inbox's mount()). That's what lets a "Message"
    // button, a notification link, or the mobile list all land in the
    // same two-pane experience instead of a separate standalone thread.
    Route::livewire('messages', 'pages::messages.inbox')->name('messages.index');
    Route::livewire('messages/{conversation}', 'pages::messages.inbox')->name('messages.show');

    Route::livewire('notifications', 'pages::notifications.index')->name('notifications.index');

    Route::livewire('bookmarks', 'pages::bookmarks.index')->name('bookmarks.index');

    Route::livewire('discover', 'pages::discover.index')->name('discover.index');

    Route::livewire('communities', 'pages::communities.index')->name('communities.index');
    Route::livewire('communities/create', 'pages::communities.create')->name('communities.create');
    Route::livewire('communities/{community:slug}', 'pages::communities.show')->name('communities.show');
    Route::livewire('communities/{community:slug}/edit', 'pages::communities.edit')->name('communities.edit');

    Route::post('communities/{community:slug}/avatar', [CommunityPhotoController::class, 'updateAvatar'])->name('communities.avatar');
    Route::post('communities/{community:slug}/cover', [CommunityPhotoController::class, 'updateCover'])->name('communities.cover');

    Route::middleware('feature:live')->group(function () {
        Route::livewire('live', 'pages::live.index')->name('live.index');
        Route::livewire('live/{liveSession}', 'pages::live.show')->name('live.show');

        Route::livewire('culture-sprint', 'pages::culture-sprint.index')->name('culture-sprint.index');
    });

    Route::livewire('topics/{hashtag:slug}', 'pages::topics.show')->name('topics.show');
    Route::get('mentions/search', MentionSearchController::class)->name('mentions.search');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
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
require __DIR__.'/admin.php';
