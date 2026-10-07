<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game;

class GameController extends Controller
{
    public function create(){
        return view('games.create');
    }

    public function store(request $request){
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $game = new Game();
        $game->name = $request->input('name');
        $game->code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $game->save();

        return redirect()->route('games.lobby', $game)->with('success', 'Game created successfully!');
    
    }

    public function lobby(Game $game){
        $game = Game::findOrFail($game);
        return view('games.lobby', compact('game'));
    }

}
