<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    $stmt = $conn->prepare('DELETE FROM books WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book deleted successfully.'];
}

header('Location: books.php');
exit();
