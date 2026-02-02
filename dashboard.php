<?php
/**
 * Admin Dashboard
 */

require_once '../config/database.php';
$conn = getConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Get statistics
$stats = [];

// Total books
$result = $conn->query("SELECT COUNT(*) as count FROM books");
$stats['total_books'] = $result->fetch_assoc()['count'];

// Total customers
$result = $conn->query("SELECT COUNT(*) as count FROM customers");
$stats['total_customers'] = $result->fetch_assoc()['count'];

// Total orders
$result = $conn->query("SELECT COUNT(*) as count FROM customer_orders");
$stats['total_orders'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM customer_orders WHERE order_status = 'Completed'");
$stats['total_revenue'] = $result->fetch_assoc()['total'];

// Today's orders
$result = $conn->query("SELECT COUNT(*) as count FROM customer_orders WHERE DATE(order_date) = CURDATE()");
$stats['today_orders'] = $result->fetch_assoc()['count'];

// Pending publisher orders
$result = $conn->query("SELECT COUNT(*) as count FROM publisher_orders WHERE order_status = 'Pending'");
$stats['pending_publisher_orders'] = $result->fetch_assoc()['count'];

// Low stock books
$result = $conn->query("SELECT COUNT(*) as count FROM books WHERE quantity_in_stock < minimum_threshold");
$stats['low_stock_books'] = $result->fetch_assoc()['count'];

// Recent orders
$recent_orders_query = "
    SELECT co.order_id, co.order_date, co.total_amount, co.order_status,
           CONCAT(c.first_name, ' ', c.last_name) as customer_name
    FROM customer_orders co
    JOIN customers c ON co.customer_id = c.customer_id
    ORDER BY co.order_date DESC
    LIMIT 5
";
$recent_orders = $conn->query($recent_orders_query)->fetch_all(MYSQLI_ASSOC);

// Low stock books list
$low_stock_query = "
    SELECT b.isbn, b.title, b.quantity_in_stock, b.minimum_threshold,
           p.publisher_name
    FROM books b
    JOIN publishers p ON b.publisher_id = p.publisher_id
    WHERE b.quantity_in_stock < b.minimum_threshold
    ORDER BY b.quantity_in_stock ASC
    LIMIT 5
";
$low_stock_books = $conn->query($low_stock_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> BookStore Admin
            </a>
            <nav class="nav-links">
                <a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="books.php"><i class="fas fa-book"></i> Books</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container admin-container">
        <h1 class="page-title">
            <i class="fas fa-tachometer-alt"></i> Dashboard
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
        </h1>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon books">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_books']; ?></h3>
                    <p>Total Books</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_customers']; ?></h3>
                    <p>Customers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_orders']; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['today_orders']; ?></h3>
                    <p>Today's Orders</p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon pending">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending_publisher_orders']; ?></h3>
                    <p>Pending Publisher Orders</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $stats['low_stock_books'] > 0 ? 'danger' : ''; ?>">
                <div class="stat-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['low_stock_books']; ?></h3>
                    <p>Low Stock Books</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Orders</h3>
                    <a href="orders.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted">No orders yet</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($order['order_date'])); ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                                <?php echo $order['order_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Low Stock Alert -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                    <a href="books.php" class="btn btn-sm">Manage</a>
                </div>
                <div class="card-body">
                    <?php if (empty($low_stock_books)): ?>
                        <p class="text-success"><i class="fas fa-check-circle"></i> All books are well stocked!</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Stock</th>
                                    <th>Threshold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_books as $book): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($book['isbn']); ?></small>
                                        </td>
                                        <td class="text-danger"><?php echo $book['quantity_in_stock']; ?></td>
                                        <td><?php echo $book['minimum_threshold']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 BookStore Admin. Alexandria University - Database Systems Project</p>
        </div>
    </footer>
</body>
</html>
