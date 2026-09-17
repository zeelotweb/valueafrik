<?php

use App\Models\BugReport;
use App\Models\User;
use Livewire\Livewire;

test('an authenticated user can submit a bug report from the floating button', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::layout.report-bug')
        ->set('description', 'The countdown on the call screen never moves.')
        ->call('submit', 'https://valueafrik.test/live/42')
        ->assertHasNoErrors()
        ->assertSet('open', false)
        ->assertSet('description', '');

    $report = BugReport::first();

    expect($report)->not->toBeNull();
    expect($report->user_id)->toBe($user->id);
    expect($report->url)->toBe('https://valueafrik.test/live/42');
    expect($report->description)->toBe('The countdown on the call screen never moves.');
    expect($report->status)->toBe('open');
});

test('a bug report requires a description', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::layout.report-bug')
        ->set('description', '')
        ->call('submit', 'https://valueafrik.test/dashboard')
        ->assertHasErrors(['description']);

    expect(BugReport::count())->toBe(0);
});

test('a non-admin cannot access the bug reports admin page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.bug-reports'))->assertForbidden();
});

test('an admin can view and resolve a bug report', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $report = BugReport::create([
        'user_id' => $reporter->id,
        'url' => 'https://valueafrik.test/live/1',
        'description' => 'Self-inset video stretches in fullscreen.',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.bug-reports')
        ->assertSee('Self-inset video stretches in fullscreen.')
        ->call('resolve', $report->id);

    expect($report->fresh()->status)->toBe('resolved');
    expect($report->fresh()->resolved_by)->toBe($admin->id);
});

test('a demoted admin loses the ability to resolve a bug report immediately', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $report = BugReport::create([
        'user_id' => $reporter->id,
        'url' => 'https://valueafrik.test/dashboard',
        'description' => 'Notification bell never clears.',
    ]);

    $component = Livewire::actingAs($admin)->test('pages::admin.bug-reports');
    $admin->forceFill(['is_admin' => false])->save();

    $component->call('resolve', $report->id)->assertForbidden();

    expect($report->fresh()->status)->toBe('open');
});
