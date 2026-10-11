@extends('layouts.app')

@section('title', 'Game Lobby')

@section('content')

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert-error">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <h1>Lobby: {{ $game->name }}</h1>

    <h2>Game Code: {{ $game->code }}</h2>

    <div id="join-section">
        <h3>Scan de QR-code om deel te nemen:</h3>
        <div id="join-qrcode"></div>
    </div>

    <h2>Players:</h2>

    <div id="players">
        @forelse ($game->players as $player)
            <p data-player-id="{{ $player->id }}">
                {{ $player->name }}
            </p>
        @empty
            <p id="no-players">
                Nog geen spelers in de lobby.
            </p>
        @endforelse
    </div>

    <p id="game-status">
        @if ($game->status === 'running')
            Game is bezig
        @else
            Wachten om de game te starten
        @endif
    </p>

    @if ($game->status === 'lobby')

        <form
            id="start-game-form"
            action="{{ route('games.start', $game) }}"
            method="POST"
        >
            @csrf

            <button
                type="submit"
                id="start-game-button"
            >
                Start Game
            </button>
        </form>

    @else

        <button
            type="button"
            id="start-game-button"
            disabled
        >
            Game gestart
        </button>

    @endif

    <h2>Bodycams</h2>

    <div id="camera-grid"></div>

@endsection


@push('scripts')

<script>

    /*
    |--------------------------------------------------------------------------
    | SDP HELPERS
    |--------------------------------------------------------------------------
    */

    function normalizeSdp(sdp) {

        if (typeof sdp !== 'string') {
            return sdp;
        }

        /*
         * Eerst alle mogelijke line endings
         * naar gewone \n omzetten.
         */
        let normalized =
            sdp
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n');

        /*
         * Eventuele line breaks aan het einde verwijderen.
         */
        normalized =
            normalized.replace(/\n+$/, '');

        /*
         * Daarna alles opnieuw als correcte SDP CRLF
         * opbouwen en afsluiten met CRLF.
         */
        normalized =
            normalized.replace(/\n/g, '\r\n')
            + '\r\n';

        return normalized;
    }


    function normalizeDescription(description) {

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
            type: description.type,
            sdp: normalizeSdp(description.sdp),
        };
    }


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
            wsHost: window.location.hostname,
            wssPort: 443,
            forceTLS: true,
            enabledTransports: ['ws'],
        }
    );


    window.pusher = pusher;


    pusher.connection.bind(
        'connected',
        function () {

            console.log(
                '✅ Host connected to Reverb'
            );

        }
    );


    pusher.connection.bind(
        'error',
        function (error) {

            console.error(
                '❌ Pusher error:',
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
        'game.{{ $game->id }}'
    );


    window.gameChannel = channel;


    channel.bind(
        'pusher:subscription_succeeded',
        function () {

            console.log(
                '✅ Host subscribed to game.{{ $game->id }}'
            );

        }
    );


    channel.bind_global(
        function (eventName, data) {

            console.log(
                '📡 EVENT:',
                eventName,
                data
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PLAYER JOINED
    |--------------------------------------------------------------------------
    */

    channel.bind(
        'player.joined',
        function (data) {

            console.log(
                '👤 Player joined:',
                data.player
            );


            const noPlayers =
                document.getElementById(
                    'no-players'
                );


            if (noPlayers) {
                noPlayers.remove();
            }


            const existingPlayer =
                document.querySelector(
                    '[data-player-id="' +
                    data.player.id +
                    '"]'
                );


            if (existingPlayer) {
                return;
            }


            const playerElement =
                document.createElement('p');


            playerElement.dataset.playerId =
                data.player.id;


            playerElement.textContent =
                data.player.name;


            document
                .getElementById('players')
                .appendChild(
                    playerElement
                );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | WEBRTC
    |--------------------------------------------------------------------------
    */

    const peerConnections =
        new Map();


    const pendingIceCandidates =
        new Map();


    const rtcConfiguration = {
        iceServers: []
    };


    /*
    |--------------------------------------------------------------------------
    | SIGNAL HOST → PLAYER
    |--------------------------------------------------------------------------
    */

    async function sendHostSignal(
        playerId,
        signal
    ) {

        const response =
            await fetch(

                '{{ route('games.webrtc-signal', $game) }}',

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
                        JSON.stringify({
                            player_id: playerId,
                            ...signal
                        })
                }
            );


        if (!response.ok) {

            const errorText =
                await response.text();


            console.error(
                '❌ Host signaling fout:',
                errorText
            );


            throw new Error(
                'WebRTC-signaal kon niet verstuurd worden.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | VERBINDING MET PLAYER
    |--------------------------------------------------------------------------
    */

    async function connectToPlayer(
        player
    ) {

        const playerId =
            Number(player.id);


        console.log(
            '📹 WebRTC verbinding maken met:',
            player
        );


        if (
            peerConnections.has(
                playerId
            )
        ) {

            console.log(
                '⚠️ PeerConnection bestaat al voor:',
                player.name
            );

            return;

        }


        const peerConnection =
            new RTCPeerConnection(
                rtcConfiguration
            );


        peerConnections.set(
            playerId,
            peerConnection
        );


        pendingIceCandidates.set(
            playerId,
            []
        );


        /*
         * Host wil video ontvangen.
         */
        peerConnection.addTransceiver(
            'video',
            {
                direction: 'recvonly'
            }
        );


        /*
        |--------------------------------------------------------------------------
        | VIDEO ONTVANGEN
        |--------------------------------------------------------------------------
        */

        peerConnection.ontrack =
            function (event) {

                console.log(
                    '🎥 Video ontvangen van:',
                    player.name
                );


                let card =
                    document.getElementById(
                        'camera-player-' +
                        playerId
                    );


                if (!card) {

                    card =
                        document.createElement(
                            'div'
                        );


                    card.id =
                        'camera-player-' +
                        playerId;


                    const video =
                        document.createElement(
                            'video'
                        );


                    video.id =
                        'camera-video-' +
                        playerId;


                    video.autoplay =
                        true;


                    video.playsInline =
                        true;


                    video.muted =
                        true;


                    video.style.width =
                        '100%';


                    video.style.maxWidth =
                        '400px';


                    const title =
                        document.createElement(
                            'h3'
                        );


                    title.textContent =
                        player.name;


                    const status =
                        document.createElement(
                            'p'
                        );


                    status.id =
                        'camera-status-' +
                        playerId;


                    status.textContent =
                        'Verbinden...';


                    card.appendChild(
                        video
                    );


                    card.appendChild(
                        title
                    );


                    card.appendChild(
                        status
                    );


                    document
                        .getElementById(
                            'camera-grid'
                        )
                        .appendChild(
                            card
                        );

                }


                const video =
                    document.getElementById(
                        'camera-video-' +
                        playerId
                    );


                if (
                    event.streams
                    &&
                    event.streams[0]
                ) {

                    video.srcObject =
                        event.streams[0];

                }

            };


        /*
        |--------------------------------------------------------------------------
        | ICE HOST → PLAYER
        |--------------------------------------------------------------------------
        */

        peerConnection.onicecandidate =
            async function (event) {

                if (!event.candidate) {

                    console.log(
                        '🧊 Host ICE gathering klaar'
                    );

                    return;

                }


                try {

                    await sendHostSignal(
                        playerId,
                        {
                            kind: 'ice',

                            candidate:
                                event.candidate
                                    .toJSON()
                        }
                    );

                } catch (error) {

                    console.error(
                        '❌ Host ICE versturen mislukt:',
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
                    '🔗 WebRTC status',
                    player.name,
                    ':',
                    state
                );


                const status =
                    document.getElementById(
                        'camera-status-' +
                        playerId
                    );


                if (status) {

                    status.textContent =
                        'WebRTC: ' +
                        state;

                }

            };


        peerConnection.oniceconnectionstatechange =
            function () {

                console.log(
                    '🧊 ICE status',
                    player.name,
                    ':',
                    peerConnection
                        .iceConnectionState
                );

            };


        /*
        |--------------------------------------------------------------------------
        | OFFER MAKEN
        |--------------------------------------------------------------------------
        */

        const offer =
            await peerConnection
                .createOffer();


        await peerConnection
            .setLocalDescription(
                offer
            );


        const normalizedOffer =
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


        console.log(
            '✅ Offer eindigt op CRLF:',
            normalizedOffer.sdp.endsWith(
                '\r\n'
            )
        );


        await sendHostSignal(
            playerId,
            {
                kind: 'offer',

                description:
                    normalizedOffer
            }
        );


        console.log(
            '📤 WebRTC offer verstuurd naar',
            player.name
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CAMERA READY
    |--------------------------------------------------------------------------
    */

    channel.bind(
        'camera.ready',
        async function (data) {

            console.log(
                '📷 Camera ready:',
                data.player
            );


            try {

                await connectToPlayer(
                    data.player
                );

            } catch (error) {

                console.error(
                    '❌ WebRTC starten mislukt:',
                    error
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PLAYER SIGNAL ONTVANGEN
    |--------------------------------------------------------------------------
    */

    channel.bind(
        'webrtc.signal',
        async function (signal) {

            if (
                signal.sender !== 'player'
            ) {

                return;

            }


            const playerId =
                Number(
                    signal.player_id
                );


            const peerConnection =
                peerConnections.get(
                    playerId
                );


            if (!peerConnection) {

                console.warn(
                    '⚠️ Geen PeerConnection voor:',
                    playerId
                );

                return;

            }


            try {

                /*
                |--------------------------------------------------------------------------
                | ANSWER
                |--------------------------------------------------------------------------
                */

                if (
                    signal.kind === 'answer'
                ) {

                    console.log(
                        '📥 Answer ontvangen van:',
                        playerId
                    );


                    const normalizedAnswer =
                        normalizeDescription(
                            signal.description
                        );


                    console.log(
                        '✅ Answer eindigt op CRLF:',
                        normalizedAnswer.sdp.endsWith(
                            '\r\n'
                        )
                    );


                    await peerConnection
                        .setRemoteDescription(
                            normalizedAnswer
                        );


                    console.log(
                        '✅ Host remote description ingesteld'
                    );


                    const queuedCandidates =
                        pendingIceCandidates
                            .get(
                                playerId
                            )
                        ?? [];


                    for (
                        const candidate
                        of queuedCandidates
                    ) {

                        await peerConnection
                            .addIceCandidate(
                                candidate
                            );

                    }


                    pendingIceCandidates.set(
                        playerId,
                        []
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | ICE VAN PLAYER
                |--------------------------------------------------------------------------
                */

                if (
                    signal.kind === 'ice'
                ) {

                    if (
                        peerConnection
                            .remoteDescription
                    ) {

                        await peerConnection
                            .addIceCandidate(
                                signal.candidate
                            );

                    } else {

                        pendingIceCandidates
                            .get(
                                playerId
                            )
                            ?.push(
                                signal.candidate
                            );

                    }

                }

            } catch (error) {

                console.error(
                    '❌ WebRTC fout op host:',
                    error
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | QR CODE
    |--------------------------------------------------------------------------
    */

    const joinUrl =
        window.location.origin
        + '/join?code={{ $game->code }}';


    const qrCodeElement =
        document.getElementById(
            'join-qrcode'
        );


    if (qrCodeElement) {

        new QRCode(
            qrCodeElement,
            {
                text: joinUrl,
                width: 200,
                height: 200,
            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | GAME STARTEN ZONDER RELOAD
    |--------------------------------------------------------------------------
    */

    const startGameForm =
        document.getElementById(
            'start-game-form'
        );


    const startGameButton =
        document.getElementById(
            'start-game-button'
        );


    const gameStatus =
        document.getElementById(
            'game-status'
        );


    startGameForm
        ?.addEventListener(
            'submit',

            async function (event) {

                event.preventDefault();


                startGameButton.disabled =
                    true;


                startGameButton.textContent =
                    'Game starten...';


                gameStatus.textContent =
                    'Game wordt gestart...';


                try {

                    const response =
                        await fetch(

                            startGameForm.action,

                            {
                                method:
                                    'POST',

                                headers: {

                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                },

                                body:
                                    new FormData(
                                        startGameForm
                                    ),
                            }

                        );


                    const data =
                        await response.json();


                    if (!response.ok) {

                        throw new Error(
                            data.message
                            ??
                            'Game kon niet gestart worden.'
                        );

                    }


                    console.log(
                        '✅ Game gestart:',
                        data
                    );


                    startGameButton.textContent =
                        'Game gestart';


                    gameStatus.textContent =
                        'Game is bezig';


                    const joinSection =
                        document.getElementById(
                            'join-section'
                        );


                    if (joinSection) {

                        joinSection.style.display =
                            'none';

                    }

                } catch (error) {

                    console.error(
                        '❌ Game starten mislukt:',
                        error
                    );


                    startGameButton.disabled =
                        false;


                    startGameButton.textContent =
                        'Start Game';


                    gameStatus.textContent =
                        error.message;

                }

            }
        );

</script>

@endpush