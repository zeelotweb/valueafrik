<?php

use App\Games\Ayo\AyoBot;
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

test('visiting Ayo for the first time asks who you\'re playing, without starting a game yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('games.ayo.play'))
        ->assertOk()
        ->assertSee('Who are you playing?');

    expect(GameSession::where('user_id', $user->id)->exists())->toBeFalse();
});

test('choosing to play a friend starts a fresh hot-seat game', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_HUMAN)
        ->assertSet('setup', false);

    $session = GameSession::where('user_id', $user->id)->sole();

    expect($session->status)->toBe(GameSession::STATUS_ACTIVE)
        ->and($session->opponent)->toBe(GameSession::OPPONENT_HUMAN)
        ->and($session->difficulty)->toBeNull()
        ->and($session->state['pits'])->toBe(array_fill(0, 12, 4));
});

test('choosing to play the computer requires a real difficulty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_COMPUTER, 'impossible')
        ->assertStatus(422);

    expect(GameSession::where('user_id', $user->id)->exists())->toBeFalse();
});

test('choosing to play the computer starts a game at that difficulty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_COMPUTER, AyoBot::DIFFICULTY_HIGH)
        ->assertSet('setup', false)
        ->assertSet('difficulty', AyoBot::DIFFICULTY_HIGH);

    $session = GameSession::where('user_id', $user->id)->sole();
    expect($session->opponent)->toBe(GameSession::OPPONENT_COMPUTER)
        ->and($session->difficulty)->toBe(AyoBot::DIFFICULTY_HIGH);
});

test('returning to Ayo resumes the same in-progress game instead of asking again', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::games.ayo.play')->call('startGame', GameSession::OPPONENT_HUMAN)->call('play', 0);

    $sessionId = GameSession::where('user_id', $user->id)->sole()->id;

    Livewire::actingAs($user)->test('pages::games.ayo.play')
        ->assertSet('setup', false)
        ->assertSet('sessionId', $sessionId);

    expect(GameSession::where('user_id', $user->id)->count())->toBe(1);
});

test('a move updates the board and persists it', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_HUMAN)
        ->call('play', 0);

    expect($component->get('pits'))->not->toBe(array_fill(0, 12, 4));

    $session = GameSession::where('user_id', $user->id)->sole();
    expect($session->state['pits'])->toBe($component->get('pits'))
        ->and($session->state['turn'])->toBe($component->get('turn'));
});

test('a pit that is not a legal move cannot be played', function () {
    $user = User::factory()->create();

    // Illegal — either forged, or a stale double-submit of an already-used
    // pit — is a silent no-op, not an error page: nothing about the board
    // changes.
    $component = Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_HUMAN)
        ->call('play', 6)
        ->assertOk();

    expect($component->get('pits'))->toBe(array_fill(0, 12, 4))
        ->and($component->get('turn'))->toBe(0);
});

test('starting the next round is only possible once the current one is over', function () {
    $user = User::factory()->create();
    GameSession::create([
        'user_id' => $user->id,
        'type' => GameSession::TYPE_AYO,
        'opponent' => GameSession::OPPONENT_HUMAN,
        'state' => (new AyoGame)->toArray(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startNextRound')
        ->assertOk()
        ->assertSet('round', 1);
});

test('restarting finishes the current session and asks who you\'re playing again', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_HUMAN)
        ->call('play', 0);
    $firstSessionId = $component->get('sessionId');

    $component->call('newGame')->assertSet('setup', true);

    expect(GameSession::find($firstSessionId)->status)->toBe(GameSession::STATUS_FINISHED)
        ->and(GameSession::where('user_id', $user->id)->where('status', GameSession::STATUS_ACTIVE)->exists())->toBeFalse();
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
        'opponent' => GameSession::OPPONENT_HUMAN,
        'state' => (new AyoGame)->toArray(),
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_HUMAN)
        ->set('sessionId', $session->id)
        ->call('play', 0);

    expect($session->fresh()->state['pits'][0])->toBe(4);
});

test('the computer replies on its own turn, in the same request', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('startGame', GameSession::OPPONENT_COMPUTER, AyoBot::DIFFICULTY_BASIC)
        ->call('play', 0);

    // The computer holds seat 1. If it's already moved, the turn is back to
    // the human (seat 0) rather than sitting on the computer waiting for a
    // client that will never come.
    expect($component->get('turn'))->toBe(0);

    $totalSeeds = array_sum($component->get('pits')) + array_sum($component->get('captured'));
    expect($totalSeeds)->toBe(48);

    $session = GameSession::where('user_id', $user->id)->sole();
    expect($session->state['turn'])->toBe(0);
});

test('a forged move for the computer\'s own pits is rejected', function () {
    $user = User::factory()->create();

    // Hand-build a state where it's already the computer's turn, the way a
    // tampered request (or a stale double-submit) could claim — the server
    // should never let a client move on the computer's behalf, no matter
    // what turn it claims, but it should just ignore it rather than error.
    $session = GameSession::create([
        'user_id' => $user->id,
        'type' => GameSession::TYPE_AYO,
        'opponent' => GameSession::OPPONENT_COMPUTER,
        'difficulty' => AyoBot::DIFFICULTY_BASIC,
        'state' => AyoGame::fromArray(['turn' => 1])->toArray(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::games.ayo.play')
        ->call('play', 6)
        ->assertOk();

    expect($session->fresh()->state['pits'][6])->toBe(4);
});
