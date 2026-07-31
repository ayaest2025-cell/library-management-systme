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

// Status summary counts
$activeBorrowedCount = 0;
$returnedTodayCount = 0;
$overdueCount = 0;

$countStmt = $conn->query("SELECT COUNT(*) AS cnt FROM borrow_transactions WHERE status = 'Borrowed'");
if ($countStmt) {
    $activeBorrowedCount = (int) $countStmt->fetch_assoc()['cnt'];
}

$today = date('Y-m-d');
$todayStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM borrow_transactions WHERE status = 'Returned' AND return_date = ?");
$todayStmt->bind_param('s', $today);
$todayStmt->execute();
$todayResult = $todayStmt->get_result();
if ($todayResult) {
    $returnedTodayCount = (int) $todayResult->fetch_assoc()['cnt'];
}

$overdueStmt = $conn->query("SELECT COUNT(*) AS cnt FROM borrow_transactions WHERE status = 'Borrowed' AND due_date < CURDATE()");
if ($overdueStmt) {
    $overdueCount = (int) $overdueStmt->fetch_assoc()['cnt'];
}

// Borrow History with search & pagination
$historySearch = trim($_GET['history_search'] ?? '');
$historyStatus = trim($_GET['history_status'] ?? '');
$historyPage = max(1, (int) ($_GET['history_page'] ?? 1));
$historyPerPage = 10;
$historyOffset = ($historyPage - 1) * $historyPerPage;

$historyWhere = [];
$historyParams = [];
$historyTypes = '';

if ($historySearch !== '') {
    $historyWhere[] = '(m.full_name LIKE ? OR b.title LIKE ? OR bt.status LIKE ?)';
    $like = "%$historySearch%";
    array_push($historyParams, $like, $like, $like);
    $historyTypes .= 'sss';
}

if ($historyStatus !== '') {
    $historyWhere[] = 'bt.status = ?';
    $historyParams[] = $historyStatus;
    $historyTypes .= 's';
}

$historyWhereClause = $historyWhere ? 'WHERE ' . implode(' AND ', $historyWhere) : '';

// Count total for pagination
$countSql = "SELECT COUNT(*) AS total FROM borrow_transactions bt JOIN members m ON m.id = bt.member_id JOIN books b ON b.id = bt.book_id $historyWhereClause";
$countStmt = $conn->prepare($countSql);
if ($historyParams) {
    $countStmt->bind_param($historyTypes, ...$historyParams);
}
$countStmt->execute();
$totalHistory = (int) $countStmt->get_result()->fetch_assoc()['total'];
$totalHistoryPages = max(1, ceil($totalHistory / $historyPerPage));

// Fetch history records
$historySql = "
    SELECT bt.id, bt.borrow_date, bt.due_date, bt.return_date, bt.status, bt.created_at,
           m.full_name AS member_name, m.member_code,
           b.title AS book_title, b.isbn, b.author
    FROM borrow_transactions bt
    JOIN members m ON m.id = bt.member_id
    JOIN books b ON b.id = bt.book_id
    $historyWhereClause
    ORDER BY bt.created_at DESC
    LIMIT ? OFFSET ?
";
$historyParams[] = $historyPerPage;
$historyParams[] = $historyOffset;
$historyTypes .= 'ii';

$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param($historyTypes, ...$historyParams);
$historyStmt->execute();
$historyTransactions = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

        <!-- Status Summary -->
        <section class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-warning shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="display-6 text-warning mb-2">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <h3 class="h5 mb-1"><?php echo (int) $activeBorrowedCount; ?></h3>
                        <p class="text-muted mb-0">Currently Borrowed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="display-6 text-success mb-2">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="h5 mb-1"><?php echo (int) $returnedTodayCount; ?></h3>
                        <p class="text-muted mb-0">Returned Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="display-6 text-danger mb-2">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="h5 mb-1"><?php echo (int) $overdueCount; ?></h3>
                        <p class="text-muted mb-0">Overdue Books</p>
                    </div>
                </div>
            </div>
        </section>

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

        <!-- Borrow History -->
        <section class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-1">
                            <i class="bi bi-clock-history me-2"></i>Borrow History
                        </h5>
                        <p class="text-muted mb-0">Complete transaction history with search and filter.</p>
                    </div>
                    <span class="badge bg-secondary"><?php echo (int) $totalHistory; ?> total records</span>
                </div>

                <!-- Search & Filter -->
                <form method="get" action="" class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="history_search" 
                                   value="<?php echo htmlspecialchars($historySearch); ?>" 
                                   placeholder="Search by member, book, or status...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="history_status">
                            <option value="">All Statuses</option>
                            <option value="Borrowed" <?php echo $historyStatus === 'Borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                            <option value="Returned" <?php echo $historyStatus === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </form>

                <?php if (!empty($historyTransactions)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Member</th>
                                    <th>Book</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historyTransactions as $txn):
                                    $isOverdue = $txn['status'] === 'Borrowed' && strtotime($txn['due_date']) < strtotime(date('Y-m-d'));
                                    $returnedLate = $txn['status'] === 'Returned' && $txn['return_date'] && strtotime($txn['return_date']) > strtotime($txn['due_date']);
                                ?>
                                    <tr class="<?php echo $isOverdue ? 'table-danger' : ($returnedLate ? 'table-warning' : ''); ?>">
                                        <td><?php echo (int) $txn['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($txn['member_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($txn['member_code']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($txn['book_title']); ?>
                                            <?php if ($txn['isbn']): ?>
                                                <br><small class="text-muted">ISBN: <?php echo htmlspecialchars($txn['isbn']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($txn['borrow_date']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['due_date']); ?></td>
                                        <td>
                                            <?php echo $txn['return_date'] ? htmlspecialchars($txn['return_date']) : '<span class="text-muted">—</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($txn['status'] === 'Borrowed' && $isOverdue): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php elseif ($txn['status'] === 'Borrowed'): ?>
                                                <span class="badge bg-warning text-dark">Borrowed</span>
                                            <?php elseif ($returnedLate): ?>
                                                <span class="badge bg-warning text-dark">Returned (Late)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalHistoryPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <li class="page-item <?php echo $historyPage <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?history_page=1&amp;history_search=<?php echo urlencode($historySearch); ?>&amp;history_status=<?php echo urlencode($historyStatus); ?>">First</a>
                            </li>
                            <li class="page-item <?php echo $historyPage <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?history_page=<?php echo $historyPage - 1; ?>&amp;history_search=<?php echo urlencode($historySearch); ?>&amp;history_status=<?php echo urlencode($historyStatus); ?>">Prev</a>
                            </li>
                            <?php
                            $startPage = max(1, $historyPage - 2);
                            $endPage = min($totalHistoryPages, $historyPage + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $historyPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?history_page=<?php echo $i; ?>&amp;history_search=<?php echo urlencode($historySearch); ?>&amp;history_status=<?php echo urlencode($historyStatus); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $historyPage >= $totalHistoryPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?history_page=<?php echo $historyPage + 1; ?>&amp;history_search=<?php echo urlencode($historySearch); ?>&amp;history_status=<?php echo urlencode($historyStatus); ?>">Next</a>
                            </li>
                            <li class="page-item <?php echo $historyPage >= $totalHistoryPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?history_page=<?php echo $totalHistoryPages; ?>&amp;history_search=<?php echo urlencode($historySearch); ?>&amp;history_status=<?php echo urlencode($historyStatus); ?>">Last</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-light text-center mb-0">
                        <i class="bi bi-inbox me-2"></i>No transaction history found matching your criteria.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

