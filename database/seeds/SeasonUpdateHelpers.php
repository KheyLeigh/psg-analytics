<?php
declare(strict_types=1);
// Fonctions déterministes utilisées par la tâche planifiée d'automatisation de
// la collecte (spec 2026-09-08) : comparent des données fraîchement scrappées
// aux seeds déjà présents. Purement fonctionnelles, aucun accès réseau ni
// fichier ici (l'écriture vit dans SeasonUpdateWriters.php) : testables sur
// des tableaux synthétiques, sans dépendre de FBref/Understat.

// Renvoie les lignes de $scraped absentes de $existing (comparaison par
// round_label + adversaire + date), même format tuple que matches_l1.php :
// [round_label, date, adversaire, est_domicile, buts_psg, buts_adv, affluence, possession].
function season_update_new_l1_matches(array $scraped, array $existing): array
{
    $seen = [];
    foreach ($existing as $row) {
        $seen[$row[0] . '|' . $row[2] . '|' . $row[1]] = true;
    }
    $new = [];
    foreach ($scraped as $row) {
        $key = $row[0] . '|' . $row[2] . '|' . $row[1];
        if (!isset($seen[$key])) {
            $new[] = $row;
        }
    }
    return $new;
}

// Idem pour matches_other.php, format tuple [competition_key, round_label,
// date, venue, adversaire, buts_psg, buts_adv, possession, affluence,
// prolongation, tirs_au_but, score_tab]. Comparaison par competition_key +
// round_label + date.
function season_update_new_other_matches(array $scraped, array $existing): array
{
    $seen = [];
    foreach ($existing as $row) {
        $seen[$row[0] . '|' . $row[1] . '|' . $row[2]] = true;
    }
    $new = [];
    foreach ($scraped as $row) {
        $key = $row[0] . '|' . $row[1] . '|' . $row[2];
        if (!isset($seen[$key])) {
            $new[] = $row;
        }
    }
    return $new;
}

// Compare l'effectif scrappé (liste de ['first' => prénom, 'last' => nom]) à
// players.php existant (tuples [num, prénom, nom, poste, poste_détaillé,
// nationalité, capitaine]). Renvoie ['nouveaux' => [...noms...], 'absents' =>
// [...noms...]]. Résolution par nom/prénom exact, comme migrator_resolve_person :
// aucune normalisation, un écart est toujours signalé à Mathis, jamais résolu
// automatiquement.
function season_update_roster_diff(array $scrapedRoster, array $existingPlayers): array
{
    $scrapedKeys = [];
    foreach ($scrapedRoster as $p) {
        $displayName = $p['last'] !== '' ? $p['last'] : $p['first'];
        $scrapedKeys[$p['first'] . '|' . $p['last']] = $displayName;
    }
    $existingKeys = [];
    foreach ($existingPlayers as $p) {
        [, $first, $last] = $p;
        $displayName = $last !== '' ? $last : $first;
        $existingKeys[$first . '|' . $last] = $displayName;
    }
    return [
        'nouveaux' => array_values(array_diff_key($scrapedKeys, $existingKeys)),
        'absents'  => array_values(array_diff_key($existingKeys, $scrapedKeys)),
    ];
}

// Compare les noms de compétitions rencontrées au calendrier scrappé au
// catalogue compétitions connu (valeurs 'name' de competitions.php). Renvoie
// la liste des noms de compétitions inconnues (à créer manuellement par Mathis).
function season_update_unknown_competitions(array $scrapedCompetitionNames, array $knownCompetitions): array
{
    $known = array_map(static fn (array $c): string => $c['name'], $knownCompetitions);
    return array_values(array_diff($scrapedCompetitionNames, $known));
}
