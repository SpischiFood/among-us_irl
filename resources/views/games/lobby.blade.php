@extends('layouts.app')

@section('title', 'Game Lobby')

@section('content')

    {{-- Meldingen --}}
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


    {{-- Game info --}}
    <h1>Lobby: {{ $game->name }}</h1>

    <h2>Game Code: {{ $game->code }}</h2>


    {{-- QR code --}}
    <div id="join-section">
        <h3>Scan the QR code to join the game:</h3>

        <div id="join-qrcode"></div>
    </div>


    {{-- Spelers --}}
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


    {{-- Game status --}}
    <p id="game-status">
        @if ($game->status === 'running')
            Game is bezig

            
        @else
            Wachten om de game te starten
        @endif
    </p>

    @if ($game->status === 'running')
        <h2>Bodycams</h2>
        <div id="camera-grid"></div>
    @endif


    {{-- Start knop --}}
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

@endsection


@push('scripts')
<script>

    /*
    |--------------------------------------------------------------------------
    | Reverb / Pusher
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


    pusher.connection.bind('connected', function () {
        console.log('✅ Connected to Reverb');
    });


    pusher.connection.bind('error', function (error) {
        console.error(
            '❌ Pusher error:',
            error
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Game channel
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
                '✅ Subscribed to game.{{ $game->id }}'
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Development logging
    |--------------------------------------------------------------------------
    */

    channel.bind_global(function (eventName, data) {
        console.log(
            '📡 EVENT:',
            eventName,
            data
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Player joined
    |--------------------------------------------------------------------------
    */

    channel.bind('player.joined', function (data) {

        console.log(
            '👤 Player joined:',
            data.player
        );


        const noPlayers =
            document.getElementById('no-players');


        if (noPlayers) {
            noPlayers.remove();
        }


        const existingPlayer =
            document.querySelector(
                '[data-player-id="' + data.player.id + '"]'
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
            .appendChild(playerElement);
    });


    /*
    |--------------------------------------------------------------------------
    | WebRTC configuratie
    |--------------------------------------------------------------------------
    |
    | BELANGRIJK:
    | Deze variabelen staan BUITEN camera.ready.
    | Zo blijven de verbindingen bestaan.
    |
    */

    const peerConnections = new Map();

    const pendingIceCandidates = new Map();


    const rtcConfiguration = {
        iceServers: []
    };


    /*
    |--------------------------------------------------------------------------
    | WebRTC signal van host naar Laravel sturen
    |--------------------------------------------------------------------------
    */

    async function sendHostSignal(playerId, signal) {

        const response = await fetch(
            '{{ route('games.webrtc-signal', $game) }}',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',

                    'X-CSRF-TOKEN':
                        document
                            .querySelector(
                                'meta[name="csrf-token"]'
                            )
                            .content
                },

                body: JSON.stringify({
                    player_id: playerId,
                    ...signal
                })
            }
        );


        if (!response.ok) {

            const errorText =
                await response.text();

            console.error(
                'WebRTC signaling response:',
                errorText
            );

            throw new Error(
                'WebRTC-signaal kon niet verstuurd worden.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WebRTC verbinding aanmaken voor speler
    |--------------------------------------------------------------------------
    */

    async function connectToPlayer(player) {

        console.log(
            '📹 WebRTC verbinding maken met:',
            player
        );


        /*
         * Als er al een verbinding bestaat,
         * geen tweede maken.
         */
        if (peerConnections.has(player.id)) {

            console.log(
                '⚠️ Er bestaat al een verbinding voor',
                player.name
            );

            return;
        }


        const peerConnection =
            new RTCPeerConnection(
                rtcConfiguration
            );


        peerConnections.set(
            player.id,
            peerConnection
        );


        pendingIceCandidates.set(
            player.id,
            []
        );


        /*
         * Host ontvangt video.
         * Host stuurt zelf geen camera terug.
         */
        peerConnection.addTransceiver(
            'video',
            {
                direction: 'recvonly'
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Video ontvangen
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
                        'camera-player-' + player.id
                    );


                if (!card) {

                    card =
                        document.createElement('div');


                    card.id =
                        'camera-player-' + player.id;


                    const video =
                        document.createElement('video');


                    video.id =
                        'camera-video-' + player.id;

                    video.autoplay = true;
                    video.playsInline = true;
                    video.muted = true;

                    video.style.maxWidth =
                        '400px';


                    const title =
                        document.createElement('h3');


                    title.textContent =
                        player.name;


                    card.appendChild(video);

                    card.appendChild(title);


                    document
                        .getElementById('camera-grid')
                        .appendChild(card);
                }


                const video =
                    document.getElementById(
                        'camera-video-' + player.id
                    );


                if (event.streams[0]) {
                    video.srcObject =
                        event.streams[0];
                }
            };


        /*
        |--------------------------------------------------------------------------
        | ICE van host naar speler
        |--------------------------------------------------------------------------
        */

        peerConnection.onicecandidate =
            async function (event) {

                if (!event.candidate) {
                    return;
                }


                console.log(
                    '🧊 Host ICE candidate:',
                    event.candidate
                );


                try {

                    await sendHostSignal(
                        player.id,
                        {
                            kind: 'ice',

                            candidate:
                                event.candidate.toJSON()
                        }
                    );

                } catch (error) {

                    console.error(
                        '❌ ICE versturen mislukt:',
                        error
                    );
                }
            };


        /*
        |--------------------------------------------------------------------------
        | Status van WebRTC
        |--------------------------------------------------------------------------
        */

        peerConnection.onconnectionstatechange =
            function () {

                console.log(
                    '🔗 WebRTC status',
                    player.name,
                    ':',
                    peerConnection.connectionState
                );
            };


        peerConnection.oniceconnectionstatechange =
            function () {

                console.log(
                    '🧊 ICE status',
                    player.name,
                    ':',
                    peerConnection.iceConnectionState
                );
            };


        /*
        |--------------------------------------------------------------------------
        | Offer maken
        |--------------------------------------------------------------------------
        */

        const offer =
            await peerConnection.createOffer();


        await peerConnection.setLocalDescription(
            offer
        );


        await sendHostSignal(
            player.id,
            {
                kind: 'offer',

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
            '📤 WebRTC offer verstuurd naar',
            player.name
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Camera ready
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
                    '❌ WebRTC verbinding starten mislukt:',
                    error
                );
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | WebRTC signaling ontvangen van speler
    |--------------------------------------------------------------------------
    */

    channel.bind(
        'webrtc.signal',
        async function (signal) {

            /*
             * Host hoeft zijn eigen signaling-events
             * niet opnieuw te verwerken.
             */
            if (signal.sender !== 'player') {
                return;
            }


            const playerId =
                Number(signal.player_id);


            const peerConnection =
                peerConnections.get(playerId);


            if (!peerConnection) {

                console.warn(
                    '⚠️ Geen peer connection voor speler:',
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

                if (signal.kind === 'answer') {

                    console.log(
                        '📥 WebRTC answer ontvangen van:',
                        playerId
                    );


                    await peerConnection
                        .setRemoteDescription(
                            signal.description
                        );


                    /*
                     * ICE kandidaten die eventueel
                     * te vroeg binnenkwamen nu toevoegen.
                     */
                    const queuedCandidates =
                        pendingIceCandidates.get(
                            playerId
                        ) ?? [];


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
                | ICE VAN SPELER
                |--------------------------------------------------------------------------
                */

                if (signal.kind === 'ice') {

                    console.log(
                        '🧊 ICE candidate ontvangen van:',
                        playerId
                    );


                    if (
                        peerConnection
                            .remoteDescription
                    ) {

                        await peerConnection
                            .addIceCandidate(
                                signal.candidate
                            );

                    } else {

                        /*
                         * Answer is nog niet verwerkt.
                         * Candidate tijdelijk bewaren.
                         */
                        pendingIceCandidates
                            .get(playerId)
                            ?.push(
                                signal.candidate
                            );
                    }
                }

            } catch (error) {

                console.error(
                    '❌ WebRTC signaling fout op host:',
                    error
                );
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | QR code
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
    | Game starten zonder page reload
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


    startGameForm?.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            startGameButton.disabled = true;

            startGameButton.textContent =
                'Game starten...';


            gameStatus.textContent =
                'Game wordt gestart...';


            try {

                const response = await fetch(
                    startGameForm.action,
                    {
                        method: 'POST',

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


                let data = {};


                try {

                    data =
                        await response.json();

                } catch (error) {

                    console.error(
                        'Server stuurde geen geldige JSON terug.',
                        error
                    );
                }


                if (!response.ok) {

                    throw new Error(
                        data.message
                        ?? 'Game kon niet gestart worden.'
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


                startGameButton.disabled = false;

                startGameButton.textContent =
                    'Start Game';


                gameStatus.textContent =
                    error.message;
            }
        }
    );

</script>
@endpush