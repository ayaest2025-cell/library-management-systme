<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$id) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid book selection.'];
    } else {
        $lookup = $conn->prepare('SELECT cover_image FROM books WHERE id = ?');
        $lookup->bind_param('i', $id);
        $lookup->execute();
        $book = $lookup->get_result()->fetch_assoc();

        if (!$book) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Book not found.'];
        } else {
            $stmt = $conn->prepare('DELETE FROM books WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows === 1) {
                deleteBookCover($book['cover_image']);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book deleted successfully.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Unable to delete the book.'];
            }
        }
    }
}

header('Location: books.php');
exit();
