@extends('layouts.app')

@section('title', $task['name'])
@section('body-class', 'task-device-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/task-device.css') }}">
@endpush

@section('content')

    <main
        id="task-device"
        class="task-device-screen"
        data-game-id="{{ $game->id }}"
        data-game-status="{{ $game->status }}"
        data-task-id="{{ $task['id'] }}"
        data-pusher-key="{{ config('broadcasting.connections.reverb.key') }}"
        data-complete-url="{{ route('task-devices.complete', [
            'game' => $game,
            'taskId' => $task['id'],
        ]) }}"
    >

        <div class="task-device-card">

            {{-- =====================================================
                 HEADER
                 ===================================================== --}}

            <div class="task-device-header">

                <div class="task-device-brand">
                    AMONG US IRL
                </div>

                <div class="task-device-label">
                    TASK DEVICE
                </div>

            </div>


            {{-- =====================================================
                 TAAK
                 ===================================================== --}}

            <div class="task-device-content">

                <h1 class="task-device-name">
                    {{ $task['name'] }}
                </h1>


                <div class="task-device-location">

                    <span class="task-device-location-label">
                        Locatie
                    </span>

                    <strong>
                        {{ $task['location'] }}
                    </strong>

                </div>

            </div>


            {{-- =====================================================
                 GAME IN LOBBY
                 ===================================================== --}}

            @if ($game->status === 'lobby')

                <div
                    id="task-waiting"
                    class="task-device-state"
                >

                    <button
                        type="button"
                        id="complete-task-button"
                        class="complete-task-button"
                        disabled
                    >
                        WACHTEN
                    </button>


                    <p
                        id="task-device-status"
                        class="task-device-status"
                    >
                        Dit apparaat is gekoppeld aan deze taak.
                        Wachten tot de spelleider de game start.
                    </p>

                </div>


            {{-- =====================================================
                 GAME BEZIG
                 ===================================================== --}}

            @elseif ($game->status === 'running')

                <div
                    id="task-running"
                    class="task-device-state"
                >

                    <button
                        type="button"
                        id="complete-task-button"
                        class="complete-task-button {{ ($task['completed'] ?? false) ? 'completed' : '' }}"
                        @disabled($task['completed'] ?? false)
                    >

                        @if ($task['completed'] ?? false)
                            VOLTOOID
                        @else
                            VOLTOOI
                        @endif

                    </button>


                    <p
                        id="task-device-status"
                        class="task-device-status"
                    >

                        @if ($task['completed'] ?? false)

                            Deze taak is voltooid.

                        @else

                            Voer de taak uit en druk daarna op de knop.

                        @endif

                    </p>

                </div>


            {{-- =====================================================
                 GAME AFGELOPEN
                 ===================================================== --}}

            @elseif ($game->status === 'finished')

                <div
                    id="task-finished"
                    class="task-device-state"
                >

                    <button
                        type="button"
                        id="complete-task-button"
                        class="complete-task-button completed"
                        disabled
                    >
                        GAME OVER
                    </button>


                    <p
                        id="task-device-status"
                        class="task-device-status"
                    >
                        Deze game is afgelopen.
                    </p>

                </div>

            @endif


            {{-- =====================================================
                 DEVELOPMENT INFO
                 ===================================================== --}}

            <div class="task-device-footer">

                <span>
                    {{ $game->name }}
                </span>

                <span>
                    {{ $game->code }}
                </span>

            </div>

        </div>

    </main>

@endsection


@push('scripts')

    <script src="{{ asset('js/task-device.js') }}"></script>

@endpush