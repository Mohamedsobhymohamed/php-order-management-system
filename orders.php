<?php
/**
 * Admin Orders Management
 */

require_once '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'confirm_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $update_stmt = $conn->prepare("UPDATE publisher_orders SET order_status = 'Confirmed', confirmed_date = NOW() WHERE order_id = ?");
        $update_stmt->bind_param("i", $order_id);
        if ($update_stmt->execute()) {
            $success = 'Order confirmed! Stock has been updated automatically.';
        } else {
            $error = 'Error confirming order';
        }
    }
    
    if ($action === 'cancel_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $update_stmt = $conn->prepare("UPDATE publisher_orders SET order_status = 'Cancelled' WHERE order_id = ?");
        $update_stmt->bind_param("i", $order_id);
        if ($update_stmt->execute()) {
            $success = 'Order cancelled';
        }
    }
}

// Get pending publisher orders
$pending_orders = $conn->query("
    SELECT po.*, b.title, p.publisher_name
    FROM publisher_orders po
    JOIN books b ON po.isbn = b.isbn
    JOIN publishers p ON b.publisher_id = p.publisher_id
    WHERE po.order_status = 'Pending'
    ORDER BY po.order_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Get confirmed publisher orders
$confirmed_orders = $conn->query("
    SELECT po.*, b.title, p.publisher_name
    FROM publisher_orders po
    JOIN books b ON po.isbn = b.isbn
    JOIN publishers p ON b.publisher_id = p.publisher_id
    WHERE po.order_status = 'Confirmed'
    ORDER BY po.confirmed_date DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get customer orders
$customer_orders = $conn->query("
    SELECT co.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email,
           COUNT(oi.order_item_id) as item_count
    FROM customer_orders co
    JOIN customers c ON co.customer_id = c.customer_id
    LEFT JOIN order_items oi ON co.order_id = oi.order_id
    GROUP BY co.order_id
    ORDER BY co.order_date DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - BookStore Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="header-container">
            <a href="dashboard.php" class="logo"><i class="fas fa-book-open"></i> BookStore Admin</a>
            <nav class="nav-links">
                <a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="books.php"><i class="fas fa-book"></i> Books</a>
                <a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container admin-container">
        <h1 class="page-title"><i class="fas fa-shopping-cart"></i> Order Management</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Pending Publisher Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-truck"></i> Pending Publisher Orders (<?php echo count($pending_orders); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($pending_orders)): ?>
                    <p class="text-success"><i class="fas fa-check-circle"></i> No pending orders</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Book</th>
                                <th>ISBN</th>
                                <th>Publisher</th>
                                <th>Quantity</th>
                                <th>Order Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['title']); ?></td>
                                    <td><?php echo $order['isbn']; ?></td>
                                    <td><?php echo htmlspecialchars($order['publisher_name']); ?></td>
                                    <td><strong><?php echo $order['quantity_ordered']; ?></strong></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="confirm_order">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm this order? Stock will be updated.')">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this order?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Confirmed Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-check-circle"></i> Recently Confirmed Publisher Orders</h3>
            </div>
            <div class="card-body">
                <?php if (empty($confirmed_orders)): ?>
                    <p class="text-muted">No confirmed orders yet</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Book</th>
                                <th>Publisher</th>
                                <th>Quantity</th>
                                <th>Confirmed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($confirmed_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['title']); ?></td>
                                    <td><?php echo htmlspecialchars($order['publisher_name']); ?></td>
                                    <td><?php echo $order['quantity_ordered']; ?></td>
                                    <td><?php echo $order['confirmed_date'] ? date('M d, Y H:i', strtotime($order['confirmed_date'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Customer Orders</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['email']); ?></td>
                                <td><?php echo $order['item_count']; ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                        <?php echo $order['order_status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
