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

$stmt = $conn->prepare('SELECT id, title, status, borrower_name FROM books WHERE id = ?');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Book not found.'];
    header('Location: books.php');
    exit();
}

if ($book['status'] !== 'Borrowed') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'This book is not currently borrowed.'];
    header('Location: books.php');
    exit();
}

$stmt = $conn->prepare('UPDATE books SET status = ?, borrower_name = ? WHERE id = ?');
$availableStatus = 'Available';
$borrowerName = '';
$stmt->bind_param('ssi', $availableStatus, $borrowerName, $bookId);
$stmt->execute();

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Book returned successfully.'];
header('Location: books.php');
exit();
