@extends('layouts.app')

@section('title', 'Among Us IRL')
@section('body-class', 'player-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/player.css') }}">
@endpush

@section('content')

    <main id="player-game" data-game-status="{{ $player->game->status }}">
        @if ($player->game->status === 'lobby')
            @include('players.partials.waiting')

        @elseif ($player->game->status === 'running')
            @include('players.partials.role')
            @include('players.partials.player-card')
            @include('players.partials.reporter')
            @include('players.partials.meeting')
            @include('players.partials.game-ended', ['visible' => false])

        @elseif ($player->game->status === 'finished')
            @include('players.partials.game-ended', ['visible' => true])
        @endif
    </main>

    <script type="application/json" id="player-game-config">@json($playerConfig)</script>

@endsection

@push('scripts')
    @if ($player->game->status !== 'finished')
        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        <script type="module" src="{{ asset('js/player-game.js') }}"></script>
    @endif
@endpush
