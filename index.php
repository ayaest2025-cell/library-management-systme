<?php
$pageTitle = "Library Management System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Library Management System</h1>
        <p>Manage books, categories, and members in one place.</p>
    </header>

    <nav>
        <a href="index.php">Home</a>
        <a href="books.php">Books</a>
        <a href="add-book.php">Add Book</a>
    </nav>

    <main>
        <section class="welcome">
            <h2>Welcome!</h2>
            <p>Welcome to the library management homepage. You can browse books and add new records with ease.</p>
        </section>
    </main>
</body>
</html>
