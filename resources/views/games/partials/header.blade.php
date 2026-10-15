<header class="host-header">
    <div>
        <p class="host-kicker">Among Us IRL</p>
        <h1>{{ $game->name }}</h1>
        <p class="host-game-code">Game code: <strong>{{ $game->code }}</strong></p>
    </div>

    <button
        type="button"
        id="stop-game-button"
        class="stop-game-button"
        @if ($game->status !== 'running') hidden @endif
    >
        Stop Game
    </button>
</header>
