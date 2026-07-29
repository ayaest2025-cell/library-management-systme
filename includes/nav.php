<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="top-nav">
    <div class="brand">Library System</div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="books.php">Books</a>
        <a href="add-book.php">Add Book</a>
        <a href="categories.php">Categories</a>
        <a href="members.php">Members</a>
        <a href="profile.php">Profile</a>
        <span class="user-pill">Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
