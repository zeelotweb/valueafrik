<?php

namespace App\Games\Ayo;

/**
 * Move selection for a computer opponent. Framework-free, like AyoGame
 * itself — it only ever reasons about the board, never about sessions or
 * users. Every difficulty picks the move with the best minimax-searched
 * outcome; what changes is how many turns ahead it searches.
 */
class AyoBot
{
    public const DIFFICULTY_BASIC = 'basic';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HIGH = 'high';

    /** @var array<string, int> plies of alternating turns each difficulty searches */
    private const SEARCH_DEPTH = [
        self::DIFFICULTY_BASIC => 1,
        self::DIFFICULTY_MEDIUM => 3,
        self::DIFFICULTY_HIGH => 5,
    ];

    /** Picks a legal move for whoever's turn it currently is in $game. */
    public static function chooseMove(AyoGame $game, string $difficulty): int
    {
        $player = $game->turn;
        $depth = self::SEARCH_DEPTH[$difficulty] ?? self::SEARCH_DEPTH[self::DIFFICULTY_BASIC];

        $bestScore = null;
        $bestMoves = [];

        foreach ($game->legalMoves() as $pit) {
            $score = self::search(self::simulate($game, $pit), $player, $depth - 1);

            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $bestMoves = [$pit];
            } elseif ($score === $bestScore) {
                $bestMoves[] = $pit;
            }
        }

        // A tie (common at low search depth, and always on the very first
        // move of a round) picks randomly rather than always favoring the
        // lowest pit index, so the bot doesn't play identically every game.
        return $bestMoves[array_rand($bestMoves)];
    }

    /**
     * Negamax over full turns (each of AyoGame's own moves already resolves
     * an entire turn, relay included) — from $player's perspective, a node
     * where it's $player's turn again picks the best continuation, and a
     * node where it's the opponent's turn assumes they do the same for
     * themselves, i.e. the worst continuation for $player.
     */
    private static function search(AyoGame $game, int $player, int $depth): int
    {
        if ($depth <= 0 || $game->roundOver || $game->winner !== null) {
            return $game->playerTotal($player) - $game->playerTotal(1 - $player);
        }

        $turn = $game->turn;
        $scores = array_map(
            fn ($pit) => self::search(self::simulate($game, $pit), $player, $depth - 1),
            $game->legalMoves(),
        );

        return $turn === $player ? max($scores) : min($scores);
    }

    private static function simulate(AyoGame $game, int $pit): AyoGame
    {
        $clone = AyoGame::fromArray($game->toArray());
        $clone->play($pit);

        return $clone;
    }
}
