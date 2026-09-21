<?php
declare(strict_types=1);
// Correspondance identifiant joueur FotMob vers identifiant joueur PSG Analytics
// (table players, saison 2026-27). Relevé le 21 septembre 2026 sur les pages de
// match FotMob.
//
// Pourquoi passer par un identifiant et non par le nom : FotMob orthographie le
// même joueur différemment d'un match à l'autre (Ferran Torres en J1, Ferrán
// Torres en J2 et en C1). Une résolution par nom scinderait un joueur en deux
// identités, exactement le défaut que players.php cherche à éviter avec sa
// comparaison de chaînes stricte. L'identifiant FotMob, lui, est stable.
//
// À compléter à chaque nouveau tireur (recrue, jeune lancé en cours de saison) :
// un tireur sans correspondance ici est écarté ET signalé par
// fotmob_build_shotmap(), jamais rattaché au hasard.
return [
    // playerId FotMob => id joueur en base
    267365  => 30, // Marquinhos
    1137670 => 35, // Willian Pacho
    770881  => 28, // Achraf Hakimi
    1158945 => 34, // Nuno Mendes
    210111  => 32, // Lucas Digne
    1061249 => 37, // Vitinha
    605654  => 36, // Fabián Ruiz
    1342757 => 41, // João Neves
    1367615 => 40, // Warren Zaïre-Emery
    1574189 => 38, // Senny Mayulu
    1682313 => 39, // Dro Fernández
    692984  => 44, // Ousmane Dembélé
    881771  => 43, // Ferran Torres
    900433  => 42, // Khvicha Kvaratskhelia
    1339208 => 46, // Désiré Doué
    1305618 => 45, // Maghnes Akliouche
    1396372 => 47, // Mika Godts
];
