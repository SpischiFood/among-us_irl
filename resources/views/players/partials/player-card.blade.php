<div id="nametag-screen" class="player-screen" hidden>
    <div id="player-card" class="player-card @if (!$player->alive) player-card-dead @endif">
        <div class="player-card-title">AMONG US IRL</div>
        <div class="player-card-name">{{ strtoupper($player->name) }}</div>

        <div id="bodycam-status" class="bodycam-indicator" aria-live="polite">
            ● BODYCAM STARTING
        </div>

        @if ($player->alive)
            <button type="button" id="report-button" class="report-button">REPORT</button>
            <p id="report-help" class="report-help">Druk wanneer iemand jouw body gevonden heeft.</p>
        @else
            <button type="button" id="report-button" class="report-button" disabled>DOOD</button>
            <p id="report-help" class="report-help">Je bent dood. Je blijft wel verder filmen.</p>
        @endif

        <video id="camera-preview" autoplay muted playsinline class="hidden-camera-preview"></video>
        <p id="webrtc-status" class="webrtc-status">Wachten op verbinding...</p>
    </div>
</div>
