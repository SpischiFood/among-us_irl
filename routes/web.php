<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\WebRtcSignalController;
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

Route::post('/games/{game}/start', [GameController::class, 'start'])
    ->name('games.start');

Route::get('/games/{game}', [GameController::class, 'show'])
    ->name('games.show');

Route::post('/player/{token}/camera-ready', [PlayerController::class, 'cameraReady'])
    ->name('players.cameraReady');

Route::post('/games/{game}/webrtc-signal', [WebRtcSignalController::class, 'fromHost'])
    ->name('games.webrtc-signal');

Route::post('/player/{token}/webrtc-signal', [WebRtcSignalController::class, 'fromPlayer'])
    ->name('players.webrtc-signal');

Route::post('/player/{token}/report', [PlayerController::class, 'report'])
    ->name('players.report');
