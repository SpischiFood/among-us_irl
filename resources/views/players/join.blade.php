@extends('layouts.app')

@section('title', 'Join game')

@section('content')
    <h1>Join game</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('players.store') }}">
        @csrf

        <div>
            <label for="name">Naam</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                required
            >
        </div>

        <div>
            <label for="code">Game code</label>
            <input
                type="text"
                id="code"
                name="code"
                value="{{ old('code') }}"
                maxlength="6"
                required
            >
        </div>

        <button type="submit">
            Join game
        </button>
    </form>
@endsection