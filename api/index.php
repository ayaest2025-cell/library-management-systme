<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/routes.php';

try {
    $path = getRequestPath();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    handleRoute($path, $method);
} catch (ApiException $e) {
    sendJson([
        'success' => false,
        'message' => $e->getMessage(),
        'status' => $e->statusCode,
    ], $e->statusCode);
} catch (Throwable $e) {
    $debug = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];

    error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    sendJson([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debug,
    ], 500);
}
