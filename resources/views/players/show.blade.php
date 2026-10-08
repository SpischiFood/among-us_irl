@extends('layouts.app')

@section('title', 'Among us')

@section('content')
    <h1>Welkom {{ $player->name }}</h1>
    
    @if ($player->game->status === 'lobby')
        <div id="waiting-screen">
            <h2>Wachten op de spelleider...</h2>
            <p>Game: {{ $player->game->name }}</p>
        </div>

    @elseif ($player->game->status === 'running')
        <div id="game-screen">
            <h2>Your role</h2>
            <p>{{ $player->role }}</p>
        </div>
    @endif
@endsection

@push('scripts')
<script>
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

    const channel = pusher.subscribe(
        'game.{{ $player->game->id }}'
    );

    channel.bind('game.started', function(data) {
        console.log('Game started:', data);

        window.location.reload();
    });
</script>
@endpush