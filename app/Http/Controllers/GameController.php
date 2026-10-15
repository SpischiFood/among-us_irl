<?php

namespace App\Http\Controllers;

use App\Events\GameEnded;
use App\Events\GameStarted;
use App\Events\MeetingEnded;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;

class GameController extends Controller
{
    public function create(): View
    {
        return view('games.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $game = new Game;

        $game->name = $validated['name'];
        $game->code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $game->save();

        return redirect()
            ->route('games.lobby', $game)
            ->with('success', 'Game created successfully!');
    }

    /*
    |--------------------------------------------------------------------------
    | GAME INSTELLINGEN
    |--------------------------------------------------------------------------
    |
    | De host kan hier vóór de start:
    |
    | - het aantal impostors instellen
    | - een JSON-bestand met taken uploaden
    |
    | Het JSON-bestand zelf wordt niet opgeslagen.
    | De inhoud wordt opgeslagen in games.tasks.
    |
    */

    public function updateSettings(Request $request, Game $game): JsonResponse
    {
        if ($game->status !== 'lobby') {
            return response()->json([
                'error' => 'Instellingen kunnen alleen voor de start aangepast worden.',
            ], 400);
        }

        /*
         * Algemene input valideren.
         *
         * tasks_file is nullable zodat de host bijvoorbeeld alleen
         * het aantal impostors kan aanpassen zonder opnieuw een
         * JSON-bestand te moeten uploaden.
         */

        $validated = $request->validate([
            'impostor_count' => ['required', 'integer', 'min:1', 'max:5'],
            'tasks_file' => ['nullable', 'file', 'max:2048'],
        ]);

        $game->impostor_count = $validated['impostor_count'];

        /*
         * Alleen taken vervangen wanneer er effectief
         * een nieuw bestand geüpload werd.
         */

        if ($request->hasFile('tasks_file')) {

            $file = $request->file('tasks_file');

            /*
             * Extensie controleren.
             */

            if (strtolower($file->getClientOriginalExtension()) !== 'json') {
                return response()->json([
                    'error' => 'Het takenbestand moet een .json-bestand zijn.',
                ], 422);
            }

            /*
             * JSON lezen.
             */

            try {

                $tasks = json_decode(
                    file_get_contents($file->getRealPath()),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            } catch (JsonException $exception) {

                return response()->json([
                    'error' => 'Het bestand bevat geen geldige JSON.',
                ], 422);
            }

            /*
             * Structuur van de JSON controleren.
             *
             * Verwacht:
             *
             * [
             *     {
             *         "name": "Taaknaam",
             *         "location": "Locatie"
             *     }
             * ]
             */

            $taskValidator = Validator::make(
                [
                    'tasks' => $tasks,
                ],
                [
                    'tasks' => ['required', 'array', 'min:1'],
                    'tasks.*' => ['required', 'array'],
                    'tasks.*.name' => ['required', 'string', 'max:100'],
                    'tasks.*.location' => ['required', 'string', 'max:100'],
                ]
            );

            if ($taskValidator->fails()) {
                return response()->json([
                    'error' => 'Het JSON-bestand heeft niet de juiste structuur.',
                    'errors' => $taskValidator->errors(),
                ], 422);
            }

            /*
             * Geldige taken opslaan als JSON in de database.
             *
             * Omdat Game::casts() tasks => array bevat,
             * zet Laravel dit automatisch om naar JSON.
             */

            $game->tasks = collect($tasks)
                ->map(function ($task) {
                    return [
                        'id' => (string) Str::uuid(),
                        'name' => $task['name'],
                        'location' => $task['location'],
                        'completed' => false,
                    ];
                })
                ->values()
                ->all();
        }

        $game->save();

        return response()->json([
            'success' => true,
            'message' => 'Instellingen opgeslagen.',
            'settings' => [
                'impostor_count' => $game->impostor_count,
                'tasks' => $game->tasks ?? [],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOBBY
    |--------------------------------------------------------------------------
    */

    public function lobby(Game $game): View
    {
        $game->load('players');

        $hostConfig = [
            'gameId' => $game->id,
            'gameCode' => $game->code,
            'gameStatus' => $game->status,

            'pusherKey' => config('broadcasting.connections.reverb.key'),

            'startUrl' => route('games.start', $game),
            'settingsUrl' => route('games.settings.update', $game),
            'meetingEndUrl' => route('games.meeting.end', $game),
            'webrtcSignalUrl' => route('games.webrtc-signal', $game),
            'stopUrl' => route('games.stop', $game),

            'players' => $game->players
                ->map(fn ($player) => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'alive' => (bool) $player->alive,
                ])
                ->values(),
        ];

        return view('games.lobby', compact(
            'game',
            'hostConfig'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | GAME STARTEN
    |--------------------------------------------------------------------------
    */

    public function start(Request $request, Game $game): JsonResponse|RedirectResponse
    {
        if ($game->status !== 'lobby') {

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Deze game is al gestart.',
                ], 422);
            }

            return back()->withErrors([
                'game' => 'Deze game is al gestart.',
            ]);
        }

        $players = $game->players;

        /*
         * Voorlopig tijdens development minimum 1 speler.
         *
         * Later kunnen we dit bijvoorbeeld op minimum 4 zetten.
         */

        if ($players->count() < 1) {

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Er is minimaal 1 speler nodig om te starten.',
                ], 422);
            }

            return back()->withErrors([
                'game' => 'Er is minimaal 1 speler nodig om te starten.',
            ]);
        }

        /*
         * Er moeten altijd meer spelers dan impostors zijn.
         */

        if ($game->impostor_count >= $players->count()) {

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Er moeten meer spelers dan impostors zijn.',
                ], 422);
            }

            return back()->withErrors([
                'game' => 'Er moeten meer spelers dan impostors zijn.',
            ]);
        }

        /*
         * Een game mag niet starten zonder taken.
         */

        if (empty($game->tasks)) {

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Upload eerst een takenbestand.',
                ], 422);
            }

            return back()->withErrors([
                'game' => 'Upload eerst een takenbestand.',
            ]);
        }

        /*
         * Willekeurig de juiste hoeveelheid impostors kiezen.
         */

        $impostorIds = $players
            ->shuffle()
            ->take($game->impostor_count)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($game, $players, $impostorIds): void {

            foreach ($players as $player) {

                $player->role = in_array($player->id, $impostorIds, true)
                    ? 'impostor'
                    : 'crewmate';

                $player->alive = true;

                $player->save();
            }

            $game->status = 'running';
            $game->save();
        });

        /*
         * Relaties opnieuw laden zodat het event
         * de nieuwe rollen/status heeft.
         */

        $game->refresh();
        $game->load('players');

        GameStarted::dispatch($game);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Game started!',
            ]);
        }

        return back()->with('success', 'Game started!');
    }

    public function show(Game $game): View
    {
        return $this->lobby($game);
    }

    /*
    |--------------------------------------------------------------------------
    | MEETING BEËINDIGEN
    |--------------------------------------------------------------------------
    */

    public function endMeeting(Request $request, Game $game): JsonResponse
    {
        $validated = $request->validate([
            'voted_out_player_id' => ['required', 'integer'],
        ]);

        $player = $game->players()
            ->whereKey($validated['voted_out_player_id'])
            ->first();

        if (! $player) {
            return response()->json([
                'error' => 'Ongeldige speler.',
            ], 422);
        }

        if (! $player->alive) {
            return response()->json([
                'error' => 'Speler is al dood.',
            ], 422);
        }

        $player->alive = false;
        $player->save();

        MeetingEnded::dispatch(
            $game->id,
            $player->id,
            $player->name
        );

        return response()->json([
            'success' => true,

            'voted_out' => [
                'id' => $player->id,
                'name' => $player->name,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GAME STOPPEN
    |--------------------------------------------------------------------------
    */

    public function stop(Game $game): JsonResponse
    {
        if ($game->status !== 'running') {
            return response()->json([
                'error' => 'Deze game is niet bezig.',
            ], 400);
        }

        $game->status = 'finished';
        $game->save();

        GameEnded::dispatch($game->id);

        return response()->json([
            'success' => true,
        ]);
    }
}
