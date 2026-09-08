<?php
declare(strict_types=1);
// Effectif professionnel 2026-27 (source : captures d'écran application de suivi de
// Mathis, croisées avec FBref, relevé le 8 septembre 2026). Prénom/nom des joueurs déjà
// présents en 2025-26 repris à l'identique (voir verified/2025-26/players.php) : la
// résolution people.id est une comparaison de chaînes stricte (migrator_resolve_person()),
// une graphie différente créerait une identité en double au lieu de relier les saisons.
// Format identique à verified/2025-26/players.php : [numéro, prénom, nom, poste,
// poste détaillé, nationalité, capitaine].
return [
    // [num, prénom, nom, position, position_détaillée, nationalité, capitaine]
    [30, 'Lucas', 'Chevalier', 'GK', 'GK', 'France', false],
    [39, 'Matvey', 'Safonov', 'GK', 'GK', 'Russie', false],
    [16, 'Alessandro', 'Longoni', 'GK', 'GK', 'Italie', false],
    [2,  'Achraf', 'Hakimi', 'DF', 'RB', 'Maroc', false],
    [4,  'Lucas', 'Beraldo', 'DF', 'CB', 'Brésil', false],
    [5,  'Marquinhos', '', 'DF', 'CB', 'Brésil', true],
    [6,  'Illia', 'Zabarnyi', 'DF', 'CB', 'Ukraine', false],
    [12, 'Lucas', 'Digne', 'DF', 'LB', 'France', false],
    [21, 'Lucas', 'Hernandez', 'DF', 'LB', 'France', false],
    [25, 'Nuno', 'Mendes', 'DF', 'LB', 'Portugal', false],
    [51, 'Willian', 'Pacho', 'DF', 'CB', 'Équateur', false],
    [8,  'Fabián', 'Ruiz', 'MF', 'CM', 'Espagne', false],
    [17, 'Vitinha', '', 'MF', 'DM', 'Portugal', false],
    [24, 'Senny', 'Mayulu', 'MF', 'CM', 'France', false],
    [27, 'Dro', 'Fernández', 'MF', 'AM', 'Espagne', false],
    [33, 'Warren', 'Zaïre-Emery', 'MF', 'CM', 'France', false],
    [87, 'João', 'Neves', 'MF', 'CM', 'Portugal', false],
    [7,  'Khvicha', 'Kvaratskhelia', 'FW', 'LW', 'Géorgie', false],
    [9,  'Ferran', 'Torres', 'FW', 'CF', 'Espagne', false],
    [10, 'Ousmane', 'Dembélé', 'FW', 'CF', 'France', false],
    [11, 'Maghnes', 'Akliouche', 'FW', 'RW', 'France', false],
    [14, 'Désiré', 'Doué', 'FW', 'RW', 'France', false],
    [22, 'Mika', 'Godts', 'FW', 'LW', 'Belgique', false],
    [29, 'Bradley', 'Barcola', 'FW', 'LW', 'France', false],
    [47, 'Quentin', 'Ndjantou', 'FW', 'LW', 'France', false],
    [49, 'Ibrahim', 'Mbaye', 'FW', 'RW', 'Sénégal', false],
];
