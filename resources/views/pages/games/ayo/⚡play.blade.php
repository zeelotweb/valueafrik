<?php

use App\Games\Ayo\AyoBot;
use App\Games\Ayo\AyoGame;
use App\Models\GameSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ayo')] class extends Component {
    /**
     * True until an opponent is chosen. Every game-state property below
     * gets a safe default rather than being left unset, since Livewire
     * hydrates every public property on every request regardless of which
     * branch the template took last time.
     */
    public bool $setup = false;

    public int $sessionId = 0;

    public string $opponent = GameSession::OPPONENT_HUMAN;

    public ?string $difficulty = null;

    /** @var list<int> */
    public array $pits = [];

    /** @var list<int> */
    public array $owners = [];

    /** @var array{0: int, 1: int} */
    public array $captured = [0, 0];

    public int $turn = 0;

    public int $round = 1;

    public bool $roundOver = false;

    /** @var 0|1|null */
    public ?int $winner = null;

    /** @var array{totals: array{0:int,1:int}, gained: array{0:int,1:int}, stuck: int}|null */
    public ?array $roundResult = null;

    /** @var array{0: int|null, 1: int|null} */
    public array $openingPit = [null, null];

    public function mount(): void
    {
        $session = GameSession::activeOfType(Auth::id(), GameSession::TYPE_AYO)->latest()->first();

        if (! $session) {
            $this->setup = true;

            return;
        }

        $this->sessionId = $session->id;
        $this->opponent = $session->opponent;
        $this->difficulty = $session->difficulty;
        $this->applyState($session->state);
    }

    #[Computed]
    public function legalPits(): array
    {
        return $this->setup || $this->roundOver || $this->winner !== null ? [] : $this->game()->legalMoves();
    }

    public function startGame(string $opponent, ?string $difficulty = null): void
    {
        abort_unless(in_array($opponent, [GameSession::OPPONENT_HUMAN, GameSession::OPPONENT_COMPUTER], true), 422);

        if ($opponent === GameSession::OPPONENT_COMPUTER) {
            abort_unless(in_array($difficulty, [AyoBot::DIFFICULTY_BASIC, AyoBot::DIFFICULTY_MEDIUM, AyoBot::DIFFICULTY_HIGH], true), 422);
        } else {
            $difficulty = null;
        }

        $session = $this->createSession($opponent, $difficulty);

        $this->sessionId = $session->id;
        $this->opponent = $opponent;
        $this->difficulty = $difficulty;
        $this->applyState($session->state);
        $this->setup = false;
    }

    public function play(int $pit): void
    {
        // legalPits is what disables a pit's button in the template, so a
        // real click can't name an illegal pit or one of the computer's —
        // except a slow request (the board is otherwise still clickable
        // while one is in flight) can get double-submitted from a snapshot
        // that's gone stale the moment the first one resolves. That's not
        // an attack, just a race, so it's a silent no-op rather than an
        // error page; a genuinely forged request gets the same non-answer.
        if (! in_array($pit, $this->legalPits, true)) {
            return;
        }

        if ($this->opponent === GameSession::OPPONENT_COMPUTER && $this->turn !== 0) {
            return;
        }

        $game = $this->game();
        $events = $game->play($pit);
        $this->letBotReplyIfDue($game, $events);

        $this->applyState($game->toArray());
        // legalPits is #[Computed] — memoized from the check above, against
        // the board before this move. Without busting that cache, the
        // template renders whose-turn-it-was instead of whose-turn-it-is,
        // leaving the player who just moved still clickable and the player
        // who should move now stuck disabled.
        unset($this->legalPits);
        $this->persist($game);
        $this->dispatch('ayo-moved', events: $events);
        $this->showEndOfRoundModalsIfAny();
    }

    public function startNextRound(): void
    {
        // Same reasoning as play(): a stale double-submit of this button is
        // a race, not an attack, so it's a no-op rather than an error page.
        if (! $this->roundOver || $this->winner !== null) {
            return;
        }

        $game = $this->game();
        $game->startNextRound();
        $events = [];
        $this->letBotReplyIfDue($game, $events);

        $this->applyState($game->toArray());
        unset($this->legalPits);
        $this->persist($game);
        $this->modal('ayo-round-over')->close();

        if ($events !== []) {
            $this->dispatch('ayo-moved', events: $events);
        }

        $this->showEndOfRoundModalsIfAny();
    }

    public function newGame(): void
    {
        // The session id is a plain client-visible property — nothing stops
        // a forged request from setting it to someone else's session. This
        // is the backstop: the update below only ever touches a row that is
        // both this id and this user's, so a forged id just updates nothing.
        GameSession::where('id', $this->sessionId)->where('user_id', Auth::id())->update([
            'status' => GameSession::STATUS_FINISHED,
        ]);

        $this->setup = true;
        $this->modal('ayo-game-over')->close();
    }

    /**
     * Plays the computer's turn, if there is one due right now — after the
     * human's move, or when the computer is the one who opens a new round.
     * Its whole turn resolves server-side in the same request; the client
     * never sees an intermediate state where it's waiting on the computer.
     */
    private function letBotReplyIfDue(AyoGame $game, array &$events): void
    {
        if ($this->opponent === GameSession::OPPONENT_COMPUTER && $game->turn === 1 && ! $game->roundOver && $game->winner === null) {
            $pit = AyoBot::chooseMove($game, $this->difficulty);
            $events = array_merge($events, $game->play($pit));
        }
    }

    private function showEndOfRoundModalsIfAny(): void
    {
        if ($this->winner !== null) {
            $this->modal('ayo-game-over')->show();
        } elseif ($this->roundOver) {
            $this->modal('ayo-round-over')->show();
        }
    }

    private function createSession(string $opponent, ?string $difficulty): GameSession
    {
        return GameSession::create([
            'user_id' => Auth::id(),
            'type' => GameSession::TYPE_AYO,
            'opponent' => $opponent,
            'difficulty' => $difficulty,
            'state' => (new AyoGame)->toArray(),
        ]);
    }

    private function game(): AyoGame
    {
        return AyoGame::fromArray([
            'pits' => $this->pits,
            'owners' => $this->owners,
            'captured' => $this->captured,
            'turn' => $this->turn,
            'round' => $this->round,
            'roundOver' => $this->roundOver,
            'winner' => $this->winner,
            'roundResult' => $this->roundResult,
            'openingPit' => $this->openingPit,
        ]);
    }

    /** @param array<string, mixed> $state */
    private function applyState(array $state): void
    {
        $this->pits = $state['pits'];
        $this->owners = $state['owners'];
        $this->captured = $state['captured'];
        $this->turn = $state['turn'];
        $this->round = $state['round'];
        $this->roundOver = $state['roundOver'];
        $this->winner = $state['winner'];
        $this->roundResult = $state['roundResult'];
        // Absent on a session saved before this field existed.
        $this->openingPit = $state['openingPit'] ?? [null, null];
    }

    private function persist(AyoGame $game): void
    {
        GameSession::where('id', $this->sessionId)->where('user_id', Auth::id())->update([
            'state' => $game->toArray(),
            'status' => $game->winner !== null ? GameSession::STATUS_FINISHED : GameSession::STATUS_ACTIVE,
        ]);
    }
}; ?>

<div
    class="mx-auto w-full max-w-2xl"
    x-data="{
        flash: null,
        async animate(events) {
            for (const event of events) {
                if (event.pit === undefined) { continue; }
                this.flash = event.pit;
                await new Promise((resolve) => setTimeout(resolve, 90));
            }
            this.flash = null;
        },
    }"
    x-on:ayo-moved.window="animate($event.detail.events)"
>
    @php
        $vsComputer = $opponent === \App\Models\GameSession::OPPONENT_COMPUTER;
        $difficultyLabels = [
            \App\Games\Ayo\AyoBot::DIFFICULTY_BASIC => __('Basic'),
            \App\Games\Ayo\AyoBot::DIFFICULTY_MEDIUM => __('Medium'),
            \App\Games\Ayo\AyoBot::DIFFICULTY_HIGH => __('High'),
        ];
        $seat0Label = $vsComputer ? __('You') : __('Player 1');
        $seat1Label = $vsComputer ? __('Computer (:level)', ['level' => $difficultyLabels[$difficulty] ?? $difficulty]) : __('Player 2');
        // "Your turn" reads naturally where ":name's turn" (with $seat0Label
        // being the pronoun "You") would not.
        $turnLabel = $vsComputer
            ? ($turn === 0 ? __('Your turn') : __("Computer's turn"))
            : __(":name's turn", ['name' => $turn === 0 ? $seat0Label : $seat1Label]);

        // "wins"/"wins the game" is third person — correct for "Player 1" or
        // "Computer (High)", but not for the pronoun "You" ("You wins" is
        // wrong), so the human's own win in vs-computer mode gets its own
        // phrasing instead of dropping "You" into the same template.
        $winsPitSentence = function (int $winner, int $loser, int $count) use ($vsComputer, $seat0Label, $seat1Label) {
            $loserLabel = $loser === 0 ? $seat0Label : $seat1Label;

            if ($vsComputer && $winner === 0) {
                return trans_choice('You win 1 pit from :other.|You win :count pits from :other.', $count, ['other' => $loserLabel, 'count' => $count]);
            }

            return trans_choice(':name wins 1 pit from :other.|:name wins :count pits from :other.', $count, ['name' => $winner === 0 ? $seat0Label : $seat1Label, 'other' => $loserLabel, 'count' => $count]);
        };
        $winsGameSentence = function (?int $winner) use ($vsComputer, $seat0Label, $seat1Label) {
            $winner ??= 0;
            if ($vsComputer && $winner === 0) {
                return __('You win the game!');
            }

            return __(':name wins the game!', ['name' => $winner === 0 ? $seat0Label : $seat1Label]);
        };
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Ayo') }}</flux:heading>
            <flux:subheading>{{ __('A Yorùbá seed-sowing game — pass the device between turns.') }}</flux:subheading>
        </div>

        @unless ($setup)
            <flux:button
                wire:click="newGame"
                wire:confirm="{{ __('Start a new game? Your current progress will be lost.') }}"
                wire:loading.attr="disabled"
                size="sm"
                variant="ghost"
                icon="arrow-path"
            >
                {{ __('Restart') }}
            </flux:button>
        @endunless
    </div>

    <details class="surface-card mt-4 p-4 text-sm text-stone-600 dark:text-stone-400">
        <summary class="cursor-pointer font-medium text-stone-900 dark:text-white">{{ __('How to play') }}</summary>
        <div class="mt-3 space-y-2 leading-relaxed">
            <p>{{ __('Ayo (also called Oware or Awale) is a seed-sowing game with deep roots across West Africa — this version follows Yorùbá relay-sowing rules.') }}</p>
            <ul class="list-disc space-y-1 ps-5">
                <li>{{ __('Pick one of your pits. Its seeds are sown one by one into the pits after it.') }}</li>
                <li>{{ __("If your last seed lands in a pit that already has seeds, pick that pit up too and keep sowing — that's a relay.") }}</li>
                <li>{{ __('Your turn ends when your last seed lands in an empty pit.') }}</li>
                <li>{{ __('Whenever a pit reaches exactly 4 seeds, its owner captures them — unless your own last seed made it 4, in which case you capture it instead.') }}</li>
                <li>{{ __("A round ends when the player to move has no seeds left on their side. Every 4 seeds above your fair share wins you one of the opponent's pits.") }}</li>
                <li>{{ __('Own every pit on the board to win the game.') }}</li>
            </ul>
        </div>
    </details>

    @if ($setup)
        <div class="surface-card mt-4 p-5">
            <h2 class="font-semibold text-stone-900 dark:text-white">{{ __('Who are you playing?') }}</h2>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    wire:click="startGame('human')"
                    wire:loading.attr="disabled"
                    class="surface-card p-4 text-start transition hover:border-stone-300 dark:hover:border-stone-700"
                    data-test="setup-human"
                >
                    <flux:icon.user-group class="size-5 text-stone-500 dark:text-stone-400" />
                    <div class="mt-2 font-medium text-stone-900 dark:text-white">{{ __('Play a friend') }}</div>
                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">{{ __('Same device, passed back and forth.') }}</p>
                </button>

                <div class="surface-card p-4">
                    <flux:icon.cpu-chip class="size-5 text-stone-500 dark:text-stone-400" />
                    <div class="mt-2 font-medium text-stone-900 dark:text-white">{{ __('Play the computer') }}</div>
                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">{{ __('Choose how hard it plays.') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($difficultyLabels as $value => $label)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="startGame('computer', '{{ $value }}')"
                                wire:loading.attr="disabled"
                                data-test="setup-computer-{{ $value }}"
                            >
                                {{ $label }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="surface-card mt-3 flex items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-medium text-stone-400 dark:text-stone-500">{{ __('Challenge a friend') }}</div>
                    <p class="mt-1 text-xs text-stone-400 dark:text-stone-500">{{ __("Invite someone from their profile to a real match — you'll be able to let others watch, too.") }}</p>
                </div>
                <x-coming-soon-badge />
            </div>
        </div>
    @else
        <div class="surface-card mt-4 flex items-center justify-between p-3 text-sm">
            <span class="text-stone-500 dark:text-stone-400">{{ __('Round :number', ['number' => $round]) }}</span>
            <span class="inline-flex items-center gap-2 font-medium text-stone-900 dark:text-white">
                <span class="size-2 rounded-full bg-stone-900 dark:bg-white"></span>
                {{ $turnLabel }}
            </span>
        </div>

        <div class="surface-card mt-3 p-4">
            {{-- The top row is pits 11 down to 6, so it reads as a continuation of the sowing direction above the bottom row. --}}
            <div class="mb-2 flex items-center justify-between text-xs font-medium text-stone-500 dark:text-stone-400">
                <span>{{ $seat1Label }}</span>
                <span>{{ __('Captured: :count', ['count' => $captured[1]]) }}</span>
            </div>
            <div class="grid grid-cols-6 gap-2">
                @php $legalPits = $this->legalPits; @endphp
                @foreach ([11, 10, 9, 8, 7, 6] as $i)
                    @include('pages.games.ayo.pit', ['i' => $i, 'legalPits' => $legalPits, 'openingPit' => $openingPit])
                @endforeach
            </div>

            <div class="grid grid-cols-6 gap-2 mt-2">
                @foreach ([0, 1, 2, 3, 4, 5] as $i)
                    @include('pages.games.ayo.pit', ['i' => $i, 'legalPits' => $legalPits, 'openingPit' => $openingPit])
                @endforeach
            </div>
            <div class="mt-2 flex items-center justify-between text-xs font-medium text-stone-500 dark:text-stone-400">
                <span>{{ $seat0Label }}</span>
                <span>{{ __('Captured: :count', ['count' => $captured[0]]) }}</span>
            </div>
        </div>

        <flux:modal name="ayo-round-over" class="max-w-sm">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Round :number is over', ['number' => $round]) }}</flux:heading>

                @if ($roundResult)
                    <div class="space-y-1 text-sm text-stone-600 dark:text-stone-400">
                        <p>{{ __(':name finished with :count seeds.', ['name' => $seat0Label, 'count' => $roundResult['totals'][0]]) }}</p>
                        <p>{{ __(':name finished with :count seeds.', ['name' => $seat1Label, 'count' => $roundResult['totals'][1]]) }}</p>
                        @if ($roundResult['gained'][0] > 0)
                            <p>{{ $winsPitSentence(0, 1, $roundResult['gained'][0]) }}</p>
                        @elseif ($roundResult['gained'][1] > 0)
                            <p>{{ $winsPitSentence(1, 0, $roundResult['gained'][1]) }}</p>
                        @else
                            <p>{{ __('An even split — pit ownership stays the same.') }}</p>
                        @endif
                    </div>
                @endif

                <flux:button wire:click="startNextRound" wire:loading.attr="disabled" variant="primary" class="btn-flat-primary w-full">
                    {{ __('Start round :number', ['number' => $round + 1]) }}
                </flux:button>
            </div>
        </flux:modal>

        <flux:modal name="ayo-game-over" class="max-w-sm">
            <div class="space-y-4 text-center">
                <flux:heading size="lg">
                    {{ $winsGameSentence($winner) }}
                </flux:heading>
                <p class="text-sm text-stone-600 dark:text-stone-400">{{ __('One side now owns every pit on the board.') }}</p>

                <flux:button wire:click="newGame" wire:loading.attr="disabled" variant="primary" class="btn-flat-primary w-full">
                    {{ __('Play again') }}
                </flux:button>
            </div>
        </flux:modal>
    @endif
</div>
