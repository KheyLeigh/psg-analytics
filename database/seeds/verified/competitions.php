<?php
declare(strict_types=1);
// Les 6 compétitions du palmarès 2025-26 (spec section 3).
// `key` permet une résolution stable indépendante de l'ordre d'insertion.
return [
    ['key' => 'ligue1',        'name' => 'Ligue 1',                  'type' => 'league',           'scope' => 'domestic'],
    ['key' => 'ldc',           'name' => 'Ligue des Champions',      'type' => 'cup',               'scope' => 'european'],
    ['key' => 'trophee',       'name' => 'Trophée des Champions',    'type' => 'super_cup',         'scope' => 'domestic'],
    ['key' => 'supercoupe',    'name' => 'Supercoupe UEFA',          'type' => 'super_cup',         'scope' => 'european'],
    ['key' => 'intercontinent','name' => 'Coupe Intercontinentale',  'type' => 'intercontinental',  'scope' => 'world'],
    ['key' => 'coupe_france',  'name' => 'Coupe de France',          'type' => 'cup',               'scope' => 'domestic'],
];
