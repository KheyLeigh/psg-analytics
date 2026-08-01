<?php
declare(strict_types=1);
// Top buteurs et passeurs vérifiés en Ligue 1 (spec section 3).
// Clés = nom de famille tel qu'il apparaît dans players.php (ou prénom
// quand le nom de famille est vide, ex. Vitinha).
return [
    'goals' => [
        'Barcola'       => ['goals' => 11, 'apps' => 29],
        'Dembélé'       => ['goals' => 10, 'apps' => 22],
        'Kvaratskhelia' => ['goals' => 8,  'apps' => 28],
        'Doué'          => ['goals' => 7,  'apps' => 23],
        'Ramos'         => ['goals' => 6,  'apps' => 30],
    ],
    // Dembélé totalise 20 buts toutes compétitions (hors périmètre L1 ici).
    'assists' => [
        'Vitinha' => 7,
        'Dembélé' => 7,
        'Mendes'  => 5,
    ],
];
