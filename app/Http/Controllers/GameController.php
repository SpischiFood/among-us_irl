<?php

namespace App\Http\Controllers;

use App\Events\GameStarted;
use App\Events\MeetingEnded;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function create()
    {
        return view('games.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $game = new Game;
        $game->name = $request->input('name');
        $game->code = strtoupper(
            substr(md5(uniqid(rand(), true)), 0, 6)
        );

        $game->save();

        return redirect()
            ->route('games.lobby', $game)
            ->with('success', 'Game created successfully!');
    }

    public function lobby(Game $game)
    {
        return view('games.lobby', compact('game'));
    }

    public function start(Request $request, Game $game)
    {
        // Game mag maar één keer gestart worden
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

        // Minimum 4 spelers
        if ($players->count() < 1) {

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Er zijn minimaal 4 spelers nodig om te starten.',
                ], 422);
            }

            return back()->withErrors([
                'game' => 'Er zijn minimaal 4 spelers nodig om te starten.',
            ]);
        }

        DB::transaction(function () use ($game, $players) {

            // Iedereen eerst crewmate maken
            foreach ($players as $player) {
                $player->role = 'crewmate';
                $player->alive = true;
                $player->save();
            }

            // Twee willekeurige impostors
            $impostors = $players
                ->shuffle()
                ->take(1);

            foreach ($impostors as $impostor) {
                $impostor->role = 'impostor';
                $impostor->save();
            }

            // Game starten
            $game->status = 'running';
            $game->save();
        });

        // Alle spelers realtime laten weten dat de game gestart is
        GameStarted::dispatch($game);

        // AJAX/fetch vanuit host lobby
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Game started!',
            ]);
        }

        // Fallback indien de route toch klassiek wordt aangeroepen
        return back()->with(
            'success',
            'Game started!'
        );
    }

    public function show(Game $game)
    {
        return view('games.show', compact('game'));
    }

    public function endMeeting(Request $request, Game $game)
    {
        $validated = $request->validate(['voted_out_player_id' => ['required', 'integer']]);

        $player = $game
            ->players()
            ->whereKey($validated['voted_out_player_id'])
            ->first();

        if (! $player) {
            return response()->json(['error' => 'Ongeldige speler.'], 422);
        }

        if (! $player->alive) {
            return response()->json(['error' => 'Speler is al dood.'], 422);
        }

        $player->alive = false;
        $player->save();

        MeetingEnded::dispatch(
            $game->id,
            $player->id,
            $player->name,
        );

        return response()->json([
            'success' => true,
            'voted_out' => [
                'id' => $player->id,
                'name' => $player->name,
            ],
        ]);
    }
}
