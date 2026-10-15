<div id="waiting-screen" class="waiting-screen">
    <p class="screen-kicker">Among Us IRL</p>
    <h1>{{ strtoupper($player->name) }}</h1>
    <h2>Wachten op de spelleider...</h2>
    <p>Game: {{ $player->game->name }}</p>
    <p id="connection-status" class="connection-status">Verbinding maken...</p>
</div>
