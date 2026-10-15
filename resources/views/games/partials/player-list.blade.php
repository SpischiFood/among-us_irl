<section class="host-panel players-panel">
    <div class="section-heading">
        <div>
            <p class="section-kicker">Lobby</p>
            <h2>Players</h2>
        </div>

        <p id="game-status" class="game-status game-status-{{ $game->status }}">
            @if ($game->status === 'running')
                Game is bezig
            @elseif ($game->status === 'finished')
                Game afgelopen
            @else
                Wachten om de game te starten
            @endif
        </p>
    </div>

    <div id="players" class="player-chip-list">
        @forelse ($game->players as $player)
            <p data-player-id="{{ $player->id }}">{{ $player->name }}</p>
        @empty
            <p id="no-players" class="empty-state">Nog geen spelers in de lobby.</p>
        @endforelse
    </div>

    @if ($game->status === 'lobby')
        <form id="start-game-form" action="{{ route('games.start', $game) }}" method="POST">
            @csrf

            <button type="submit" id="start-game-button" class="primary-button">
                Start Game
            </button>
        </form>
    @else
        <button type="button" id="start-game-button" class="primary-button" disabled>
            @if ($game->status === 'finished')
                Game afgelopen
            @else
                Game gestart
            @endif
        </button>
    @endif
</section>
