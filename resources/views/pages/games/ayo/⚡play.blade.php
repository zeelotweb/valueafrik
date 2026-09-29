<?php

use App\Games\Ayo\AyoGame;
use App\Models\GameSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ayo')] class extends Component {
    public int $sessionId;

    /** @var list<int> */
    public array $pits;

    /** @var list<int> */
    public array $owners;

    /** @var array{0: int, 1: int} */
    public array $captured;

    public int $turn;

    public int $round;

    public bool $roundOver;

    /** @var 0|1|null */
    public ?int $winner = null;

    /** @var array{totals: array{0:int,1:int}, gained: array{0:int,1:int}, stuck: int}|null */
    public ?array $roundResult = null;

    public function mount(): void
    {
        $session = GameSession::activeOfType(Auth::id(), GameSession::TYPE_AYO)->latest()->first()
            ?? $this->createSession();

        $this->sessionId = $session->id;
        $this->applyState($session->state);
    }

    #[Computed]
    public function legalPits(): array
    {
        return $this->roundOver || $this->winner !== null ? [] : $this->game()->legalMoves();
    }

    public function play(int $pit): void
    {
        // legalPits is what disables a pit's button in the template — this
        // is the backstop for a forged wire:click that skips the disabled
        // state entirely, not a path a real click can reach.
        abort_unless(in_array($pit, $this->legalPits, true), 422);

        $game = $this->game();
        $events = $game->play($pit);

        $this->applyState($game->toArray());
        $this->persist($game);
        $this->dispatch('ayo-moved', events: $events);

        if ($this->winner !== null) {
            $this->modal('ayo-game-over')->show();
        } elseif ($this->roundOver) {
            $this->modal('ayo-round-over')->show();
        }
    }

    public function startNextRound(): void
    {
        abort_unless($this->roundOver && $this->winner === null, 422);

        $game = $this->game();
        $game->startNextRound();

        $this->applyState($game->toArray());
        $this->persist($game);
        $this->modal('ayo-round-over')->close();
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

        $session = $this->createSession();
        $this->sessionId = $session->id;
        $this->applyState($session->state);
        $this->modal('ayo-game-over')->close();
    }

    private function createSession(): GameSession
    {
        return GameSession::create([
            'user_id' => Auth::id(),
            'type' => GameSession::TYPE_AYO,
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
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Ayo') }}</flux:heading>
            <flux:subheading>{{ __('A Yorùbá seed-sowing game — pass the device between turns.') }}</flux:subheading>
        </div>

        <flux:button
            wire:click="newGame"
            wire:confirm="{{ __('Start a new game? Your current progress will be lost.') }}"
            size="sm"
            variant="ghost"
            icon="arrow-path"
        >
            {{ __('Restart') }}
        </flux:button>
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

    <div class="surface-card mt-4 flex items-center justify-between p-3 text-sm">
        <span class="text-stone-500 dark:text-stone-400">{{ __('Round :number', ['number' => $round]) }}</span>
        <span class="inline-flex items-center gap-2 font-medium text-stone-900 dark:text-white">
            <span class="size-2 rounded-full bg-stone-900 dark:bg-white"></span>
            {{ $turn === 0 ? __('Player 1\'s turn') : __("Player 2's turn") }}
        </span>
    </div>

    <div class="surface-card mt-3 p-4">
        {{-- Player 2's row: pits 11 down to 6, so it reads as a continuation of the sowing direction above Player 1's row. --}}
        <div class="mb-2 flex items-center justify-between text-xs font-medium text-stone-500 dark:text-stone-400">
            <span>{{ __('Player 2') }}</span>
            <span>{{ __('Captured: :count', ['count' => $captured[1]]) }}</span>
        </div>
        <div class="grid grid-cols-6 gap-2">
            @php $legalPits = $this->legalPits; @endphp
            @foreach ([11, 10, 9, 8, 7, 6] as $i)
                @include('pages.games.ayo.pit', ['i' => $i, 'legalPits' => $legalPits])
            @endforeach
        </div>

        <div class="grid grid-cols-6 gap-2 mt-2">
            @foreach ([0, 1, 2, 3, 4, 5] as $i)
                @include('pages.games.ayo.pit', ['i' => $i, 'legalPits' => $legalPits])
            @endforeach
        </div>
        <div class="mt-2 flex items-center justify-between text-xs font-medium text-stone-500 dark:text-stone-400">
            <span>{{ __('Player 1') }}</span>
            <span>{{ __('Captured: :count', ['count' => $captured[0]]) }}</span>
        </div>
    </div>

    <flux:modal name="ayo-round-over" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Round :number is over', ['number' => $round]) }}</flux:heading>

            @if ($roundResult)
                <div class="space-y-1 text-sm text-stone-600 dark:text-stone-400">
                    <p>{{ __('Player 1 finished with :count seeds.', ['count' => $roundResult['totals'][0]]) }}</p>
                    <p>{{ __('Player 2 finished with :count seeds.', ['count' => $roundResult['totals'][1]]) }}</p>
                    @if ($roundResult['gained'][0] > 0)
                        <p>{{ trans_choice('Player 1 wins 1 pit from Player 2.|Player 1 wins :count pits from Player 2.', $roundResult['gained'][0], ['count' => $roundResult['gained'][0]]) }}</p>
                    @elseif ($roundResult['gained'][1] > 0)
                        <p>{{ trans_choice('Player 2 wins 1 pit from Player 1.|Player 2 wins :count pits from Player 1.', $roundResult['gained'][1], ['count' => $roundResult['gained'][1]]) }}</p>
                    @else
                        <p>{{ __('An even split — pit ownership stays the same.') }}</p>
                    @endif
                </div>
            @endif

            <flux:button wire:click="startNextRound" variant="primary" class="btn-flat-primary w-full">
                {{ __('Start round :number', ['number' => $round + 1]) }}
            </flux:button>
        </div>
    </flux:modal>

    <flux:modal name="ayo-game-over" class="max-w-sm">
        <div class="space-y-4 text-center">
            <flux:heading size="lg">
                {{ $winner === 0 ? __('Player 1 wins the game!') : __('Player 2 wins the game!') }}
            </flux:heading>
            <p class="text-sm text-stone-600 dark:text-stone-400">{{ __('One side now owns every pit on the board.') }}</p>

            <flux:button wire:click="newGame" variant="primary" class="btn-flat-primary w-full">
                {{ __('Play again') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
