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
use Illuminate\View\View;

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

    public function lobby(Game $game): View
    {
        $game->load('players');

        $hostConfig = [
            'gameId' => $game->id,
            'gameCode' => $game->code,
            'gameStatus' => $game->status,
            'pusherKey' => config('broadcasting.connections.reverb.key'),
            'startUrl' => route('games.start', $game),
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

        return view('games.lobby', compact('game', 'hostConfig'));
    }

    public function start(Request $request, Game $game): JsonResponse|RedirectResponse
    {
        if ($game->status !== 'lobby') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Deze game is al gestart.'], 422);
            }

            return back()->withErrors(['game' => 'Deze game is al gestart.']);
        }

        $players = $game->players;

        // Huidige testinstelling uit het bestaande project bewust behouden.
        if ($players->count() < 1) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Er zijn minimaal 4 spelers nodig om te starten.'], 422);
            }

            return back()->withErrors(['game' => 'Er zijn minimaal 4 spelers nodig om te starten.']);
        }

        DB::transaction(function () use ($game, $players): void {
            foreach ($players as $player) {
                $player->role = 'crewmate';
                $player->alive = true;
                $player->save();
            }

            // Huidige testinstelling: één impostor.
            foreach ($players->shuffle()->take(1) as $impostor) {
                $impostor->role = 'impostor';
                $impostor->save();
            }

            $game->status = 'running';
            $game->save();
        });

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
        return view('games.show', compact('game'));
    }

    public function endMeeting(Request $request, Game $game): JsonResponse
    {
        $validated = $request->validate([
            'voted_out_player_id' => ['required', 'integer'],
        ]);

        $player = $game->players()->whereKey($validated['voted_out_player_id'])->first();

        if (! $player) {
            return response()->json(['error' => 'Ongeldige speler.'], 422);
        }

        if (! $player->alive) {
            return response()->json(['error' => 'Speler is al dood.'], 422);
        }

        $player->alive = false;
        $player->save();

        MeetingEnded::dispatch($game->id, $player->id, $player->name);

        return response()->json([
            'success' => true,
            'voted_out' => [
                'id' => $player->id,
                'name' => $player->name,
            ],
        ]);
    }

    public function stop(Game $game): JsonResponse
    {
        if ($game->status !== 'running') {
            return response()->json(['error' => 'Deze game is niet bezig.'], 400);
        }

        $game->status = 'finished';
        $game->save();

        GameEnded::dispatch($game->id);

        return response()->json(['success' => true]);
    }
}
