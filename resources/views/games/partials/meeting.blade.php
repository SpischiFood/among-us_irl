<div id="host-meeting-screen" class="host-meeting-screen" hidden>
    <p class="meeting-kicker">Fysieke discussie & stemming</p>
    <h1>MEETING</h1>
    <p class="meeting-help">Klik na de stemming op de speler die uit het spel is gestemd.</p>

    <div id="meeting-player-grid" class="meeting-player-grid">
        @foreach ($game->players as $player)
            @if ($player->alive)
                <button
                    type="button"
                    class="meeting-player-button"
                    data-player-id="{{ $player->id }}"
                    data-player-name="{{ $player->name }}"
                    data-alive="1"
                >
                    {{ $player->name }}
                </button>
            @else
                <button
                    type="button"
                    class="meeting-player-button"
                    data-player-id="{{ $player->id }}"
                    data-player-name="{{ $player->name }}"
                    data-alive="0"
                    disabled
                >
                    {{ $player->name }} — DOOD
                </button>
            @endif
        @endforeach
    </div>

    <p id="host-meeting-error" class="host-meeting-error" hidden></p>
</div>
