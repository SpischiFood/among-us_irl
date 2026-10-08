<?php

namespace App\Http\Controllers;

use App\Events\GameStarted;
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
        $game->code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $game->save();

        return redirect()->route('games.lobby', $game)->with('success', 'Game created successfully!');

    }

    public function lobby(Game $game)
    {
        return view('games.lobby', compact('game'));
    }

    public function start(Game $game)
    {
        if ($game->status !== 'lobby') {
            return back()->withErrors(['game' => 'Deze game is al gestart.']);
        }

        $players = $game->players;
        if ($players->count() < 4) {
            return back()->withErrors(['game' => 'Er zijn minimaal 4 spelers nodig om te starten.']);
        }

        DB::transaction(function () use ($game, $players) {
            foreach ($players as $player) {
                $player->role = 'crewmate';
                $player->alive = true;
                $player->save();
            }

            $impostors = $players->shuffle()->take(2);

            foreach ($impostors as $impostor) {
                $impostor->role = 'impostor';
                $impostor->save();
            }

            $game->status = 'running';
            $game->save();
        });

        GameStarted::dispatch($game);

        return back()->with('success', 'Game started!');
    }
}
