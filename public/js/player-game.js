import { normalizeDescription } from './webrtc-utils.js';

(function () {
    const root = document.getElementById('player-game');
    const configElement = document.getElementById('player-game-config');

    if (!root || !configElement) {
        return;
    }

    const config = JSON.parse(configElement.textContent);
    const currentPlayerId = Number(config.playerId);
    let isCurrentPlayerDead = !config.playerAlive;
    let localStream = null;
    let peerConnection = null;
    let pendingIceCandidates = [];

    const rtcConfiguration = { iceServers: [] };

    const confirmRoleButton = document.getElementById('confirm-role');
    const roleScreen = document.getElementById('role-screen');
    const nametagScreen = document.getElementById('nametag-screen');
    const playerCard = document.getElementById('player-card');
    const cameraPreview = document.getElementById('camera-preview');
    const cameraError = document.getElementById('camera-error');
    const webrtcStatus = document.getElementById('webrtc-status');
    const bodycamStatus = document.getElementById('bodycam-status');
    const connectionStatus = document.getElementById('connection-status');
    const reportButton = document.getElementById('report-button');
    const reportHelp = document.getElementById('report-help');
    const reporterScreen = document.getElementById('reporter-screen');
    const cancelReportButton = document.getElementById('cancel-report-button');
    const reportError = document.getElementById('report-error');
    const meetingScreen = document.getElementById('meeting-screen');
    const meetingBody = document.getElementById('meeting-body');
    const meetingReporter = document.getElementById('meeting-reporter');
    const gameEndedScreen = document.getElementById('game-ended-screen');

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    function reporterButtons() {
        return document.querySelectorAll('.reporter-button');
    }

    function setPlayerDead() {
        isCurrentPlayerDead = true;
        playerCard?.classList.add('player-card-dead');

        if (reportButton) {
            reportButton.textContent = 'DOOD';
            reportButton.disabled = true;
        }

        if (reportHelp) {
            reportHelp.textContent = 'Je bent dood. Je blijft wel verder filmen.';
        }
    }

    function returnToGameScreen() {
        if (meetingScreen) {
            meetingScreen.hidden = true;
        }

        if (reporterScreen) {
            reporterScreen.hidden = true;
        }

        if (roleScreen) {
            roleScreen.hidden = true;
        }

        if (nametagScreen) {
            nametagScreen.hidden = false;
        }

        if (isCurrentPlayerDead) {
            setPlayerDead();
        }
    }

    function setBodycamState(state) {
        if (!bodycamStatus) {
            return;
        }

        const labels = {
            new: '● BODYCAM STARTING',
            connecting: '● BODYCAM CONNECTING',
            connected: '● BODYCAM LIVE',
            disconnected: '● BODYCAM CONNECTION LOST',
            failed: '● BODYCAM OFFLINE',
            closed: '● BODYCAM OFFLINE',
        };

        bodycamStatus.textContent = labels[state] ?? bodycamStatus.textContent;
    }

    async function sendPlayerSignal(signal) {
        const response = await fetch(config.webrtcSignalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(signal),
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('WebRTC signaling fout:', errorText);
            throw new Error('WebRTC-signaal kon niet verstuurd worden.');
        }
    }

    async function handleOffer(signal) {
        if (webrtcStatus) {
            webrtcStatus.textContent = 'Offer ontvangen van host...';
        }

        setBodycamState('connecting');

        if (peerConnection) {
            peerConnection.close();
        }

        peerConnection = new RTCPeerConnection(rtcConfiguration);
        pendingIceCandidates = [];

        peerConnection.onicecandidate = async function (event) {
            if (!event.candidate) {
                return;
            }

            try {
                await sendPlayerSignal({
                    kind: 'ice',
                    candidate: event.candidate.toJSON(),
                });
            } catch (error) {
                console.error('ICE candidate versturen mislukt:', error);
            }
        };

        peerConnection.onconnectionstatechange = function () {
            const state = peerConnection.connectionState;

            if (webrtcStatus) {
                webrtcStatus.textContent = 'WebRTC: ' + state;
            }

            setBodycamState(state);
        };

        peerConnection.oniceconnectionstatechange = function () {
            console.log('GSM ICE status:', peerConnection.iceConnectionState);
        };

        const normalizedOffer = normalizeDescription(signal.description);
        await peerConnection.setRemoteDescription(normalizedOffer);

        if (!localStream) {
            throw new Error('Camera stream bestaat nog niet.');
        }

        localStream.getTracks().forEach(function (track) {
            peerConnection.addTrack(track, localStream);
        });

        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);

        const normalizedAnswer = normalizeDescription({
            type: peerConnection.localDescription.type,
            sdp: peerConnection.localDescription.sdp,
        });

        await sendPlayerSignal({
            kind: 'answer',
            description: normalizedAnswer,
        });

        if (webrtcStatus) {
            webrtcStatus.textContent = 'Answer verstuurd. Verbinding maken...';
        }

        for (const candidate of pendingIceCandidates) {
            await peerConnection.addIceCandidate(candidate);
        }

        pendingIceCandidates = [];
    }

    function showGameEnded() {
        if (localStream) {
            localStream.getTracks().forEach(function (track) {
                track.stop();
            });
            localStream = null;
        }

        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }

        [roleScreen, nametagScreen, reporterScreen, meetingScreen].forEach(function (screen) {
            if (screen) {
                screen.hidden = true;
            }
        });

        if (gameEndedScreen) {
            gameEndedScreen.hidden = false;
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

    window.playerPusher = pusher;

    pusher.connection.bind('connected', function () {
        if (connectionStatus) {
            connectionStatus.textContent = 'Verbonden. Wachten op de spelleider...';
        }
    });

    pusher.connection.bind('error', function (error) {
        console.error('Reverb fout op gsm:', error);
    });

    const channel = pusher.subscribe('game.' + config.gameId);
    window.playerGameChannel = channel;

    channel.bind('pusher:subscription_succeeded', function () {
        if (confirmRoleButton) {
            confirmRoleButton.disabled = false;
            confirmRoleButton.textContent = 'Ik heb mijn rol gezien';
        }

        if (connectionStatus) {
            connectionStatus.textContent = 'Verbonden. Wachten op de spelleider...';
        }
    });

    channel.bind_global(function (eventName, data) {
        console.log('GSM EVENT:', eventName, data);
    });

    channel.bind('game.started', function () {
        window.location.reload();
    });

    channel.bind('body.reported', function (data) {
        if (Number(data.body.id) === currentPlayerId) {
            setPlayerDead();
        }

        if (nametagScreen) {
            nametagScreen.hidden = true;
        }

        if (roleScreen) {
            roleScreen.hidden = true;
        }

        if (reporterScreen) {
            reporterScreen.hidden = true;
        }

        if (meetingBody) {
            meetingBody.textContent = data.body.name + ' werd dood gevonden';
        }

        if (meetingReporter) {
            meetingReporter.textContent = 'Reported by ' + data.reporter.name;
        }

        if (meetingScreen) {
            meetingScreen.hidden = false;
        }

        document.querySelector(
            '.reporter-button[data-reporter-id="' + data.body.id + '"]'
        )?.remove();
    });

    channel.bind('meeting.ended', function (data) {
        const votedOutPlayerId = Number(
            data.voted_out?.id ?? data.votedOutPlayerId ?? data.voted_out_player_id
        );

        if (votedOutPlayerId === currentPlayerId) {
            setPlayerDead();
        }

        document.querySelector(
            '.reporter-button[data-reporter-id="' + votedOutPlayerId + '"]'
        )?.remove();

        returnToGameScreen();
    });

    channel.bind('game.ended', function () {
        showGameEnded();
    });

    channel.bind('webrtc.signal', async function (signal) {
        if (signal.sender !== 'host' || Number(signal.player_id) !== currentPlayerId) {
            return;
        }

        try {
            if (signal.kind === 'offer') {
                await handleOffer(signal);
            }

            if (signal.kind === 'ice') {
                if (peerConnection?.remoteDescription) {
                    await peerConnection.addIceCandidate(signal.candidate);
                } else {
                    pendingIceCandidates.push(signal.candidate);
                }
            }
        } catch (error) {
            console.error('WebRTC fout op gsm:', error);

            if (webrtcStatus) {
                webrtcStatus.textContent = 'WebRTC fout: ' + error.message;
            }

            setBodycamState('failed');
        }
    });

    confirmRoleButton?.addEventListener('click', async function () {
        confirmRoleButton.disabled = true;
        confirmRoleButton.textContent = 'Camera starten...';

        try {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 360 },
                    frameRate: { ideal: 15, max: 20 },
                },
                audio: false,
            });

            if (cameraPreview) {
                cameraPreview.srcObject = localStream;
            }

            if (roleScreen) {
                roleScreen.hidden = true;
            }

            if (nametagScreen) {
                nametagScreen.hidden = false;
            }

            if (isCurrentPlayerDead) {
                setPlayerDead();
            }

            setBodycamState('new');

            if (webrtcStatus) {
                webrtcStatus.textContent = 'Camera klaar. Host verwittigen...';
            }

            const response = await fetch(config.cameraReadyUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Camera-ready fout:', errorText);
                throw new Error('Kon camera-status niet doorgeven aan de host.');
            }

            if (webrtcStatus) {
                webrtcStatus.textContent = 'Camera klaar. Wachten op host...';
            }
        } catch (error) {
            console.error('Camera fout:', error);

            confirmRoleButton.disabled = false;
            confirmRoleButton.textContent = 'Opnieuw proberen';

            if (cameraError) {
                cameraError.hidden = false;
                cameraError.textContent = 'Camera kon niet worden gestart: ' + error.message;
            }

            setBodycamState('failed');
        }
    });

    reportButton?.addEventListener('click', function () {
        if (reportButton.disabled || isCurrentPlayerDead) {
            return;
        }

        if (nametagScreen) {
            nametagScreen.hidden = true;
        }

        if (reporterScreen) {
            reporterScreen.hidden = false;
        }

        if (reportError) {
            reportError.hidden = true;
            reportError.textContent = '';
        }
    });

    cancelReportButton?.addEventListener('click', function () {
        if (reporterScreen) {
            reporterScreen.hidden = true;
        }

        if (nametagScreen) {
            nametagScreen.hidden = false;
        }

        if (reportError) {
            reportError.hidden = true;
        }
    });

    reporterButtons().forEach(function (button) {
        button.addEventListener('click', async function () {
            const reporterId = Number(button.dataset.reporterId);

            reporterButtons().forEach(function (item) {
                item.disabled = true;
            });

            if (cancelReportButton) {
                cancelReportButton.disabled = true;
            }

            if (reportError) {
                reportError.hidden = false;
                reportError.textContent = 'Report versturen...';
            }

            try {
                const response = await fetch(config.reportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ reporter_id: reporterId }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? data.message ?? 'Report kon niet verstuurd worden.');
                }

                if (reportError) {
                    reportError.textContent = 'Report verstuurd...';
                }
            } catch (error) {
                console.error('Report fout:', error);

                if (reportError) {
                    reportError.hidden = false;
                    reportError.textContent = error.message;
                }

                reporterButtons().forEach(function (item) {
                    item.disabled = false;
                });

                if (cancelReportButton) {
                    cancelReportButton.disabled = false;
                }
            }
        });
    });
})();
