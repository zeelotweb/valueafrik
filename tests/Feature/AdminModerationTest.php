<?php

use App\Models\Community;
use App\Models\CommunityReport;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Symfony\Component\Console\Command\Command;

function reportedCommunityPost(): CommunityReport
{
    $owner = User::factory()->create();
    $community = $owner->ownedCommunities()->create([
        'name' => 'Moderation Test Community',
        'slug' => 'moderation-test-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $post = $community->posts()->create(['user_id' => $owner->id, 'body' => 'Reported content.']);
    $reporter = User::factory()->create();

    $report = new CommunityReport([
        'community_id' => $community->id,
        'reporter_id' => $reporter->id,
        'reason' => 'Spam.',
    ]);
    $report->reportable()->associate($post);
    $report->save();

    return $report;
}

test('a non-admin cannot access the admin area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.reports'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
});

test('an admin can view open reports', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = reportedCommunityPost();

    Livewire::actingAs($admin)
        ->test('pages::admin.reports')
        ->assertSee('Reported content.')
        ->assertSee('Spam.');
});

test('an admin can dismiss a report without touching the content', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = reportedCommunityPost();

    Livewire::actingAs($admin)
        ->test('pages::admin.reports')
        ->call('dismiss', $report->id);

    expect($report->fresh()->status)->toBe('resolved');
    expect($report->fresh()->resolved_by)->toBe($admin->id);
    expect($report->reportable()->exists())->toBeTrue();
});

test('an admin can remove reported content, which also resolves the report', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = reportedCommunityPost();
    $postId = $report->reportable_id;

    Livewire::actingAs($admin)
        ->test('pages::admin.reports')
        ->call('removeContent', $report->id);

    expect($report->fresh()->status)->toBe('resolved');
    expect(\App\Models\CommunityPost::withTrashed()->find($postId)->trashed())->toBeTrue();
});

test('a non-admin cannot dismiss or remove reports', function () {
    $user = User::factory()->create();
    $report = reportedCommunityPost();

    Livewire::actingAs($user)
        ->test('pages::admin.reports')
        ->assertForbidden();
});

test('an admin can ban a user, who is then signed out on their next request', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('startBan', $target->id)
        ->set('banReason', 'Repeated harassment.')
        ->call('confirmBan');

    expect($target->fresh()->isBanned())->toBeTrue();
    expect($target->fresh()->ban_reason)->toBe('Repeated harassment.');

    $this->actingAs($target->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an admin can unban a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $target->ban('test');

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('unban', $target->id);

    expect($target->fresh()->isBanned())->toBeFalse();
});

test('an admin cannot ban themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('startBan', $admin->id)
        ->assertForbidden();
});

test('a non-admin cannot ban users', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.users')
        ->assertForbidden();
});

test('the admin:grant and admin:revoke commands toggle admin access', function () {
    $user = User::factory()->create();

    Artisan::call('admin:grant', ['email' => $user->email]);
    expect($user->fresh()->isAdmin())->toBeTrue();

    Artisan::call('admin:revoke', ['email' => $user->email]);
    expect($user->fresh()->isAdmin())->toBeFalse();
});

test('the admin:grant command fails gracefully for an unknown email', function () {
    $exitCode = Artisan::call('admin:grant', ['email' => 'nobody@example.com']);

    expect($exitCode)->toBe(Command::FAILURE);
});

// --- demoted-admin regression -------------------------------------------
//
// mount() only re-runs on the component's initial render, not on
// subsequent Livewire action calls within the same browser session — so
// a check placed only in mount() doesn't protect an admin who's demoted
// mid-session but keeps an open tab. Every mutating method needs its own
// check, mirroring the Livewire::test()-bypasses-route-middleware
// convention already established elsewhere.

test('a demoted admin loses the ability to dismiss reports immediately, not just on next page load', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = reportedCommunityPost();

    // mount() only runs once, when the component is first created — an
    // admin demoted after that point but still holding this same
    // component instance (an open browser tab) must be blocked by a
    // check inside the action method itself, not just at mount time.
    $component = Livewire::actingAs($admin)->test('pages::admin.reports');
    $admin->forceFill(['is_admin' => false])->save();

    $component->call('dismiss', $report->id)->assertForbidden();
});

test('a demoted admin loses the ability to remove reported content immediately', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = reportedCommunityPost();

    $component = Livewire::actingAs($admin)->test('pages::admin.reports');
    $admin->forceFill(['is_admin' => false])->save();

    $component->call('removeContent', $report->id)->assertForbidden();

    expect($report->fresh()->status)->toBe('open');
});

test('a demoted admin loses the ability to ban a user immediately', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();

    $component = Livewire::actingAs($admin)->test('pages::admin.users');
    $admin->forceFill(['is_admin' => false])->save();

    $component->call('startBan', $target->id)->assertForbidden();

    expect($target->fresh()->isBanned())->toBeFalse();
});

test('a demoted admin loses the ability to unban a user immediately', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $target->ban('test');

    $component = Livewire::actingAs($admin)->test('pages::admin.users');
    $admin->forceFill(['is_admin' => false])->save();

    $component->call('unban', $target->id)->assertForbidden();

    expect($target->fresh()->isBanned())->toBeTrue();
});

// --- moderation audit trail ----------------------------------------------

test('banning a user records who banned them and why', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('startBan', $target->id)
        ->set('banReason', 'Repeated harassment.')
        ->call('confirmBan');

    $log = $target->moderationLogs()->latest()->first();

    expect($log->action)->toBe('banned');
    expect($log->actor_id)->toBe($admin->id);
    expect($log->reason)->toBe('Repeated harassment.');
});

test('unbanning preserves the original ban reason on the log even though it is wiped from the user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $target->ban('Repeated harassment.', $admin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('unban', $target->id);

    expect($target->fresh()->ban_reason)->toBeNull();

    $log = $target->moderationLogs()->where('action', 'unbanned')->first();

    expect($log->actor_id)->toBe($admin->id);
    expect($log->reason)->toBe('Repeated harassment.');
});
