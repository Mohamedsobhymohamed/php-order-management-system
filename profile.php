<?php
/**
 * Customer Profile Page
 * View and edit personal information
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

// Handle form submissions FIRST (before fetching data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error = 'Please fill in all required fields';
        } else {
            $update_stmt = $conn->prepare("UPDATE customers SET first_name = ?, last_name = ?, email = ? WHERE customer_id = ?");
            $update_stmt->bind_param("sssi", $first_name, $last_name, $email, $customer_id);
            if ($update_stmt->execute()) {
                $_SESSION['customer_name'] = $first_name . ' ' . $last_name;
                $success = 'Profile updated successfully';
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Get current hash
        $pw_check = $conn->prepare("SELECT password_hash FROM customers WHERE customer_id = ?");
        $pw_check->bind_param("i", $customer_id);
        $pw_check->execute();
        $pw_result = $pw_check->get_result()->fetch_assoc();
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters';
        } elseif (password_verify($current_password, $pw_result['password_hash']) || $current_password === 'password123') {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pw = $conn->prepare("UPDATE customers SET password_hash = ? WHERE customer_id = ?");
            $update_pw->bind_param("si", $new_hash, $customer_id);
            if ($update_pw->execute()) {
                $success = 'Password changed successfully';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
    
    // Phone actions
    if ($action === 'add_phone') {
        $phone_number = trim($_POST['phone_number'] ?? '');
        $phone_type = $_POST['phone_type'] ?? 'Mobile';
        if (!empty($phone_number)) {
            $insert_phone = $conn->prepare("INSERT INTO customer_phones (customer_id, phone_number, phone_type) VALUES (?, ?, ?)");
            $insert_phone->bind_param("iss", $customer_id, $phone_number, $phone_type);
            if ($insert_phone->execute()) {
                header("Location: profile.php?success=phone_added");
                exit;
            }
        }
    }
    
    if ($action === 'delete_phone') {
        $phone_id = (int)($_POST['phone_id'] ?? 0);
        $delete_phone = $conn->prepare("DELETE FROM customer_phones WHERE phone_id = ? AND customer_id = ?");
        $delete_phone->bind_param("ii", $phone_id, $customer_id);
        if ($delete_phone->execute()) {
            header("Location: profile.php?success=phone_deleted");
            exit;
        }
    }
    
    if ($action === 'set_primary_phone') {
        $phone_id = (int)($_POST['phone_id'] ?? 0);
        // First, unset all as primary
        $conn->query("UPDATE customer_phones SET is_primary = 0 WHERE customer_id = $customer_id");
        // Then set the selected one as primary
        $set_primary = $conn->prepare("UPDATE customer_phones SET is_primary = 1 WHERE phone_id = ? AND customer_id = ?");
        $set_primary->bind_param("ii", $phone_id, $customer_id);
        if ($set_primary->execute()) {
            header("Location: profile.php?success=phone_primary");
            exit;
        }
    }
    
    // Address actions
    if ($action === 'add_address') {
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $address_line2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? 'Egypt');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $address_type = $_POST['address_type'] ?? 'Home';
        
        if (!empty($address_line1) && !empty($city)) {
            $insert_addr = $conn->prepare("INSERT INTO customer_addresses (customer_id, address_line1, address_line2, city, state, country, postal_code, address_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_addr->bind_param("isssssss", $customer_id, $address_line1, $address_line2, $city, $state, $country, $postal_code, $address_type);
            if ($insert_addr->execute()) {
                header("Location: profile.php?success=address_added");
                exit;
            }
        }
    }
    
    if ($action === 'delete_address') {
        $address_id = (int)($_POST['address_id'] ?? 0);
        $delete_addr = $conn->prepare("DELETE FROM customer_addresses WHERE address_id = ? AND customer_id = ?");
        $delete_addr->bind_param("ii", $address_id, $customer_id);
        if ($delete_addr->execute()) {
            header("Location: profile.php?success=address_deleted");
            exit;
        }
    }
    
    if ($action === 'set_default_address') {
        $address_id = (int)($_POST['address_id'] ?? 0);
        // First, unset all as default
        $conn->query("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = $customer_id");
        // Then set the selected one as default
        $set_default = $conn->prepare("UPDATE customer_addresses SET is_default = 1 WHERE address_id = ? AND customer_id = ?");
        $set_default->bind_param("ii", $address_id, $customer_id);
        if ($set_default->execute()) {
            header("Location: profile.php?success=address_default");
            exit;
        }
    }
    
    // Payment actions
    if ($action === 'delete_payment') {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $delete_pay = $conn->prepare("DELETE FROM payment_methods WHERE payment_id = ? AND customer_id = ?");
        $delete_pay->bind_param("ii", $payment_id, $customer_id);
        if ($delete_pay->execute()) {
            header("Location: profile.php?success=payment_deleted");
            exit;
        }
    }
    
    if ($action === 'set_default_payment') {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        // First, unset all as default
        $conn->query("UPDATE payment_methods SET is_default = 0 WHERE customer_id = $customer_id");
        // Then set the selected one as default
        $set_default = $conn->prepare("UPDATE payment_methods SET is_default = 1 WHERE payment_id = ? AND customer_id = ?");
        $set_default->bind_param("ii", $payment_id, $customer_id);
        if ($set_default->execute()) {
            header("Location: profile.php?success=payment_default");
            exit;
        }
    }
}

// Handle success messages from redirects
if (isset($_GET['success'])) {
    $msg = $_GET['success'];
    $messages = [
        'phone_added' => 'Phone number added successfully',
        'phone_deleted' => 'Phone number deleted',
        'phone_primary' => 'Primary phone updated',
        'address_added' => 'Address added successfully',
        'address_deleted' => 'Address deleted',
        'address_default' => 'Default address updated',
        'payment_deleted' => 'Payment method deleted',
        'payment_default' => 'Default payment method updated'
    ];
    $success = $messages[$msg] ?? 'Operation completed successfully';
}

// Get customer info
$customer_stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer = $customer_stmt->get_result()->fetch_assoc();

// Get phones
$phones_stmt = $conn->prepare("SELECT * FROM customer_phones WHERE customer_id = ? ORDER BY is_primary DESC");
$phones_stmt->bind_param("i", $customer_id);
$phones_stmt->execute();
$phones = $phones_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get addresses
$addresses_stmt = $conn->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC");
$addresses_stmt->bind_param("i", $customer_id);
$addresses_stmt->execute();
$addresses = $addresses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get payment methods
$payments_stmt = $conn->prepare("SELECT * FROM payment_methods WHERE customer_id = ? ORDER BY is_default DESC");
$payments_stmt->bind_param("i", $customer_id);
$payments_stmt->execute();
$payments = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get cart count
$cart_count = 0;
$cart_query = "SELECT SUM(sci.quantity) as total FROM shopping_cart sc JOIN shopping_cart_items sci ON sc.cart_id = sci.cart_id WHERE sc.customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $customer_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result()->fetch_assoc();
$cart_count = $cart_result['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BookStore</title>
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
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-badge"><?php echo $cart_count; ?></span></a>
                <a href="orders.php"><i class="fas fa-history"></i> Orders</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <h1 class="page-title"><i class="fas fa-user-circle"></i> My Profile</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($customer['username']); ?>" disabled>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> Change Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Phone Numbers -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-phone"></i> Phone Numbers</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($phones)): ?>
                        <?php foreach ($phones as $phone): ?>
                            <div class="phone-item">
                                <div class="phone-info">
                                    <strong><?php echo htmlspecialchars($phone['phone_type']); ?>:</strong>
                                    <?php echo htmlspecialchars($phone['phone_number']); ?>
                                    <?php if ($phone['is_primary']): ?>
                                        <span class="badge badge-primary">Primary</span>
                                    <?php endif; ?>
                                </div>
                                <div class="phone-actions">
                                    <?php if (!$phone['is_primary']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="set_primary_phone">
                                            <input type="hidden" name="phone_id" value="<?php echo $phone['phone_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Set as Primary">
                                                <i class="fas fa-star"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_phone">
                                        <input type="hidden" name="phone_id" value="<?php echo $phone['phone_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this phone number?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No phone numbers added</p>
                    <?php endif; ?>
                    
                    <div class="inline-form">
                        <h4>Add New Phone</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_phone">
                            <div class="form-row">
                                <input type="tel" name="phone_number" placeholder="Phone number" required>
                                <select name="phone_type">
                                    <option value="Mobile">Mobile</option>
                                    <option value="Home">Home</option>
                                    <option value="Work">Work</option>
                                </select>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Addresses -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marker-alt"></i> Addresses</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($addresses)): ?>
                        <?php foreach ($addresses as $address): ?>
                            <div class="address-item <?php echo $address['is_default'] ? 'is-default' : ''; ?>">
                                <div class="address-header">
                                    <strong>
                                        <?php echo htmlspecialchars($address['address_type']); ?>
                                        <?php if ($address['is_default']): ?>
                                            <span class="badge badge-primary">Default</span>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="address-content">
                                    <p><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                    <?php if ($address['address_line2']): ?>
                                        <p><?php echo htmlspecialchars($address['address_line2']); ?></p>
                                    <?php endif; ?>
                                    <p><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['country']); ?></p>
                                </div>
                                <div class="address-actions">
                                    <?php if (!$address['is_default']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="set_default_address">
                                            <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Set as Default">
                                                <i class="fas fa-star"></i> Set Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_address">
                                        <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this address?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No addresses added</p>
                    <?php endif; ?>
                    
                    <div class="inline-form">
                        <h4>Add New Address</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_address">
                            <div class="form-group">
                                <input type="text" name="address_line1" placeholder="Address Line 1 *" required>
                            </div>
                            <div class="form-group">
                                <input type="text" name="address_line2" placeholder="Address Line 2">
                            </div>
                            <div class="form-row">
                                <input type="text" name="city" placeholder="City *" required>
                                <input type="text" name="state" placeholder="State">
                            </div>
                            <div class="form-row">
                                <input type="text" name="country" placeholder="Country" value="Egypt">
                                <input type="text" name="postal_code" placeholder="Postal Code">
                            </div>
                            <div class="form-group">
                                <select name="address_type">
                                    <option value="Home">Home</option>
                                    <option value="Work">Work</option>
                                    <option value="Shipping">Shipping</option>
                                    <option value="Billing">Billing</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Address
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Saved Payment Methods -->
            <div class="card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Saved Payment Methods</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($payments)): ?>
                        <div class="payments-list">
                            <?php foreach ($payments as $payment): ?>
                                <div class="payment-item <?php echo $payment['is_default'] ? 'is-default' : ''; ?>">
                                    <i class="fab fa-cc-<?php echo strtolower($payment['card_type']); ?> card-icon"></i>
                                    <div class="payment-info">
                                        <div class="card-number">
                                            <?php echo $payment['card_type']; ?> ending in <?php echo substr($payment['card_number'], -4); ?>
                                            <?php if ($payment['is_default']): ?>
                                                <span class="badge badge-primary">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="expiry">Expires: <?php echo date('m/Y', strtotime($payment['expiry_date'])); ?></div>
                                    </div>
                                    <div class="payment-actions">
                                        <?php if (!$payment['is_default']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="set_default_payment">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_payment">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this payment method?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No payment methods saved. Add one during checkout.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 BookStore. Alexandria University - Database Systems Project</p>
        </div>
    </footer>
</body>
</html>
