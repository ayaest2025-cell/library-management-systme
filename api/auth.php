<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function createJwt(array $payload): string
{
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $header = str_replace(['+', '/', '='], ['-', '_', ''], $header);

    $body = base64_encode(json_encode(array_merge($payload, ['iat' => time(), 'exp' => time() + JWT_TTL]), JSON_UNESCAPED_SLASHES));
    $body = str_replace(['+', '/', '='], ['-', '_', ''], $body);

    $signatureInput = $header . '.' . $body;
    $signature = hash_hmac('sha256', $signatureInput, JWT_SECRET, true);
    $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $header . '.' . $body . '.' . $signature;
}

function decodeJwt(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new ApiException('Invalid token format.', 401);
    }

    [$header, $payload, $signature] = $parts;
    $expected = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true);
    $expectedEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expected));

    if (!hash_equals($expectedEncoded, $signature)) {
        throw new ApiException('Invalid token signature.', 401);
    }

    $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload), true), true);
    if (!is_array($decodedPayload)) {
        throw new ApiException('Invalid token payload.', 401);
    }

    if (($decodedPayload['exp'] ?? 0) < time()) {
        throw new ApiException('Token expired.', 401);
    }

    return $decodedPayload;
}

function authenticate(array $allowedRoles = []): array
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        throw new ApiException('Missing bearer token.', 401);
    }

    $jwt = trim($matches[1]);
    $payload = decodeJwt($jwt);

    if (!isset($payload['sub'], $payload['role'])) {
        throw new ApiException('Invalid token payload.', 401);
    }

    if ($allowedRoles !== [] && !in_array($payload['role'], $allowedRoles, true)) {
        throw new ApiException('Forbidden.', 403);
    }

    return $payload;
}

function loginUser(string $email, string $password): array
{
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new ApiException('Invalid email or password.', 401);
    }

    $tokenPayload = [
        'sub' => (int) $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'name' => $user['full_name'],
    ];

    return [
        'token' => createJwt($tokenPayload),
        'user' => [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
    ];
}

function registerUser(array $input): array
{
    $pdo = getPdo();
    $fullName = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role = isset($input['role']) ? strtolower((string) $input['role']) : 'borrower';

    if ($fullName === '' || $email === '' || $password === '') {
        throw new ApiException('full_name, email, and password are required.', 400);
    }

    if (!in_array($role, ['admin', 'borrower'], true)) {
        throw new ApiException('role must be admin or borrower.', 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$fullName, $email, $hash, $role]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'full_name' => $fullName,
        'email' => $email,
        'role' => $role,
    ];
}

function getUserProfile(int $userId): array
{
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT id, full_name, email, role, first_name, last_name, phone, address, profile_image FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new ApiException('User not found.', 404);
    }

    return $user;
}

function updateUserProfile(int $userId, array $input): array
{
    $pdo = getPdo();
    $allowed = ['first_name', 'last_name', 'phone', 'address', 'profile_image'];
    $updates = [];
    $values = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $updates[] = "$field = ?";
            $values[] = trim((string) $input[$field]);
        }
    }

    if ($updates === []) {
        throw new ApiException('No profile fields provided.', 400);
    }

    $values[] = $userId;
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
    $stmt->execute($values);

    return getUserProfile($userId);
}
