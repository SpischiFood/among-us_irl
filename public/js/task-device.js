(function () {
    /*
    |--------------------------------------------------------------------------
    | ROOT + CONFIG
    |--------------------------------------------------------------------------
    */

    const root = document.getElementById('task-device');

    if (!root) {
        return;
    }

    const gameId = Number(root.dataset.gameId);
    const taskId = root.dataset.taskId;
    const completeUrl = root.dataset.completeUrl;
    const pusherKey = root.dataset.pusherKey;

    let currentGameStatus = root.dataset.gameStatus;


    /*
    |--------------------------------------------------------------------------
    | HTML ELEMENTEN
    |--------------------------------------------------------------------------
    */

    const completeButton = document.getElementById('complete-task-button');
    const taskStatus = document.getElementById('task-device-status');


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }


    /*
    |--------------------------------------------------------------------------
    | UI STATES
    |--------------------------------------------------------------------------
    */

    function showWaitingState() {
        currentGameStatus = 'lobby';

        if (completeButton) {
            completeButton.disabled = true;
            completeButton.textContent = 'WACHTEN';
            completeButton.classList.remove('completed');
        }

        if (taskStatus) {
            taskStatus.textContent =
                'Dit apparaat is gekoppeld aan deze taak. Wachten tot de spelleider de game start.';
        }
    }


    function showRunningState() {
        currentGameStatus = 'running';

        if (completeButton) {
            completeButton.disabled = false;
            completeButton.textContent = 'VOLTOOI';
            completeButton.classList.remove('completed');
        }

        if (taskStatus) {
            taskStatus.textContent =
                'Voer de taak uit en druk daarna op de knop.';
        }
    }


    function showCompletedState() {
        if (completeButton) {
            completeButton.disabled = true;
            completeButton.textContent = 'VOLTOOID';
            completeButton.classList.add('completed');
        }

        if (taskStatus) {
            taskStatus.textContent = 'Deze taak is voltooid.';
        }
    }


    function showFinishedState() {
        currentGameStatus = 'finished';

        if (completeButton) {
            completeButton.disabled = true;
            completeButton.textContent = 'GAME OVER';
            completeButton.classList.add('completed');
        }

        if (taskStatus) {
            taskStatus.textContent = 'Deze game is afgelopen.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TAAK VOLTOOIEN
    |--------------------------------------------------------------------------
    */

    completeButton?.addEventListener('click', async function () {
        if (currentGameStatus !== 'running') {
            return;
        }

        completeButton.disabled = true;
        completeButton.textContent = 'BEZIG...';

        if (taskStatus) {
            taskStatus.textContent = 'Taak wordt voltooid...';
        }


        try {
            const response = await fetch(completeUrl, {
                method: 'POST',

                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });


            let data = {};

            try {
                data = await response.json();
            } catch (error) {
                console.error(
                    'Server stuurde geen geldige JSON terug.',
                    error
                );
            }


            if (!response.ok) {
                throw new Error(
                    data.error ??
                    data.message ??
                    'Taak kon niet voltooid worden.'
                );
            }


            showCompletedState();

            console.log('Taak voltooid:', taskId);

        } catch (error) {
            console.error('Taak voltooien mislukt:', error);

            completeButton.disabled = false;
            completeButton.textContent = 'VOLTOOI';

            if (taskStatus) {
                taskStatus.textContent = error.message;
            }
        }
    });


    /*
    |--------------------------------------------------------------------------
    | REVERB / PUSHER
    |--------------------------------------------------------------------------
    */

    if (!window.Pusher) {
        console.error('Pusher is niet geladen.');
        return;
    }


    if (!pusherKey) {
        console.error('Geen Reverb/Pusher key gevonden.');
        return;
    }


    const pusher = new window.Pusher(pusherKey, {
        cluster: 'mt1',
        wsHost: window.location.hostname,
        wssPort: 443,
        forceTLS: true,
        enabledTransports: ['ws'],
    });


    window.taskDevicePusher = pusher;


    /*
    |--------------------------------------------------------------------------
    | CONNECTION LOGGING
    |--------------------------------------------------------------------------
    */

    pusher.connection.bind('connected', function () {
        console.log('Task device verbonden met Reverb');
    });


    pusher.connection.bind('error', function (error) {
        console.error('Reverb fout op task device:', error);
    });


    /*
    |--------------------------------------------------------------------------
    | GAME CHANNEL
    |--------------------------------------------------------------------------
    */

    const channel = pusher.subscribe('game.' + gameId);

    window.taskDeviceChannel = channel;


    channel.bind('pusher:subscription_succeeded', function () {
        console.log('Task device subscribed op game.' + gameId);
    });


    /*
    |--------------------------------------------------------------------------
    | GAME GESTART
    |--------------------------------------------------------------------------
    */

    channel.bind('game.started', function () {
        console.log('Game gestart - task device wordt actief');

        showRunningState();
    });


    /*
    |--------------------------------------------------------------------------
    | GAME GESTOPT
    |--------------------------------------------------------------------------
    */

    channel.bind('game.ended', function () {
        console.log('Game afgelopen');

        showFinishedState();
    });
})();