<div id="role-screen" class="role-screen">
    <p class="screen-kicker">{{ $player->game->name }}</p>
    <h1>{{ strtoupper($player->name) }}</h1>

    @if ($player->role === 'impostor')
        <h2 class="role-title role-impostor">IMPOSTOR</h2>

        <div class="role-extra">
            <h3>Mede-impostor(s)</h3>

            @forelse ($fellowImpostors as $impostor)
                <p>{{ $impostor->name }}</p>
            @empty
                <p>Je bent de enige impostor.</p>
            @endforelse
        </div>
    @else
        <h2 class="role-title role-crewmate">CREWMATE</h2>
    @endif

    <button type="button" id="confirm-role" class="confirm-role-button" disabled>
        Verbinding maken...
    </button>

    <p id="camera-error" class="camera-error" hidden></p>
</div>
