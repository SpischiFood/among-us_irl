@extends('layouts.app')

@section('title', 'Wachten op game')

@section('content')
    <h1>Welkom {{ $player->name }}</h1>

    <p>
        Je bent toegevoegd aan:
        <strong>{{ $player->game->name }}</strong>
    </p>

    <p>
        Game code:
        <strong>{{ $player->game->code }}</strong>
    </p>

    <p>Wachten tot de spelleider de game start...</p>
@endsection