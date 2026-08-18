<?php
declare(strict_types=1);
// Table de routage déclarative : méthode+chemin → contrôleur/action.
return [
    'GET /' => ['HomeController', 'index'],
    'GET /dashboard' => ['DashboardController', 'index'],
    'GET /joueurs' => ['PlayerController', 'index'],
    'GET /joueurs/{id}' => ['PlayerController', 'show'],
    'GET /matchs' => ['MatchController', 'index'],
    'GET /matchs/{id}' => ['MatchController', 'show'],
    'GET /styleguide' => ['StyleguideController', 'index'],

    'GET /api/players' => ['PlayerApiController', 'index'],
    'GET /api/players/{id}' => ['PlayerApiController', 'show'],
    'GET /api/players/{id}/timeline' => ['PlayerApiController', 'timeline'],
    'GET /api/compare' => ['PlayerApiController', 'compare'],

    'GET /api/kpis' => ['StatsApiController', 'kpis'],
    'GET /api/distribution' => ['StatsApiController', 'distribution'],
    'GET /api/heatmap' => ['StatsApiController', 'heatmap'],

    'GET /api/matches' => ['MatchApiController', 'index'],
    'GET /api/matches/{id}' => ['MatchApiController', 'show'],

    'GET /api/competitions' => ['CompetitionApiController', 'index'],

    'GET /api/export/players.csv' => ['ExportApiController', 'playersCsv'],
    'GET /api/export/report.pdf' => ['ExportApiController', 'reportPdf'],
];
