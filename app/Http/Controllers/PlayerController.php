<?php

namespace App\Http\Controllers;

use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    public function create()
    {
        return view('players.join');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:50',
            'code' => 'required|string|size:6',
        ]);

        $game = Game::where('code', strtoupper($validated['code']))
            ->firstOrFail();

        if ($game->status !== 'lobby') {
            return back()
                ->withErrors([
                    'code' => 'Deze game is al gestart.',
                ])
                ->withInput();
        }

        $player = Player::create([
            'game_id' => $game->id,
            'name' => $validated['name'],
            'token' => Str::uuid(),
            'role' => null,
            'alive' => true,
        ]);
        PlayerJoined::dispatch($player);

        return redirect()->route('players.show', [
            'token' => $player->token,
        ]);
    }

    public function show(string $token)
    {
        $player = Player::where('token', $token)
            ->with('game')
            ->firstOrFail();

        return view('players.show', compact('player'));
    }
}
