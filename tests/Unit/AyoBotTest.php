<?php

use App\Games\Ayo\AyoBot;
use App\Games\Ayo\AyoGame;

test('every difficulty always picks one of the actual legal moves', function (string $difficulty) {
    $game = AyoGame::fromArray([
        'pits' => [3, 0, 2, 1, 0, 4, 4, 4, 4, 4, 4, 4],
        'owners' => [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1],
        'turn' => 0,
    ]);

    for ($i = 0; $i < 15; $i++) {
        expect(AyoBot::chooseMove(AyoGame::fromArray($game->toArray()), $difficulty))
            ->toBeIn($game->legalMoves());
    }
})->with([AyoBot::DIFFICULTY_BASIC, AyoBot::DIFFICULTY_MEDIUM, AyoBot::DIFFICULTY_HIGH]);

test('with only one legal move, every difficulty plays it', function (string $difficulty) {
    $game = AyoGame::fromArray([
        'pits' => [0, 0, 0, 0, 0, 3, 4, 4, 4, 4, 4, 4],
        'owners' => [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1],
        'turn' => 0,
    ]);

    expect(AyoBot::chooseMove($game, $difficulty))->toBe(5);
})->with([AyoBot::DIFFICULTY_BASIC, AyoBot::DIFFICULTY_MEDIUM, AyoBot::DIFFICULTY_HIGH]);

test('the basic bot plays whichever legal move is strictly best one turn ahead', function () {
    // Pit 2 nets player 0 a clear lead (+3) after just this one move; every
    // other legal move leaves them behind. A one-ply search only ever looks
    // this far, so it has no reason to pick anything else.
    $game = AyoGame::fromArray([
        'pits' => [0, 3, 4, 1, 4, 3, 2, 4, 0, 0, 2, 2],
        'owners' => [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1],
        'turn' => 0,
    ]);

    for ($i = 0; $i < 10; $i++) {
        expect(AyoBot::chooseMove(AyoGame::fromArray($game->toArray()), AyoBot::DIFFICULTY_BASIC))->toBe(2);
    }
});

test('a deeper search avoids a trap a shallow one walks into', function () {
    // A position (found by exhaustive search over random boards) where the
    // one-ply-best move and the five-ply-best move are each a strict,
    // unambiguous best for their own depth — and they disagree, because the
    // shallow search can't see what the opponent does in reply.
    $base = [
        'pits' => [1, 2, 4, 4, 0, 0, 5, 3, 0, 3, 3, 5],
        'owners' => [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1],
        'captured' => [3, 2],
        'turn' => 0,
    ];

    for ($i = 0; $i < 8; $i++) {
        expect(AyoBot::chooseMove(AyoGame::fromArray($base), AyoBot::DIFFICULTY_BASIC))->toBe(3)
            ->and(AyoBot::chooseMove(AyoGame::fromArray($base), AyoBot::DIFFICULTY_HIGH))->toBe(2);
    }
});

test('the bot never mutates the game it was asked to move for', function () {
    $game = AyoGame::fromArray([
        'pits' => [4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4],
        'turn' => 0,
    ]);
    $before = $game->toArray();

    AyoBot::chooseMove($game, AyoBot::DIFFICULTY_HIGH);

    expect($game->toArray())->toBe($before);
});
