<?php
declare(strict_types=1);
// Écriture des fichiers seeds verified/{saison}/ pour la tâche planifiée
// d'automatisation (spec 2026-09-08). Rendu volontairement simple (var_export) :
// ces fichiers sont désormais générés à chaque passage, pas mis en forme à la
// main comme les seeds 2025-26.

// Ajoute $newRows à la fin du tableau retourné par le fichier matches_l1.php
// existant (ou en repart d'un tableau vide si le fichier est encore vide),
// et réécrit le fichier en entier. $newRows est déjà filtré par
// season_update_new_l1_matches() : cette fonction ne fait aucune déduplication.
function season_update_append_matches(string $path, array $newRows): void
{
    $existing = is_file($path) ? require $path : [];
    $all = array_merge($existing, $newRows);
    season_update_write_php_array($path, $all, 'Matchs de Ligue 1, mis à jour automatiquement (voir sources.php).');
}

// Idem pour matches_other.php.
function season_update_append_other_matches(string $path, array $newRows): void
{
    $existing = is_file($path) ? require $path : [];
    $all = array_merge($existing, $newRows);
    season_update_write_php_array($path, $all, 'Matchs hors Ligue 1, mis à jour automatiquement (voir sources.php).');
}

// players_l1_fbref.php et player_season.php portent des totaux cumulatifs
// FBref : remplacés en entier à chaque passage, jamais complétés ligne par
// ligne (une correction FBref d'un mois donné doit se répercuter, pas
// s'additionner à l'ancien total).
function season_update_replace_totals(string $path, array $totals, string $comment): void
{
    season_update_write_php_array($path, $totals, $comment);
}

// Rendu commun : un fichier PHP valide, une seule instruction return, formaté
// par var_export (lisible mais pas mis en forme à la main, à la différence des
// seeds 2025-26 écrits manuellement).
function season_update_write_php_array(string $path, array $data, string $comment): void
{
    // $comment finit dans un commentaire // sur une seule ligne : un retour à la
    // ligne, ou la balise de fermeture PHP (point d'interrogation suivi d'un
    // chevron fermant), y ferait sortir la suite du commentaire (donc du
    // fichier généré), avec le fichier PHP obtenu invalide ou, pire, du code
    // arbitraire exécuté au require (cette balise termine un commentaire //
    // même sans retour à la ligne, comportement documenté du langage : ne
    // jamais l'écrire en toutes lettres dans un commentaire // du code source
    // lui-même, comme cette explication l'illustre par nécessité). Aucun
    // appelant actuel ne passe l'un ou l'autre, mais la signature
    // (string $comment) ne le garantit pas : on neutralise les deux plutôt
    // que de faire confiance à l'appelant.
    $safeComment = str_replace(["\r\n", "\n", "\r", '?>'], [' ', ' ', ' ', '? >'], $comment);
    $php = "<?php\ndeclare(strict_types=1);\n// {$safeComment}\n"
        . '// Fichier généré automatiquement, dernière mise à jour : ' . date('Y-m-d') . ".\n"
        . 'return ' . var_export($data, true) . ";\n";
    file_put_contents($path, $php);
}

// Met à jour collected_at pour les clés de sources données (ex.
// ['fbref' => '2026-09-15']), en réécrivant sources.php en entier tout en
// conservant les autres champs de chaque source inchangés. Les clés absentes
// de $collectedAtByKey ne sont pas touchées.
function season_update_touch_sources(string $sourcesPath, array $collectedAtByKey): void
{
    $sources = require $sourcesPath;
    foreach ($sources as &$source) {
        if (isset($collectedAtByKey[$source['key']])) {
            $source['collected_at'] = $collectedAtByKey[$source['key']];
        }
    }
    unset($source);
    $php = "<?php\ndeclare(strict_types=1);\n"
        . "// Sources de données, traçabilité obligatoire (spec section 3).\n"
        . "// `key` permet une résolution stable indépendante de l'ordre d'insertion.\n"
        . "// collected_at mis à jour automatiquement par la tâche planifiée (spec 2026-09-08).\n"
        . 'return ' . var_export($sources, true) . ";\n";
    file_put_contents($sourcesPath, $php);
}
