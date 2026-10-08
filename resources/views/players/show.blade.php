@extends('layouts.app')

@section('title', 'Among us')

@section('content')
    <h1>{{ strtoupper($player->name) }}</h1>
    
    @if ($player->game->status === 'lobby')
        <div id="waiting-screen">
            <h2>Wachten op de spelleider...</h2>
            <p>Game: {{ $player->game->name }}</p>
        </div>

    @elseif ($player->game->status === 'running')
        <div id="game-screen">
            <h2>Game: {{ $player->game->name }}</h2>
            @if ($player->role === 'impostor')
                <h2>IMPOSTOR</h2>
                <h3>Mede impostor(s):</h3>
                @forelse ($fellowImpostors as $impostor)
                    <p>{{ $impostor->name }}</p>
                @empty
                    <p>Je bent de enige impostor.</p>
                @endforelse
            @else
                <h2>CREWMATE</h2>
            @endif
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