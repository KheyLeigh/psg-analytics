<?php
declare(strict_types=1);
// Table de routage déclarative : méthode+chemin → contrôleur/action.
return [
    'GET /' => ['HomeController', 'index'],

    'GET /api/players' => ['PlayerApiController', 'index'],
    'GET /api/players/{id}' => ['PlayerApiController', 'show'],
    'GET /api/players/{id}/timeline' => ['PlayerApiController', 'timeline'],
    'GET /api/compare' => ['PlayerApiController', 'compare'],

    'GET /api/kpis' => ['StatsApiController', 'kpis'],
    'GET /api/distribution' => ['StatsApiController', 'distribution'],
    'GET /api/heatmap' => ['StatsApiController', 'heatmap'],
];
