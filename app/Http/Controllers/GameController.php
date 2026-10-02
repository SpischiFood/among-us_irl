<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GameController extends Controller
{
    public function create(){
        return view('games.create');
    }

    public function store(request $request){
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $game = new \App\Models\Game();
        $game->name = $request->input('name');
        $game->code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $game->save();

        return redirect()->route('games.lobby', $game)->with('success', 'Game created successfully!');
    
    }

    public function lobby($game){
        $game = \App\Models\Game::where(id, $game)->firstOrFail();
        return view('games.lobby', compact('game'));
    }

}
