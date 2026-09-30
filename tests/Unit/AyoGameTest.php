<?php

use App\Games\Ayo\AyoGame;

test('a fresh board has 4 seeds in every pit, split 6 and 6 between the players', function () {
    $game = new AyoGame;

    expect($game->pits)->toBe(array_fill(0, 12, 4))
        ->and($game->ownedCount(0))->toBe(6)
        ->and($game->ownedCount(1))->toBe(6)
        ->and($game->legalMoves(0))->toBe([0, 1, 2, 3, 4, 5])
        ->and($game->turn)->toBe(0);
});

test('playing an opponent\'s pit is illegal', function () {
    $game = new AyoGame;

    expect(fn () => $game->play(6))->toThrow(InvalidArgumentException::class);
});

test('playing an empty pit is illegal', function () {
    $game = AyoGame::fromArray(['pits' => [0, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4]]);

    expect(fn () => $game->play(0))->toThrow(InvalidArgumentException::class);
});

test('lets the sower keep a pit their last seed makes four', function () {
    $game = AyoGame::fromArray(['pits' => [4, 0, 0, 0, 0, 3, 0, 0, 3, 0, 0, 4]]);
    $game->play(5);

    expect($game->captured[0])->toBe(4)
        ->and($game->pits[8])->toBe(0);
});

test('gives the owner a pit a non-last seed makes four', function () {
    $game = AyoGame::fromArray(['pits' => [4, 0, 0, 0, 0, 4, 0, 3, 0, 0, 0, 1]]);
    $game->play(5);

    expect($game->captured[1])->toBe(4)
        ->and($game->pits[7])->toBe(0);
});

test('a pit that starts a round at four is not harvested on its way to five', function () {
    $game = AyoGame::fromArray(['pits' => [2, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]]);
    $game->play(0);

    expect($game->pits[1])->toBe(5)
        ->and($game->captured)->toBe([0, 0]);
});

test('landing on a non-empty, non-four pit relays into it instead of ending the turn', function () {
    $game = AyoGame::fromArray(['pits' => [1, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]]);
    $events = $game->play(0);

    expect($game->pits)->toBe([0, 0, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0])
        ->and($game->turn)->toBe(1)
        ->and(collect($events)->where('type', 'pickup'))->toHaveCount(2);
});

test('a capture on the last seed still ends the turn', function () {
    $game = AyoGame::fromArray(['pits' => [1, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]]);
    $game->play(0);

    expect($game->turn)->toBe(1)
        ->and($game->pits[1])->toBe(0)
        ->and($game->captured[0])->toBe(4);
});

test('a round ends the moment the next player has no seeds to move', function () {
    $game = AyoGame::fromArray([
        'pits' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        'captured' => [28, 0],
    ]);
    $game->play(0);

    expect($game->roundOver)->toBeTrue()
        ->and($game->roundResult['stuck'])->toBe(1)
        // 25 harvested + 4 on the board = 29 against a 24-seed baseline for
        // 6 owned pits: 5 excess seeds is one full pit's worth (floor(5/4)).
        ->and($game->roundResult['gained'][0])->toBe(1)
        ->and($game->owners[6])->toBe(0)
        ->and($game->ownedCount(0))->toBe(7);
});

test('starting the next round resets the board to four seeds per pit on the new ownership', function () {
    $game = AyoGame::fromArray([
        'pits' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        'captured' => [28, 0],
    ]);
    $game->play(0);
    $game->startNextRound();

    expect($game->pits)->toBe(array_fill(0, 12, 4))
        ->and($game->captured)->toBe([0, 0])
        ->and($game->round)->toBe(2)
        // The player who ran dry last round opens the next one.
        ->and($game->turn)->toBe(1)
        ->and($game->owners[6])->toBe(0);
});

test('a player wins by ending up with every pit', function () {
    $game = AyoGame::fromArray([
        'pits' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        'owners' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1],
        'captured' => [80, 0],
    ]);
    $game->play(0);

    expect($game->winner)->toBe(0)
        ->and($game->ownedCount(0))->toBe(12)
        ->and($game->roundOver)->toBeTrue();
});

test('the round cannot be replayed once it is over', function () {
    $game = AyoGame::fromArray([
        'pits' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        'captured' => [28, 0],
    ]);
    $game->play(0);

    expect(fn () => $game->play(1))->toThrow(LogicException::class);
});

test('the next round cannot start before this one is over', function () {
    $game = new AyoGame;

    expect(fn () => $game->startNextRound())->toThrow(LogicException::class);
});

test('round state survives a trip through toArray and fromArray', function () {
    $game = AyoGame::fromArray(['pits' => [1, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]]);
    $game->play(0);

    $restored = AyoGame::fromArray($game->toArray());

    expect($restored->pits)->toBe($game->pits)
        ->and($restored->owners)->toBe($game->owners)
        ->and($restored->turn)->toBe($game->turn);
});

test('a player\'s opening pit for the round is recorded on their first move and never changes after', function () {
    $game = new AyoGame;

    expect($game->openingPit)->toBe([null, null]);

    $game->play(2);
    expect($game->openingPit)->toBe([2, null]);

    // A relay from pit 2 lands the turn back with player 0 (the board is
    // symmetric and small enough that this is common); whichever pit they
    // play next must not overwrite the one they opened with.
    if ($game->turn === 0 && ! $game->roundOver) {
        $secondPit = $game->legalMoves()[0];
        $game->play($secondPit);

        expect($game->openingPit[0])->toBe(2);
    }
});

test('each player gets their own opening pit for the round, independently', function () {
    $game = AyoGame::fromArray([
        'pits' => [1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0],
    ]);

    $game->play(0);
    expect($game->openingPit)->toBe([0, null]);

    $game->play(6);
    expect($game->openingPit)->toBe([0, 6]);
});

test('starting the next round clears both players\' opening pits', function () {
    $game = AyoGame::fromArray([
        'pits' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        'captured' => [28, 0],
    ]);
    $game->play(0);
    expect($game->openingPit[0])->not->toBeNull();

    $game->startNextRound();

    expect($game->openingPit)->toBe([null, null]);
});
