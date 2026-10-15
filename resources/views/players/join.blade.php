@extends('layouts.app')

@section('title', 'Join game')
@section('body-class', 'simple-page')

@section('content')
    <main class="simple-shell">
        <div class="form-card">
            <p class="simple-kicker">Player</p>
            <h1>Join game</h1>

            @include('partials.alerts')

            <form method="POST" action="{{ route('players.store') }}" class="simple-form">
                @csrf

                <label for="name">Naam</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>

                <label for="code">Game code</label>
                <input type="text" id="code" name="code" value="{{ old('code', request('code')) }}" maxlength="6" required>

                <button type="submit" class="primary-button">Join game</button>
            </form>
        </div>
    </main>
@endsection
