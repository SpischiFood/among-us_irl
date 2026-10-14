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
         NORMAAL HOSTSCHERM
         ========================================================= --}}

    <div id="host-game-screen">

        <h1>Lobby: {{ $game->name }}</h1>

        <h2>Game Code: {{ $game->code }}</h2>


        {{-- =====================================================
             QR CODE
             ===================================================== --}}

        @if ($game->status === 'lobby')

            <div id="join-section">

                <h3>Scan de QR-code om deel te nemen:</h3>

                <div id="join-qrcode"></div>

            </div>

        @endif


        {{-- =====================================================
             PLAYERS
             ===================================================== --}}

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


        {{-- =====================================================
             GAME STATUS
             ===================================================== --}}

        <p id="game-status">

            @if ($game->status === 'running')
                Game is bezig
            @else
                Wachten om de game te starten
            @endif

        </p>


        {{-- =====================================================
             START GAME
             ===================================================== --}}

        @if ($game->status === 'lobby')

            <form id="start-game-form" action="{{ route('games.start', $game) }}" method="POST">

                @csrf

                <button type="submit" id="start-game-button">
                    Start Game
                </button>

            </form>

        @else

            <button type="button" id="start-game-button" disabled>
                Game gestart
            </button>

        @endif


        {{-- =====================================================
             BODYCAMS
             ===================================================== --}}

        <section id="bodycams-section">

            <h2>Bodycams</h2>

            <div id="camera-grid" class="camera-grid"></div>

        </section>

    </div>


    {{-- =========================================================
         MEETING HOSTSCHERM
         ========================================================= --}}

    <div id="host-meeting-screen" class="host-meeting-screen" style="display: none;">

        <h1>MEETING</h1>

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


        <p id="host-meeting-error" style="display: none;"></p>

    </div>

@endsection



@push('scripts')

<script>

    /*
    |--------------------------------------------------------------------------
    | HTML ELEMENTEN
    |--------------------------------------------------------------------------
    */

    const hostMeetingScreen = document.getElementById('host-meeting-screen');
    const meetingPlayerGrid = document.getElementById('meeting-player-grid');
    const hostMeetingError = document.getElementById('host-meeting-error');

    const startGameForm = document.getElementById('start-game-form');
    const startGameButton = document.getElementById('start-game-button');
    const gameStatus = document.getElementById('game-status');
    


    /*
    |--------------------------------------------------------------------------
    | SPELERS DIE BIJ HET LADEN VAN DE PAGINA AL BESTAAN
    |--------------------------------------------------------------------------
    |
    | Dit is belangrijk wanneer de host tijdens een lopende game refresht.
    |
    | De oude JavaScript RTCPeerConnections verdwijnen dan, maar Laravel
    | kent uiteraard nog steeds alle spelers in de game.
    |
    */

    const initialPlayers = @json(
        $game->players->map->only(['id', 'name'])->values()
    );


    /*
    |--------------------------------------------------------------------------
    | SDP NORMALISATIE
    |--------------------------------------------------------------------------
    */

    function normalizeSdp(sdp) {

        if (typeof sdp !== 'string') {
            return sdp;
        }

        let normalized = sdp.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

        normalized = normalized.replace(/\n+$/, '');

        return normalized.replace(/\n/g, '\r\n') + '\r\n';
    }


    function normalizeDescription(description) {

        if (!description || !description.type || typeof description.sdp !== 'string') {
            return description;
        }

        return {
            type: description.type,
            sdp: normalizeSdp(description.sdp)
        };
    }


    /*
    |--------------------------------------------------------------------------
    | REVERB / PUSHER
    |--------------------------------------------------------------------------
    */

    Pusher.logToConsole = true;


    const pusher = new Pusher('{{ config('broadcasting.connections.reverb.key') }}', {
        cluster: 'mt1',
        wsHost: window.location.hostname,
        wssPort: 443,
        forceTLS: true,
        enabledTransports: ['ws'],
    });


    window.pusher = pusher;


    /*
    |--------------------------------------------------------------------------
    | REVERB CONNECTION STATUS
    |--------------------------------------------------------------------------
    */

    pusher.connection.bind('connected', function () {

        console.log('✅ Host connected to Reverb');

    });


    pusher.connection.bind('error', function (error) {

        console.error('❌ Pusher error:', error);

    });


    /*
    |--------------------------------------------------------------------------
    | GAME CHANNEL
    |--------------------------------------------------------------------------
    */

    const channel = pusher.subscribe('game.{{ $game->id }}');

    window.gameChannel = channel;


    /*
    |--------------------------------------------------------------------------
    | CHANNEL KLAAR
    |--------------------------------------------------------------------------
    |
    | BELANGRIJK BIJ HOST REFRESH:
    |
    | Wanneer de host tijdens het spel refresht zijn alle oude
    | RTCPeerConnections verdwenen.
    |
    | Zodra Reverb opnieuw verbonden is maken we daarom opnieuw
    | een WebRTC offer voor iedere speler.
    |
    */

    channel.bind('pusher:subscription_succeeded', async function () {

        console.log('✅ Host subscribed to game.{{ $game->id }}');


        @if ($game->status === 'running')

            console.log('♻️ Hostpagina geladen tijdens een lopende game');
            console.log('📹 Bodycams opnieuw verbinden...');


            for (const player of initialPlayers) {

                try {

                    console.log('🔄 Bodycam opnieuw verbinden:', player.name);

                    await connectToPlayer(player);

                } catch (error) {

                    /*
                     * Dit kan bijvoorbeeld gebeuren wanneer een speler
                     * zijn camera nog niet heeft gestart.
                     *
                     * Wanneer die speler later camera.ready verstuurt,
                     * wordt connectToPlayer opnieuw uitgevoerd.
                     */

                    console.warn('⚠️ Bodycam kon nog niet opnieuw verbonden worden:', player.name, error);

                }

            }


            console.log('✅ Poging tot herstellen van bodycams afgerond');

        @endif

    });


    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT LOGGING
    |--------------------------------------------------------------------------
    */

    channel.bind_global(function (eventName, data) {

        console.log('📡 HOST EVENT:', eventName, data);

    });


    /*
    |--------------------------------------------------------------------------
    | PLAYER JOINED
    |--------------------------------------------------------------------------
    */

    channel.bind('player.joined', function (data) {

        console.log('👤 Player joined:', data.player);


        const noPlayers = document.getElementById('no-players');

        if (noPlayers) {
            noPlayers.remove();
        }


        /*
         * Speler toevoegen aan gewone spelerslijst.
         */

        const existingPlayer = document.querySelector('#players [data-player-id="' + data.player.id + '"]');


        if (!existingPlayer) {

            const playerElement = document.createElement('p');

            playerElement.dataset.playerId = data.player.id;
            playerElement.textContent = data.player.name;

            document.getElementById('players').appendChild(playerElement);
        }


        /*
         * Speler ook toevoegen aan het meeting-scherm.
         */

        const existingMeetingButton = document.querySelector(
            '.meeting-player-button[data-player-id="' + data.player.id + '"]'
        );


        if (!existingMeetingButton && meetingPlayerGrid) {

            const meetingButton = document.createElement('button');

            meetingButton.type = 'button';
            meetingButton.classList.add('meeting-player-button');

            meetingButton.dataset.playerId = data.player.id;
            meetingButton.dataset.playerName = data.player.name;
            meetingButton.dataset.alive = '1';

            meetingButton.textContent = data.player.name;

            meetingPlayerGrid.appendChild(meetingButton);
        }

    });


    /*
    |--------------------------------------------------------------------------
    | MEETING HULPFUNCTIES
    |--------------------------------------------------------------------------
    */

    function markMeetingPlayerDead(playerId) {

        const button = document.querySelector(
            '.meeting-player-button[data-player-id="' + playerId + '"]'
        );


        if (!button) {
            return;
        }


        button.dataset.alive = '0';
        button.disabled = true;

        const playerName = button.dataset.playerName;

        button.textContent = playerName + ' — DOOD';
    }


    function refreshMeetingButtons() {

        document.querySelectorAll('.meeting-player-button').forEach(function (button) {

            button.disabled = button.dataset.alive !== '1';

        });
    }


    function showMeetingScreen() {

        refreshMeetingButtons();


        if (hostMeetingError) {
            hostMeetingError.style.display = 'none';
            hostMeetingError.textContent = '';
        }


        if (hostMeetingScreen) {
            hostMeetingScreen.style.display = 'flex';
        }
    }


    function hideMeetingScreen() {

        if (hostMeetingScreen) {
            hostMeetingScreen.style.display = 'none';
        }


        refreshMeetingButtons();
    }


    /*
    |--------------------------------------------------------------------------
    | BODY REPORTED
    |--------------------------------------------------------------------------
    */

    channel.bind('body.reported', function (data) {

        console.log('🚨 HOST - BODY REPORTED:', data);


        /*
         * De gevonden body is al dood en kan dus
         * niet meer uitgestemd worden.
         */

        markMeetingPlayerDead(data.body.id);


        /*
         * Meeting op host tonen.
         */

        showMeetingScreen();

    });


    /*
    |--------------------------------------------------------------------------
    | MEETING ENDED
    |--------------------------------------------------------------------------
    */

    channel.bind('meeting.ended', function (data) {

        console.log('🗳️ HOST - Meeting afgelopen:', data);


        if (data.voted_out) {
            markMeetingPlayerDead(data.voted_out.id);
        }


        hideMeetingScreen();

    });


    /*
    |--------------------------------------------------------------------------
    | SPELER UITSTEMMEN
    |--------------------------------------------------------------------------
    */

    meetingPlayerGrid?.addEventListener('click', async function (event) {

        const button = event.target.closest('.meeting-player-button');


        if (!button || button.disabled) {
            return;
        }


        const playerId = Number(button.dataset.playerId);
        const playerName = button.dataset.playerName;


        const confirmed = confirm(playerName + ' uit het spel stemmen?');


        if (!confirmed) {
            return;
        }


        /*
         * Tijdens de request alle buttons blokkeren.
         */

        document.querySelectorAll('.meeting-player-button').forEach(function (item) {

            item.disabled = true;

        });


        if (hostMeetingError) {
            hostMeetingError.style.display = 'none';
            hostMeetingError.textContent = '';
        }


        try {

            const response = await fetch('{{ route('games.meeting.end', $game) }}', {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },

                body: JSON.stringify({
                    voted_out_player_id: playerId
                })
            });


            const data = await response.json();


            if (!response.ok) {
                throw new Error(data.error ?? data.message ?? 'Meeting kon niet beëindigd worden.');
            }


            console.log('✅ Uitgestemd:', data);


            markMeetingPlayerDead(playerId);

            hideMeetingScreen();

        } catch (error) {

            console.error('❌ Meeting fout:', error);


            if (hostMeetingError) {
                hostMeetingError.style.display = 'block';
                hostMeetingError.textContent = error.message;
            }


            refreshMeetingButtons();
        }

    });


    /*
    |--------------------------------------------------------------------------
    | WEBRTC DATA
    |--------------------------------------------------------------------------
    */

    const peerConnections = new Map();
    const pendingIceCandidates = new Map();
    const disconnectTimers = new Map();


    const rtcConfiguration = {
        iceServers: []
    };


    /*
    |--------------------------------------------------------------------------
    | BODYCAM CARD MAKEN
    |--------------------------------------------------------------------------
    */

    function createBodycamCard(player) {

        const playerId = Number(player.id);

        let card = document.getElementById('camera-player-' + playerId);


        if (card) {
            return card;
        }


        /*
         * CARD
         */

        card = document.createElement('div');

        card.id = 'camera-player-' + playerId;
        card.classList.add('bodycam-card');


        /*
         * VIDEO
         */

        const video = document.createElement('video');

        video.id = 'camera-video-' + playerId;
        video.autoplay = true;
        video.playsInline = true;
        video.muted = true;


        /*
         * INFO BALK
         */

        const info = document.createElement('div');

        info.classList.add('bodycam-info');


        /*
         * PLAYER NAME
         */

        const playerName = document.createElement('span');

        playerName.classList.add('bodycam-name');
        playerName.textContent = player.name;


        /*
         * CONNECTION STATUS
         */

        const status = document.createElement('span');

        status.id = 'camera-status-' + playerId;
        status.classList.add('bodycam-status');
        status.textContent = 'Verbinden...';


        info.appendChild(playerName);
        info.appendChild(status);

        card.appendChild(video);
        card.appendChild(info);

        document.getElementById('camera-grid').appendChild(card);


        return card;
    }


    /*
    |--------------------------------------------------------------------------
    | BODYCAM STATUS AANPASSEN
    |--------------------------------------------------------------------------
    */

    function setBodycamStatus(playerId, status) {

        playerId = Number(playerId);

        const element = document.getElementById('camera-status-' + playerId);


        if (element) {
            element.textContent = status;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WEBRTC CONNECTION OPRUIMEN
    |--------------------------------------------------------------------------
    */

    function cleanupPeerConnection(playerId, removeCard = false) {

        playerId = Number(playerId);

        console.log('🧹 PeerConnection cleanup:', playerId);


        /*
         * Disconnect timer verwijderen.
         */

        if (disconnectTimers.has(playerId)) {

            clearTimeout(disconnectTimers.get(playerId));

            disconnectTimers.delete(playerId);
        }


        /*
         * PeerConnection ophalen en sluiten.
         */

        const peerConnection = peerConnections.get(playerId);


        if (peerConnection) {

            peerConnection.ontrack = null;
            peerConnection.onicecandidate = null;
            peerConnection.onconnectionstatechange = null;
            peerConnection.oniceconnectionstatechange = null;

            peerConnection.close();

            peerConnections.delete(playerId);
        }


        /*
         * ICE queue verwijderen.
         */

        pendingIceCandidates.delete(playerId);


        /*
         * Video loskoppelen.
         */

        const video = document.getElementById('camera-video-' + playerId);


        if (video) {
            video.srcObject = null;
        }


        /*
         * Eventueel volledige kaart verwijderen.
         */

        if (removeCard) {

            const card = document.getElementById('camera-player-' + playerId);


            if (card) {
                card.remove();
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SIGNALING HOST → PLAYER
    |--------------------------------------------------------------------------
    */

    async function sendHostSignal(playerId, signal) {

        const response = await fetch('{{ route('games.webrtc-signal', $game) }}', {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },

            body: JSON.stringify({
                player_id: Number(playerId),
                ...signal
            })
        });


        if (!response.ok) {

            const errorText = await response.text();

            console.error('❌ Host signaling fout:', errorText);

            throw new Error('WebRTC-signaal kon niet verstuurd worden.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WEBRTC CONNECTION VOOR PLAYER
    |--------------------------------------------------------------------------
    */

    async function connectToPlayer(player) {

        const playerId = Number(player.id);


        console.log('📹 Bodycam verbinden:', player.name, playerId);


        /*
         * Bestaande verbinding vervangen.
         */

        if (peerConnections.has(playerId)) {

            console.log('♻️ Oude verbinding vervangen:', player.name);

            cleanupPeerConnection(playerId, false);
        }


        /*
         * Bodycam card maken / behouden.
         */

        createBodycamCard(player);

        setBodycamStatus(playerId, 'Verbinden...');


        /*
         * Nieuwe PeerConnection.
         */

        const peerConnection = new RTCPeerConnection(rtcConfiguration);

        peerConnections.set(playerId, peerConnection);
        pendingIceCandidates.set(playerId, []);


        /*
         * Host ontvangt video en stuurt zelf geen video terug.
         */

        peerConnection.addTransceiver('video', {
            direction: 'recvonly'
        });


        /*
        |--------------------------------------------------------------------------
        | VIDEO ONTVANGEN
        |--------------------------------------------------------------------------
        */

        peerConnection.ontrack = function (event) {

            console.log('🎥 Video ontvangen van:', player.name);


            const video = document.getElementById('camera-video-' + playerId);


            if (video && event.streams && event.streams[0]) {
                video.srcObject = event.streams[0];
            }


            setBodycamStatus(playerId, 'LIVE');


            event.track.onended = function () {

                console.log('⏹️ Videotrack gestopt:', player.name);

                setBodycamStatus(playerId, 'Camera gestopt');
            };

        };


        /*
        |--------------------------------------------------------------------------
        | ICE HOST → PLAYER
        |--------------------------------------------------------------------------
        */

        peerConnection.onicecandidate = async function (event) {

            if (!event.candidate) {

                console.log('🧊 Host ICE gathering klaar:', player.name);

                return;
            }


            console.log('🧊 Host ICE:', player.name, event.candidate);


            try {

                await sendHostSignal(playerId, {
                    kind: 'ice',
                    candidate: event.candidate.toJSON()
                });

            } catch (error) {

                console.error('❌ Host ICE versturen mislukt:', error);

            }

        };


        /*
        |--------------------------------------------------------------------------
        | CONNECTION STATUS
        |--------------------------------------------------------------------------
        */

        peerConnection.onconnectionstatechange = function () {

            const state = peerConnection.connectionState;


            console.log('🔗 WebRTC', player.name, state);


            /*
             * CONNECTED
             */

            if (state === 'connected') {

                if (disconnectTimers.has(playerId)) {

                    clearTimeout(disconnectTimers.get(playerId));

                    disconnectTimers.delete(playerId);
                }


                setBodycamStatus(playerId, 'LIVE');
            }


            /*
             * DISCONNECTED
             */

            if (state === 'disconnected') {

                setBodycamStatus(playerId, 'Verbinding onderbroken...');


                if (!disconnectTimers.has(playerId)) {

                    const timer = setTimeout(function () {

                        console.log('🗑️ Bodycam verwijderd na disconnect:', player.name);

                        cleanupPeerConnection(playerId, true);

                    }, 10000);


                    disconnectTimers.set(playerId, timer);
                }
            }


            /*
             * FAILED
             */

            if (state === 'failed') {

                setBodycamStatus(playerId, 'Verbinding mislukt');


                setTimeout(function () {

                    cleanupPeerConnection(playerId, true);

                }, 5000);
            }


            /*
             * CLOSED
             */

            if (state === 'closed') {

                cleanupPeerConnection(playerId, true);

            }

        };


        /*
        |--------------------------------------------------------------------------
        | ICE CONNECTION STATUS
        |--------------------------------------------------------------------------
        */

        peerConnection.oniceconnectionstatechange = function () {

            console.log('🧊 ICE', player.name, peerConnection.iceConnectionState);

        };


        /*
        |--------------------------------------------------------------------------
        | WEBRTC OFFER
        |--------------------------------------------------------------------------
        */

        const offer = await peerConnection.createOffer();

        await peerConnection.setLocalDescription(offer);


        /*
         * SDP normaliseren.
         */

        const normalizedOffer = normalizeDescription({
            type: peerConnection.localDescription.type,
            sdp: peerConnection.localDescription.sdp
        });


        console.log('✅ Offer CRLF:', normalizedOffer.sdp.endsWith('\r\n'));


        /*
         * Offer naar speler sturen.
         */

        await sendHostSignal(playerId, {
            kind: 'offer',
            description: normalizedOffer
        });


        console.log('📤 WebRTC offer verstuurd naar:', player.name);

    }


    /*
    |--------------------------------------------------------------------------
    | CAMERA READY
    |--------------------------------------------------------------------------
    */

    channel.bind('camera.ready', async function (data) {

        console.log('📷 Camera ready:', data.player);


        try {

            await connectToPlayer(data.player);

        } catch (error) {

            console.error('❌ WebRTC starten mislukt:', error);

        }

    });


    /*
    |--------------------------------------------------------------------------
    | SIGNALING PLAYER → HOST
    |--------------------------------------------------------------------------
    */

    channel.bind('webrtc.signal', async function (signal) {

        /*
         * Host krijgt ook zijn eigen signaling terug.
         */

        if (signal.sender !== 'player') {
            return;
        }


        const playerId = Number(signal.player_id);

        const peerConnection = peerConnections.get(playerId);


        if (!peerConnection) {

            console.warn('⚠️ Geen PeerConnection voor player:', playerId);

            return;
        }


        try {

            /*
            |--------------------------------------------------------------------------
            | ANSWER
            |--------------------------------------------------------------------------
            */

            if (signal.kind === 'answer') {

                console.log('📥 Answer ontvangen van:', playerId);


                const normalizedAnswer = normalizeDescription(signal.description);


                console.log('✅ Answer CRLF:', normalizedAnswer.sdp.endsWith('\r\n'));


                await peerConnection.setRemoteDescription(normalizedAnswer);


                console.log('✅ Remote description ingesteld:', playerId);


                /*
                 * ICE die voor het answer binnenkwam verwerken.
                 */

                const queuedCandidates = pendingIceCandidates.get(playerId) ?? [];


                for (const candidate of queuedCandidates) {

                    await peerConnection.addIceCandidate(candidate);

                }


                pendingIceCandidates.set(playerId, []);
            }


            /*
            |--------------------------------------------------------------------------
            | ICE VAN PLAYER
            |--------------------------------------------------------------------------
            */

            if (signal.kind === 'ice') {

                console.log('🧊 Player ICE:', playerId);


                if (peerConnection.remoteDescription) {

                    await peerConnection.addIceCandidate(signal.candidate);

                    console.log('✅ ICE toegevoegd:', playerId);

                } else {

                    console.log('⏳ ICE tijdelijk bewaren:', playerId);

                    pendingIceCandidates.get(playerId)?.push(signal.candidate);
                }
            }

        } catch (error) {

            console.error('❌ WebRTC fout op host:', error);

        }

    });


    /*
    |--------------------------------------------------------------------------
    | QR CODE
    |--------------------------------------------------------------------------
    */

    const joinUrl = window.location.origin + '/join?code={{ $game->code }}';

    const qrCodeElement = document.getElementById('join-qrcode');


    if (qrCodeElement) {

        new QRCode(qrCodeElement, {
            text: joinUrl,
            width: 200,
            height: 200,
        });

    }


    /*
    |--------------------------------------------------------------------------
    | GAME STARTEN ZONDER RELOAD
    |--------------------------------------------------------------------------
    */

    startGameForm?.addEventListener('submit', async function (event) {

        event.preventDefault();


        startGameButton.disabled = true;
        startGameButton.textContent = 'Game starten...';

        gameStatus.textContent = 'Game wordt gestart...';


        try {

            const response = await fetch(startGameForm.action, {
                method: 'POST',

                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },

                body: new FormData(startGameForm)
            });


            let data = {};


            try {

                data = await response.json();

            } catch (error) {

                console.error('Server stuurde geen geldige JSON terug.', error);

            }


            if (!response.ok) {

                throw new Error(data.message ?? 'Game kon niet gestart worden.');

            }


            console.log('✅ Game gestart:', data);


            startGameButton.textContent = 'Game gestart';

            gameStatus.textContent = 'Game is bezig';


            /*
             * QR-code verwijderen.
             */

            const joinSection = document.getElementById('join-section');


            if (joinSection) {
                joinSection.style.display = 'none';
            }

        } catch (error) {

            console.error('❌ Game starten mislukt:', error);


            startGameButton.disabled = false;
            startGameButton.textContent = 'Start Game';

            gameStatus.textContent = error.message;

        }

    });

</script>

@endpush