<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskDeviceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TASK DEVICE KIEZEN
    |--------------------------------------------------------------------------
    |
    | Wordt geopend via:
    |
    | /join/task-device?code=ABC123
    |
    | De game wordt opgezocht op basis van de code uit de QR-code.
    |
    */

    public function create(Request $request): View
    {
        $code = strtoupper(trim($request->query('code', '')));

        if ($code === '') {
            abort(404, 'Geen gamecode opgegeven.');
        }

        $game = Game::where('code', $code)->first();

        if (! $game) {
            abort(404, 'Game niet gevonden.');
        }

        /*
         * Een task-device wordt vóór de start aan een taak gekoppeld.
         */

        if ($game->status !== 'lobby') {
            abort(403, 'Task devices kunnen alleen voor de start gekoppeld worden.');
        }

        $tasks = $game->tasks ?? [];

        if (empty($tasks)) {
            abort(422, 'Voor deze game zijn nog geen taken ingesteld.');
        }

        return view('task-devices.select', [
            'game' => $game,
            'tasks' => $tasks,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TASK DEVICE SCHERM
    |--------------------------------------------------------------------------
    |
    | Toont één specifieke taak.
    |
    | Het device blijft via deze URL aan die taak gekoppeld.
    |
    */

    public function show(Game $game, string $taskId): View
    {
        $task = collect($game->tasks ?? [])
            ->firstWhere('id', $taskId);

        if (! $task) {
            abort(404, 'Taak niet gevonden.');
        }

        return view('task-devices.show', [
            'game' => $game,
            'task' => $task,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TAAK VOLTOOIEN
    |--------------------------------------------------------------------------
    */

    public function complete(Game $game, string $taskId): JsonResponse
    {
        /*
         * Taken mogen alleen voltooid worden wanneer
         * het spel daadwerkelijk bezig is.
         */

        if ($game->status !== 'running') {
            return response()->json([
                'error' => 'Deze taak kan alleen tijdens een lopende game voltooid worden.',
            ], 422);
        }

        $tasks = $game->tasks ?? [];

        $taskFound = false;
        $alreadyCompleted = false;

        foreach ($tasks as &$task) {

            if (($task['id'] ?? null) !== $taskId) {
                continue;
            }

            $taskFound = true;

            if ($task['completed'] ?? false) {
                $alreadyCompleted = true;
                break;
            }

            $task['completed'] = true;

            break;
        }

        unset($task);

        if (! $taskFound) {
            return response()->json([
                'error' => 'Taak niet gevonden.',
            ], 404);
        }

        if ($alreadyCompleted) {
            return response()->json([
                'success' => true,
                'already_completed' => true,
                'message' => 'Deze taak was al voltooid.',
            ]);
        }

        $game->tasks = $tasks;
        $game->save();

        /*
         * Later sturen we hier ook een Reverb-event:
         *
         * TaskCompleted::dispatch(...)
         *
         * zodat de host zijn taskbar onmiddellijk kan aanpassen.
         */

        return response()->json([
            'success' => true,
            'already_completed' => false,
            'message' => 'Taak voltooid.',
        ]);
    }
}
