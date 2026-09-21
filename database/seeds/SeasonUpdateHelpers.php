<?php
declare(strict_types=1);
// Fonctions déterministes utilisées par la tâche planifiée d'automatisation de
// la collecte (spec 2026-09-08) : comparent des données fraîchement scrappées
// aux seeds déjà présents. Purement fonctionnelles, aucun accès réseau ni
// fichier ici (l'écriture vit dans SeasonUpdateWriters.php) : testables sur
// des tableaux synthétiques, sans dépendre de FBref/Understat.

// Identité d'un match de Ligue 1 : la journée seule. Elle est unique sur une
// saison (J1 à J34), à la différence de la date, qui bouge quand un match est
// reporté. Prendre la date dans la clé dupliquerait la ligne au lieu de la
// corriger.
function season_update_l1_match_key(array $row): string
{
    return (string) $row[0];
}

// Identité d'un match hors Ligue 1 : compétition + tour + lieu + adversaire.
// Le lieu est indispensable pour séparer l'aller du retour d'un même tour à
// élimination directe (même tour, même adversaire). La date en est exclue pour
// la même raison qu'en Ligue 1 : elle doit rester corrigeable.
function season_update_other_match_key(array $row): string
{
    return $row[0] . '|' . $row[1] . '|' . $row[3] . '|' . $row[4];
}

// Noms des champs des deux formats de tuple, dans l'ordre : servent à nommer
// les champs corrigés dans le journal renvoyé par les fonctions de
// rafraîchissement, pour que le rapport hebdomadaire soit lisible.
const SEASON_UPDATE_L1_FIELDS = [
    'round', 'date', 'adversaire', 'domicile', 'buts_psg', 'buts_adv', 'affluence', 'possession',
];
const SEASON_UPDATE_OTHER_FIELDS = [
    'competition', 'round', 'date', 'lieu', 'adversaire', 'buts_psg', 'buts_adv',
    'possession', 'affluence', 'prolongation', 'tirs_au_but', 'score_tab',
];

// Renvoie les lignes de $scraped absentes de $existing, même format tuple que
// matches_l1.php : [round_label, date, adversaire, est_domicile, buts_psg,
// buts_adv, affluence, possession].
function season_update_new_l1_matches(array $scraped, array $existing): array
{
    return season_update_new_matches($scraped, $existing, 'season_update_l1_match_key');
}

// Idem pour matches_other.php, format tuple [competition_key, round_label,
// date, venue, adversaire, buts_psg, buts_adv, possession, affluence,
// prolongation, tirs_au_but, score_tab].
function season_update_new_other_matches(array $scraped, array $existing): array
{
    return season_update_new_matches($scraped, $existing, 'season_update_other_match_key');
}

// Met à jour en place les lignes de $existing déjà connues à partir du relevé
// $scraped, sans jamais en ajouter ni en retirer (l'ajout reste le travail de
// season_update_new_l1_matches). FBref complète ou corrige régulièrement un
// match après coup : affluence publiée avec plusieurs jours de retard,
// possession revue, séance de tirs au but renseignée. Renvoie
// ['rows' => lignes fusionnées, 'corrections' => journal des champs modifiés].
function season_update_refresh_l1_matches(array $scraped, array $existing): array
{
    return season_update_refresh_matches(
        $scraped,
        $existing,
        'season_update_l1_match_key',
        SEASON_UPDATE_L1_FIELDS,
        static fn (array $row): string => "{$row[0]} {$row[2]}",
    );
}

// Idem pour matches_other.php.
function season_update_refresh_other_matches(array $scraped, array $existing): array
{
    return season_update_refresh_matches(
        $scraped,
        $existing,
        'season_update_other_match_key',
        SEASON_UPDATE_OTHER_FIELDS,
        static fn (array $row): string => "{$row[0]} {$row[1]} {$row[4]}",
    );
}

// Corps commun des deux détections d'ajout : $key donne l'identité d'une ligne.
function season_update_new_matches(array $scraped, array $existing, callable $key): array
{
    $seen = [];
    foreach ($existing as $row) {
        $seen[$key($row)] = true;
    }
    $new = [];
    foreach ($scraped as $row) {
        if (!isset($seen[$key($row)])) {
            $new[] = $row;
        }
    }
    return $new;
}

// Corps commun des deux rafraîchissements. Règle de fusion, champ par champ :
// une valeur scrappée non nulle et différente remplace l'ancienne et part au
// journal ; une valeur scrappée nulle est ignorée. Ce dernier point est
// volontaire, la source omet parfois un champ qu'elle avait déjà publié (ou
// l'inverse) : un relevé incomplet ne doit jamais effacer une donnée vérifiée.
function season_update_refresh_matches(array $scraped, array $existing, callable $key, array $fields, callable $label): array
{
    $bySource = [];
    foreach ($scraped as $row) {
        $bySource[$key($row)] = $row;
    }

    $corrections = [];
    foreach ($existing as $i => $row) {
        $source = $bySource[$key($row)] ?? null;
        if ($source === null) {
            continue;
        }
        foreach ($fields as $index => $field) {
            $valeur = $source[$index] ?? null;
            if ($valeur === null || $valeur === $row[$index]) {
                continue;
            }
            $corrections[] = [
                'match' => $label($row),
                'champ' => $field,
                'avant' => $row[$index],
                'apres' => $valeur,
            ];
            $existing[$i][$index] = $valeur;
        }
    }

    return ['rows' => $existing, 'corrections' => $corrections];
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
