@extends('layouts.app')

@section('title', 'Among Us IRL')

@section('body-class', 'player-page')

@section('content')

    {{-- =========================================================
         LOBBY / WACHTSCHERM
         ========================================================= --}}

    @if ($player->game->status === 'lobby')

        <div id="waiting-screen">

            <h1>{{ strtoupper($player->name) }}</h1>

            <h2>Wachten op de spelleider...</h2>

            <p>
                Game: {{ $player->game->name }}
            </p>

            <p id="connection-status">
                Verbinding maken...
            </p>

        </div>


    {{-- =========================================================
         GAME IS GESTART
         ========================================================= --}}

    @elseif ($player->game->status === 'running')


        {{-- =====================================================
             ROLSCHERM
             ===================================================== --}}

        <div id="role-screen">

            <h1>{{ strtoupper($player->name) }}</h1>

            <h2>
                Game: {{ $player->game->name }}
            </h2>


            @if ($player->role === 'impostor')

                <h2>IMPOSTOR</h2>

                <h3>Mede-impostor(s):</h3>

                @forelse ($fellowImpostors as $impostor)

                    <p>
                        {{ $impostor->name }}
                    </p>

                @empty

                    <p>
                        Je bent de enige impostor.
                    </p>

                @endforelse

            @else

                <h2>CREWMATE</h2>

            @endif


            <button
                type="button"
                id="confirm-role"
                disabled
            >
                Verbinding maken...
            </button>


            <p
                id="camera-error"
                style="display: none;"
            ></p>

        </div>


        {{-- =====================================================
             SPELERKAART TIJDENS HET SPEL
             ===================================================== --}}

        <div
            id="nametag-screen"
            class="player-screen"
            style="display: none;"
        >

            <div
                id="player-card"
                class="player-card {{ !$player->alive ? 'player-card-dead' : '' }}"
            >

                <div class="player-card-title">
                    AMONG US IRL
                </div>


                <div class="player-card-name">
                    {{ strtoupper($player->name) }}
                </div>


                <div
                    id="bodycam-status"
                    class="bodycam-indicator"
                    aria-live="polite"
                >
                    ● BODYCAM STARTING
                </div>


                <button
                    type="button"
                    id="report-button"
                    class="report-button"
                    {{ !$player->alive ? 'disabled' : '' }}
                >
                    {{ $player->alive ? 'REPORT' : 'DOOD' }}
                </button>


                <p
                    id="report-help"
                    class="report-help"
                >
                    @if ($player->alive)

                        Druk wanneer iemand jouw body gevonden heeft.

                    @else

                        Je bent dood. Je blijft wel verder filmen.

                    @endif
                </p>


                {{-- Camera blijft actief, preview blijft verborgen --}}
                <video
                    id="camera-preview"
                    autoplay
                    muted
                    playsinline
                    class="hidden-camera-preview"
                ></video>


                <p
                    id="webrtc-status"
                    class="webrtc-status"
                >
                    Wachten op verbinding...
                </p>

            </div>

        </div>


        {{-- =====================================================
             REPORTER KIEZEN
             ===================================================== --}}

        <div
            id="reporter-screen"
            class="reporter-screen"
            style="display: none;"
        >

            <div class="reporter-card">

                <h2>
                    Wie ben jij?
                </h2>

                <p>
                    Kies jouw naam om deze body te rapporteren.
                </p>


                <div class="reporter-grid">

                    @forelse ($reportCandidates as $candidate)

                        <button
                            type="button"
                            class="reporter-button"
                            data-reporter-id="{{ $candidate->id }}"
                        >
                            {{ $candidate->name }}
                        </button>

                    @empty

                        <p>
                            Geen spelers beschikbaar.
                        </p>

                    @endforelse

                </div>


                <button
                    type="button"
                    id="cancel-report-button"
                    class="cancel-report-button"
                >
                    Annuleren
                </button>


                <p
                    id="report-error"
                    class="report-error"
                    style="display: none;"
                ></p>

            </div>

        </div>


        {{-- =====================================================
             BODY REPORTED / MEETING
             ===================================================== --}}

        <div
            id="meeting-screen"
            class="meeting-screen"
            style="display: none;"
        >

            <div class="meeting-card">

                <div class="meeting-title">
                    BODY REPORTED!
                </div>


                <div
                    id="meeting-body"
                    class="meeting-body"
                ></div>


                <div
                    id="meeting-reporter"
                    class="meeting-reporter"
                ></div>


                <p class="meeting-instruction">
                    Ga naar de meeting.
                </p>

            </div>

        </div>

    @endif

@endsection



@push('scripts')

<script>

    /*
    |--------------------------------------------------------------------------
    | HTML ELEMENTEN
    |--------------------------------------------------------------------------
    */

    const confirmRoleButton =
        document.getElementById('confirm-role');

    const roleScreen =
        document.getElementById('role-screen');

    const nametagScreen =
        document.getElementById('nametag-screen');

    const playerCard =
        document.getElementById('player-card');

    const cameraPreview =
        document.getElementById('camera-preview');

    const cameraError =
        document.getElementById('camera-error');

    const webrtcStatus =
        document.getElementById('webrtc-status');

    const bodycamStatus =
        document.getElementById('bodycam-status');

    const connectionStatus =
        document.getElementById('connection-status');


    /*
    |--------------------------------------------------------------------------
    | REPORT ELEMENTEN
    |--------------------------------------------------------------------------
    */

    const reportButton =
        document.getElementById('report-button');

    const reportHelp =
        document.getElementById('report-help');

    const reporterScreen =
        document.getElementById('reporter-screen');

    const cancelReportButton =
        document.getElementById('cancel-report-button');

    const reporterButtons =
        document.querySelectorAll('.reporter-button');

    const reportError =
        document.getElementById('report-error');


    /*
    |--------------------------------------------------------------------------
    | MEETING ELEMENTEN
    |--------------------------------------------------------------------------
    */

    const meetingScreen =
        document.getElementById('meeting-screen');

    const meetingBody =
        document.getElementById('meeting-body');

    const meetingReporter =
        document.getElementById('meeting-reporter');


    /*
    |--------------------------------------------------------------------------
    | PLAYER STATUS
    |--------------------------------------------------------------------------
    */

    let isCurrentPlayerDead =
        {{ $player->alive ? 'false' : 'true' }};


    /*
    |--------------------------------------------------------------------------
    | WEBRTC VARIABELEN
    |--------------------------------------------------------------------------
    */

    let localStream =
        null;

    let peerConnection =
        null;

    let pendingIceCandidates =
        [];


    const rtcConfiguration = {
        iceServers: []
    };


    /*
    |--------------------------------------------------------------------------
    | SDP NORMALISATIE
    |--------------------------------------------------------------------------
    */

    function normalizeSdp(sdp) {

        if (
            typeof sdp !== 'string'
        ) {

            return sdp;

        }


        let normalized =
            sdp
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n');


        normalized =
            normalized.replace(
                /\n+$/,
                ''
            );


        return normalized
            .replace(
                /\n/g,
                '\r\n'
            )
            + '\r\n';

    }


    function normalizeDescription(
        description
    ) {

        if (
            !description
            ||
            !description.type
            ||
            typeof description.sdp !== 'string'
        ) {

            return description;

        }


        return {

            type:
                description.type,

            sdp:
                normalizeSdp(
                    description.sdp
                )

        };

    }


    /*
    |--------------------------------------------------------------------------
    | PLAYER DEAD MAKEN
    |--------------------------------------------------------------------------
    */

    function setPlayerDead() {

        console.log(
            '💀 Deze speler is dood'
        );


        isCurrentPlayerDead =
            true;


        /*
         * Kaart zwart-wit / grijs.
         */
        if (playerCard) {

            playerCard.classList.add(
                'player-card-dead'
            );

        }


        /*
         * REPORT wordt DOOD.
         */
        if (reportButton) {

            reportButton.textContent =
                'DOOD';

            reportButton.disabled =
                true;

        }


        /*
         * Helptekst aanpassen.
         */
        if (reportHelp) {

            reportHelp.textContent =
                'Je bent dood. Je blijft wel verder filmen.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | TERUG NAAR HET SPEL NA EEN MEETING
    |--------------------------------------------------------------------------
    */

    function returnToGameScreen() {

        console.log(
            '🎮 Terug naar het spel'
        );


        /*
         * Meeting weg.
         */
        if (meetingScreen) {

            meetingScreen.style.display =
                'none';

        }


        /*
         * Reporterkeuze zeker weg.
         */
        if (reporterScreen) {

            reporterScreen.style.display =
                'none';

        }


        /*
         * Rolscreen zeker weg.
         */
        if (roleScreen) {

            roleScreen.style.display =
                'none';

        }


        /*
         * Player screen opnieuw tonen.
         */
        if (nametagScreen) {

            nametagScreen.style.display =
                'flex';

        }


        /*
         * Indien deze speler dood is,
         * dead-state opnieuw toepassen.
         */
        if (isCurrentPlayerDead) {

            setPlayerDead();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | REVERB / PUSHER
    |--------------------------------------------------------------------------
    */

    Pusher.logToConsole =
        true;


    const pusher =
        new Pusher(

            '{{ config('broadcasting.connections.reverb.key') }}',

            {
                cluster:
                    'mt1',

                wsHost:
                    window.location.hostname,

                wssPort:
                    443,

                forceTLS:
                    true,

                enabledTransports:
                    ['ws'],
            }

        );


    window.pusher =
        pusher;


    /*
    |--------------------------------------------------------------------------
    | REVERB CONNECTION STATUS
    |--------------------------------------------------------------------------
    */

    pusher.connection.bind(

        'connected',

        function () {

            console.log(
                '✅ Player connected to Reverb'
            );


            if (connectionStatus) {

                connectionStatus.textContent =
                    'Verbonden. Wachten op de spelleider...';

            }

        }

    );


    pusher.connection.bind(

        'error',

        function (error) {

            console.error(
                '❌ Reverb fout:',
                error
            );

        }

    );


    /*
    |--------------------------------------------------------------------------
    | GAME CHANNEL
    |--------------------------------------------------------------------------
    */

    const channel =
        pusher.subscribe(
            'game.{{ $player->game->id }}'
        );


    window.gameChannel =
        channel;


    /*
    |--------------------------------------------------------------------------
    | CHANNEL KLAAR
    |--------------------------------------------------------------------------
    */

    channel.bind(

        'pusher:subscription_succeeded',

        function () {

            console.log(
                '✅ Player subscribed to game.{{ $player->game->id }}'
            );


            if (confirmRoleButton) {

                confirmRoleButton.disabled =
                    false;

                confirmRoleButton.textContent =
                    'Ik heb mijn rol gezien';

            }


            if (connectionStatus) {

                connectionStatus.textContent =
                    'Verbonden. Wachten op de spelleider...';

            }

        }

    );


    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT LOGGING
    |--------------------------------------------------------------------------
    */

    channel.bind_global(

        function (
            eventName,
            data
        ) {

            console.log(
                '📡 GSM EVENT:',
                eventName,
                data
            );

        }

    );


    /*
    |--------------------------------------------------------------------------
    | GAME STARTED
    |--------------------------------------------------------------------------
    */

    channel.bind(

        'game.started',

        function (data) {

            console.log(
                '🎮 Game started:',
                data
            );


            window.location.reload();

        }

    );


    /*
    |--------------------------------------------------------------------------
    | BODY REPORTED
    |--------------------------------------------------------------------------
    */

    channel.bind(

        'body.reported',

        function (data) {

            console.log(
                '🚨 BODY REPORTED:',
                data
            );


            /*
             * Is deze gsm van de body?
             */
            const currentPlayerId =
                {{ $player->id }};


            if (
                Number(data.body.id)
                ===
                currentPlayerId
            ) {

                setPlayerDead();

            }


            /*
             * Andere schermen verbergen.
             */
            if (nametagScreen) {

                nametagScreen.style.display =
                    'none';

            }


            if (roleScreen) {

                roleScreen.style.display =
                    'none';

            }


            if (reporterScreen) {

                reporterScreen.style.display =
                    'none';

            }


            /*
             * Meeting-informatie.
             */
            if (meetingBody) {

                meetingBody.textContent =
                    data.body.name
                    + ' werd dood gevonden';

            }


            if (meetingReporter) {

                meetingReporter.textContent =
                    'Reported by '
                    + data.reporter.name;

            }


            /*
             * Meeting tonen.
             */
            if (meetingScreen) {

                meetingScreen.style.display =
                    'flex';

            }


            /*
             * De nieuwe dode speler lokaal
             * verwijderen uit mogelijke reporters.
             */
            const deadPlayerButton =
                document.querySelector(

                    '.reporter-button'
                    + '[data-reporter-id="'
                    + data.body.id
                    + '"]'

                );


            if (deadPlayerButton) {

                deadPlayerButton.remove();

            }

        }

    );


    /*
    |--------------------------------------------------------------------------
    | MEETING AFGELOPEN
    |--------------------------------------------------------------------------
    |
    | Dit event gaan we straks vanaf het hostscherm versturen.
    |
    | Levende spelers:
    |     → normale player card
    |
    | Dode speler:
    |     → grijze player card
    |     → DOOD disabled
    |
    */

    channel.bind(

        'meeting.ended',

        function (data) {

            console.log(
                '🗳️ Meeting afgelopen:',
                data
            );


            returnToGameScreen();

        }

    );


    /*
    |--------------------------------------------------------------------------
    | SIGNALING SPELER → HOST
    |--------------------------------------------------------------------------
    */

    async function sendPlayerSignal(
        signal
    ) {

        const response =
            await fetch(

                '{{ route('players.webrtc-signal', ['token' => $player->token]) }}',

                {
                    method:
                        'POST',

                    headers: {

                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            document
                                .querySelector(
                                    'meta[name="csrf-token"]'
                                )
                                .content

                    },

                    body:
                        JSON.stringify(
                            signal
                        )

                }

            );


        if (!response.ok) {

            const errorText =
                await response.text();


            console.error(
                '❌ WebRTC signaling fout:',
                errorText
            );


            throw new Error(
                'WebRTC-signaal kon niet verstuurd worden.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | WEBRTC SIGNALING HOST → SPELER
    |--------------------------------------------------------------------------
    */

    channel.bind(

        'webrtc.signal',

        async function (signal) {

            /*
             * Alleen signalen van host.
             */
            if (
                signal.sender !== 'host'
            ) {

                return;

            }


            /*
             * Alleen voor deze speler.
             */
            if (
                Number(signal.player_id)
                !==
                {{ $player->id }}
            ) {

                return;

            }


            try {

                /*
                |--------------------------------------------------------------------------
                | OFFER
                |--------------------------------------------------------------------------
                */

                if (
                    signal.kind === 'offer'
                ) {

                    console.log(
                        '📥 Offer ontvangen'
                    );


                    if (webrtcStatus) {

                        webrtcStatus.textContent =
                            'Offer ontvangen van host...';

                    }


                    if (bodycamStatus) {

                        bodycamStatus.textContent =
                            '● BODYCAM CONNECTING';

                    }


                    /*
                     * Oude verbinding sluiten.
                     */
                    if (peerConnection) {

                        peerConnection.close();

                    }


                    /*
                     * Nieuwe PeerConnection.
                     */
                    peerConnection =
                        new RTCPeerConnection(
                            rtcConfiguration
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | ICE GSM → HOST
                    |--------------------------------------------------------------------------
                    */

                    peerConnection.onicecandidate =
                        async function (event) {

                            if (
                                !event.candidate
                            ) {

                                return;

                            }


                            try {

                                await sendPlayerSignal({

                                    kind:
                                        'ice',

                                    candidate:
                                        event
                                            .candidate
                                            .toJSON()

                                });

                            } catch (error) {

                                console.error(
                                    '❌ ICE versturen mislukt:',
                                    error
                                );

                            }

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | CONNECTION STATUS
                    |--------------------------------------------------------------------------
                    */

                    peerConnection.onconnectionstatechange =
                        function () {

                            const state =
                                peerConnection
                                    .connectionState;


                            console.log(
                                '🔗 GSM WebRTC:',
                                state
                            );


                            if (webrtcStatus) {

                                webrtcStatus.textContent =
                                    'WebRTC: '
                                    + state;

                            }


                            if (bodycamStatus) {

                                if (
                                    state === 'new'
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM STARTING';

                                }


                                if (
                                    state === 'connecting'
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM CONNECTING';

                                }


                                if (
                                    state === 'connected'
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM LIVE';

                                }


                                if (
                                    state === 'disconnected'
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM CONNECTION LOST';

                                }


                                if (
                                    state === 'failed'
                                    ||
                                    state === 'closed'
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM OFFLINE';

                                }

                            }

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | ICE CONNECTION STATUS
                    |--------------------------------------------------------------------------
                    */

                    peerConnection.oniceconnectionstatechange =
                        function () {

                            console.log(
                                '🧊 GSM ICE:',
                                peerConnection
                                    .iceConnectionState
                            );

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | OFFER INSTELLEN
                    |--------------------------------------------------------------------------
                    */

                    const normalizedOffer =
                        normalizeDescription(
                            signal.description
                        );


                    await peerConnection
                        .setRemoteDescription(
                            normalizedOffer
                        );


                    /*
                     * Camera moet bestaan.
                     */
                    if (
                        !localStream
                    ) {

                        throw new Error(
                            'Camera stream bestaat nog niet.'
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CAMERATRACKS TOEVOEGEN
                    |--------------------------------------------------------------------------
                    */

                    localStream
                        .getTracks()
                        .forEach(

                            function (track) {

                                peerConnection
                                    .addTrack(
                                        track,
                                        localStream
                                    );

                            }

                        );


                    /*
                    |--------------------------------------------------------------------------
                    | ANSWER MAKEN
                    |--------------------------------------------------------------------------
                    */

                    const answer =
                        await peerConnection
                            .createAnswer();


                    await peerConnection
                        .setLocalDescription(
                            answer
                        );


                    const normalizedAnswer =
                        normalizeDescription({

                            type:
                                peerConnection
                                    .localDescription
                                    .type,

                            sdp:
                                peerConnection
                                    .localDescription
                                    .sdp

                        });


                    /*
                    |--------------------------------------------------------------------------
                    | ANSWER → HOST
                    |--------------------------------------------------------------------------
                    */

                    await sendPlayerSignal({

                        kind:
                            'answer',

                        description:
                            normalizedAnswer

                    });


                    if (webrtcStatus) {

                        webrtcStatus.textContent =
                            'Answer verstuurd. Verbinding maken...';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | WACHTENDE ICE
                    |--------------------------------------------------------------------------
                    */

                    for (
                        const candidate
                        of pendingIceCandidates
                    ) {

                        await peerConnection
                            .addIceCandidate(
                                candidate
                            );

                    }


                    pendingIceCandidates =
                        [];

                }


                /*
                |--------------------------------------------------------------------------
                | ICE VAN HOST
                |--------------------------------------------------------------------------
                */

                if (
                    signal.kind === 'ice'
                ) {

                    if (
                        peerConnection
                        &&
                        peerConnection
                            .remoteDescription
                    ) {

                        await peerConnection
                            .addIceCandidate(
                                signal.candidate
                            );

                    } else {

                        pendingIceCandidates
                            .push(
                                signal.candidate
                            );

                    }

                }

            } catch (error) {

                console.error(
                    '❌ WebRTC fout:',
                    error
                );


                if (webrtcStatus) {

                    webrtcStatus.textContent =
                        'WebRTC fout: '
                        + error.message;

                }


                if (bodycamStatus) {

                    bodycamStatus.textContent =
                        '● BODYCAM OFFLINE';

                }

            }

        }

    );


    /*
    |--------------------------------------------------------------------------
    | ROL BEVESTIGEN + CAMERA STARTEN
    |--------------------------------------------------------------------------
    */

    confirmRoleButton
        ?.addEventListener(

            'click',

            async function () {

                confirmRoleButton.disabled =
                    true;


                confirmRoleButton.textContent =
                    'Camera starten...';


                try {

                    /*
                    |--------------------------------------------------------------------------
                    | CAMERA STARTEN
                    |--------------------------------------------------------------------------
                    */

                    localStream =
                        await navigator
                            .mediaDevices
                            .getUserMedia({

                                video: {

                                    facingMode:
                                        'user',

                                    width: {
                                        ideal: 640
                                    },

                                    height: {
                                        ideal: 360
                                    },

                                    frameRate: {
                                        ideal: 15,
                                        max: 20
                                    }

                                },

                                audio:
                                    false

                            });


                    console.log(
                        '📷 Camera gestart'
                    );


                    cameraPreview.srcObject =
                        localStream;


                    /*
                     * Rolscreen weg.
                     */
                    roleScreen.style.display =
                        'none';


                    /*
                     * Player screen tonen.
                     */
                    nametagScreen.style.display =
                        'flex';


                    /*
                     * Indien deze speler al dood was
                     * vóór bijvoorbeeld een refresh:
                     *
                     * DOOD status opnieuw toepassen.
                     */
                    if (
                        isCurrentPlayerDead
                    ) {

                        setPlayerDead();

                    }


                    if (bodycamStatus) {

                        bodycamStatus.textContent =
                            '● BODYCAM STARTING';

                    }


                    if (webrtcStatus) {

                        webrtcStatus.textContent =
                            'Camera klaar. Host verwittigen...';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CAMERA READY → HOST
                    |--------------------------------------------------------------------------
                    */

                    const response =
                        await fetch(

                            '{{ route('players.cameraReady', ['token' => $player->token]) }}',

                            {
                                method:
                                    'POST',

                                headers: {

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        document
                                            .querySelector(
                                                'meta[name="csrf-token"]'
                                            )
                                            .content

                                }

                            }

                        );


                    if (
                        !response.ok
                    ) {

                        const errorText =
                            await response.text();


                        console.error(
                            'Camera-ready fout:',
                            errorText
                        );


                        throw new Error(
                            'Kon camera-status niet doorgeven aan de host.'
                        );

                    }


                    if (webrtcStatus) {

                        webrtcStatus.textContent =
                            'Camera klaar. Wachten op host...';

                    }

                } catch (error) {

                    console.error(
                        '❌ Camera fout:',
                        error
                    );


                    confirmRoleButton.disabled =
                        false;


                    confirmRoleButton.textContent =
                        'Opnieuw proberen';


                    if (cameraError) {

                        cameraError.style.display =
                            'block';


                        cameraError.textContent =
                            'Camera kon niet worden gestart: '
                            + error.message;

                    }


                    if (bodycamStatus) {

                        bodycamStatus.textContent =
                            '● BODYCAM OFFLINE';

                    }

                }

            }

        );


    /*
    |--------------------------------------------------------------------------
    | REPORT KNOP
    |--------------------------------------------------------------------------
    */

    reportButton
        ?.addEventListener(

            'click',

            function () {

                /*
                 * Dode speler kan niet reporten.
                 */
                if (
                    reportButton.disabled
                    ||
                    isCurrentPlayerDead
                ) {

                    return;

                }


                console.log(
                    '🚨 REPORT ingedrukt'
                );


                /*
                 * Playerkaart weg.
                 */
                if (nametagScreen) {

                    nametagScreen.style.display =
                        'none';

                }


                /*
                 * Reporterselectie tonen.
                 */
                if (reporterScreen) {

                    reporterScreen.style.display =
                        'flex';

                }


                /*
                 * Oude error wissen.
                 */
                if (reportError) {

                    reportError.style.display =
                        'none';

                    reportError.textContent =
                        '';

                }

            }

        );


    /*
    |--------------------------------------------------------------------------
    | REPORT ANNULEREN
    |--------------------------------------------------------------------------
    */

    cancelReportButton
        ?.addEventListener(

            'click',

            function () {

                if (reporterScreen) {

                    reporterScreen.style.display =
                        'none';

                }


                if (nametagScreen) {

                    nametagScreen.style.display =
                        'flex';

                }


                if (reportError) {

                    reportError.style.display =
                        'none';

                }

            }

        );


    /*
    |--------------------------------------------------------------------------
    | REPORTER KIEZEN
    |--------------------------------------------------------------------------
    */

    reporterButtons.forEach(

        function (button) {

            button.addEventListener(

                'click',

                async function () {

                    const reporterId =
                        Number(
                            button.dataset.reporterId
                        );


                    /*
                     * Geen dubbele clicks.
                     */
                    reporterButtons.forEach(

                        function (item) {

                            item.disabled =
                                true;

                        }

                    );


                    if (cancelReportButton) {

                        cancelReportButton.disabled =
                            true;

                    }


                    if (reportError) {

                        reportError.style.display =
                            'block';

                        reportError.textContent =
                            'Report versturen...';

                    }


                    try {

                        /*
                         * token = gevonden body
                         *
                         * reporter_id = speler
                         * die de body gevonden heeft
                         */
                        const response =
                            await fetch(

                                '{{ route('players.report', ['token' => $player->token]) }}',

                                {
                                    method:
                                        'POST',

                                    headers: {

                                        'Content-Type':
                                            'application/json',

                                        'Accept':
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            document
                                                .querySelector(
                                                    'meta[name="csrf-token"]'
                                                )
                                                .content

                                    },

                                    body:
                                        JSON.stringify({

                                            reporter_id:
                                                reporterId

                                        })

                                }

                            );


                        const data =
                            await response.json();


                        if (
                            !response.ok
                        ) {

                            throw new Error(
                                data.error
                                ??
                                data.message
                                ??
                                'Report kon niet verstuurd worden.'
                            );

                        }


                        console.log(
                            '✅ Report verwerkt:',
                            data
                        );


                        /*
                         * body.reported komt via Reverb.
                         */
                        if (reportError) {

                            reportError.textContent =
                                'Report verstuurd...';

                        }

                    } catch (error) {

                        console.error(
                            '❌ Report fout:',
                            error
                        );


                        if (reportError) {

                            reportError.style.display =
                                'block';

                            reportError.textContent =
                                error.message;

                        }


                        /*
                         * Opnieuw proberen toelaten.
                         */
                        reporterButtons.forEach(

                            function (item) {

                                item.disabled =
                                    false;

                            }

                        );


                        if (cancelReportButton) {

                            cancelReportButton.disabled =
                                false;

                        }

                    }

                }

            );

        }

    );

</script>

@endpush