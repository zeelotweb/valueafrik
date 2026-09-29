<?php

use App\Games\Ayo\AyoGame;
use App\Models\GameSession;
use App\Models\User;
use Livewire\Livewire;

test('the games hub links to Ayo', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('games.index'))
        ->assertOk()
        ->assertSee('Ayo')
        ->assertSee(route('games.ayo.play'), false);
});

test('visiting Ayo for the first time starts a fresh game', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('games.ayo.play'))
        ->assertOk()
        ->assertSee('Ayo');

    $session = GameSession::where('user_id', $user->id)->where('type', GameSession::TYPE_AYO)->first();

    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(GameSession::STATUS_ACTIVE)
        ->and($session->state['pits'])->toBe(array_fill(0, 12, 4));
});

test('returning to Ayo resumes the same in-progress game instead of starting over', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::games.ayo.play')->call('play', 0);

    $session = GameSession::where('user_id', $user->id)->sole();
    expect($session->state['pits'][0])->toBe(0);

    Livewire::actingAs($user)->test('pages::games.ayo.play');

    expect(GameSession::where('user_id', $user->id)->count())->toBe(1);
});

test('a move updates the board and persists it', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('play', 0)
        ->assertSet('turn', 1);

    $session = GameSession::where('user_id', $user->id)->sole();
    expect($session->state['turn'])->toBe(1)
        ->and($session->state['pits'][0])->toBe(0);
});

test('a pit that is not a legal move cannot be played', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('play', 6)
        ->assertStatus(422);
});

test('starting the next round is only possible once the current one is over', function () {
    $user = User::factory()->create();
    GameSession::create([
        'user_id' => $user->id,
        'type' => GameSession::TYPE_AYO,
        'state' => (new AyoGame)->toArray(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startNextRound')
        ->assertStatus(422);
});

test('starting a new game finishes the old session and opens a fresh one', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('pages::games.ayo.play')->call('play', 0);
    $firstSessionId = $component->get('sessionId');

    $component->call('newGame');

    expect(GameSession::find($firstSessionId)->status)->toBe(GameSession::STATUS_FINISHED)
        ->and(GameSession::where('user_id', $user->id)->where('status', GameSession::STATUS_ACTIVE)->count())->toBe(1)
        ->and($component->get('sessionId'))->not->toBe($firstSessionId);
});

test('a signed-out visitor is sent to log in before playing', function () {
    $this->get(route('games.ayo.play'))->assertRedirect(route('login'));
});

test('one player cannot touch another player\'s Ayo session', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $session = GameSession::create([
        'user_id' => $owner->id,
        'type' => GameSession::TYPE_AYO,
        'state' => (new AyoGame)->toArray(),
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::games.ayo.play')
        ->set('sessionId', $session->id)
        ->call('play', 0);

    expect($session->fresh()->state['pits'][0])->toBe(4);
});
