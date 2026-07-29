<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/routes.php';

try {
    $path = getRequestPath();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    handleRoute($path, $method);
} catch (ApiException $e) {
    sendJson(['success' => false, 'message' => $e->getMessage()], $e->statusCode);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Internal server error.'], 500);
}
