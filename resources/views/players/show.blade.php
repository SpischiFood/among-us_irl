@extends('layouts.app')

@section('title', 'Among Us IRL')

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


            {{--
                Pas actief wanneer deze gsm effectief
                verbonden is met het Reverb-kanaal.
            --}}

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

            <div class="player-card">

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
                >
                    REPORT
                </button>


                <p class="report-help">
                    Druk wanneer je een body gevonden hebt.
                </p>


                {{--
                    Camera blijft actief voor WebRTC,
                    maar hoeft niet zichtbaar te zijn voor de speler.
                --}}

                <video
                    id="camera-preview"
                    autoplay
                    muted
                    playsinline
                    class="hidden-camera-preview"
                ></video>


                {{--
                    Voorlopig handig tijdens development.
                    Later kunnen we deze tekst verbergen.
                --}}

                <p
                    id="webrtc-status"
                    class="webrtc-status"
                >
                    Wachten op verbinding...
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

    const reportButton =
        document.getElementById('report-button');


    /*
    |--------------------------------------------------------------------------
    | WEBRTC VARIABELEN
    |--------------------------------------------------------------------------
    */

    let localStream = null;

    let peerConnection = null;

    let pendingIceCandidates = [];


    /*
     * Je huidige setup werkt zonder STUN/TURN,
     * dus veranderen we dat voorlopig niet.
     */
    const rtcConfiguration = {
        iceServers: []
    };


    /*
    |--------------------------------------------------------------------------
    | REVERB / PUSHER
    |--------------------------------------------------------------------------
    */

    Pusher.logToConsole = true;


    const pusher = new Pusher(
        '{{ config('broadcasting.connections.reverb.key') }}',
        {
            cluster: 'mt1',

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
    |
    | De speler mag zijn rol pas bevestigen wanneer
    | deze gsm zeker WebRTC-events kan ontvangen.
    |
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
        function (eventName, data) {

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
    |
    | In lobby:
    |
    | game.started
    |     ↓
    | reload
    |     ↓
    | Laravel toont de rol
    |
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
    | SIGNALING SPELER → HOST
    |--------------------------------------------------------------------------
    */

    async function sendPlayerSignal(
        signal
    ) {

        console.log(
            '📤 GSM WebRTC signal:',
            signal
        );


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
                '❌ WebRTC signaling serverfout:',
                errorText
            );


            throw new Error(
                'WebRTC-signaal kon niet verstuurd worden.'
            );

        }


        console.log(
            '✅ WebRTC signal verstuurd'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | WEBRTC SIGNALING HOST → SPELER
    |--------------------------------------------------------------------------
    */

    channel.bind(
        'webrtc.signal',

        async function (signal) {

            console.log(
                '📡 WebRTC signal ontvangen op gsm:',
                signal
            );


            /*
             * Alleen host-signalen verwerken.
             */
            if (
                signal.sender !== 'host'
            ) {

                return;

            }


            /*
             * Alleen signalen voor deze speler.
             */
            if (
                Number(signal.player_id)
                !== {{ $player->id }}
            ) {

                return;

            }


            try {

                /*
                |--------------------------------------------------------------------------
                | OFFER VAN HOST
                |--------------------------------------------------------------------------
                */

                if (
                    signal.kind === 'offer'
                ) {

                    console.log(
                        '📥 WebRTC offer ontvangen van host'
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
                     * Oude verbinding eventueel sluiten.
                     */
                    if (peerConnection) {

                        peerConnection.close();

                    }


                    /*
                     * Nieuwe peer connection.
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

                            if (!event.candidate) {

                                return;

                            }


                            console.log(
                                '🧊 GSM ICE candidate:',
                                event.candidate
                            );


                            try {

                                await sendPlayerSignal(
                                    {
                                        kind:
                                            'ice',

                                        candidate:
                                            event
                                                .candidate
                                                .toJSON()
                                    }
                                );

                            } catch (error) {

                                console.error(
                                    '❌ ICE candidate versturen mislukt:',
                                    error
                                );

                            }

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | WEBRTC CONNECTION STATUS
                    |--------------------------------------------------------------------------
                    */

                    peerConnection.onconnectionstatechange =
                        function () {

                            const state =
                                peerConnection
                                    .connectionState;


                            console.log(
                                '🔗 GSM WebRTC status:',
                                state
                            );


                            /*
                             * Development info.
                             */
                            if (webrtcStatus) {

                                webrtcStatus.textContent =
                                    'WebRTC: ' + state;

                            }


                            /*
                             * Status die tijdens het spel
                             * zichtbaar blijft.
                             */
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
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM OFFLINE';

                                }


                                if (
                                    state === 'closed'
                                ) {

                                    bodycamStatus.textContent =
                                        '● BODYCAM OFFLINE';

                                }

                            }

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | ICE STATUS
                    |--------------------------------------------------------------------------
                    */

                    peerConnection.oniceconnectionstatechange =
                        function () {

                            console.log(
                                '🧊 GSM ICE status:',
                                peerConnection
                                    .iceConnectionState
                            );

                        };


                    /*
                    |--------------------------------------------------------------------------
                    | OFFER INSTELLEN
                    |--------------------------------------------------------------------------
                    */

                    await peerConnection
                        .setRemoteDescription(
                            signal.description
                        );


                    console.log(
                        '✅ Remote description ingesteld'
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | CAMERA MOET AL BESTAAN
                    |--------------------------------------------------------------------------
                    */

                    if (!localStream) {

                        throw new Error(
                            'Camera stream bestaat nog niet.'
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CAMERATRACK TOEVOEGEN
                    |--------------------------------------------------------------------------
                    */

                    localStream
                        .getTracks()
                        .forEach(
                            function (track) {

                                console.log(
                                    '➕ Track toevoegen:',
                                    track.kind
                                );


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


                    console.log(
                        '✅ WebRTC answer gemaakt'
                    );


                    await peerConnection
                        .setLocalDescription(
                            answer
                        );


                    console.log(
                        '✅ Local description ingesteld'
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | ANSWER NAAR HOST
                    |--------------------------------------------------------------------------
                    */

                    await sendPlayerSignal(
                        {
                            kind:
                                'answer',

                            description: {

                                type:
                                    peerConnection
                                        .localDescription
                                        .type,

                                sdp:
                                    peerConnection
                                        .localDescription
                                        .sdp

                            }
                        }
                    );


                    console.log(
                        '📤 WebRTC answer verstuurd naar host'
                    );


                    if (webrtcStatus) {

                        webrtcStatus.textContent =
                            'Answer verstuurd. Verbinding maken...';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | EERDER ONTVANGEN ICE VERWERKEN
                    |--------------------------------------------------------------------------
                    */

                    for (
                        const candidate
                        of pendingIceCandidates
                    ) {

                        console.log(
                            '🧊 Wachtende ICE toevoegen'
                        );


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

                    console.log(
                        '🧊 ICE ontvangen van host'
                    );


                    if (
                        peerConnection
                        &&
                        peerConnection
                            .remoteDescription
                    ) {

                        console.log(
                            '🧊 ICE onmiddellijk toevoegen'
                        );


                        await peerConnection
                            .addIceCandidate(
                                signal.candidate
                            );

                    } else {

                        console.log(
                            '🧊 ICE tijdelijk bewaren'
                        );


                        pendingIceCandidates
                            .push(
                                signal.candidate
                            );

                    }

                }

            } catch (error) {

                console.error(
                    '❌ WebRTC fout op gsm:',
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

                /*
                 * Geen dubbele klik toelaten.
                 */
                confirmRoleButton.disabled =
                    true;


                confirmRoleButton.textContent =
                    'Camera starten...';


                try {

                    console.log(
                        '🔒 Secure context:',
                        window.isSecureContext
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | CAMERA STARTEN
                    |--------------------------------------------------------------------------
                    */

                    localStream =
                        await navigator
                            .mediaDevices
                            .getUserMedia(
                                {
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
                                }
                            );


                    console.log(
                        '📷 Camera gestart:',
                        localStream
                    );


                    /*
                     * Preview blijft bestaan,
                     * maar is via CSS verborgen.
                     */
                    cameraPreview.srcObject =
                        localStream;


                    /*
                     * Rol weg.
                     */
                    roleScreen.style.display =
                        'none';


                    /*
                     * Spelerkaart tonen.
                     *
                     * FLEX gebruiken zodat je
                     * .player-screen styling behouden blijft.
                     */
                    nametagScreen.style.display =
                        'flex';


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


                    if (!response.ok) {

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


                    console.log(
                        '📤 Camera-ready verstuurd naar host'
                    );


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
    | REPORT
    |--------------------------------------------------------------------------
    |
    | De knop staat nu klaar in de interface.
    |
    | In de volgende stap koppelen we hier:
    |
    | REPORT
    |   ↓
    | POST naar Laravel
    |   ↓
    | BodyReported event
    |   ↓
    | Reverb
    |   ↓
    | alle spelers + host krijgen meeting-scherm
    |
    */

</script>

@endpush