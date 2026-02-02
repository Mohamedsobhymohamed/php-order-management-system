<?php
/**
 * Admin Reports Page
 * Uses stored procedures for generating reports
 */

require_once '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$report_type = $_GET['report'] ?? '';
$report_date = $_GET['date'] ?? date('Y-m-d');
$report_isbn = $_GET['isbn'] ?? '';
$report_data = [];
$report_title = '';

// Generate reports based on type
switch ($report_type) {
    case 'previous_month':
        $report_title = 'Sales - Previous Month';
        $result = $conn->query("CALL get_sales_previous_month()");
        if ($result) {
            $report_data = $result->fetch_all(MYSQLI_ASSOC);
            $result->close();
            $conn->next_result();
        }
        break;
        
    case 'specific_date':
        $report_title = 'Sales for ' . date('F j, Y', strtotime($report_date));
        $stmt = $conn->prepare("CALL get_sales_for_date(?)");
        $stmt->bind_param("s", $report_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $report_data = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
        $conn->next_result();
        break;
        
    case 'top_customers':
        $report_title = 'Top 5 Customers (Last 3 Months)';
        $result = $conn->query("CALL get_top_customers(3)");
        if ($result) {
            $report_data = $result->fetch_all(MYSQLI_ASSOC);
            $result->close();
            $conn->next_result();
        }
        break;
        
    case 'top_books':
        $report_title = 'Top 10 Selling Books (Last 3 Months)';
        $result = $conn->query("CALL get_top_selling_books(3)");
        if ($result) {
            $report_data = $result->fetch_all(MYSQLI_ASSOC);
            $result->close();
            $conn->next_result();
        }
        break;
        
    case 'book_orders':
        if (!empty($report_isbn)) {
            $report_title = 'Publisher Order Count for ISBN: ' . $report_isbn;
            $stmt = $conn->prepare("CALL get_book_order_count(?)");
            $stmt->bind_param("s", $report_isbn);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
            $conn->next_result();
        }
        break;
}

// Get books for dropdown
$books = $conn->query("SELECT isbn, title FROM books ORDER BY title")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - BookStore Admin</title>
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
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container admin-container">
        <h1 class="page-title"><i class="fas fa-chart-bar"></i> Reports</h1>
        
        <!-- Report Selection -->
        <div class="reports-grid">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Sales Reports</h3>
                </div>
                <div class="card-body">
                    <div class="report-buttons">
                        <a href="reports.php?report=previous_month" class="btn btn-primary btn-block <?php echo $report_type === 'previous_month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-minus"></i> Sales - Previous Month
                        </a>
                        
                        <form action="reports.php" method="GET" class="report-form">
                            <input type="hidden" name="report" value="specific_date">
                            <div class="form-group">
                                <label>Sales for Specific Date:</label>
                                <div class="input-group">
                                    <input type="date" name="date" value="<?php echo $report_date; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> View
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Top Performance</h3>
                </div>
                <div class="card-body">
                    <div class="report-buttons">
                        <a href="reports.php?report=top_customers" class="btn btn-success btn-block <?php echo $report_type === 'top_customers' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Top 5 Customers (3 Months)
                        </a>
                        
                        <a href="reports.php?report=top_books" class="btn btn-info btn-block <?php echo $report_type === 'top_books' ? 'active' : ''; ?>">
                            <i class="fas fa-book"></i> Top 10 Selling Books (3 Months)
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-truck"></i> Publisher Orders</h3>
                </div>
                <div class="card-body">
                    <form action="reports.php" method="GET" class="report-form">
                        <input type="hidden" name="report" value="book_orders">
                        <div class="form-group">
                            <label>Times Book Has Been Ordered:</label>
                            <select name="isbn" required>
                                <option value="">Select a book...</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?php echo $book['isbn']; ?>" <?php echo $report_isbn === $book['isbn'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($book['title']); ?> (<?php echo $book['isbn']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-search"></i> View Order Count
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Report Results -->
        <?php if (!empty($report_type)): ?>
            <div class="card report-results">
                <div class="card-header">
                    <h3><i class="fas fa-table"></i> <?php echo $report_title; ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($report_data)): ?>
                        <p class="text-muted"><i class="fas fa-info-circle"></i> No data found for this report.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($report_data[0]) as $col): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $col)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                            <td>
                                                <?php 
                                                if (strpos($key, 'amount') !== false || strpos($key, 'spent') !== false || 
                                                    strpos($key, 'revenue') !== false || strpos($key, 'sales') !== false) {
                                                    echo '$' . number_format((float)$value, 2);
                                                } elseif (strpos($key, 'date') !== false && $value) {
                                                    echo date('M d, Y', strtotime($value));
                                                } else {
                                                    echo htmlspecialchars($value ?? 'N/A');
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if ($report_type === 'previous_month' || $report_type === 'specific_date'): ?>
                            <?php 
                            $total_sales = array_sum(array_column($report_data, 'total_sales'));
                            $total_revenue = array_sum(array_column($report_data, 'total_revenue'));
                            if ($total_sales || $total_revenue):
                            ?>
                                <div class="report-summary">
                                    <p><strong>Total: </strong> $<?php echo number_format($total_sales ?: $total_revenue, 2); ?></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 BookStore Admin. Alexandria University - Database Systems Project</p>
        </div>
    </footer>
</body>
</html>
