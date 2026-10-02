@extends('layouts.app');
@section('title', 'Game Lobby')
@section('content')
    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    <h1>lobby: {{ $game->name }}</h1>
    <h2>Game Code: {{ $game->code }}</h2>
    <h2>Players:</h2>
    <ul>
        @foreach ($game->players as $player)
            <li>{{ $player->name }}</li>
        @endforeach
    </ul>
    <form action="{{ route('games.start', $game) }}" method="POST">
        @csrf
        <button type="submit">Start Game</button>
    </form>
