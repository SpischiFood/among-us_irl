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

        return redirect()->route('games.create')->with('success', 'Game created successfully! Code: ' . $game->code);
        

    }

}
