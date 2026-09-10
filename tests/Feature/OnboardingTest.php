<?php

use App\Models\Community;
use App\Models\Heritage;
use App\Models\Interest;
use App\Models\Language;
use App\Models\User;
use Livewire\Livewire;

test('a user who has not onboarded is redirected there from the main app', function () {
    $user = User::factory()->unonboarded()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.index'));
});

test('an onboarded user visiting the onboarding page is redirected to the dashboard', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding.index')
        ->assertRedirect(route('dashboard'));
});

test('saving roots during onboarding persists them and advances to the follow step', function () {
    $user = User::factory()->unonboarded()->create();
    $language = Language::create(['name' => 'Yoruba', 'slug' => 'yoruba-onboarding']);
    $heritage = Heritage::create(['name' => 'Igbo', 'slug' => 'igbo-onboarding']);
    $interest = Interest::create(['name' => 'Cooking', 'slug' => 'cooking-onboarding']);

    Livewire::actingAs($user)
        ->test('pages::onboarding.index')
        ->set('bio', 'Hello world')
        ->set('languageIds', [$language->id])
        ->set('heritageIds', [$heritage->id])
        ->set('interestIds', [$interest->id])
        ->call('saveRoots')
        ->assertSet('step', 2);

    expect($user->fresh()->profile->bio)->toBe('Hello world');
    expect($user->fresh()->languages()->pluck('languages.id')->all())->toBe([$language->id]);
});

test('skipping roots advances to the follow step without saving anything', function () {
    $user = User::factory()->unonboarded()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding.index')
        ->call('skipRoots')
        ->assertSet('step', 2);

    expect($user->fresh()->profile)->toBeNull();
});

test('finishing onboarding marks the user onboarded and redirects to the dashboard', function () {
    $user = User::factory()->unonboarded()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding.index')
        ->set('step', 3)
        ->call('finish')
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->hasCompletedOnboarding())->toBeTrue();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

test('suggested communities during onboarding are the most populated public ones', function () {
    $user = User::factory()->unonboarded()->create();
    $owner = User::factory()->create();

    $popular = $owner->ownedCommunities()->create([
        'name' => 'Popular', 'slug' => 'popular-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC, 'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $popular->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
    $popular->members()->attach(User::factory()->create()->id, ['role' => 'member', 'status' => 'active']);

    $private = $owner->ownedCommunities()->create([
        'name' => 'Private', 'slug' => 'private-'.uniqid(),
        'visibility' => Community::VISIBILITY_PRIVATE, 'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $private->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $component = Livewire::actingAs($user)->test('pages::onboarding.index')->set('step', 3);

    $names = $component->get('suggestedCommunities')->pluck('name');

    expect($names)->toContain('Popular');
    expect($names)->not->toContain('Private');
});
