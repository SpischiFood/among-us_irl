@extends('layouts.app')

@section('title', 'Game Lobby')
@section('body-class', 'host-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/host.css') }}">
@endpush

@section('content')

    @include('partials.alerts')

    <main
        id="host-game"
        data-game-id="{{ $game->id }}"
        data-game-status="{{ $game->status }}"
    >
        @include('games.partials.header')

        <div id="host-game-content" @if ($game->status === 'finished') hidden @endif>
            @if ($game->status === 'lobby')
                <div class="host-lobby-grid">
                    @include('games.partials.lobby-info')
                    @include('games.partials.player-list')
                </div>
            @else
                @include('games.partials.player-list')
            @endif

            @include('games.partials.bodycams')
        </div>

        @include('games.partials.meeting')
        @include('games.partials.game-ended')
    </main>

    <script type="application/json" id="host-game-config">@json($hostConfig)</script>

@endsection

@push('scripts')
    @if ($game->status !== 'finished')
        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script type="module" src="{{ asset('js/host-game.js') }}"></script>
    @endif
@endpush
