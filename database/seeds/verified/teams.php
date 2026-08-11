<?php
declare(strict_types=1);
// PSG, les 17 adversaires de Ligue 1 2025-26 (double round-robin) et les
// 12 adversaires additionnels rencontrés en coupes/Ligue des Champions
// (source fbref, préfixe pays retiré). `name` reprend exactement la valeur
// `adversaire` utilisée dans matches_l1.php / matches_other.php pour
// permettre une résolution directe par nom.
return [
    ['name' => 'Paris Saint-Germain', 'short_name' => 'PSG',  'country' => 'France', 'is_psg' => true],
    ['name' => 'Nantes',              'short_name' => 'FCN',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Angers',              'short_name' => 'SCO',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Toulouse',            'short_name' => 'TFC',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Lens',                'short_name' => 'RCL',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Marseille',           'short_name' => 'OM',   'country' => 'France', 'is_psg' => false],
    ['name' => 'Auxerre',             'short_name' => 'AJA',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Lille',               'short_name' => 'LOSC', 'country' => 'France', 'is_psg' => false],
    ['name' => 'Strasbourg',          'short_name' => 'RCS',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Brest',               'short_name' => 'SB29', 'country' => 'France', 'is_psg' => false],
    ['name' => 'Lorient',             'short_name' => 'FCL',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Nice',                'short_name' => 'OGCN', 'country' => 'France', 'is_psg' => false],
    ['name' => 'Lyon',                'short_name' => 'OL',   'country' => 'France', 'is_psg' => false],
    ['name' => 'Le Havre',            'short_name' => 'HAC',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Monaco',              'short_name' => 'ASM',  'country' => 'Monaco', 'is_psg' => false],
    ['name' => 'Rennes',              'short_name' => 'SRFC', 'country' => 'France', 'is_psg' => false],
    ['name' => 'Metz',                'short_name' => 'FCM',  'country' => 'France', 'is_psg' => false],
    ['name' => 'Paris FC',            'short_name' => 'PFC',  'country' => 'France', 'is_psg' => false],
    // Adversaires additionnels (Supercoupe UEFA, Ligue des Champions, Coupe de France)
    ['name' => 'Tottenham',              'short_name' => 'TOT', 'country' => 'Angleterre', 'is_psg' => false],
    ['name' => 'Atalanta',               'short_name' => 'ATA', 'country' => 'Italie',     'is_psg' => false],
    ['name' => 'Barcelona',              'short_name' => 'BAR', 'country' => 'Espagne',    'is_psg' => false],
    ['name' => 'Leverkusen',             'short_name' => 'B04', 'country' => 'Allemagne',  'is_psg' => false],
    ['name' => 'Bayern Munich',          'short_name' => 'BAY', 'country' => 'Allemagne',  'is_psg' => false],
    ['name' => 'Athletic Club',          'short_name' => 'ATH', 'country' => 'Espagne',    'is_psg' => false],
    ['name' => 'Vendée Fontenay Foot',   'short_name' => 'VFF', 'country' => 'France',     'is_psg' => false],
    ['name' => 'Sporting CP',            'short_name' => 'SCP', 'country' => 'Portugal',   'is_psg' => false],
    ['name' => 'Newcastle',              'short_name' => 'NEW', 'country' => 'Angleterre', 'is_psg' => false],
    ['name' => 'Chelsea',                'short_name' => 'CHE', 'country' => 'Angleterre', 'is_psg' => false],
    ['name' => 'Liverpool',              'short_name' => 'LIV', 'country' => 'Angleterre', 'is_psg' => false],
    ['name' => 'Arsenal',                'short_name' => 'ARS', 'country' => 'Angleterre', 'is_psg' => false],
];
