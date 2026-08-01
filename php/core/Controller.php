<?php
declare(strict_types=1);
// Contrôleur de base : rendu HTML et réponses JSON.
abstract class Controller
{
    protected function render(string $page, array $data = []): void
    {
        Response::html(View::render($page, $data));
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }
}
