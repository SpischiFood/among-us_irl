@extends('layouts.app')

@section('title', 'Among us')

@section('content')

    <h1>{{ strtoupper($player->name) }}</h1>


    {{-- =========================================================
         WACHTSCHERM
         ========================================================= --}}

    @if ($player->game->status === 'lobby')

        <div id="waiting-screen">

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

        {{-- =============================
             ROLSCHERM
             ============================= --}}

        <div id="role-screen">

            <h2>
                Game: {{ $player->game->name }}
            </h2>


            @if ($player->role === 'impostor')

                <h2>IMPOSTOR</h2>

                <h3>Mede impostor(s):</h3>

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
                BELANGRIJK:
                knop begint disabled.

                Pas wanneer Reverb effectief subscribed is,
                mag de speler zijn camera starten.
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


        {{-- =============================
             BODYCAM SCHERM
             ============================= --}}

        <div
            id="nametag-screen"
            style="display: none;"
        >

            <p>
                ● BODYCAM LIVE
            </p>


            <video
                id="camera-preview"
                autoplay
                muted
                playsinline
                style="
                    max-width: 100%;
                    transform: scaleX(-1);
                "
            ></video>


            <p id="webrtc-status">
                Wachten op verbinding met host...
            </p>

        </div>

    @endif

@endsection



@push('scripts')

<script>

    /*
    |--------------------------------------------------------------------------
    | HTML ELEMENTEN
    |--------------------------------------------------------------------------
    |
    | Deze staan bewust helemaal bovenaan.
    | Zo kunnen we ze ook gebruiken zodra Reverb subscribed is.
    |
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

    const connectionStatus =
        document.getElementById('connection-status');


    /*
    |--------------------------------------------------------------------------
    | WEBRTC VARIABELEN
    |--------------------------------------------------------------------------
    */

    let localStream = null;

    let peerConnection = null;

    let pendingIceCandidates = [];


    /*
     * Voorlopig nog zonder STUN/TURN.
     *
     * Dat houden we bewust eenvoudig tot
     * offer/answer volledig werkt.
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

            wssPort: 443,

            forceTLS: true,

            enabledTransports: ['ws'],
        }

    );


    window.pusher = pusher;


    /*
    |--------------------------------------------------------------------------
    | PUSHER VERBINDINGSSTATUS
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

    const channel = pusher.subscribe(
        'game.{{ $player->game->id }}'
    );


    window.gameChannel = channel;


    /*
    |--------------------------------------------------------------------------
    | CHANNEL IS KLAAR
    |--------------------------------------------------------------------------
    |
    | ZEER BELANGRIJK:
    |
    | Vanaf dit moment weten we dat de gsm WebRTC-events kan ontvangen.
    |
    | Daarom wordt PAS NU de knop actief.
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
    | TIJDELIJKE DEVELOPMENT LOGGING
    |--------------------------------------------------------------------------
    |
    | Hiermee zien we alle events die de gsm ontvangt.
    |
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
    | De speler zit eerst op zijn wachtpagina.
    |
    | Host start game
    |      ↓
    | game.started
    |      ↓
    | pagina reload
    |      ↓
    | rol wordt server-side weergegeven
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
    | SIGNALING VERSTUREN VAN SPELER → HOST
    |--------------------------------------------------------------------------
    */

    async function sendPlayerSignal(
        signal
    ) {

        console.log(
            '📤 GSM WebRTC signal versturen:',
            signal
        );


        const response =
            await fetch(

                '{{ route('players.webrtc-signal', ['token' => $player->token]) }}',

                {
                    method: 'POST',

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
    | WEBRTC SIGNALING ONTVANGEN VAN HOST
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
             * Alleen berichten VAN DE HOST.
             */
            if (
                signal.sender !== 'host'
            ) {

                return;

            }


            /*
             * Alleen berichten voor DEZE speler.
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
                | OFFER
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


                    /*
                     * Oude verbinding eventueel sluiten.
                     */
                    if (peerConnection) {

                        peerConnection.close();

                    }


                    /*
                     * Nieuwe WebRTC verbinding.
                     */
                    peerConnection =
                        new RTCPeerConnection(
                            rtcConfiguration
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | ICE VAN GSM → HOST
                    |--------------------------------------------------------------------------
                    */

                    peerConnection.onicecandidate =
                        async function (event) {

                            if (
                                !event.candidate
                            ) {

                                return;

                            }


                            console.log(
                                '🧊 GSM ICE candidate:',
                                event.candidate
                            );


                            try {

                                await sendPlayerSignal(
                                    {
                                        kind: 'ice',

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
                    | VERBINDINGSSTATUS
                    |--------------------------------------------------------------------------
                    */

                    peerConnection
                        .onconnectionstatechange =
                        function () {

                            const state =
                                peerConnection
                                    .connectionState;


                            console.log(
                                '🔗 GSM WebRTC status:',
                                state
                            );


                            if (webrtcStatus) {

                                webrtcStatus.textContent =
                                    'WebRTC: ' + state;

                            }

                        };


                    peerConnection
                        .oniceconnectionstatechange =
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
                    | CONTROLEREN OF CAMERA BESTAAT
                    |--------------------------------------------------------------------------
                    */

                    if (!localStream) {

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
                            kind: 'answer',

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

                        /*
                         * Offer is nog niet verwerkt.
                         *
                         * Candidate bewaren.
                         */
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
                 * Meteen opnieuw uitschakelen.
                 *
                 * Zo kan iemand niet tweemaal
                 * camera.ready versturen.
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

                                    audio: false
                                }
                            );


                    console.log(
                        '📷 Camera gestart:',
                        localStream
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | LOKALE PREVIEW
                    |--------------------------------------------------------------------------
                    */

                    cameraPreview.srcObject =
                        localStream;


                    /*
                     * Rol verbergen.
                     */
                    roleScreen.style.display =
                        'none';


                    /*
                     * Bodycam tonen.
                     */
                    nametagScreen.style.display =
                        'block';


                    if (webrtcStatus) {

                        webrtcStatus.textContent =
                            'Camera klaar. Host verwittigen...';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CAMERA READY NAAR HOST
                    |--------------------------------------------------------------------------
                    |
                    | ZEER BELANGRIJK:
                    |
                    | We zitten hier al subscribed op Reverb.
                    |
                    | Bovendien bestaat localStream al.
                    |
                    | Daardoor kan de host nu veilig zijn
                    | WebRTC offer versturen.
                    |
                    */

                    const response =
                        await fetch(

                            '{{ route('players.cameraReady', ['token' => $player->token]) }}',

                            {
                                method: 'POST',

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

                }

            }

        );

</script>

@endpush