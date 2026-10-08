@extends('layouts.app')

@section('title', 'Among us')

@section('content')
    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert-error">
            {{ session('error') }}
        </div>
    @endif

    <h1>Game started: {{ $game->name }}</h1>
@endsection