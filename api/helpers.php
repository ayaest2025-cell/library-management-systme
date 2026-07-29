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

    $segments = explode('/', $path);
    $apiIndex = array_search('api', $segments, true);

    if ($apiIndex !== false) {
        $tail = array_slice($segments, $apiIndex + 1);
        if (($tail[0] ?? '') === 'index.php') {
            $tail = array_slice($tail, 1);
        }
        return implode('/', $tail);
    }

    return $path;
}

/** Upload a book cover image from a multipart upload. Returns the relative path or null. */
function uploadBookCover(array $file, ?string &$error): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Upload a valid cover image smaller than 5 MB.';
        return null;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
        $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $error = 'Unable to create the cover upload folder.';
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$imageInfo[2]];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
        $error = 'Unable to upload the cover image.';
        return null;
    }

    return 'uploads/' . $fileName;
}

/** Remove a cover only when it is an application-managed upload. */
function deleteBookCover(?string $coverImage): void
{
    if (!$coverImage || !preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|gif|webp)$#', $coverImage)) {
        return;
    }

    $path = __DIR__ . '/' . $coverImage;
    if (is_file($path)) {
        unlink($path);
    }
}
