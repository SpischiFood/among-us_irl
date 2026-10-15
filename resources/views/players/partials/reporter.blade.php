<div id="reporter-screen" class="reporter-screen" hidden>
    <div class="reporter-card">
        <p class="screen-kicker">Body gevonden</p>
        <h2>Wie ben jij?</h2>
        <p>Kies jouw naam om deze body te rapporteren.</p>

        <div class="reporter-grid">
            @forelse ($reportCandidates as $candidate)
                <button
                    type="button"
                    class="reporter-button"
                    data-reporter-id="{{ $candidate->id }}"
                >
                    {{ $candidate->name }}
                </button>
            @empty
                <p>Geen spelers beschikbaar.</p>
            @endforelse
        </div>

        <button type="button" id="cancel-report-button" class="cancel-report-button">
            Annuleren
        </button>

        <p id="report-error" class="report-error" hidden></p>
    </div>
</div>
