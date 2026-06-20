<?php
declare(strict_types=1);

$siteUrl = trim($_POST['site_url'] ?? '');
$siteType = $_POST['site_type'] ?? 'dashboard';
$objective = trim($_POST['objective'] ?? '');
$selectedSignals = $_POST['signals'] ?? [];
$uploadCount = isset($_FILES['evidence_files']) ? count(array_filter($_FILES['evidence_files']['name'] ?? [])) : 0;

$signalsCatalog = [
		'network' => ['label' => 'Onglet réseau', 'hint' => 'captures des requêtes, XHR, fetch, websocket'],
		'html' => ['label' => 'HTML source', 'hint' => 'code HTML rendu ou extrait d’éléments clés'],
		'headers' => ['label' => 'Headers réponse', 'hint' => 'status, cache, auth, set-cookie, cors'],
		'js' => ['label' => 'JavaScript', 'hint' => 'fichiers front, bundles, endpoints visibles'],
		'cookies' => ['label' => 'Cookies / storage', 'hint' => 'session, csrf, localStorage, tokens'],
		'sitemap' => ['label' => 'Plan du site', 'hint' => 'arborescence, pages, navigation, routes'],
];

$workflowSteps = [
		[
				'title' => 'Contexte initial',
				'body' => 'L’utilisateur décrit le site, le type d’interface et l’objectif d’analyse.',
		],
		[
				'title' => 'Collecte guidée',
				'body' => 'L’IA demande les signaux utiles un par un pour réduire les ambiguïtés.',
		],
		[
				'title' => 'Construction du contexte',
				'body' => 'Les preuves sont structurées pour déduire les routes, les appels API et les protections.',
		],
		[
				'title' => 'Verdict',
				'body' => 'Le système estime si le backend semble exploitable, avec un niveau de confiance et les limites.',
		],
];

$assistantPrompts = [
		'Montre-moi le premier flux visible dès l’ouverture de la page.',
		'Quels appels réseau apparaissent quand tu cliques sur le bouton principal ?',
		'Quels headers sont renvoyés sur les requêtes les plus importantes ?',
		'Y a-t-il un token CSRF, un cookie de session ou une authentification implicite ?',
];

$riskMatrix = [
		['label' => 'API publique ou semi-publique', 'score' => 82, 'tone' => 'high'],
		['label' => 'Authentification forte / tokens', 'score' => 41, 'tone' => 'medium'],
		['label' => 'End points cachés dans le JS', 'score' => 73, 'tone' => 'high'],
		['label' => 'Protection anti-bot / rate limit', 'score' => 58, 'tone' => 'medium'],
];

function e(string $value): string
{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function selected(string $key, array $values): string
{
		return in_array($key, $values, true) ? 'checked' : '';
}

function scoreFromSignals(array $signals, string $objective, string $siteUrl): int
{
		$score = 38;

		$score += min(18, count($signals) * 4);
		$score += $objective !== '' ? 12 : 0;
		$score += $siteUrl !== '' ? 8 : 0;

		return max(0, min(100, $score));
}

$confidence = scoreFromSignals($selectedSignals, $objective, $siteUrl);
$readiness = 'Probabilité faible ou trop d’opacité pour conclure';
if ($confidence >= 75) {
	$readiness = 'Bonne probabilité de reconstruction du backend';
} elseif ($confidence >= 55) {
	$readiness = 'Probabilité moyenne, besoin d’indices supplémentaires';
}

?><!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>SAE Scrap Assistant — Analyse guidée du backend</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
	<link rel="stylesheet" href="../assets/css/scrap.css" />
</head>
<body class="scrap-page">
	<main class="scrap-shell">
		<section class="hero-panel">
			<div class="hero-copy">
				<div class="eyebrow">Assistant d’exploration backend</div>
				<h1>Déduire la structure d’une API à partir de preuves visuelles et textuelles.</h1>
				<p>
					Cette interface ne lance aucun scraping. Elle guide l’utilisateur, collecte des indices,
					puis construit un contexte exploitable pour une API d’intelligence artificielle qui
					estime comment le backend fonctionne et si une reproduction technique paraît réaliste.
				</p>

				<div class="hero-actions">
					<a href="#analysis-form" class="btn-primary-strong"><i class="bi bi-stars"></i> Démarrer l’analyse</a>
					<button type="button" class="btn-primary-soft" data-insert-demo><i class="bi bi-magic"></i> Injecter un exemple</button>
				</div>

				<div class="hero-kpis">
					<article>
						<span>Contexte construit</span>
						<strong><?= count($signalsCatalog) ?> blocs</strong>
					</article>
					<article>
						<span>Probabilité actuelle</span>
						<strong><?= $confidence ?>%</strong>
					</article>
					<article>
						<span>État</span>
						<strong><?= e($readiness) ?></strong>
					</article>
				</div>
			</div>

			<aside class="hero-side card-panel">
				<div class="panel-title-row">
					<h2>Mode de fonctionnement</h2>
					<span class="chip chip-accent">Guidé par IA</span>
				</div>
				<ol class="workflow-list">
					<?php foreach ($workflowSteps as $step): ?>
						<li>
							<strong><?= e($step['title']) ?></strong>
							<p><?= e($step['body']) ?></p>
						</li>
					<?php endforeach; ?>
				</ol>
			</aside>
		</section>

		<section class="workspace-grid">
			<form id="analysis-form" class="card-panel analysis-form" method="post" enctype="multipart/form-data">
				<div class="panel-title-row">
					<h2>Entrées utilisateur</h2>
					<span class="chip">Étape 1</span>
				</div>

				<div class="field-group">
					<label for="site_url">URL du site à analyser</label>
					<input type="url" id="site_url" name="site_url" placeholder="https://exemple.com" value="<?= e($siteUrl) ?>" />
					<small>On ne visite pas le site automatiquement. L’URL sert à contextualiser l’analyse.</small>
				</div>

				<div class="grid-2">
					<div class="field-group">
						<label for="site_type">Type d’interface</label>
						<select id="site_type" name="site_type">
							<option value="dashboard" <?= $siteType === 'dashboard' ? 'selected' : '' ?>>Dashboard / application web</option>
							<option value="ecommerce" <?= $siteType === 'ecommerce' ? 'selected' : '' ?>>E-commerce</option>
							<option value="portal" <?= $siteType === 'portal' ? 'selected' : '' ?>>Portail métier</option>
							<option value="public" <?= $siteType === 'public' ? 'selected' : '' ?>>Site public / vitrine</option>
							<option value="api" <?= $siteType === 'api' ? 'selected' : '' ?>>API ou back-office</option>
						</select>
					</div>

					<div class="field-group">
						<label for="objective">Objectif principal</label>
						<input type="text" id="objective" name="objective" placeholder="Expliquer le backend, trouver les endpoints, mesurer la faisabilité" value="<?= e($objective) ?>" />
					</div>
				</div>

				<div class="field-group">
					<label>Fichiers / captures visibles</label>
					<div class="upload-box">
						<input type="file" id="evidence_files" name="evidence_files[]" multiple accept="image/*,.pdf,.txt,.json,.har,.csv" />
						<p>Ajoute des captures, extraits HAR, fichiers JSON, logs ou documents que l’utilisateur voit à l’écran.</p>
						<div class="upload-meta">
							<span><i class="bi bi-paperclip"></i> <strong id="file-count"><?= $uploadCount ?></strong> fichier(s) détecté(s)</span>
							<span><i class="bi bi-shield-check"></i> Aucun transfert automatique côté front</span>
						</div>
					</div>
				</div>

				<div class="field-group">
					<label>Signaux à fournir à l’IA</label>
					<div class="signals-grid">
						<?php foreach ($signalsCatalog as $key => $signal): ?>
							<label class="signal-card">
								<input type="checkbox" name="signals[]" value="<?= e($key) ?>" <?= selected($key, $selectedSignals) ?> />
								<span>
									<strong><?= e($signal['label']) ?></strong>
									<small><?= e($signal['hint']) ?></small>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="field-group">
					<label for="context_notes">Description principale de ce que vous voyez</label>
					<textarea id="context_notes" name="context_notes" rows="8" placeholder="Décris exactement la page, les boutons, les actions possibles, les éléments qui changent, les indices d’authentification, et tout ce qui semble utile."><?= e(trim($_POST['context_notes'] ?? '')) ?></textarea>
					<small>C’est le champ le plus important. Il sert de base au contexte construit par l’IA.</small>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn-primary-strong"><i class="bi bi-robot"></i> Générer le contexte IA</button>
					<button type="button" class="btn-primary-soft" data-reset-form><i class="bi bi-arrow-counterclockwise"></i> Réinitialiser</button>
				</div>
			</form>

			<aside class="sidebar-stack">
				<section class="card-panel assistant-panel">
					<div class="panel-title-row">
						<h2>Questions de l’assistant</h2>
						<span class="chip chip-success">Interaction</span>
					</div>
					<div class="assistant-thread">
						<?php foreach ($assistantPrompts as $index => $prompt): ?>
							<article class="message message-assistant">
								<span class="message-index">0<?= $index + 1 ?></span>
								<p><?= e($prompt) ?></p>
							</article>
						<?php endforeach; ?>
						<article class="message message-user" id="live-preview-message">
							<span class="message-index">Vous</span>
							<p id="live-description-preview"><?= $objective !== '' ? e($objective) : 'La description principale s’affichera ici en temps réel.' ?></p>
						</article>
					</div>
				</section>

				<section class="card-panel verdict-panel">
					<div class="panel-title-row">
						<h2>Déduction automatique</h2>
						<span class="chip chip-warning">Estimation</span>
					</div>

					<div class="verdict-score">
						<div>
							<span>Confiance estimée</span>
							<strong id="confidence-score"><?= $confidence ?>%</strong>
						</div>
						<div class="score-bar" aria-hidden="true"><span style="width: <?= $confidence ?>%"></span></div>
					</div>

					<p class="verdict-text" id="verdict-text"><?= e($readiness) ?></p>
					<ul class="verdict-list">
						<li><i class="bi bi-check2-circle"></i> Contexte structuré pour une API d’analyse</li>
						<li><i class="bi bi-diagram-3"></i> Hypothèses de routes et d’authentification</li>
						<li><i class="bi bi-lock"></i> Signaux de protection ou d’opacité</li>
					</ul>
				</section>
			</aside>
		</section>

		<section class="insight-grid">
			<article class="card-panel insight-panel">
				<div class="panel-title-row">
					<h2>Contexte IA généré</h2>
					<span class="chip">Synthèse</span>
				</div>
				<div class="context-preview" id="context-preview">
					<p><strong>URL:</strong> <span id="preview-url"><?= $siteUrl !== '' ? e($siteUrl) : 'Non renseignée' ?></span></p>
					<p><strong>Type:</strong> <span id="preview-type"><?= e($siteType) ?></span></p>
					<p><strong>Objectif:</strong> <span id="preview-objective"><?= $objective !== '' ? e($objective) : 'Aucun objectif fourni pour le moment.' ?></span></p>
					<p><strong>Signaux:</strong> <span id="preview-signals"><?= !empty($selectedSignals) ? e(implode(', ', array_map('strval', $selectedSignals))) : 'Aucun signal sélectionné' ?></span></p>
					<p><strong>Fichiers:</strong> <span id="preview-files"><?= $uploadCount ?> fichier(s)</span></p>
				</div>
			</article>

			<article class="card-panel insight-panel">
				<div class="panel-title-row">
					<h2>Indices techniques</h2>
					<span class="chip chip-accent">Analyse</span>
				</div>
				<div class="risk-list">
					<?php foreach ($riskMatrix as $risk): ?>
						<div class="risk-item risk-<?= e($risk['tone']) ?>">
							<div>
								<strong><?= e($risk['label']) ?></strong>
								<div class="score-bar thin"><span style="width: <?= (int) $risk['score'] ?>%"></span></div>
							</div>
							<span><?= (int) $risk['score'] ?>%</span>
						</div>
					<?php endforeach; ?>
				</div>
			</article>
		</section>

		<section class="card-panel api-playbook">
			<div class="panel-title-row">
				<h2>Stratégie de collecte recommandée</h2>
				<span class="chip chip-success">Plan de dialogue</span>
			</div>
			<div class="playbook-grid">
				<div>
					<h3>Ce que l’IA doit demander</h3>
					<ul>
						<li>Le cheminement exact quand l’utilisateur clique sur les zones importantes.</li>
						<li>Les requêtes réseau observées dans l’onglet DevTools.</li>
						<li>Les headers de réponse, les cookies, les jetons et les statuts HTTP.</li>
						<li>Les fichiers JavaScript qui révèlent les routes internes ou les clés de configuration.</li>
						<li>Les captures de vues clés pour comprendre les comportements dynamiques.</li>
					</ul>
				</div>
				<div>
					<h3>Ce que l’IA doit produire</h3>
					<ul>
						<li>Une cartographie du backend probable, par module ou par domaine fonctionnel.</li>
						<li>Une liste d’endpoints supposés avec méthode, rôle et niveau de confiance.</li>
						<li>Un verdict sur la possibilité d’automatiser un accès ou une reproduction.</li>
						<li>Les zones d’incertitude et les preuves supplémentaires à demander.</li>
						<li>Un résumé final lisible pour un utilisateur non technique.</li>
					</ul>
				</div>
			</div>
		</section>
	</main>

	<script src="../assets/js/scrap.js"></script>
</body>
</html>
