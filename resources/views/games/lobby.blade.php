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

    // Handig tijdens development via DevTools
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

    // Handig tijdens development via DevTools
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
    | Tijdelijke globale logging
    |--------------------------------------------------------------------------
    |
    | Zo zien we tijdens development elk event dat op dit game-kanaal
    | binnenkomt.
    |
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


        // Vermijd dubbele spelers in de lijst
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
    | Camera ready
    |--------------------------------------------------------------------------
    */

    channel.bind('camera.ready', function (data) {

        console.log(
            '📷 Camera ready:',
            data.player
        );

        /*
         * Hier voegen we straks WebRTC toe.
         *
         * De host weet nu:
         * - welke speler klaar is
         * - diens player ID
         * - diens naam
         *
         * Volgende stap:
         * create RTCPeerConnection voor deze speler.
         */

    });


    /*
    |--------------------------------------------------------------------------
    | QR code
    |--------------------------------------------------------------------------
    */

    const joinUrl =
        window.location.origin
        + '/join?code={{ $game->code }}';


    const qrCodeElement =
        document.getElementById('join-qrcode');


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
        document.getElementById('start-game-form');

    const startGameButton =
        document.getElementById('start-game-button');

    const gameStatus =
        document.getElementById('game-status');


    startGameForm?.addEventListener(
        'submit',
        async function (event) {

            // Heel belangrijk:
            // geen klassieke form submit / page reload
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
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },

                        // @csrf zit in het formulier
                        // en wordt via FormData meegestuurd
                        body: new FormData(startGameForm),
                    }
                );


                let data = {};

                try {
                    data = await response.json();
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


                /*
                 * Vanaf nu kan niemand meer joinen,
                 * dus de QR-code hoeft niet meer zichtbaar te zijn.
                 */
                const joinSection =
                    document.getElementById('join-section');

                if (joinSection) {
                    joinSection.style.display = 'none';
                }


                /*
                 * BELANGRIJK:
                 *
                 * We reloaden hier NIET.
                 *
                 * Daardoor blijft:
                 *
                 * host
                 *   ↓
                 * Reverb
                 *   ↓
                 * game.X
                 *
                 * continu verbonden.
                 */

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