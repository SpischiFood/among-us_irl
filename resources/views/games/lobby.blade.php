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

    <h1>Lobby: {{ $game->name }}</h1>

    <h2>Game Code: {{ $game->code }}</h2>

    <h3>Scan the QR code to join the game:</h3>
    <div id="join-qrcode"></div>

    <h2>Players:</h2>

    <div id="players">
        @forelse ($game->players as $player)
            <p>{{ $player->name }}</p>
        @empty
            <p id="no-players">Nog geen spelers in de lobby.</p>
        @endforelse
    </div>

    <form action="{{ route('games.start', $game) }}" method="POST">
        @csrf

        <button type="submit">
            Start Game
        </button>
    </form>

@endsection

@push('scripts')
    <script>
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

        pusher.connection.bind('connected', function () {
            console.log('Connected to Reverb');
        });

        const channel = pusher.subscribe('game.{{ $game->id }}');

        channel.bind('pusher:subscription_succeeded', function () {
            console.log('Subscribed to game.{{ $game->id }}');
        });

        channel.bind('player.joined', function(data) {
            console.log('Player joined:', data);

            const noPlayers = document.getElementById('no-players');

            if (noPlayers) {
                noPlayers.remove();
            }

            const playerElement = document.createElement('p');
            playerElement.textContent = data.player.name;

            document
                .getElementById('players')
                .appendChild(playerElement);
        });

        const joinUrl = window.location.origin + '/join?code={{ $game->code }}';

        document.getElementById('join-link').href = joinUrl;
        document.getElementById('join-link').textContent = joinUrl;

        new QRCode(document.getElementById('join-qrcode'), {
            text: joinUrl,
            width: 200,
            height: 200,
        });
    </script>
@endpush