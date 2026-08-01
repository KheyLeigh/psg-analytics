<?php
declare(strict_types=1);
// php/core/Response.php
// Fabrique les réponses HTTP (JSON ou HTML) et l'enveloppe standard de l'API.
final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    public static function apiEnvelope(array $data, ?array $meta = null): array
    {
        return [
            'data'   => $data,
            'meta'   => $meta ?? [],
            'source' => ['generated_at' => date('c')],
        ];
    }
}
