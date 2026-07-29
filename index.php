<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = "Library Management System";

$totalBooks = $conn->query('SELECT COUNT(*) AS total_books FROM books')->fetch_assoc()['total_books'];
$totalCategories = $conn->query('SELECT COUNT(DISTINCT category) AS total_categories FROM books')->fetch_assoc()['total_categories'];
$recentlyAdded = $conn->query('SELECT COUNT(*) AS recently_added FROM books WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetch_assoc()['recently_added'];
$recentBooks = $conn->query('SELECT title, created_at FROM books ORDER BY created_at DESC LIMIT 3')->fetch_all(MYSQLI_ASSOC);
$activeLoans = $conn->query('SELECT COUNT(*) AS total_active_loans FROM books WHERE status = "Borrowed"')->fetch_assoc()['total_active_loans'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <header>
        <h1>Library Management System</h1>
        <p>Manage books, categories, and members in one place.</p>
    </header>

    <main>
        <section class="welcome main-container">
            <h2>Welcome!</h2>
            <p>Welcome to the library management homepage. You can browse books and add new records with ease.</p>

            <div class="stats-grid">
                <article class="stat-card">
                    <h3>Total Books</h3>
                    <p class="stat-value"><?php echo (int) $totalBooks; ?></p>
                </article>

                <article class="stat-card">
                    <h3>Total Categories</h3>
                    <p class="stat-value"><?php echo (int) $totalCategories; ?></p>
                </article>

                <article class="stat-card">
                    <h3>Recently Added</h3>
                    <p class="stat-value"><?php echo (int) $recentlyAdded; ?></p>
                    <ul class="stat-list">
                        <?php if (!empty($recentBooks)): ?>
                            <?php foreach ($recentBooks as $book): ?>
                                <li><?php echo htmlspecialchars($book['title']); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No recent books yet.</li>
                        <?php endif; ?>
                    </ul>
                </article>

                <article class="stat-card">
                    <h3>Active Loans</h3>
                    <p class="stat-value"><?php echo (int) $activeLoans; ?></p>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
