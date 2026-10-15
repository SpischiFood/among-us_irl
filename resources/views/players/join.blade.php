@extends('layouts.app')

@section('title', 'Join Game')
@section('body-class', 'join-page')

@section('content')

    <main class="join-screen">

        <div class="join-card">

            {{-- =====================================================
                 HEADER
                 ===================================================== --}}

            <div class="join-header">

                <div class="join-brand">
                    AMONG US IRL
                </div>

                <h1>
                    Join Game
                </h1>


                @if (request('code'))

                    <p class="join-game-code">

                        Game code

                        <strong>
                            {{ strtoupper(request('code')) }}
                        </strong>

                    </p>

                @endif

            </div>


            {{-- =====================================================
                 FOUTMELDINGEN
                 ===================================================== --}}

            @if ($errors->any())

                <div class="join-errors">

                    @foreach ($errors->all() as $error)

                        <p>
                            {{ $error }}
                        </p>

                    @endforeach

                </div>

            @endif


            {{-- =====================================================
                 KEUZE: SPELER OF TASK DEVICE
                 ===================================================== --}}

            @if (request('mode') !== 'player')

                <section class="join-choice">

                    <div class="join-choice-header">

                        <h2>
                            Hoe wil je deelnemen?
                        </h2>

                        <p>
                            Kies wat dit toestel tijdens het spel zal zijn.
                        </p>

                    </div>


                    <div class="join-choice-grid">

                        {{-- =================================================
                             SPELER
                             ================================================= --}}

                        <a
                            href="{{ route('players.create', [
                                'code' => request('code'),
                                'mode' => 'player',
                            ]) }}"
                            class="join-choice-button join-choice-player"
                        >

                            <div class="join-choice-icon">
                                👤
                            </div>


                            <div class="join-choice-content">

                                <h3>
                                    Speler
                                </h3>

                                <p>
                                    Speel mee als crewmate of impostor.
                                </p>

                            </div>

                        </a>


                        {{-- =================================================
                             TASK DEVICE
                             ================================================= --}}

                        <a
                            href="{{ route('task-devices.create', [
                                'code' => request('code'),
                            ]) }}"
                            class="join-choice-button join-choice-task"
                        >

                            <div class="join-choice-icon">
                                ✓
                            </div>


                            <div class="join-choice-content">

                                <h3>
                                    Taak device
                                </h3>

                                <p>
                                    Koppel dit apparaat aan één van de taken.
                                </p>

                            </div>

                        </a>

                    </div>

                </section>


            {{-- =====================================================
                 SPELER AANMELDEN
                 ===================================================== --}}

            @else

                <section class="player-join">

                    <div class="player-join-header">

                        <h2>
                            Speler aanmelden
                        </h2>

                        <p>
                            Geef je naam in om deel te nemen aan het spel.
                        </p>

                    </div>


                    <form
                        method="POST"
                        action="{{ route('players.store') }}"
                        class="player-join-form"
                    >

                        @csrf


                        {{-- =================================================
                             NAAM
                             ================================================= --}}

                        <div class="join-form-field">

                            <label for="name">
                                Naam
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="Jouw naam"
                                maxlength="100"
                                autocomplete="off"
                                required
                                autofocus
                            >

                        </div>


                        {{-- =================================================
                             GAME CODE
                             ================================================= --}}

                        <div class="join-form-field">

                            <label for="code">
                                Game code
                            </label>

                            <input
                                type="text"
                                id="code"
                                name="code"
                                value="{{ old('code', request('code')) }}"
                                placeholder="ABC123"
                                maxlength="6"
                                autocomplete="off"
                                required
                            >

                        </div>


                        {{-- =================================================
                             JOIN
                             ================================================= --}}

                        <button
                            type="submit"
                            class="join-player-button"
                        >
                            Join Game
                        </button>

                    </form>


                    {{-- =====================================================
                         TERUG
                         ===================================================== --}}

                    <a
                        href="{{ route('players.create', [
                            'code' => request('code'),
                        ]) }}"
                        class="join-back-button"
                    >
                        ← Terug naar keuze
                    </a>

                </section>

            @endif

        </div>

    </main>

@endsection