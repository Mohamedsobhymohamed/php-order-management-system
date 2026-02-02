<?php
/**
 * BookStore - Home Page
 * Displays all books with search functionality
 */

require_once 'config/database.php';
$conn = getConnection();

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$author = isset($_GET['author']) ? trim($_GET['author']) : '';

// Build query using the view
$query = "SELECT * FROM vw_books_full_details WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR isbn LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($author)) {
    $query .= " AND authors LIKE ?";
    $params[] = "%$author%";
    $types .= "s";
}

$query .= " ORDER BY title";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = ['Science', 'Art', 'Religion', 'History', 'Geography'];

// Get cart count for logged-in user
$cart_count = 0;
if (isset($_SESSION['customer_id'])) {
    $cart_query = "SELECT SUM(sci.quantity) as total 
                   FROM shopping_cart sc 
                   JOIN shopping_cart_items sci ON sc.cart_id = sci.cart_id 
                   WHERE sc.customer_id = ?";
    $cart_stmt = $conn->prepare($cart_query);
    $cart_stmt->bind_param("i", $_SESSION['customer_id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result()->fetch_assoc();
    $cart_count = $cart_result['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookStore - Online Book Shop</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-book-open"></i> BookStore
            </a>
            
            <form class="search-form" action="index.php" method="GET">
                <input type="text" name="search" placeholder="Search books by title or ISBN..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            
            <nav class="nav-links">
                <?php if (isset($_SESSION['customer_id'])): ?>
                    <a href="profile.php"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['customer_name']); ?></a>
                    <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-badge"><?php echo $cart_count; ?></span></a>
                    <a href="orders.php"><i class="fas fa-history"></i> Orders</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
                <a href="admin/login.php"><i class="fas fa-cog"></i> Admin</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to BookStore</h1>
            <p>Discover your next favorite book from our collection</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container">
        <!-- Filters -->
        <div class="filters">
            <h3><i class="fas fa-filter"></i> Filter Books</h3>
            <form action="index.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Category:</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Author:</label>
                    <input type="text" name="author" placeholder="Author name..." 
                           value="<?php echo htmlspecialchars($author); ?>">
                </div>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="index.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Books Grid -->
        <div class="books-section">
            <h2><i class="fas fa-book"></i> Available Books (<?php echo count($books); ?>)</h2>
            
            <?php if (empty($books)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <p>No books found matching your criteria.</p>
                    <a href="index.php" class="btn btn-primary">View All Books</a>
                </div>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-image">
                                <?php 
                                // Check if image exists, otherwise use placeholder
                                $image_path = 'assets/images/books/' . ($book['image_url'] ?? 'default.png');
                                if (!file_exists($image_path) || empty($book['image_url'])) {
                                    $image_path = 'assets/images/books/default.png';
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>"
                                     onerror="this.src='assets/images/books/default.png'">
                                <span class="category-badge <?php echo strtolower($book['category']); ?>">
                                    <?php echo $book['category']; ?>
                                </span>
                            </div>
                            <div class="book-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author">
                                    <i class="fas fa-user-edit"></i> 
                                    <?php echo htmlspecialchars($book['authors'] ?? 'Unknown Author'); ?>
                                </p>
                                <p class="book-publisher">
                                    <i class="fas fa-building"></i> 
                                    <?php echo htmlspecialchars($book['publisher_name']); ?>
                                </p>
                                <p class="book-isbn">
                                    <i class="fas fa-barcode"></i> ISBN: <?php echo htmlspecialchars($book['isbn']); ?>
                                </p>
                                <p class="book-year">
                                    <i class="fas fa-calendar"></i> <?php echo $book['publication_year']; ?>
                                </p>
                                <div class="book-footer">
                                    <span class="book-price">$<?php echo number_format($book['selling_price'], 2); ?></span>
                                    <span class="stock-status <?php echo $book['quantity_in_stock'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                        <?php if ($book['quantity_in_stock'] > 0): ?>
                                            <i class="fas fa-check-circle"></i> In Stock (<?php echo $book['quantity_in_stock']; ?>)
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($book['quantity_in_stock'] > 0): ?>
                                    <?php if (isset($_SESSION['customer_id'])): ?>
                                        <button class="btn btn-primary btn-add-cart" 
                                                onclick="addToCart('<?php echo $book['isbn']; ?>')">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php?redirect=add_to_cart&isbn=<?php echo urlencode($book['isbn']); ?>" 
                                           class="btn btn-primary btn-add-cart">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-disabled" disabled>
                                        <i class="fas fa-ban"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 BookStore. Alexandria University - Database Systems Project</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
