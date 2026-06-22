(() => {
    const form = document.getElementById('analysis-form');
    if (!form) {
        return;
    }

    const objective = document.getElementById('objective');
    const siteUrl = document.getElementById('site_url');
    const siteType = document.getElementById('site_type');
    const contextNotes = document.getElementById('context_notes');
    const fileInput = document.getElementById('evidence_files');
    const fileCount = document.getElementById('file-count');
    const confidenceScore = document.getElementById('confidence-score');
    const verdictText = document.getElementById('verdict-text');
    const previewUrl = document.getElementById('preview-url');
    const previewType = document.getElementById('preview-type');
    const previewObjective = document.getElementById('preview-objective');
    const previewSignals = document.getElementById('preview-signals');
    const previewFiles = document.getElementById('preview-files');
    const livePreview = document.getElementById('live-description-preview');
    const resetButton = document.querySelector('[data-reset-form]');
    const demoButton = document.querySelector('[data-insert-demo]');

    const verdictRules = [
        { min: 75, text: 'Bonne probabilité de reconstruction du backend' },
        { min: 55, text: 'Probabilité moyenne, besoin d’indices supplémentaires' },
        { min: 0, text: 'Probabilité faible ou trop d’opacité pour conclure' },
    ];

    const typeLabels = {
        dashboard: 'Dashboard / application web',
        ecommerce: 'E-commerce',
        portal: 'Portail métier',
        public: 'Site public / vitrine',
        api: 'API ou back-office',
    };

    const updateSummary = () => {
        const chosenSignals = [...form.querySelectorAll('input[name="signals[]"]:checked')].map((input) => input.value);
        const selectedFiles = fileInput.files ? fileInput.files.length : 0;
        const objectiveValue = objective.value.trim();
        const urlValue = siteUrl.value.trim();
        const typeValue = siteType.value;

        const score = Math.max(
            0,
            Math.min(
                100,
                38 + (chosenSignals.length * 4) + (objectiveValue ? 12 : 0) + (urlValue ? 8 : 0)
            )
        );

        const verdict = verdictRules.find((rule) => score >= rule.min) || verdictRules[verdictRules.length - 1];

        confidenceScore.textContent = `${score}%`;
        verdictText.textContent = verdict.text;
        previewUrl.textContent = urlValue || 'Non renseignée';
        previewType.textContent = typeLabels[typeValue] || typeValue;
        previewObjective.textContent = objectiveValue || 'Aucun objectif fourni pour le moment.';
        previewSignals.textContent = chosenSignals.length ? chosenSignals.join(', ') : 'Aucun signal sélectionné';
        previewFiles.textContent = `${selectedFiles} fichier(s)`;
        livePreview.textContent = objectiveValue || 'La description principale s’affichera ici en temps réel.';
        fileCount.textContent = String(selectedFiles);

        const bar = confidenceScore.closest('.verdict-score')?.querySelector('.score-bar span');
        if (bar) {
            bar.style.width = `${score}%`;
        }
    };

    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);

    if (fileInput) {
        fileInput.addEventListener('change', updateSummary);
    }

    if (demoButton) {
        demoButton.addEventListener('click', () => {
            siteUrl.value = 'https://client.example.com/portal';
            siteType.value = 'portal';
            objective.value = 'Décrire comment le back-office charge les données et si l’API semble exposable.';
            contextNotes.value = 'L’interface montre un tableau de bord avec un menu latéral, un tableau de données, un bouton de filtrage et plusieurs états de chargement. La partie la plus importante est le comportement des appels réseau après un clic sur une carte et sur le bouton de recherche.';

            form.querySelectorAll('input[name="signals[]"]').forEach((input, index) => {
                input.checked = index < 4;
            });

            updateSummary();
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            form.reset();
            updateSummary();
        });
    }

    updateSummary();
})();