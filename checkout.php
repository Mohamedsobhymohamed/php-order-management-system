<?php
/**
 * Checkout Page
 * Process orders with payment methods
 */

require_once 'config/database.php';
$conn = getConnection();

// Check if logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customer_id = $_SESSION['customer_id'];
$error = '';
$success = '';

// Get cart items
$cart_query = "SELECT cart_id FROM shopping_cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $customer_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

if ($cart_result->num_rows === 0) {
    header('Location: cart.php');
    exit;
}

$cart_id = $cart_result->fetch_assoc()['cart_id'];

// Get cart items with book info
$items_query = "
    SELECT sci.isbn, sci.quantity, b.title, b.selling_price, b.quantity_in_stock
    FROM shopping_cart_items sci
    JOIN books b ON sci.isbn = b.isbn
    WHERE sci.cart_id = ?
";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $cart_id);
$items_stmt->execute();
$cart_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['selling_price'] * $item['quantity'];
}

// Get saved payment methods
$payments_query = "SELECT * FROM payment_methods WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC";
$payments_stmt = $conn->prepare($payments_query);
$payments_stmt->bind_param("i", $customer_id);
$payments_stmt->execute();
$payment_methods = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get saved addresses
$addresses_query = "SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC";
$addresses_stmt = $conn->prepare($addresses_query);
$addresses_stmt->bind_param("i", $customer_id);
$addresses_stmt->execute();
$addresses = $addresses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    
    try {
        $payment_id = null;
        $shipping_address_id = null;
        
        // Handle payment method
        $use_saved_payment = isset($_POST['use_saved_payment']) && $_POST['use_saved_payment'] == '1';
        
        if ($use_saved_payment && isset($_POST['saved_payment_id'])) {
            $payment_id = (int)$_POST['saved_payment_id'];
            
            // Verify payment belongs to customer
            $verify_payment = $conn->prepare("SELECT payment_id FROM payment_methods WHERE payment_id = ? AND customer_id = ?");
            $verify_payment->bind_param("ii", $payment_id, $customer_id);
            $verify_payment->execute();
            if ($verify_payment->get_result()->num_rows === 0) {
                throw new Exception("Invalid payment method");
            }
        } else {
            // Process new card
            $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
            $card_holder = trim($_POST['card_holder'] ?? '');
            $expiry_month = $_POST['expiry_month'] ?? '';
            $expiry_year = $_POST['expiry_year'] ?? '';
            $card_type = $_POST['card_type'] ?? 'Visa';
            $save_payment = isset($_POST['save_payment']);
            
            // Validate card
            if (strlen($card_number) < 13 || strlen($card_number) > 16) {
                throw new Exception("Invalid card number");
            }
            if (empty($card_holder)) {
                throw new Exception("Card holder name is required");
            }
            
            // Check expiry date
            $expiry_date = sprintf('%04d-%02d-01', $expiry_year, $expiry_month);
            if (strtotime($expiry_date) < strtotime('first day of this month')) {
                throw new Exception("Card has expired");
            }
            
            // Insert payment method
            $insert_payment = $conn->prepare("
                INSERT INTO payment_methods (customer_id, card_number, card_holder_name, expiry_date, card_type, is_default)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $is_default = $save_payment ? 1 : 0;
            $expiry_date_full = $expiry_year . '-' . $expiry_month . '-28';
            $insert_payment->bind_param("issssi", $customer_id, $card_number, $card_holder, $expiry_date_full, $card_type, $is_default);
            $insert_payment->execute();
            $payment_id = $conn->insert_id;
            
            // Delete if not saving
            if (!$save_payment) {
                // We'll keep it for the order but mark as not default
                $conn->query("UPDATE payment_methods SET is_default = 0 WHERE payment_id = $payment_id");
            }
        }
        
        // Handle shipping address
        $use_saved_address = isset($_POST['use_saved_address']) && $_POST['use_saved_address'] == '1';
        
        if ($use_saved_address && isset($_POST['saved_address_id'])) {
            $shipping_address_id = (int)$_POST['saved_address_id'];
            
            // Verify address belongs to customer
            $verify_address = $conn->prepare("SELECT address_id FROM customer_addresses WHERE address_id = ? AND customer_id = ?");
            $verify_address->bind_param("ii", $shipping_address_id, $customer_id);
            $verify_address->execute();
            if ($verify_address->get_result()->num_rows === 0) {
                throw new Exception("Invalid shipping address");
            }
        } elseif (!empty($addresses)) {
            // Use default address
            $shipping_address_id = $addresses[0]['address_id'];
        } else {
            throw new Exception("No shipping address available. Please add an address in your profile.");
        }
        
        // Check stock availability
        foreach ($cart_items as $item) {
            $stock_check = $conn->prepare("SELECT quantity_in_stock FROM books WHERE isbn = ? FOR UPDATE");
            $stock_check->bind_param("s", $item['isbn']);
            $stock_check->execute();
            $stock_result = $stock_check->get_result()->fetch_assoc();
            
            if ($stock_result['quantity_in_stock'] < $item['quantity']) {
                throw new Exception("Insufficient stock for: " . $item['title'] . ". Only " . $stock_result['quantity_in_stock'] . " available.");
            }
        }
        
        // Create order
        $order_stmt = $conn->prepare("
            INSERT INTO customer_orders (customer_id, payment_id, shipping_address_id, total_amount, order_status)
            VALUES (?, ?, ?, ?, 'Completed')
        ");
        $order_stmt->bind_param("iiid", $customer_id, $payment_id, $shipping_address_id, $total);
        $order_stmt->execute();
        $order_id = $conn->insert_id;
        
        // Add order items (triggers will update stock)
        foreach ($cart_items as $item) {
            $item_stmt = $conn->prepare("
                INSERT INTO order_items (order_id, isbn, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");
            $item_stmt->bind_param("isid", $order_id, $item['isbn'], $item['quantity'], $item['selling_price']);
            $item_stmt->execute();
        }
        
        // Clear cart
        $conn->query("DELETE FROM shopping_cart_items WHERE cart_id = $cart_id");
        
        $conn->commit();
        
        // Redirect to success page
        header("Location: orders.php?success=1&order_id=$order_id");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Get cart count
$cart_count = array_sum(array_column($cart_items, 'quantity'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BookStore</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-book-open"></i> BookStore
            </a>
            <nav class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-badge"><?php echo $cart_count; ?></span></a>
                <a href="orders.php"><i class="fas fa-history"></i> Orders</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <h1 class="page-title"><i class="fas fa-credit-card"></i> Checkout</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="checkout-form">
            <div class="checkout-layout">
                <!-- Payment Section -->
                <div class="checkout-section">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($payment_methods)): ?>
                                <div class="payment-option">
                                    <label class="radio-label">
                                        <input type="radio" name="use_saved_payment" value="1" checked onchange="togglePaymentForm()">
                                        Use saved payment method
                                    </label>
                                    <div id="saved-payments" class="saved-methods">
                                        <?php foreach ($payment_methods as $pm): ?>
                                            <label class="method-card <?php echo $pm['is_default'] ? 'default' : ''; ?>">
                                                <input type="radio" name="saved_payment_id" value="<?php echo $pm['payment_id']; ?>" 
                                                       <?php echo $pm['is_default'] ? 'checked' : ''; ?>>
                                                <i class="fab fa-cc-<?php echo strtolower($pm['card_type']); ?>"></i>
                                                <span>
                                                    <?php echo $pm['card_type']; ?> ****<?php echo substr($pm['card_number'], -4); ?>
                                                    <br><small>Expires: <?php echo date('m/Y', strtotime($pm['expiry_date'])); ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="payment-option">
                                    <label class="radio-label">
                                        <input type="radio" name="use_saved_payment" value="0" onchange="togglePaymentForm()">
                                        Use a new card
                                    </label>
                                </div>
                            <?php endif; ?>
                            
                            <div id="new-payment-form" class="<?php echo empty($payment_methods) ? '' : 'hidden'; ?>">
                                <div class="form-group">
                                    <label>Card Number *</label>
                                    <input type="text" name="card_number" placeholder="1234 5678 9012 3456" 
                                           maxlength="19" <?php echo empty($payment_methods) ? 'required' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Card Holder Name *</label>
                                    <input type="text" name="card_holder" placeholder="Name on card"
                                           <?php echo empty($payment_methods) ? 'required' : ''; ?>>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Expiry Month *</label>
                                        <select name="expiry_month" <?php echo empty($payment_methods) ? 'required' : ''; ?>>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Expiry Year *</label>
                                        <select name="expiry_year" <?php echo empty($payment_methods) ? 'required' : ''; ?>>
                                            <?php for ($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Card Type</label>
                                    <select name="card_type">
                                        <option value="Visa">Visa</option>
                                        <option value="MasterCard">MasterCard</option>
                                        <option value="AmEx">American Express</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="save_payment" value="1">
                                        Save this card for future purchases
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Address Section -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-truck"></i> Shipping Address</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($addresses)): ?>
                                <input type="hidden" name="use_saved_address" value="1">
                                <div class="saved-methods">
                                    <?php foreach ($addresses as $addr): ?>
                                        <label class="method-card <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                                            <input type="radio" name="saved_address_id" value="<?php echo $addr['address_id']; ?>"
                                                   <?php echo $addr['is_default'] ? 'checked' : ''; ?>>
                                            <span>
                                                <strong><?php echo htmlspecialchars($addr['address_type']); ?></strong><br>
                                                <?php echo htmlspecialchars($addr['address_line1']); ?><br>
                                                <?php if ($addr['address_line2']): ?>
                                                    <?php echo htmlspecialchars($addr['address_line2']); ?><br>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($addr['city']); ?>, <?php echo htmlspecialchars($addr['country']); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No shipping address found. Please <a href="profile.php">add an address</a> first.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="checkout-summary">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                        </div>
                        <div class="card-body">
                            <div class="order-items">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="order-item">
                                        <span class="item-name"><?php echo htmlspecialchars($item['title']); ?></span>
                                        <span class="item-qty">x<?php echo $item['quantity']; ?></span>
                                        <span class="item-price">$<?php echo number_format($item['selling_price'] * $item['quantity'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span class="text-success">Free</span>
                            </div>
                            <hr>
                            <div class="summary-row total">
                                <span>Total</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block btn-lg" <?php echo empty($addresses) ? 'disabled' : ''; ?>>
                                <i class="fas fa-lock"></i> Place Order
                            </button>
                            
                            <a href="cart.php" class="btn btn-secondary btn-block">
                                <i class="fas fa-arrow-left"></i> Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 BookStore. Alexandria University - Database Systems Project</p>
        </div>
    </footer>

    <script>
        function togglePaymentForm() {
            const useSaved = document.querySelector('input[name="use_saved_payment"]:checked');
            const savedPayments = document.getElementById('saved-payments');
            const newPaymentForm = document.getElementById('new-payment-form');
            
            if (useSaved && useSaved.value === '1') {
                if (savedPayments) savedPayments.classList.remove('hidden');
                newPaymentForm.classList.add('hidden');
                // Remove required from new payment fields
                newPaymentForm.querySelectorAll('input, select').forEach(el => el.removeAttribute('required'));
            } else {
                if (savedPayments) savedPayments.classList.add('hidden');
                newPaymentForm.classList.remove('hidden');
                // Add required to new payment fields
                newPaymentForm.querySelector('[name="card_number"]').setAttribute('required', '');
                newPaymentForm.querySelector('[name="card_holder"]').setAttribute('required', '');
            }
        }
    </script>
</body>
</html>
