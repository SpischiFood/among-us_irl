@extends('layouts.app')

@section('title', 'Game Lobby')

@section('content')

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <h1>Lobby: {{ $game->name }}</h1>

    <h2>Game Code: {{ $game->code }}</h2>

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

                wsHost: '127.0.0.1',
                wsPort: 8080,
                wssPort: 8080,

                forceTLS: false,
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
    </script>
@endpush