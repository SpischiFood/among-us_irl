import { normalizeDescription } from './webrtc-utils.js';

(function () {
    const root = document.getElementById('host-game');
    const configElement = document.getElementById('host-game-config');

    if (!root || !configElement) {
        return;
    }

    const config = JSON.parse(configElement.textContent);
    let currentGameStatus = config.gameStatus;

    const hostGameContent = document.getElementById('host-game-content');
    const hostMeetingScreen = document.getElementById('host-meeting-screen');
    const hostMeetingError = document.getElementById('host-meeting-error');
    const hostGameEndedScreen = document.getElementById('host-game-ended-screen');
    const meetingPlayerGrid = document.getElementById('meeting-player-grid');
    const bodycamsSection = document.getElementById('bodycams-section');
    const cameraGrid = document.getElementById('camera-grid');
    const joinSection = document.getElementById('join-section');
    const playersElement = document.getElementById('players');
    const gameStatus = document.getElementById('game-status');
    const startGameForm = document.getElementById('start-game-form');
    const startGameButton = document.getElementById('start-game-button');
    const stopGameButton = document.getElementById('stop-game-button');

    const gameSettings = document.getElementById('game-settings');
    const impostorCountInput = document.getElementById('impostor-count');
    const tasksFileInput = document.getElementById('tasks-file');
    const saveSettingsButton = document.getElementById('save-settings-button');
    const settingsStatus = document.getElementById('settings-status');

    const taskPreview = document.getElementById('task-preview');
    const taskPreviewList = document.getElementById('task-preview-list');
    const taskCount = document.getElementById('task-count');

    const peerConnections = new Map();
    const pendingIceCandidates = new Map();
    const disconnectTimers = new Map();
    const rtcConfiguration = { iceServers: [] };


    tasksFileInput?.addEventListener('change', async function () {
        const file = tasksFileInput.files[0];

        if (!file) {
            return;
        }

        if (!file.name.toLowerCase().endsWith('.json')) {
            settingsStatus.textContent = 'Kies een .json-bestand.';
            settingsStatus.className = 'settings-status settings-status-error';
            tasksFileInput.value = '';
            return;
        }

        try {
            const content = await file.text();
            const tasks = JSON.parse(content);

            if (!Array.isArray(tasks)) {
                throw new Error('De JSON moet een lijst van taken bevatten.');
            }

            for (const task of tasks) {
                if (
                    typeof task !== 'object' ||
                    typeof task.name !== 'string' ||
                    typeof task.location !== 'string'
                ) {
                    throw new Error(
                        'Elke taak moet een "name" en "location" bevatten.'
                    );
                }
            }

            settingsStatus.textContent =
                tasks.length +
                (tasks.length === 1
                    ? ' geldige taak gevonden. Klik op opslaan.'
                    : ' geldige taken gevonden. Klik op opslaan.');

            settingsStatus.className =
                'settings-status settings-status-success';

        } catch (error) {
            settingsStatus.textContent = error.message;
            settingsStatus.className =
                'settings-status settings-status-error';

            tasksFileInput.value = '';
        }
    });


    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    function setGameStatus(text, status = currentGameStatus) {
        if (!gameStatus) {
            return;
        }

        gameStatus.textContent = text;
        gameStatus.className = 'game-status game-status-' + status;
    }


    function renderTaskPreview(tasks) {
        if (!taskPreview || !taskPreviewList || !taskCount) {
            return;
        }

        taskPreviewList.innerHTML = '';

        if (!Array.isArray(tasks) || tasks.length === 0) {
            taskPreview.hidden = true;
            taskCount.textContent = '0 taken';
            return;
        }

        for (const task of tasks) {
            const item = document.createElement('div');
            item.className = 'task-preview-item';

            const name = document.createElement('div');
            name.className = 'task-preview-name';
            name.textContent = task.name;

            const location = document.createElement('div');
            location.className = 'task-preview-location';
            location.textContent = task.location;

            item.append(name, location);
            taskPreviewList.appendChild(item);
        }

        taskCount.textContent = tasks.length + (tasks.length === 1 ? ' taak' : ' taken');
        taskPreview.hidden = false;
    }

    function addPlayerToLobby(player) {
        if (!playersElement) {
            return;
        }

        document.getElementById('no-players')?.remove();

        const existingPlayer = playersElement.querySelector('[data-player-id="' + player.id + '"]');

        if (!existingPlayer) {
            const element = document.createElement('p');
            element.dataset.playerId = player.id;
            element.textContent = player.name;
            playersElement.appendChild(element);
        }
    }

    function addPlayerToMeeting(player) {
        if (!meetingPlayerGrid) {
            return;
        }

        const existingButton = meetingPlayerGrid.querySelector(
            '.meeting-player-button[data-player-id="' + player.id + '"]'
        );

        if (existingButton) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'meeting-player-button';
        button.dataset.playerId = player.id;
        button.dataset.playerName = player.name;
        button.dataset.alive = '1';
        button.textContent = player.name;

        meetingPlayerGrid.appendChild(button);
    }

    function markMeetingPlayerDead(playerId) {
        const button = document.querySelector(
            '.meeting-player-button[data-player-id="' + playerId + '"]'
        );

        if (!button) {
            return;
        }

        button.dataset.alive = '0';
        button.disabled = true;
        button.textContent = button.dataset.playerName + ' — DOOD';
    }

    function refreshMeetingButtons() {
        document.querySelectorAll('.meeting-player-button').forEach(function (button) {
            button.disabled = button.dataset.alive !== '1';
        });
    }

    function showMeetingScreen() {
        refreshMeetingButtons();

        if (hostMeetingError) {
            hostMeetingError.hidden = true;
            hostMeetingError.textContent = '';
        }

        if (hostMeetingScreen) {
            hostMeetingScreen.hidden = false;
        }
    }

    function hideMeetingScreen() {
        if (hostMeetingScreen) {
            hostMeetingScreen.hidden = true;
        }

        refreshMeetingButtons();
    }

    function createBodycamCard(player) {
        const playerId = Number(player.id);
        let card = document.getElementById('camera-player-' + playerId);

        if (card) {
            return card;
        }

        card = document.createElement('div');
        card.id = 'camera-player-' + playerId;
        card.className = 'bodycam-card';

        const video = document.createElement('video');
        video.id = 'camera-video-' + playerId;
        video.autoplay = true;
        video.playsInline = true;
        video.muted = true;

        const info = document.createElement('div');
        info.className = 'bodycam-info';

        const playerName = document.createElement('span');
        playerName.className = 'bodycam-name';
        playerName.textContent = player.name;

        const status = document.createElement('span');
        status.id = 'camera-status-' + playerId;
        status.className = 'bodycam-status';
        status.textContent = 'Verbinden...';

        info.append(playerName, status);
        card.append(video, info);
        cameraGrid?.appendChild(card);

        return card;
    }

    function setBodycamStatus(playerId, status) {
        const element = document.getElementById('camera-status-' + Number(playerId));

        if (element) {
            element.textContent = status;
        }
    }

    function cleanupPeerConnection(playerId, removeCard = false) {
        playerId = Number(playerId);

        if (disconnectTimers.has(playerId)) {
            clearTimeout(disconnectTimers.get(playerId));
            disconnectTimers.delete(playerId);
        }

        const peerConnection = peerConnections.get(playerId);

        if (peerConnection) {
            peerConnection.ontrack = null;
            peerConnection.onicecandidate = null;
            peerConnection.onconnectionstatechange = null;
            peerConnection.oniceconnectionstatechange = null;
            peerConnection.close();
            peerConnections.delete(playerId);
        }

        pendingIceCandidates.delete(playerId);

        const video = document.getElementById('camera-video-' + playerId);

        if (video) {
            video.srcObject = null;
        }

        if (removeCard) {
            document.getElementById('camera-player-' + playerId)?.remove();
        }
    }

    async function sendHostSignal(playerId, signal) {
        const response = await fetch(config.webrtcSignalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                player_id: Number(playerId),
                ...signal,
            }),
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Host signaling fout:', errorText);
            throw new Error('WebRTC-signaal kon niet verstuurd worden.');
        }
    }

    async function connectToPlayer(player) {
        const playerId = Number(player.id);

        if (peerConnections.has(playerId)) {
            cleanupPeerConnection(playerId, false);
        }

        createBodycamCard(player);
        setBodycamStatus(playerId, 'Verbinden...');

        const peerConnection = new RTCPeerConnection(rtcConfiguration);
        peerConnections.set(playerId, peerConnection);
        pendingIceCandidates.set(playerId, []);

        peerConnection.addTransceiver('video', { direction: 'recvonly' });

        peerConnection.ontrack = function (event) {
            const video = document.getElementById('camera-video-' + playerId);

            if (video && event.streams?.[0]) {
                video.srcObject = event.streams[0];
            }

            setBodycamStatus(playerId, 'LIVE');

            event.track.onended = function () {
                setBodycamStatus(playerId, 'Camera gestopt');
            };
        };

        peerConnection.onicecandidate = async function (event) {
            if (!event.candidate) {
                return;
            }

            try {
                await sendHostSignal(playerId, {
                    kind: 'ice',
                    candidate: event.candidate.toJSON(),
                });
            } catch (error) {
                console.error('Host ICE versturen mislukt:', error);
            }
        };

        peerConnection.onconnectionstatechange = function () {
            const state = peerConnection.connectionState;

            if (state === 'connected') {
                if (disconnectTimers.has(playerId)) {
                    clearTimeout(disconnectTimers.get(playerId));
                    disconnectTimers.delete(playerId);
                }

                setBodycamStatus(playerId, 'LIVE');
            }

            if (state === 'disconnected') {
                setBodycamStatus(playerId, 'Verbinding onderbroken...');

                if (!disconnectTimers.has(playerId)) {
                    const timer = setTimeout(function () {
                        cleanupPeerConnection(playerId, true);
                    }, 10000);

                    disconnectTimers.set(playerId, timer);
                }
            }

            if (state === 'failed') {
                setBodycamStatus(playerId, 'Verbinding mislukt');
                setTimeout(function () {
                    cleanupPeerConnection(playerId, true);
                }, 5000);
            }

            if (state === 'closed') {
                cleanupPeerConnection(playerId, true);
            }
        };

        peerConnection.oniceconnectionstatechange = function () {
            console.log('ICE', player.name, peerConnection.iceConnectionState);
        };

        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        const normalizedOffer = normalizeDescription({
            type: peerConnection.localDescription.type,
            sdp: peerConnection.localDescription.sdp,
        });

        await sendHostSignal(playerId, {
            kind: 'offer',
            description: normalizedOffer,
        });
    }

    function showRunningUi() {
        currentGameStatus = 'running';
        setGameStatus('Game is bezig', 'running');

        if (joinSection) {
            joinSection.hidden = true;
        }

        if (gameSettings) {
            gameSettings.hidden = true;
        }

        if (bodycamsSection) {
            bodycamsSection.hidden = false;
        }

        if (stopGameButton) {
            stopGameButton.hidden = false;
            stopGameButton.disabled = false;
            stopGameButton.textContent = 'Stop Game';
        }

        if (startGameButton) {
            startGameButton.disabled = true;
            startGameButton.textContent = 'Game gestart';
        }
    }

    function showFinishedUi() {
        currentGameStatus = 'finished';
        setGameStatus('Game afgelopen', 'finished');
        hideMeetingScreen();

        if (stopGameButton) {
            stopGameButton.hidden = true;
        }

        if (hostGameContent) {
            hostGameContent.hidden = true;
        }

        for (const playerId of [...peerConnections.keys()]) {
            cleanupPeerConnection(playerId, true);
        }

        if (hostGameEndedScreen) {
            hostGameEndedScreen.hidden = false;
        }
    }

    window.Pusher.logToConsole = true;

    const pusher = new window.Pusher(config.pusherKey, {
        cluster: 'mt1',
        wsHost: window.location.hostname,
        wssPort: 443,
        forceTLS: true,
        enabledTransports: ['ws'],
    });

    window.hostPusher = pusher;

    pusher.connection.bind('connected', function () {
        console.log('Host connected to Reverb');
    });

    pusher.connection.bind('error', function (error) {
        console.error('Reverb fout op host:', error);
    });

    const channel = pusher.subscribe('game.' + config.gameId);
    window.hostGameChannel = channel;

    channel.bind('pusher:subscription_succeeded', async function () {
        console.log('Host subscribed to game.' + config.gameId);

        if (currentGameStatus !== 'running') {
            return;
        }

        for (const player of config.players) {
            try {
                await connectToPlayer(player);
            } catch (error) {
                console.warn('Bodycam kon nog niet opnieuw verbonden worden:', player.name, error);
            }
        }
    });

    channel.bind_global(function (eventName, data) {
        console.log('HOST EVENT:', eventName, data);
    });

    channel.bind('player.joined', function (data) {
        addPlayerToLobby(data.player);
        addPlayerToMeeting(data.player);
    });

    channel.bind('game.started', function () {
        showRunningUi();
    });

    channel.bind('body.reported', function (data) {
        markMeetingPlayerDead(data.body.id);
        showMeetingScreen();
    });

    channel.bind('meeting.ended', function (data) {
        if (data.voted_out) {
            markMeetingPlayerDead(data.voted_out.id);
        }

        hideMeetingScreen();
    });

    channel.bind('game.ended', function () {
        showFinishedUi();
    });

    channel.bind('camera.ready', async function (data) {
        try {
            await connectToPlayer(data.player);
        } catch (error) {
            console.error('WebRTC starten mislukt:', error);
        }
    });

    channel.bind('webrtc.signal', async function (signal) {
        if (signal.sender !== 'player') {
            return;
        }

        const playerId = Number(signal.player_id);
        const peerConnection = peerConnections.get(playerId);

        if (!peerConnection) {
            console.warn('Geen PeerConnection voor player:', playerId);
            return;
        }

        try {
            if (signal.kind === 'answer') {
                const normalizedAnswer = normalizeDescription(signal.description);
                await peerConnection.setRemoteDescription(normalizedAnswer);

                const queuedCandidates = pendingIceCandidates.get(playerId) ?? [];

                for (const candidate of queuedCandidates) {
                    await peerConnection.addIceCandidate(candidate);
                }

                pendingIceCandidates.set(playerId, []);
            }

            if (signal.kind === 'ice') {
                if (peerConnection.remoteDescription) {
                    await peerConnection.addIceCandidate(signal.candidate);
                } else {
                    pendingIceCandidates.get(playerId)?.push(signal.candidate);
                }
            }
        } catch (error) {
            console.error('WebRTC fout op host:', error);
        }
    });

    meetingPlayerGrid?.addEventListener('click', async function (event) {
        const button = event.target.closest('.meeting-player-button');

        if (!button || button.disabled) {
            return;
        }

        const playerId = Number(button.dataset.playerId);
        const playerName = button.dataset.playerName;

        if (!confirm(playerName + ' uit het spel stemmen?')) {
            return;
        }

        document.querySelectorAll('.meeting-player-button').forEach(function (item) {
            item.disabled = true;
        });

        if (hostMeetingError) {
            hostMeetingError.hidden = true;
            hostMeetingError.textContent = '';
        }

        try {
            const response = await fetch(config.meetingEndUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ voted_out_player_id: playerId }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error ?? data.message ?? 'Meeting kon niet beëindigd worden.');
            }

            markMeetingPlayerDead(playerId);
            hideMeetingScreen();
        } catch (error) {
            console.error('Meeting fout:', error);

            if (hostMeetingError) {
                hostMeetingError.hidden = false;
                hostMeetingError.textContent = error.message;
            }

            refreshMeetingButtons();
        }
    });

    saveSettingsButton?.addEventListener('click', async function () {
        if (!impostorCountInput) {
            return;
        }

        const formData = new FormData();

        formData.append('impostor_count', impostorCountInput.value);

        if (tasksFileInput?.files.length > 0) {
            formData.append('tasks_file', tasksFileInput.files[0]);
        }

        saveSettingsButton.disabled = true;
        saveSettingsButton.textContent = 'Opslaan...'

        if (settingsStatus) {
            settingsStatus.textContent = 'Instellingen worden opgeslagen...'
            settingsStatus.className = 'settings-status';
        }

        try {
            const response = await fetch(config.settingsUrl, {
                method: 'POST',

                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            let data = {};

            try {
                data = await response.json();
            } catch (error) {
                console.error('Server stuurde geen geldige JSON terug.', error);
            }

            if (!response.ok) {
                throw new Error(
                    data.error ??
                    data.message ??
                    'Instellingen konden niet opgeslagen worden.'
                );
            }

            renderTaskPreview(data.settings.tasks);

            if (tasksFileInput) {
                tasksFileInput.value = '';
            }

            if (settingsStatus) {
                const amount = data.settings.tasks.length;

                settingsStatus.textContent = 
                    'Instellingen opgeslagen. ' + amount + (amount === 1 ? ' taak ingeladen.' : ' taken ingeladen.');
                
                settingsStatus.className = 'settings-status settings-status-success';
            }

            console.log('Game instellingen opgeslagen:', data.settings);

        } catch (error) {
            console.error('Instellingen opslaan mislukt:', error);
            
            if (settingsStatus) {
                settingsStatus.textContent = error.message;
                settingsStatus.className = 'settings-status settings-status-error';
            }
        } finally {
            saveSettingsButton.disabled = false;
            saveSettingsButton.textContent = 'Instellingen opslaan';
        }

    });

    startGameForm?.addEventListener('submit', async function (event) {
        event.preventDefault();

        startGameButton.disabled = true;
        startGameButton.textContent = 'Game starten...';
        setGameStatus('Game wordt gestart...', 'lobby');

        try {
            const response = await fetch(startGameForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(startGameForm),
            });

            let data = {};

            try {
                data = await response.json();
            } catch (error) {
                console.error('Server stuurde geen geldige JSON terug.', error);
            }

            if (!response.ok) {
                throw new Error(data.message ?? 'Game kon niet gestart worden.');
            }

            showRunningUi();
        } catch (error) {
            console.error('Game starten mislukt:', error);
            startGameButton.disabled = false;
            startGameButton.textContent = 'Start Game';
            setGameStatus(error.message, 'lobby');
        }
    });

    stopGameButton?.addEventListener('click', async function () {
        if (!confirm('Wil je het spel echt stoppen?')) {
            return;
        }

        stopGameButton.disabled = true;
        stopGameButton.textContent = 'Spel stoppen...';

        try {
            const response = await fetch(config.stopUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error ?? data.message ?? 'Spel kon niet gestopt worden.');
            }

            showFinishedUi();
        } catch (error) {
            console.error('Game stoppen mislukt:', error);
            stopGameButton.disabled = false;
            stopGameButton.textContent = 'Stop Game';
        }
    });

    const qrCodeElement = document.getElementById('join-qrcode');

    if (qrCodeElement && window.QRCode) {
        new window.QRCode(qrCodeElement, {
            text: window.location.origin + '/join?code=' + config.gameCode,
            width: 200,
            height: 200,
        });
    }
})();
