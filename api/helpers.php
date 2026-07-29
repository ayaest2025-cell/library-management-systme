<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class ApiException extends Exception
{
    public int $statusCode;

    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }
}

function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new ApiException('Invalid JSON body.', 400);
    }

    return $data;
}

function getRequestPath(): string
{
    $path = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($path, PHP_URL_PATH) ?? '/';
    $path = trim($path, '/');

    if (str_starts_with($path, 'api/')) {
        return substr($path, 4);
    }

    return $path;
}
