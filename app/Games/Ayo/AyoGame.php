<?php

namespace App\Games\Ayo;

use InvalidArgumentException;
use LogicException;

/**
 * Yoruba Ayo rules engine (relay sowing). Framework-free by design, so it
 * can be unit tested without booting the app and reused by any UI later.
 *
 * Pits 0-11 run in sowing order. Player 0 starts owning pits 0-5, player 1
 * owns 6-11. A "round" is one hand of play from a level field (4 seeds per
 * pit) until a player has no seeds left to move; a "game" is however many
 * rounds it takes one player to own all 12 pits.
 */
class AyoGame
{
    public const PITS = 12;

    public const SEEDS_PER_PIT = 4;

    /** Guards against a relay chain that never lands in an empty pit. */
    private const MAX_STEPS = 2000;

    /** @var list<int> seeds currently in each of the 12 pits */
    public array $pits;

    /** @var list<int> which player (0 or 1) owns each pit */
    public array $owners;

    /** @var array{0: int, 1: int} seeds each player has harvested this round */
    public array $captured = [0, 0];

    /** Whose turn it is: 0 or 1. */
    public int $turn = 0;

    public int $round = 1;

    public bool $roundOver = false;

    /** @var 0|1|null set once a player owns every pit */
    public ?int $winner = null;

    /** @var array{totals: array{0:int,1:int}, gained: array{0:int,1:int}, stuck: int}|null */
    public ?array $roundResult = null;

    public function __construct()
    {
        $this->pits = array_fill(0, self::PITS, self::SEEDS_PER_PIT);
        $this->owners = [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1];
    }

    /**
     * @return list<int> pits the given player (defaults to whoever's turn it is)
     *                   currently has a legal move from
     */
    public function legalMoves(?int $player = null): array
    {
        $player ??= $this->turn;

        return array_values(array_filter(
            array_keys($this->pits),
            fn ($i) => $this->owners[$i] === $player && $this->pits[$i] > 0
        ));
    }

    /**
     * Plays a full turn from the given pit, including any relay sowing.
     *
     * @return list<array<string, mixed>> events (pickup, sow, harvest, transfer)
     *                                    in the order they happened, for a UI to animate
     */
    public function play(int $pit): array
    {
        if ($this->winner !== null || $this->roundOver) {
            throw new LogicException('Round is over. Call startNextRound().');
        }

        if (! in_array($pit, $this->legalMoves(), true)) {
            throw new InvalidArgumentException('Illegal move.');
        }

        $player = $this->turn;
        $events = [];
        $pos = $segStart = $pit;
        $hand = $this->pits[$pit];
        $this->pits[$pit] = 0;
        $events[] = ['type' => 'pickup', 'pit' => $pit, 'seeds' => $hand];
        $steps = 0;

        while ($steps++ < self::MAX_STEPS) {
            while ($hand > 0) {
                $pos = ($pos + 1) % self::PITS;

                if ($pos === $segStart) {
                    continue; // never sow back into the pit this hand came from
                }

                $this->pits[$pos]++;
                $hand--;
                $events[] = ['type' => 'sow', 'pit' => $pos];

                // A non-last seed making a pit reach exactly 4 goes to that
                // side's owner. Only the turn's very last seed is the sower's
                // to keep (handled below, once the hand is empty).
                if ($hand > 0 && $this->pits[$pos] === self::SEEDS_PER_PIT) {
                    $this->harvest($pos, $this->owners[$pos], $events);
                }
            }

            $count = $this->pits[$pos];

            if ($count === 1) {
                break; // last seed landed in an empty pit: turn ends
            }

            if ($count === self::SEEDS_PER_PIT) {
                $this->harvest($pos, $player, $events); // last seed made it 4: the sower keeps it
                break;
            }

            // Last seed landed in a pit that still isn't empty and didn't
            // just hit 4: pick the whole pit back up and keep sowing.
            $hand = $count;
            $this->pits[$pos] = 0;
            $segStart = $pos;
            $events[] = ['type' => 'pickup', 'pit' => $pos, 'seeds' => $hand];
        }

        $this->turn = 1 - $player;

        if ($this->legalMoves() === []) {
            $this->endRound($events);
        }

        return $events;
    }

    /** Resets the board to a level field for the next round, on the current ownership layout. */
    public function startNextRound(): void
    {
        if (! $this->roundOver || $this->winner !== null) {
            throw new LogicException('Cannot start a new round now.');
        }

        $this->pits = array_fill(0, self::PITS, self::SEEDS_PER_PIT);
        $this->captured = [0, 0];
        $this->roundOver = false;
        $this->round++;
        // The player who ran out of seeds opens the next round.
        $this->turn = $this->roundResult['stuck'];
        $this->roundResult = null;
    }

    public function ownedCount(int $player): int
    {
        return count(array_filter($this->owners, fn ($o) => $o === $player));
    }

    /** @param list<array<string, mixed>> $events */
    private function harvest(int $pit, int $to, array &$events): void
    {
        $seeds = $this->pits[$pit];
        $this->captured[$to] += $seeds;
        $this->pits[$pit] = 0;
        $events[] = ['type' => 'harvest', 'pit' => $pit, 'by' => $to, 'seeds' => $seeds];
    }

    /** @param list<array<string, mixed>> $events */
    private function endRound(array &$events): void
    {
        $stuck = $this->turn;
        $totals = $this->captured;

        foreach ($this->pits as $i => $n) {
            $totals[$this->owners[$i]] += $n; // leftovers on the board count for whoever's pit they sit in
        }

        // totals[0] + totals[1] always equals the 48 seeds in play, so only
        // one side can ever have an excess above its own 4-per-pit baseline.
        $gained = [];
        foreach ([0, 1] as $p) {
            $excess = $totals[$p] - self::SEEDS_PER_PIT * $this->ownedCount($p);
            $gained[$p] = max(0, intdiv($excess, self::SEEDS_PER_PIT));
        }

        foreach ([0, 1] as $p) {
            $take = min($gained[$p], $this->ownedCount(1 - $p));
            $start = $p === 0 ? 6 : 0; // the opponent's pits nearest the winner's own side transfer first

            for ($k = 0; $k < self::PITS && $take > 0; $k++) {
                $i = ($start + $k) % self::PITS;

                if ($this->owners[$i] === 1 - $p) {
                    $this->owners[$i] = $p;
                    $take--;
                    $events[] = ['type' => 'transfer', 'pit' => $i, 'to' => $p];
                }
            }
        }

        $this->roundOver = true;
        $this->roundResult = ['totals' => $totals, 'gained' => $gained, 'stuck' => $stuck];

        foreach ([0, 1] as $p) {
            if ($this->ownedCount($p) === self::PITS) {
                $this->winner = $p;
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $game = new self;

        foreach ($data as $key => $value) {
            if (property_exists($game, $key)) {
                $game->$key = $value;
            }
        }

        return $game;
    }
}
