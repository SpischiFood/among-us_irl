<section id="game-settings" class="game-settings">

    {{-- =========================================================
         HEADER
         ========================================================= --}}

    <div class="settings-header">

        <div>
            <h2>Game instellingen</h2>

            <p>
                Stel de game in voordat je ze start.
            </p>
        </div>

    </div>


    {{-- =========================================================
         AANTAL IMPOSTORS
         ========================================================= --}}

    <div class="settings-block">

        <div class="settings-block-header">

            <div>
                <h3>Impostors</h3>

                <p>
                    Kies hoeveel spelers de rol van impostor krijgen.
                </p>
            </div>

        </div>


        <div class="setting-field">

            <label for="impostor-count">
                Aantal impostors
            </label>

            <input
                type="number"
                id="impostor-count"
                name="impostor_count"
                min="1"
                max="5"
                value="{{ $game->impostor_count ?? 2 }}"
            >

        </div>

    </div>


    {{-- =========================================================
         TAKENBESTAND
         ========================================================= --}}

    <div class="settings-block">

        <div class="settings-block-header">

            <div>
                <h3>Taken</h3>

                <p>
                    Upload een JSON-bestand met de taken voor deze game.
                </p>
            </div>

        </div>


        <div class="setting-field">

            <label for="tasks-file">
                Takenbestand
            </label>

            <input
                type="file"
                id="tasks-file"
                name="tasks_file"
                accept=".json,application/json"
            >

        </div>


        {{-- =====================================================
             JSON INFO
             ===================================================== --}}

        <details class="json-help">

            <summary>
                Verwacht JSON-formaat
            </summary>

            <pre>[
    {
        "name": "Draden verbinden",
        "location": "Elektriciteitslokaal"
    },
    {
        "name": "Kaart scannen",
        "location": "Inkomhal"
    }
]</pre>

        </details>

    </div>


    {{-- =========================================================
         HUIDIGE TAKEN
         ========================================================= --}}

    <div
        id="task-preview"
        class="task-preview"
        @if (empty($game->tasks))
            hidden
        @endif
    >

        <div class="task-preview-header">

            <div>
                <h3>Ingeladen taken</h3>

                <p id="task-count">
                    {{ count($game->tasks ?? []) }}
                    {{ count($game->tasks ?? []) === 1 ? 'taak' : 'taken' }}
                </p>
            </div>

        </div>


        <div
            id="task-preview-list"
            class="task-preview-list"
        >

            @foreach ($game->tasks ?? [] as $task)

                <div class="task-preview-item">

                    <div class="task-preview-name">
                        {{ $task['name'] }}
                    </div>

                    <div class="task-preview-location">
                        {{ $task['location'] }}
                    </div>

                </div>

            @endforeach

        </div>

    </div>


    {{-- =========================================================
         OPSLAAN
         ========================================================= --}}

    <div class="settings-actions">

        <button
            type="button"
            id="save-settings-button"
            class="save-settings-button"
        >
            Instellingen opslaan
        </button>


        <p
            id="settings-status"
            class="settings-status"
            aria-live="polite"
        ></p>

    </div>

</section>