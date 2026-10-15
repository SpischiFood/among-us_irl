<?php

namespace App\Http\Controllers;

use App\Events\BodyReported;
use App\Events\PlayerCameraReady;
use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function create(): View
    {
        return view('players.join');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $game = Game::where('code', strtoupper($validated['code']))->firstOrFail();

        if ($game->status !== 'lobby') {
            return back()
                ->withErrors(['code' => 'Deze game is al gestart.'])
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

        return redirect()->route('players.show', ['token' => $player->token]);
    }

    public function show(string $token): View
    {
        $player = Player::where('token', $token)->with('game')->firstOrFail();

        $fellowImpostors = collect();

        if ($player->game->status === 'running' && $player->role === 'impostor') {
            $fellowImpostors = $player->game->players()
                ->where('role', 'impostor')
                ->where('id', '!=', $player->id)
                ->get();
        }

        $reportCandidates = $player->game->players()
            ->where('id', '!=', $player->id)
            ->where('alive', true)
            ->get();

        $playerConfig = [
            'playerId' => $player->id,
            'playerAlive' => (bool) $player->alive,
            'gameId' => $player->game->id,
            'gameStatus' => $player->game->status,
            'pusherKey' => config('broadcasting.connections.reverb.key'),
            'cameraReadyUrl' => route('players.cameraReady', ['token' => $player->token]),
            'webrtcSignalUrl' => route('players.webrtc-signal', ['token' => $player->token]),
            'reportUrl' => route('players.report', ['token' => $player->token]),
        ];

        return view('players.show', compact(
            'player',
            'fellowImpostors',
            'reportCandidates',
            'playerConfig'
        ));
    }

    public function cameraReady(string $token): JsonResponse
    {
        $player = Player::where('token', $token)->with('game')->firstOrFail();

        if ($player->game->status !== 'running') {
            return response()->json(['error' => 'Game is nog niet gestart.'], 400);
        }

        PlayerCameraReady::dispatch($player->game_id, $player->id, $player->name);

        return response()->json(['success' => true]);
    }

    public function report(Request $request, string $token): JsonResponse
    {
        // De token in de URL hoort bij de gsm van de gevonden body.
        $body = Player::where('token', $token)->with('game')->firstOrFail();

        if ($body->game->status !== 'running') {
            return response()->json(['error' => 'De game is niet bezig.'], 400);
        }

        $validated = $request->validate([
            'reporter_id' => ['required', 'integer'],
        ]);

        $reporter = $body->game->players()->whereKey($validated['reporter_id'])->first();

        if (! $reporter) {
            return response()->json(['error' => 'Ongeldige reporter.'], 422);
        }

        if ($reporter->id === $body->id) {
            return response()->json(['error' => 'Je kunt jezelf niet als reporter kiezen.'], 422);
        }

        if (! $reporter->alive) {
            return response()->json(['error' => 'Deze speler is al dood en kan niet rapporteren.'], 422);
        }

        if (! $body->alive) {
            return response()->json(['error' => 'Deze body werd al gerapporteerd.'], 409);
        }

        $body->alive = false;
        $body->save();

        BodyReported::dispatch(
            $body->game_id,
            $body->id,
            $body->name,
            $reporter->id,
            $reporter->name
        );

        return response()->json([
            'success' => true,
            'body' => [
                'id' => $body->id,
                'name' => $body->name,
            ],
            'reporter' => [
                'id' => $reporter->id,
                'name' => $reporter->name,
            ],
        ]);
    }
}
