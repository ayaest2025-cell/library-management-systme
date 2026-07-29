<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'db.php';

$pageTitle = 'Borrow Book';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Fetch active members for the dropdown
$membersResult = $conn->query("SELECT id, member_code, full_name FROM members WHERE status = 'Active' ORDER BY full_name ASC");
$members = $membersResult ? $membersResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch books with available copies for the dropdown
$booksResult = $conn->query("SELECT id, title, isbn, author, available_copies FROM books WHERE available_copies > 0 ORDER BY title ASC");
$books = $booksResult ? $booksResult->fetch_all(MYSQLI_ASSOC) : [];

$selectedMemberId = (int) ($_POST['member_id'] ?? 0);
$selectedBookId = (int) ($_POST['book_id'] ?? 0);
$borrowDate = trim($_POST['borrow_date'] ?? date('Y-m-d'));
$dueDate = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days')));
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate member
    if ($selectedMemberId <= 0) {
        $message = 'Please select a member.';
    } elseif ($selectedBookId <= 0) {
        $message = 'Please select a book.';
    } elseif ($borrowDate === '' || $dueDate === '') {
        $message = 'Please enter both borrow date and due date.';
    } elseif (strtotime($dueDate) < strtotime($borrowDate)) {
        $message = 'Due date must be on or after the borrow date.';
    } else {
        // Verify member exists and is active
        $memberStmt = $conn->prepare('SELECT id, full_name FROM members WHERE id = ? AND status = ?');
        $statusActive = 'Active';
        $memberStmt->bind_param('is', $selectedMemberId, $statusActive);
        $memberStmt->execute();
        $member = $memberStmt->get_result()->fetch_assoc();

        if (!$member) {
            $message = 'Selected member not found or is not active.';
        } else {
            // Verify book exists and has available copies
            $bookStmt = $conn->prepare('SELECT id, title, available_copies FROM books WHERE id = ? AND available_copies > 0');
            $bookStmt->bind_param('i', $selectedBookId);
            $bookStmt->execute();
            $book = $bookStmt->get_result()->fetch_assoc();

            if (!$book) {
                $message = 'Selected book is not available for borrowing.';
            } else {
                // Begin transaction
                $conn->begin_transaction();
                try {
                    // Insert borrow record
                    $status = 'Borrowed';
                    $insertStmt = $conn->prepare(
                        'INSERT INTO borrow_transactions (member_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, ?)'
                    );
                    $insertStmt->bind_param('iisss', $selectedMemberId, $selectedBookId, $borrowDate, $dueDate, $status);
                    $insertStmt->execute();

                    // Update book: reduce available copies and update status
                    $updateStmt = $conn->prepare(
                        "UPDATE books SET available_copies = available_copies - 1, 
                         status = CASE WHEN available_copies - 1 <= 0 THEN 'Borrowed' ELSE 'Available' END,
                         borrower_name = ?, borrowed_at = NOW() 
                         WHERE id = ? AND available_copies > 0"
                    );
                    $memberName = $member['full_name'];
                    $updateStmt->bind_param('si', $memberName, $selectedBookId);
                    $updateStmt->execute();

                    if ($updateStmt->affected_rows === 0) {
                        throw new Exception('Unable to update book availability. The book may have been borrowed by another user.');
                    }

                    $conn->commit();

                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' => 'Book borrowed successfully! Member: ' . htmlspecialchars($member['full_name']) . ', Book: ' . htmlspecialchars($book['title']) . ', Due: ' . htmlspecialchars($dueDate)
                    ];
                    header('Location: borrow.php');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = 'Borrow failed: ' . $e->getMessage();
                }
            }
        }
    }
}
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
        <section class="card shadow-sm mx-auto" style="max-width: 750px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <i class="bi bi-journal-arrow-up me-2"></i>Borrow Book
                        </h1>
                        <p class="text-muted mb-0">Select a member and a book to issue a loan.</p>
                    </div>
                    <a href="books.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Books
                    </a>
                </div>

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

                <form method="post" action="" class="row g-3 needs-validation" novalidate>
                    <!-- Member Selection -->
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="member_id">
                            <i class="bi bi-person me-1"></i>Select Member *
                        </label>
                        <select class="form-select" id="member_id" name="member_id" required>
                            <option value="">-- Choose a member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo (int) $member['id']; ?>" <?php echo $selectedMemberId === (int) $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['full_name'] . ' (' . $member['member_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($members)): ?>
                            <div class="form-text text-warning">
                                <i class="bi bi-exclamation-circle"></i> No active members found. 
                                <a href="add-member.php" class="link-primary">Add a member first</a>.
                            </div>
                        <?php endif; ?>
                        <div class="invalid-feedback">Please select a member.</div>
                    </div>

                    <!-- Book Selection -->
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="book_id">
                            <i class="bi bi-book me-1"></i>Select Book *
                        </label>
                        <select class="form-select" id="book_id" name="book_id" required>
                            <option value="">-- Choose a book --</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo (int) $book['id']; ?>" <?php echo $selectedBookId === (int) $book['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($book['title']); ?> 
                                    (<?php echo htmlspecialchars($book['author']); ?>) 
                                    - <?php echo (int) $book['available_copies']; ?> available
                                    <?php if ($book['isbn']): ?>
                                        [ISBN: <?php echo htmlspecialchars($book['isbn']); ?>]
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($books)): ?>
                            <div class="form-text text-warning">
                                <i class="bi bi-exclamation-circle"></i> No books available for borrowing. 
                                <a href="add-book.php" class="link-primary">Add a book first</a>.
                            </div>
                        <?php endif; ?>
                        <div class="invalid-feedback">Please select a book.</div>
                    </div>

                    <!-- Borrow Date -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="borrow_date">
                            <i class="bi bi-calendar-date me-1"></i>Borrow Date *
                        </label>
                        <input class="form-control" id="borrow_date" name="borrow_date" type="date" 
                               value="<?php echo htmlspecialchars($borrowDate); ?>" required>
                        <div class="invalid-feedback">Please provide a borrow date.</div>
                    </div>

                    <!-- Due Date -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="due_date">
                            <i class="bi bi-calendar-exclamation me-1"></i>Due Date *
                        </label>
                        <input class="form-control" id="due_date" name="due_date" type="date" 
                               value="<?php echo htmlspecialchars($dueDate); ?>" required>
                        <div class="form-text">Default: 14 days from borrow date.</div>
                        <div class="invalid-feedback">Please provide a due date.</div>
                    </div>

                    <!-- Summary Card -->
                    <?php if ($selectedMemberId > 0 && $selectedBookId > 0 && $borrowDate && $dueDate): ?>
                    <div class="col-12">
                        <div class="card bg-light border-info">
                            <div class="card-body py-3">
                                <h6 class="card-title mb-2 text-info">
                                    <i class="bi bi-info-circle"></i> Loan Summary
                                </h6>
                                <div class="row row-cols-1 row-cols-md-2 g-1 small">
                                    <div class="col"><strong>Member:</strong> 
                                        <?php 
                                            $mn = $conn->prepare('SELECT full_name FROM members WHERE id = ?');
                                            $mn->bind_param('i', $selectedMemberId);
                                            $mn->execute();
                                            $mnRes = $mn->get_result()->fetch_assoc();
                                            echo htmlspecialchars($mnRes['full_name'] ?? 'N/A');
                                        ?>
                                    </div>
                                    <div class="col"><strong>Book:</strong> 
                                        <?php 
                                            $bn = $conn->prepare('SELECT title FROM books WHERE id = ?');
                                            $bn->bind_param('i', $selectedBookId);
                                            $bn->execute();
                                            $bnRes = $bn->get_result()->fetch_assoc();
                                            echo htmlspecialchars($bnRes['title'] ?? 'N/A');
                                        ?>
                                    </div>
                                    <div class="col"><strong>Borrow Date:</strong> <?php echo htmlspecialchars($borrowDate); ?></div>
                                    <div class="col"><strong>Due Date:</strong> <?php echo htmlspecialchars($dueDate); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Submit -->
                    <div class="col-12 mt-4">
                        <button class="btn btn-primary btn-lg w-100" type="submit" 
                                <?php echo empty($members) || empty($books) ? 'disabled' : ''; ?>
                                onclick="return confirm('Confirm borrowing this book? This action will reduce available copies.');">
                            <i class="bi bi-journal-check me-2"></i>Borrow Book
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Recent Borrow List -->
        <section class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <h5 class="card-title mb-3">
                    <i class="bi bi-clock-history me-2"></i>Recent Borrow Transactions
                </h5>
                <?php
                $recentStmt = $conn->query("
                    SELECT bt.id, bt.borrow_date, bt.due_date, bt.status, bt.created_at,
                           m.full_name AS member_name, m.member_code,
                           b.title AS book_title, b.isbn
                    FROM borrow_transactions bt
                    JOIN members m ON m.id = bt.member_id
                    JOIN books b ON b.id = bt.book_id
                    ORDER BY bt.created_at DESC
                    LIMIT 5
                ");
                $recentBorrows = $recentStmt ? $recentStmt->fetch_all(MYSQLI_ASSOC) : [];
                ?>
                <?php if (!empty($recentBorrows)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Member</th>
                                    <th>Book</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBorrows as $txn): ?>
                                    <tr>
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
                                            <span class="badge bg-<?php echo $txn['status'] === 'Borrowed' ? 'warning' : 'success'; ?>">
                                                <?php echo htmlspecialchars($txn['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><i class="bi bi-inbox me-1"></i> No borrow transactions yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-set due date when borrow date changes (14 days later)
        document.getElementById('borrow_date').addEventListener('change', function() {
            const borrowDate = new Date(this.value + 'T12:00:00');
            if (!isNaN(borrowDate.getTime())) {
                const dueDate = new Date(borrowDate);
                dueDate.setDate(dueDate.getDate() + 14);
                document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
            }
        });

        // Bootstrap form validation
        (function() {
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
        })();
    </script>
</body>
</html>

