<?php
declare(strict_types=1);
// php/core/Controller.php
// Base commune des contrôleurs : rendu HTML via une vue, ou réponse JSON directe.
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
