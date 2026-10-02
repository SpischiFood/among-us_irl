<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/games/create', [App\Http\Controllers\GameController::class, 'create'])->name('games.create');
Route::post('/games/store', [App\Http\Controllers\GameController::class, 'store'])->name('games.store');
Route::get('/games/{game}/lobby', [App\Http\Controllers\GameController::class, 'lobby'])->name('games.lobby');
Route::post('/games/{game}/start', [App\Http\Controllers\GameController::class, 'start'])->name('games.start');