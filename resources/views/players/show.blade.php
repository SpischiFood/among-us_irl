@extends('layouts.app')

@section('title', 'Among us')

@section('content')

    <h1>{{ strtoupper($player->name) }}</h1>

    @if ($player->game->status === 'lobby')

        <div id="waiting-screen">
            <h2>Wachten op de spelleider...</h2>
            <p>Game: {{ $player->game->name }}</p>
        </div>

    @elseif ($player->game->status === 'running')

        <div id="role-screen">

            <h2>Game: {{ $player->game->name }}</h2>

            @if ($player->role === 'impostor')

                <h2>IMPOSTOR</h2>

                <h3>Mede impostor(s):</h3>

                @forelse ($fellowImpostors as $impostor)
                    <p>{{ $impostor->name }}</p>
                @empty
                    <p>Je bent de enige impostor.</p>
                @endforelse

            @else

                <h2>CREWMATE</h2>

            @endif

            <button type="button" id="confirm-role">
                Ik heb mijn rol gezien
            </button>

            <p id="camera-error" style="display: none;"></p>

        </div>

        <div id="nametag-screen" style="display: none;">

            <h1>{{ strtoupper($player->name) }}</h1>

            <p>● BODYCAM LIVE</p>

            <video
                id="camera-preview"
                autoplay
                muted
                playsinline
                style="max-width: 100%; transform: scaleX(-1);"
            ></video>

        </div>

    @endif

@endsection


@push('scripts')

<script>

    // ---------------------------
    // REVERB
    // ---------------------------

    const pusher = new Pusher(
        '{{ config('broadcasting.connections.reverb.key') }}',
        {
            cluster: 'mt1',
            wsHost: window.location.hostname,
            wssPort: 443,
            forceTLS: true,
            enabledTransports: ['ws'],
        }
    );

    const channel = pusher.subscribe(
        'game.{{ $player->game->id }}'
    );

    channel.bind('game.started', function(data) {

        console.log('Game started:', data);

        window.location.reload();

    });


    // ---------------------------
    // BODYCAM
    // ---------------------------

    const confirmRoleButton =
        document.getElementById('confirm-role');

    const roleScreen =
        document.getElementById('role-screen');

    const nametagScreen =
        document.getElementById('nametag-screen');

    const cameraPreview =
        document.getElementById('camera-preview');

    const cameraError =
        document.getElementById('camera-error');


    let localStream = null;


    confirmRoleButton?.addEventListener(
        'click',
        async function () {

            try {

                console.log(
                    'Secure context:',
                    window.isSecureContext
                );

                localStream =
                    await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user',

                            width: {
                                ideal: 640
                            },

                            height: {
                                ideal: 360
                            },

                            frameRate: {
                                ideal: 15,
                                max: 20
                            }
                        },

                        audio: false

                    });


                cameraPreview.srcObject = localStream;


                roleScreen.style.display = 'none';

                nametagScreen.style.display = 'block';


                console.log(
                    'Camera gestart:',
                    localStream
                );

            } catch (error) {

                console.error(
                    'Camera error:',
                    error
                );


                cameraError.style.display = 'block';

                cameraError.textContent =
                    'Camera kon niet worden gestart: '
                    + error.message;

            }

        }
    );

</script>

@endpush