<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$bookId = (int) ($_GET['book_id'] ?? 0);
if ($bookId <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid book selection.'];
    header('Location: books.php');
    exit();
}

$stmt = $conn->prepare('SELECT id, title, available_copies FROM books WHERE id = ?');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Book not found.'];
    header('Location: books.php');
    exit();
}

if ((int) $book['available_copies'] < 1) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'This book is not available for borrowing.'];
    header('Location: books.php');
    exit();
}

$stmt = $conn->prepare("UPDATE books SET status = CASE WHEN available_copies = 1 THEN 'Borrowed' ELSE 'Available' END, available_copies = available_copies - 1, borrower_name = ?, borrowed_at = NOW() WHERE id = ? AND available_copies > 0");
$borrowerName = $_SESSION['user_name'] ?? 'Member';
$stmt->bind_param('si', $borrowerName, $bookId);
$stmt->execute();

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Book borrowed successfully.'];
header('Location: books.php');
exit();
