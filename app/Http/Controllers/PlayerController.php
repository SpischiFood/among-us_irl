<?php

namespace App\Http\Controllers;

use App\Events\BodyReported;
use App\Events\PlayerCameraReady;
use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | JOIN PAGINA
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('players.join');
    }

    /*
    |--------------------------------------------------------------------------
    | PLAYER JOIN GAME
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:50',
            'code' => 'required|string|size:6',
        ]);

        $game = Game::where(
            'code',
            strtoupper($validated['code'])
        )->firstOrFail();

        /*
         * Niet meer joinen wanneer game gestart is.
         */
        if ($game->status !== 'lobby') {

            return back()
                ->withErrors([
                    'code' => 'Deze game is al gestart.',
                ])
                ->withInput();

        }

        /*
         * Nieuwe speler.
         */
        $player = Player::create([
            'game_id' => $game->id,
            'name' => $validated['name'],
            'token' => Str::uuid(),
            'role' => null,
            'alive' => true,
        ]);

        /*
         * Host realtime laten weten
         * dat iemand gejoined is.
         */
        PlayerJoined::dispatch(
            $player
        );

        return redirect()->route(
            'players.show',
            [
                'token' => $player->token,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PLAYER SCREEN
    |--------------------------------------------------------------------------
    */

    public function show(string $token)
    {
        $player = Player::where(
            'token',
            $token
        )
            ->with('game')
            ->firstOrFail();

        /*
         * Alleen impostors moeten weten
         * wie de andere impostor is.
         */
        $fellowImpostors = collect();

        if (
            $player->game->status === 'running'
            &&
            $player->role === 'impostor'
        ) {

            $fellowImpostors =
                $player
                    ->game
                    ->players()
                    ->where(
                        'role',
                        'impostor'
                    )
                    ->where(
                        'id',
                        '!=',
                        $player->id
                    )
                    ->get();

        }

        /*
         * Deze lijst gaan we straks gebruiken
         * voor:
         *
         * "Wie ben jij?"
         *
         * na het indrukken van REPORT.
         *
         * Eigen speler staat er niet tussen,
         * want je kunt je eigen body niet vinden.
         */
        $reportCandidates =
            $player
                ->game
                ->players()
                ->where(
                    'id',
                    '!=',
                    $player->id
                )
                ->where('alive', true)
                ->get();

        return view(
            'players.show',
            compact(
                'player',
                'fellowImpostors',
                'reportCandidates'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CAMERA READY
    |--------------------------------------------------------------------------
    */

    public function cameraReady(string $token)
    {
        $player = Player::where(
            'token',
            $token
        )
            ->with('game')
            ->firstOrFail();

        if (
            $player->game->status
            !== 'running'
        ) {

            return response()->json([
                'error' => 'Game is nog niet gestart.',
            ], 400);

        }

        /*
         * Host mag nu WebRTC offer sturen.
         */
        PlayerCameraReady::dispatch(
            $player->game_id,
            $player->id,
            $player->name
        );

        return response()->json([
            'success' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BODY REPORT
    |--------------------------------------------------------------------------
    |
    | BELANGRIJK:
    |
    | De token in de URL hoort bij de gsm
    | waarop REPORT gedrukt werd.
    |
    | Die speler is dus de BODY.
    |
    | De speler die de body gevonden heeft,
    | wordt meegestuurd als reporter_id.
    |
    */

    public function report(
        Request $request,
        string $token
    ) {

        /*
         * GSM-eigenaar = gevonden body.
         */
        $body = Player::where(
            'token',
            $token
        )
            ->with('game')
            ->firstOrFail();

        /*
         * Alleen tijdens actieve game.
         */
        if (
            $body->game->status
            !== 'running'
        ) {

            return response()->json([
                'error' => 'De game is niet bezig.',
            ], 400);

        }

        /*
         * Reporter moet vanuit de gsm
         * meegestuurd worden.
         */
        $validated =
            $request->validate([
                'reporter_id' => [
                    'required',
                    'integer',
                ],
            ]);

        /*
         * Reporter zoeken,
         * maar ENKEL binnen dezelfde game.
         */
        $reporter =
            $body
                ->game
                ->players()
                ->whereKey(
                    $validated['reporter_id']
                )
                ->first();

        /*
         * Kan bijvoorbeeld gebeuren
         * als iemand handmatig een verkeerde ID stuurt.
         */
        if (! $reporter) {

            return response()->json([
                'error' => 'Ongeldige reporter.',
            ], 422);

        }

        /*
         * De eigenaar van deze gsm ligt dood.
         *
         * Die kan dus niet zichzelf
         * als reporter kiezen.
         */
        if (
            $reporter->id
            ===
            $body->id
        ) {

            return response()->json([
                'error' => 'Je kunt jezelf niet als reporter kiezen.',
            ], 422);

        }

        /*
         * Een reeds dode speler mag
         * geen andere body rapporteren.
         */
        if (! $reporter->alive) {

            return response()->json([
                'error' => 'Deze speler is al dood en kan niet rapporteren.',
            ], 422);

        }

        /*
         * Body werd al eerder gevonden.
         */
        if (! $body->alive) {

            return response()->json([
                'error' => 'Deze body werd al gerapporteerd.',
            ], 409);

        }

        /*
         * Nu weten we officieel dat deze
         * speler dood is.
         */
        $body->alive = false;
        $body->save();

        /*
         * Realtime event naar host
         * én alle spelers.
         */
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
