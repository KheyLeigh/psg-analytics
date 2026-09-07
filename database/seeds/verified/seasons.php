<?php
declare(strict_types=1);
// Saisons couvertes par le projet. Une seule porte is_current à la fois (vérifié
// par le Migrator). 2025-26 : saison terminée, cinq trophées. 2026-27 : saison en
// cours, structure vide pour l'instant (voir verified/2026-27/).
return [
    ['key' => '2025-26', 'label' => '2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_current' => false],
];
