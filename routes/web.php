<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\PlayerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
Route::post('/games/store', [GameController::class, 'store'])->name('games.store');
Route::get('/games/{game}/lobby', [GameController::class, 'lobby'])->name('games.lobby');
Route::post('/games/{game}/start', [GameController::class, 'start'])->name('games.start');

Route::get('/join', [PlayerController::class, 'create'])
    ->name('players.create');

Route::post('/join', [PlayerController::class, 'store'])
    ->name('players.store');

Route::get('/player/{token}', [PlayerController::class, 'show'])
    ->name('players.show');
