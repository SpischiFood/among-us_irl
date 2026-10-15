@extends('layouts.app')

@section('title', 'Create Game')
@section('body-class', 'simple-page')

@section('content')
    <main class="simple-shell">
        <div class="form-card">
            <p class="simple-kicker">Host</p>
            <h1>Create Game</h1>

            @include('partials.alerts')

            <form action="{{ route('games.store') }}" method="POST" class="simple-form">
                @csrf

                <label for="name">Game Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>

                <button type="submit" class="primary-button">Create Game</button>
            </form>
        </div>
    </main>
@endsection
