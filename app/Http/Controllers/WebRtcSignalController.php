<?php

namespace App\Http\Controllers;

use App\Events\WebRtcSignal;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebRtcSignalController extends Controller
{
    public function fromHost(Request $request, Game $game)
    {
        $data = $request->validate([
            'kind' => [
                'required',
                Rule::in(['offer', 'ice']),
            ],

            'player_id' => [
                'required',
                'integer',
            ],

            'description' => [
                'nullable',
                'array',
            ],

            'candidate' => [
                'nullable',
                'array',
            ],
        ]);

        // Controleer of de speler effectief tot deze game behoort
        $playerExists = $game
            ->players()
            ->whereKey($data['player_id'])
            ->exists();

        if (! $playerExists) {
            return response()->json([
                'message' => 'Speler behoort niet tot deze game.',
            ], 422);
        }

        WebRtcSignal::dispatch(
            $game->id,
            [
                'sender' => 'host',
                'kind' => $data['kind'],
                'player_id' => $data['player_id'],
                'description' => $data['description'] ?? null,
                'candidate' => $data['candidate'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }

    public function fromPlayer(Request $request, string $token)
    {
        $player = Player::where('token', $token)
            ->with('game')
            ->firstOrFail();

        $data = $request->validate([
            'kind' => [
                'required',
                Rule::in(['answer', 'ice']),
            ],

            'description' => [
                'nullable',
                'array',
            ],

            'candidate' => [
                'nullable',
                'array',
            ],
        ]);

        WebRtcSignal::dispatch(
            $player->game_id,
            [
                'sender' => 'player',
                'kind' => $data['kind'],
                'player_id' => $player->id,
                'description' => $data['description'] ?? null,
                'candidate' => $data['candidate'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }
}
