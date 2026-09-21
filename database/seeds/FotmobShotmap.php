<?php
declare(strict_types=1);
// Conversion des tirs FotMob vers le format shotmap du projet (spec 2026-09-21).
// Remplace la collecte Understat, dont les pages n'exposent plus aucune donnée
// embarquée depuis septembre 2026. Purement fonctionnel : aucun accès réseau ni
// fichier, le relevé brut est fourni par l'appelant.
//
// Un tir FotMob brut est un tuple, dans l'ordre :
// [playerId, playerName, x, y, expectedGoals, eventType, isBlocked, min,
//  situation, shotType].

// Terrain FotMob en mètres. Les coordonnées sont normalisées dans le sens de
// l'attaque pour les deux équipes, comme Understat : x proche de 105 = but
// adverse. On les ramène en 0-1, repère attendu par assets/js/charts/shotmap.js.
const FOTMOB_PITCH_LENGTH = 105.0;
const FOTMOB_PITCH_WIDTH  = 68.0;

// Résultat d'un tir, vocabulaire du projet : goal, saved, miss, block, post.
// Un tir contré arrive chez FotMob en AttemptSaved avec isBlocked à vrai : sans
// ce drapeau, les contres seraient comptés comme des arrêts du gardien.
function fotmob_shot_result(string $eventType, bool $isBlocked): string
{
    if ($eventType === 'AttemptSaved') {
        return $isBlocked ? 'block' : 'saved';
    }
    return match ($eventType) {
        'Goal'         => 'goal',
        'Post'         => 'post',
        'Miss'         => 'miss',
        'OwnGoal'      => 'own_goal',
        default        => strtolower($eventType),
    };
}

// Situation de jeu, ramenée au vocabulaire Understat déjà en base pour que les
// deux saisons restent comparables. Les situations que FotMob distingue en plus
// (contre-attaque notamment) sont conservées telles quelles : les perdre
// appauvrirait la donnée sans rien simplifier.
function fotmob_shot_situation(string $situation): string
{
    return match ($situation) {
        'RegularPlay'     => 'OpenPlay',
        'FreeKick'        => 'DirectFreekick',
        'ThrowInSetPiece' => 'SetPiece',
        default           => $situation,
    };
}

// Coordonnées ramenées en 0-1. x : 0 = ligne de but propre, 1 = but adverse.
// y : 0 a 1 en largeur.
function fotmob_normalise_coords(float $x, float $y): array
{
    return [
        round($x / FOTMOB_PITCH_LENGTH, 4),
        round($y / FOTMOB_PITCH_WIDTH, 4),
    ];
}

// Construit la structure shotmap complète à partir des matchs relevés.
//
// $matches : liste de ['comp' => 'Ligue 1', 'opp' => 'Rennes', 'ha' => 'a',
//            'shots' => [tuples bruts]], dans l'ordre chronologique.
// $players : [playerId FotMob => ['id' => id joueur PSG Analytics,
//            'name' => nom canonique en base]]. Le nom vient de notre base et
//            jamais de FotMob : la source orthographie le même joueur de deux
//            façons d'un match à l'autre (Ferran et Ferrán Torres), ce qui
//            scinderait un joueur en deux si on s'y fiait.
// $meta    : champs de tête du fichier ('season', 'competition',
//            'competitions', 'source', 'note').
//
// Renvoie ['shotmap' => structure prête à sérialiser, 'inconnus' => tireurs
// sans correspondance en base]. Les tirs inconnus ne sont jamais rattachés
// d'office à un joueur : ils sont écartés ET signalés, pour que l'anomalie
// remonte au lieu de polluer silencieusement la saison.
function fotmob_build_shotmap(array $matches, array $players, array $meta): array
{
    $parJoueur = [];
    $inconnus = [];

    foreach ($matches as $match) {
        foreach ($match['shots'] as $tir) {
            [$fotmobId, $nomSource, $x, $y, $xg, $eventType, $isBlocked, $min, $situation] = $tir;

            if (!isset($players[$fotmobId])) {
                $inconnus[$fotmobId] = $nomSource;
                continue;
            }
            [$nx, $ny] = fotmob_normalise_coords((float) $x, (float) $y);
            $cle = (string) $players[$fotmobId]['id'];

            $parJoueur[$cle] ??= [
                'name'        => $players[$fotmobId]['name'],
                'shots_total' => 0,
                'goals'       => 0,
                'xg_total'    => 0.0,
                'shots'       => [],
            ];
            $res = fotmob_shot_result((string) $eventType, (bool) $isBlocked);
            $parJoueur[$cle]['shots_total']++;
            $parJoueur[$cle]['xg_total'] += (float) $xg;
            if ($res === 'goal') {
                $parJoueur[$cle]['goals']++;
            }
            $parJoueur[$cle]['shots'][] = [
                'x'    => $nx,
                'y'    => $ny,
                'xg'   => round((float) $xg, 4),
                'res'  => $res,
                'min'  => (int) $min,
                'sit'  => fotmob_shot_situation((string) $situation),
                'opp'  => $match['opp'],
                'ha'   => $match['ha'],
                'comp' => $match['comp'],
            ];
        }
    }

    foreach ($parJoueur as &$joueur) {
        $joueur['xg_total'] = round($joueur['xg_total'], 2);
    }
    unset($joueur);

    // Tri par volume de tirs décroissant : le fichier reste lisible à l'oeil et
    // l'ordre ne dépend pas de qui a tiré en premier.
    uasort($parJoueur, static fn (array $a, array $b): int => $b['shots_total'] <=> $a['shots_total']);

    return [
        'shotmap'  => $meta + ['players' => $parJoueur],
        'inconnus' => $inconnus,
    ];
}
