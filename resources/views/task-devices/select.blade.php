@extends('layouts.app')

@section('title', 'Kies taak')
@section('body-class', 'task-device-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/task-device.css') }}">
@endpush

@section('content')

    <main class="task-select-screen">

        <div class="task-select-card">

            {{-- =====================================================
                 HEADER
                 ===================================================== --}}

            <div class="task-select-header">

                <div class="task-device-brand">
                    AMONG US IRL
                </div>

                <h1>
                    Kies een taak
                </h1>

                <p>
                    Selecteer welke taak dit apparaat voorstelt.
                </p>

                <div class="task-select-game">
                    Game:
                    <strong>{{ $game->name }}</strong>
                </div>

            </div>


            {{-- =====================================================
                 TAKEN
                 ===================================================== --}}

            <div class="task-device-list">

                @forelse ($tasks as $task)

                    <a
                        href="{{ route('task-devices.show', [
                            'game' => $game,
                            'taskId' => $task['id'],
                        ]) }}"
                        class="task-device-choice"
                    >

                        <div class="task-device-choice-content">

                            <div class="task-device-choice-name">
                                {{ $task['name'] }}
                            </div>

                            <div class="task-device-choice-location">
                                {{ $task['location'] }}
                            </div>

                        </div>


                        <div class="task-device-choice-arrow">
                            →
                        </div>

                    </a>

                @empty

                    <div class="task-device-empty">

                        <h2>
                            Geen taken beschikbaar
                        </h2>

                        <p>
                            Voor deze game zijn nog geen taken ingesteld.
                        </p>

                    </div>

                @endforelse

            </div>


            {{-- =====================================================
                 TERUG
                 ===================================================== --}}

            <a
                href="{{ route('players.create', [
                    'code' => $game->code,
                ]) }}"
                class="task-device-back"
            >
                ← Terug
            </a>

        </div>

    </main>

@endsection