<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'db.php';

$pageTitle = 'Return Book';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$message = '';

// Handle POST: process return
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$transactionId) {
        $message = 'Invalid transaction selected.';
    } else {
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Get the borrow transaction details
            $txnStmt = $conn->prepare('
                SELECT bt.id, bt.book_id, bt.status, b.title AS book_title 
                FROM borrow_transactions bt 
                JOIN books b ON b.id = bt.book_id 
                WHERE bt.id = ? AND bt.status = ?
            ');
            $statusBorrowed = 'Borrowed';
            $txnStmt->bind_param('is', $transactionId, $statusBorrowed);
            $txnStmt->execute();
            $transaction = $txnStmt->get_result()->fetch_assoc();

            if (!$transaction) {
                throw new Exception('Transaction not found or already returned.');
            }

            // Update transaction as returned
            $returnDate = date('Y-m-d');
            $statusReturned = 'Returned';
            $updateTxnStmt = $conn->prepare('UPDATE borrow_transactions SET return_date = ?, status = ? WHERE id = ?');
            $updateTxnStmt->bind_param('ssi', $returnDate, $statusReturned, $transactionId);
            $updateTxnStmt->execute();

            if ($updateTxnStmt->affected_rows === 0) {
                throw new Exception('Unable to update the transaction record.');
            }

            // Update book: increase available copies and reset status
            $updateBookStmt = $conn->prepare("
                UPDATE books 
                SET available_copies = available_copies + 1, 
                    status = 'Available',
                    borrower_name = NULL,
                    borrowed_at = NULL 
                WHERE id = ? AND available_copies < quantity
            ");
            $updateBookStmt->bind_param('i', $transaction['book_id']);
            $updateBookStmt->execute();

            if ($updateBookStmt->affected_rows === 0) {
                throw new Exception('Unable to update book availability.');
            }

            $conn->commit();

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Book "' . htmlspecialchars($transaction['book_title']) . '" returned successfully on ' . htmlspecialchars($returnDate) . '.'
            ];
            header('Location: return.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Return failed: ' . $e->getMessage();
        }
    }
}

// Fetch active borrowed transactions
$borrowedStmt = $conn->query("
    SELECT bt.id, bt.borrow_date, bt.due_date, bt.status, bt.created_at,
           m.full_name AS member_name, m.member_code,
           b.id AS book_id, b.title AS book_title, b.isbn, b.author, b.cover_image
    FROM borrow_transactions bt
    JOIN members m ON m.id = bt.member_id
    JOIN books b ON b.id = bt.book_id
    WHERE bt.status = 'Borrowed'
    ORDER BY bt.due_date ASC
");
$borrowedTransactions = $borrowedStmt ? $borrowedStmt->fetch_all(MYSQLI_ASSOC) : [];

// Fetch recently returned
$returnedStmt = $conn->query("
    SELECT bt.id, bt.borrow_date, bt.due_date, bt.return_date, bt.status,
           m.full_name AS member_name,
           b.title AS book_title
    FROM borrow_transactions bt
    JOIN members m ON m.id = bt.member_id
    JOIN books b ON b.id = bt.book_id
    WHERE bt.status = 'Returned'
    ORDER BY bt.return_date DESC
    LIMIT 5
");
$returnedTransactions = $returnedStmt ? $returnedStmt->fetch_all(MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="container py-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Currently Borrowed Books -->
        <section class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h4 mb-1">
                            <i class="bi bi-journal-text me-2"></i>Currently Borrowed Books
                        </h2>
                        <p class="text-muted mb-0">Click "Return" to mark a book as returned.</p>
                    </div>
                    <a href="borrow.php" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> New Borrow
                    </a>
                </div>

                <?php if (!empty($borrowedTransactions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-warning">
                                <tr>
                                    <th>Member</th>
                                    <th>Book</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th class="text-center">Overdue</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowedTransactions as $txn): 
                                    $isOverdue = strtotime($txn['due_date']) < strtotime(date('Y-m-d'));
                                ?>
                                    <tr class="<?php echo $isOverdue ? 'table-danger' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($txn['member_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($txn['member_code']); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($txn['cover_image']): ?>
                                                    <img src="<?php echo htmlspecialchars($txn['cover_image']); ?>" 
                                                         class="book-cover-thumb" alt="Cover"
                                                         style="width: 40px; height: 56px;">
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($txn['book_title']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($txn['author']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($txn['borrow_date']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($txn['due_date']); ?>
                                            <?php if ($isOverdue): ?>
                                                <br><span class="badge bg-danger mt-1">OVERDUE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($isOverdue): ?>
                                                <?php
                                                    $daysOverdue = floor((time() - strtotime($txn['due_date'])) / 86400);
                                                ?>
                                                <span class="badge bg-danger"><?php echo (int) $daysOverdue; ?> day(s)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">On time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="post" action="" class="d-inline" 
                                                  onsubmit="return confirm('Return book "<?php echo htmlspecialchars($txn['book_title'], ENT_QUOTES); ?>" borrowed by <?php echo htmlspecialchars($txn['member_name'], ENT_QUOTES); ?>?');">
                                                <input type="hidden" name="transaction_id" value="<?php echo (int) $txn['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="bi bi-arrow-return-left"></i> Return
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light text-center mb-0">
                        <i class="bi bi-inbox me-2"></i>No books are currently borrowed.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Recently Returned -->
        <?php if (!empty($returnedTransactions)): ?>
        <section class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-3">
                    <i class="bi bi-clock-history me-2"></i>Recently Returned
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Borrowed</th>
                                <th>Due</th>
                                <th>Returned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returnedTransactions as $txn): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($txn['member_name']); ?></td>
                                    <td><?php echo htmlspecialchars($txn['book_title']); ?></td>
                                    <td><?php echo htmlspecialchars($txn['borrow_date']); ?></td>
                                    <td><?php echo htmlspecialchars($txn['due_date']); ?></td>
                                    <td><?php echo htmlspecialchars($txn['return_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

