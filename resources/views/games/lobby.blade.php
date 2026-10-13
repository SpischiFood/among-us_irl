@extends('layouts.app')

@section('title', 'Game Lobby')

@section('content')

    {{-- =========================================================
         MELDINGEN
         ========================================================= --}}

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


    {{-- =========================================================
         GAME INFO
         ========================================================= --}}

    <h1>
        Lobby: {{ $game->name }}
    </h1>

    <h2>
        Game Code: {{ $game->code }}
    </h2>


    {{-- =========================================================
         QR CODE
         ========================================================= --}}

    @if ($game->status === 'lobby')

        <div id="join-section">

            <h3>
                Scan de QR-code om deel te nemen:
            </h3>

            <div id="join-qrcode"></div>

        </div>

    @endif


    {{-- =========================================================
         PLAYERS
         ========================================================= --}}

    <h2>Players:</h2>

    <div id="players">

        @forelse ($game->players as $player)

            <p
                data-player-id="{{ $player->id }}"
            >
                {{ $player->name }}
            </p>

        @empty

            <p id="no-players">
                Nog geen spelers in de lobby.
            </p>

        @endforelse

    </div>


    {{-- =========================================================
         GAME STATUS
         ========================================================= --}}

    <p id="game-status">

        @if ($game->status === 'running')

            Game is bezig

        @else

            Wachten om de game te starten

        @endif

    </p>


    {{-- =========================================================
         START GAME
         ========================================================= --}}

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


    {{-- =========================================================
         BODYCAMS
         ========================================================= --}}

    <section id="bodycams-section">

        <h2>Bodycams</h2>

        <div
            id="camera-grid"
            class="camera-grid"
        ></div>

    </section>

@endsection



@push('scripts')

<script>

    /*
    |--------------------------------------------------------------------------
    | SDP NORMALISATIE
    |--------------------------------------------------------------------------
    |
    | Laravel TrimStrings en verschillende browsers kunnen anders omgaan
    | met line endings.
    |
    | WebRTC SDP verwacht CRLF:
    |
    | \r\n
    |
    */

    function normalizeSdp(sdp) {

        if (typeof sdp !== 'string') {
            return sdp;
        }


        let normalized =
            sdp
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n');


        /*
         * Bestaande lege line endings
         * aan het einde verwijderen.
         */
        normalized =
            normalized.replace(
                /\n+$/,
                ''
            );


        /*
         * Correcte CRLF terugplaatsen.
         */
        normalized =
            normalized.replace(
                /\n/g,
                '\r\n'
            );


        /*
         * SDP hoort ook met CRLF
         * afgesloten te worden.
         */
        return normalized + '\r\n';
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


    /*
     * Handig tijdens development.
     *
     * In DevTools:
     *
     * window.pusher.connection.state
     */
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

    const channel =
        pusher.subscribe(
            'game.{{ $game->id }}'
        );


    window.gameChannel =
        channel;



    channel.bind(

        'pusher:subscription_succeeded',

        function () {

            console.log(
                '✅ Host subscribed to game.{{ $game->id }}'
            );

        }

    );


    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT LOGGING
    |--------------------------------------------------------------------------
    |
    | Voorlopig handig om alle Reverb events te zien.
    |
    */

    channel.bind_global(

        function (
            eventName,
            data
        ) {

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


            /*
             * Voorkomen dat dezelfde speler
             * twee keer toegevoegd wordt.
             */
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
                document.createElement(
                    'p'
                );


            playerElement.dataset.playerId =
                data.player.id;


            playerElement.textContent =
                data.player.name;


            document
                .getElementById(
                    'players'
                )
                .appendChild(
                    playerElement
                );

        }

    );


    /*
    |--------------------------------------------------------------------------
    | WEBRTC DATA
    |--------------------------------------------------------------------------
    |
    | Elke player krijgt zijn eigen RTCPeerConnection.
    |
    */

    const peerConnections =
        new Map();


    const pendingIceCandidates =
        new Map();


    /*
     * Als een gsm kort zijn verbinding verliest,
     * verwijderen we hem niet onmiddellijk.
     */
    const disconnectTimers =
        new Map();



    /*
     * Voorlopig zonder STUN/TURN.
     *
     * Je huidige RTC werkt zo,
     * dus veranderen we dit voorlopig niet.
     */
    const rtcConfiguration = {

        iceServers: []

    };


    /*
    |--------------------------------------------------------------------------
    | BODYCAM CARD MAKEN
    |--------------------------------------------------------------------------
    */

    function createBodycamCard(
        player
    ) {

        const playerId =
            Number(
                player.id
            );


        let card =
            document.getElementById(
                'camera-player-' +
                playerId
            );


        /*
         * Bestaat kaart al?
         */
        if (card) {

            return card;

        }


        /*
         * CARD
         */
        card =
            document.createElement(
                'div'
            );


        card.id =
            'camera-player-' +
            playerId;


        card.classList.add(
            'bodycam-card'
        );


        /*
         * VIDEO
         */
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


        /*
         * Geen audio.
         *
         * muted helpt ook bij autoplay.
         */
        video.muted =
            true;


        /*
         * INFO BALK
         */
        const info =
            document.createElement(
                'div'
            );


        info.classList.add(
            'bodycam-info'
        );


        /*
         * PLAYER NAME
         */
        const playerName =
            document.createElement(
                'span'
            );


        playerName.classList.add(
            'bodycam-name'
        );


        playerName.textContent =
            player.name;


        /*
         * CONNECTION STATUS
         */
        const status =
            document.createElement(
                'span'
            );


        status.id =
            'camera-status-' +
            playerId;


        status.classList.add(
            'bodycam-status'
        );


        status.textContent =
            'Verbinden...';


        /*
         * Elementen samenstellen.
         */
        info.appendChild(
            playerName
        );


        info.appendChild(
            status
        );


        card.appendChild(
            video
        );


        card.appendChild(
            info
        );


        document
            .getElementById(
                'camera-grid'
            )
            .appendChild(
                card
            );


        return card;
    }


    /*
    |--------------------------------------------------------------------------
    | BODYCAM STATUS AANPASSEN
    |--------------------------------------------------------------------------
    */

    function setBodycamStatus(
        playerId,
        status
    ) {

        playerId =
            Number(
                playerId
            );


        const element =
            document.getElementById(
                'camera-status-' +
                playerId
            );


        if (element) {

            element.textContent =
                status;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | WEBRTC CONNECTION OPRUIMEN
    |--------------------------------------------------------------------------
    */

    function cleanupPeerConnection(
        playerId,
        removeCard = false
    ) {

        playerId =
            Number(
                playerId
            );


        console.log(
            '🧹 PeerConnection cleanup:',
            playerId
        );


        /*
         * Disconnect timer verwijderen.
         */
        if (
            disconnectTimers.has(
                playerId
            )
        ) {

            clearTimeout(
                disconnectTimers.get(
                    playerId
                )
            );


            disconnectTimers.delete(
                playerId
            );

        }


        /*
         * PeerConnection ophalen.
         */
        const peerConnection =
            peerConnections.get(
                playerId
            );


        if (peerConnection) {

            /*
             * Event handlers eerst verwijderen.
             *
             * Zo veroorzaakt close() niet opnieuw
             * cleanup via connectionstatechange.
             */
            peerConnection.ontrack =
                null;


            peerConnection.onicecandidate =
                null;


            peerConnection.onconnectionstatechange =
                null;


            peerConnection.oniceconnectionstatechange =
                null;


            peerConnection.close();


            peerConnections.delete(
                playerId
            );

        }


        /*
         * ICE queue verwijderen.
         */
        pendingIceCandidates.delete(
            playerId
        );


        /*
         * Videostream loskoppelen.
         */
        const video =
            document.getElementById(
                'camera-video-' +
                playerId
            );


        if (video) {

            video.srcObject =
                null;

        }


        /*
         * Eventueel volledige card verwijderen.
         */
        if (removeCard) {

            const card =
                document.getElementById(
                    'camera-player-' +
                    playerId
                );


            if (card) {

                card.remove();

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | SIGNALING HOST → LARAVEL → REVERB → PLAYER
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

                            player_id:
                                Number(
                                    playerId
                                ),

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
    | WEBRTC CONNECTION VOOR PLAYER
    |--------------------------------------------------------------------------
    */

    async function connectToPlayer(
        player
    ) {

        const playerId =
            Number(
                player.id
            );


        console.log(
            '📹 Bodycam verbinden:',
            player.name,
            playerId
        );


        /*
         * Als dezelfde speler opnieuw camera.ready stuurt,
         * oude verbinding eerst opruimen.
         *
         * De card mag blijven bestaan.
         */
        if (
            peerConnections.has(
                playerId
            )
        ) {

            console.log(
                '♻️ Oude verbinding vervangen:',
                player.name
            );


            cleanupPeerConnection(
                playerId,
                false
            );

        }


        /*
         * Bodycam card alvast zichtbaar maken.
         */
        createBodycamCard(
            player
        );


        setBodycamStatus(
            playerId,
            'Verbinden...'
        );


        /*
         * Nieuwe PeerConnection.
         */
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
         * Host ontvangt video.
         *
         * Host stuurt zelf geen camera terug.
         */
        peerConnection.addTransceiver(

            'video',

            {
                direction:
                    'recvonly'
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


                const video =
                    document.getElementById(
                        'camera-video-' +
                        playerId
                    );


                if (
                    video
                    &&
                    event.streams
                    &&
                    event.streams[0]
                ) {

                    video.srcObject =
                        event.streams[0];

                }


                setBodycamStatus(
                    playerId,
                    'LIVE'
                );


                /*
                 * Detecteren wanneer camera-track stopt.
                 */
                event.track.onended =
                    function () {

                        console.log(
                            '⏹️ Videotrack gestopt:',
                            player.name
                        );


                        setBodycamStatus(
                            playerId,
                            'Camera gestopt'
                        );

                    };

            };


        /*
        |--------------------------------------------------------------------------
        | ICE HOST → PLAYER
        |--------------------------------------------------------------------------
        */

        peerConnection.onicecandidate =
            async function (event) {

                /*
                 * null betekent:
                 * gathering klaar.
                 */
                if (!event.candidate) {

                    console.log(
                        '🧊 Host ICE gathering klaar:',
                        player.name
                    );


                    return;

                }


                console.log(
                    '🧊 Host ICE:',
                    player.name,
                    event.candidate
                );


                try {

                    await sendHostSignal(

                        playerId,

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
                    '🔗 WebRTC',
                    player.name,
                    state
                );


                /*
                 * CONNECTED
                 */
                if (
                    state ===
                    'connected'
                ) {

                    /*
                     * Een eventuele timer van een
                     * tijdelijke disconnect annuleren.
                     */
                    if (
                        disconnectTimers.has(
                            playerId
                        )
                    ) {

                        clearTimeout(
                            disconnectTimers.get(
                                playerId
                            )
                        );


                        disconnectTimers.delete(
                            playerId
                        );

                    }


                    setBodycamStatus(
                        playerId,
                        'LIVE'
                    );

                }


                /*
                 * DISCONNECTED
                 *
                 * Niet onmiddellijk verwijderen.
                 * Mobiele wifi kan kort haperen.
                 */
                if (
                    state ===
                    'disconnected'
                ) {

                    setBodycamStatus(
                        playerId,
                        'Verbinding onderbroken...'
                    );


                    if (
                        !disconnectTimers.has(
                            playerId
                        )
                    ) {

                        const timer =
                            setTimeout(

                                function () {

                                    console.log(
                                        '🗑️ Bodycam verwijderd na disconnect:',
                                        player.name
                                    );


                                    cleanupPeerConnection(
                                        playerId,
                                        true
                                    );

                                },

                                10000

                            );


                        disconnectTimers.set(
                            playerId,
                            timer
                        );

                    }

                }


                /*
                 * FAILED
                 */
                if (
                    state ===
                    'failed'
                ) {

                    setBodycamStatus(
                        playerId,
                        'Verbinding mislukt'
                    );


                    setTimeout(

                        function () {

                            cleanupPeerConnection(
                                playerId,
                                true
                            );

                        },

                        5000

                    );

                }


                /*
                 * CLOSED
                 */
                if (
                    state ===
                    'closed'
                ) {

                    cleanupPeerConnection(
                        playerId,
                        true
                    );

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
                    '🧊 ICE',
                    player.name,
                    peerConnection
                        .iceConnectionState
                );

            };


        /*
        |--------------------------------------------------------------------------
        | WEBRTC OFFER
        |--------------------------------------------------------------------------
        */

        const offer =
            await peerConnection
                .createOffer();


        /*
         * Eerst lokaal opslaan.
         */
        await peerConnection
            .setLocalDescription(
                offer
            );


        /*
         * SDP normaliseren voordat hij via
         * Laravel/Reverb verzonden wordt.
         */
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
            '✅ Offer CRLF:',
            normalizedOffer
                .sdp
                .endsWith(
                    '\r\n'
                )
        );


        /*
         * Offer naar specifieke player.
         */
        await sendHostSignal(

            playerId,

            {
                kind:
                    'offer',

                description:
                    normalizedOffer
            }

        );


        console.log(
            '📤 WebRTC offer verstuurd naar:',
            player.name
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CAMERA READY
    |--------------------------------------------------------------------------
    |
    | Elke speler start hiermee zijn eigen PeerConnection.
    |
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
    | SIGNALING PLAYER → HOST
    |--------------------------------------------------------------------------
    */

    channel.bind(

        'webrtc.signal',

        async function (signal) {

            /*
             * Host krijgt ook zijn eigen signaling
             * op hetzelfde channel terug.
             */
            if (
                signal.sender !==
                'player'
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
                    '⚠️ Geen PeerConnection voor player:',
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
                    signal.kind ===
                    'answer'
                ) {

                    console.log(
                        '📥 Answer ontvangen van:',
                        playerId
                    );


                    /*
                     * Ook answer normaliseren.
                     */
                    const normalizedAnswer =
                        normalizeDescription(
                            signal.description
                        );


                    console.log(
                        '✅ Answer CRLF:',
                        normalizedAnswer
                            .sdp
                            .endsWith(
                                '\r\n'
                            )
                    );


                    await peerConnection
                        .setRemoteDescription(
                            normalizedAnswer
                        );


                    console.log(
                        '✅ Remote description ingesteld:',
                        playerId
                    );


                    /*
                     * Eventuele ICE die vóór het
                     * answer binnenkwam verwerken.
                     */
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
                    signal.kind ===
                    'ice'
                ) {

                    console.log(
                        '🧊 Player ICE:',
                        playerId
                    );


                    /*
                     * Remote SDP staat al ingesteld.
                     */
                    if (
                        peerConnection
                            .remoteDescription
                    ) {

                        await peerConnection
                            .addIceCandidate(
                                signal.candidate
                            );


                        console.log(
                            '✅ ICE toegevoegd:',
                            playerId
                        );

                    } else {

                        /*
                         * Answer nog niet verwerkt.
                         */
                        console.log(
                            '⏳ ICE tijdelijk bewaren:',
                            playerId
                        );


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


    if (
        qrCodeElement
    ) {

        new QRCode(

            qrCodeElement,

            {
                text:
                    joinUrl,

                width:
                    200,

                height:
                    200,
            }

        );

    }


    /*
    |--------------------------------------------------------------------------
    | GAME STARTEN ZONDER RELOAD
    |--------------------------------------------------------------------------
    |
    | Zeer belangrijk:
    |
    | hostpagina blijft bestaan zodat alle
    | Reverb / WebRTC verbindingen behouden blijven.
    |
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

                /*
                 * Geen klassieke reload.
                 */
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


                    /*
                     * QR-code verdwijnt.
                     */
                    const joinSection =
                        document.getElementById(
                            'join-section'
                        );


                    if (
                        joinSection
                    ) {

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