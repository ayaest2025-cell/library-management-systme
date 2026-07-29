<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

function calculateFine(?string $returnDate, string $dueDate): float
{
    if ($returnDate === null || $returnDate === '') {
        return 0.0;
    }

    $returnTime = strtotime($returnDate);
    $dueTime = strtotime($dueDate);
    if ($returnTime === false || $dueTime === false) {
        return 0.0;
    }

    if ($returnTime <= $dueTime) {
        return 0.0;
    }

    $daysLate = max(0, (int) floor(($returnTime - $dueTime) / 86400));
    return round($daysLate * 2.0, 2);
}

function handleRoute(string $path, string $method): void
{
    $pdo = getPdo();

    if ($path === '' || $path === '/') {
        sendJson(['success' => true, 'message' => 'API is running.'], 200);
        return;
    }

    if ($path === 'auth/login' && $method === 'POST') {
        $body = readJsonBody();
        $email = trim($body['email'] ?? '');
        $password = trim($body['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new ApiException('email and password are required.', 400);
        }
        sendJson(['success' => true, 'data' => loginUser($email, $password)], 200);
        return;
    }

    if ($path === 'auth/register' && $method === 'POST') {
        $body = readJsonBody();
        sendJson(['success' => true, 'data' => registerUser($body)], 201);
        return;
    }

    if ($path === 'borrow/request' && $method === 'POST') {
        $user = authenticate(['borrower']);
        $body = readJsonBody();
        $itemId = (int) ($body['item_id'] ?? 0);
        $dueDays = max(1, (int) ($body['due_days'] ?? 7));

        if ($itemId <= 0) {
            throw new ApiException('item_id is required.', 400);
        }

        $item = $pdo->prepare('SELECT id, status FROM items WHERE id = ? LIMIT 1');
        $item->execute([$itemId]);
        $itemData = $item->fetch();
        if (!$itemData) {
            throw new ApiException('Item not found.', 404);
        }
        if ($itemData['status'] !== 'available') {
            throw new ApiException('Item is not currently available for borrowing.', 409);
        }

        $dueDate = date('Y-m-d H:i:s', strtotime('+' . $dueDays . ' days'));
        $stmt = $pdo->prepare('INSERT INTO borrow_transactions (user_id, item_id, due_date, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([(int) $user['sub'], $itemId, $dueDate, 'requested']);

        $pdo->prepare('UPDATE items SET status = ? WHERE id = ?')->execute(['borrowed', $itemId]);

        sendJson(['success' => true, 'message' => 'Borrow request submitted successfully.', 'data' => ['id' => (int) $pdo->lastInsertId()]], 201);
        return;
    }

    if (preg_match('#^borrow/approve/(\d+)$#', $path, $matches) && $method === 'PUT') {
        $user = authenticate(['admin']);
        $transactionId = (int) $matches[1];
        $stmt = $pdo->prepare('SELECT id, item_id, status FROM borrow_transactions WHERE id = ? LIMIT 1');
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            throw new ApiException('Transaction not found.', 404);
        }
        if ($transaction['status'] !== 'requested') {
            throw new ApiException('This request is no longer pending approval.', 409);
        }

        $pdo->prepare('UPDATE borrow_transactions SET status = ?, issue_date = NOW() WHERE id = ?')->execute(['approved', $transactionId]);
        $pdo->prepare('UPDATE items SET status = ? WHERE id = ?')->execute(['borrowed', $transaction['item_id']]);

        sendJson(['success' => true, 'message' => 'Borrow request approved.']);
        return;
    }

    if (preg_match('#^borrow/return/(\d+)$#', $path, $matches) && $method === 'PUT') {
        authenticate(['admin', 'borrower']);
        $transactionId = (int) $matches[1];
        $stmt = $pdo->prepare('SELECT id, user_id, item_id, due_date, status FROM borrow_transactions WHERE id = ? LIMIT 1');
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            throw new ApiException('Transaction not found.', 404);
        }

        $returnDate = date('Y-m-d H:i:s');
        $fineAmount = calculateFine($returnDate, $transaction['due_date']);
        $pdo->prepare('UPDATE borrow_transactions SET return_date = ?, status = ?, fine_amount = ? WHERE id = ?')->execute([$returnDate, 'returned', number_format($fineAmount, 2, '.', ''), $transactionId]);
        $pdo->prepare('UPDATE items SET status = ? WHERE id = ?')->execute(['available', $transaction['item_id']]);

        sendJson(['success' => true, 'message' => 'Item returned successfully.', 'data' => ['fine_amount' => $fineAmount]]);
        return;
    }

    if ($path === 'items/available' && $method === 'GET') {
        authenticate(['admin', 'borrower']);
        $stmt = $pdo->query('SELECT i.id, i.title, i.barcode, c.name AS category_name, i.status FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.status = "available" ORDER BY i.id DESC');
        sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
        return;
    }

    if ($path === 'admin/overdue' && $method === 'GET') {
        authenticate(['admin']);
        $stmt = $pdo->query('SELECT bt.id, bt.user_id, bt.item_id, bt.due_date, bt.status, bt.return_date FROM borrow_transactions bt WHERE bt.status = "approved" AND bt.return_date IS NULL AND bt.due_date < NOW() ORDER BY bt.due_date ASC');
        sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
        return;
    }

    throw new ApiException('Route not found.', 404);
}
