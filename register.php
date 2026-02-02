<?php
/**
 * Customer Registration Page
 * Supports multi-valued attributes (phone, address)
 */

require_once 'config/database.php';
$conn = getConnection();

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $phone_type = $_POST['phone_type'] ?? 'Mobile';
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'Egypt');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $address_type = $_POST['address_type'] ?? 'Home';
    
    // Validation
    if (empty($username) || empty($password) || empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if username or email already exists
        $check_stmt = $conn->prepare("SELECT customer_id FROM customers WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert customer
                $insert_customer = $conn->prepare("
                    INSERT INTO customers (username, password_hash, first_name, last_name, email)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_customer->bind_param("sssss", $username, $password_hash, $first_name, $last_name, $email);
                $insert_customer->execute();
                $customer_id = $conn->insert_id;
                
                // Insert phone number if provided
                if (!empty($phone_number)) {
                    $insert_phone = $conn->prepare("
                        INSERT INTO customer_phones (customer_id, phone_number, phone_type, is_primary)
                        VALUES (?, ?, ?, 1)
                    ");
                    $insert_phone->bind_param("iss", $customer_id, $phone_number, $phone_type);
                    $insert_phone->execute();
                }
                
                // Insert address if provided
                if (!empty($address_line1) && !empty($city)) {
                    $insert_address = $conn->prepare("
                        INSERT INTO customer_addresses (customer_id, address_line1, address_line2, city, state, country, postal_code, address_type, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $insert_address->bind_param("isssssss", $customer_id, $address_line1, $address_line2, $city, $state, $country, $postal_code, $address_type);
                    $insert_address->execute();
                }
                
                // Create shopping cart for customer
                $create_cart = $conn->prepare("INSERT INTO shopping_cart (customer_id) VALUES (?)");
                $create_cart->bind_param("i", $customer_id);
                $create_cart->execute();
                
                $conn->commit();
                $success = 'Registration successful! You can now login.';
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BookStore</title>
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
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="auth-container register-container">
            <div class="auth-card wide">
                <h2><i class="fas fa-user-plus"></i> Create Account</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <p><a href="login.php">Click here to login</a></p>
                    </div>
                <?php else: ?>
                
                <form method="POST" class="auth-form register-form">
                    <!-- Account Information -->
                    <fieldset>
                        <legend><i class="fas fa-user"></i> Account Information</legend>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" required 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       placeholder="Choose a username">
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="your@email.com">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" required
                                       placeholder="Min. 6 characters">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       placeholder="Confirm your password">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                       placeholder="Your first name">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                       placeholder="Your last name">
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- Phone Information -->
                    <fieldset>
                        <legend><i class="fas fa-phone"></i> Phone Information</legend>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone_number">Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number" 
                                       value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                       placeholder="+20xxxxxxxxxx">
                            </div>
                            <div class="form-group">
                                <label for="phone_type">Phone Type</label>
                                <select id="phone_type" name="phone_type">
                                    <option value="Mobile" <?php echo ($_POST['phone_type'] ?? '') === 'Mobile' ? 'selected' : ''; ?>>Mobile</option>
                                    <option value="Home" <?php echo ($_POST['phone_type'] ?? '') === 'Home' ? 'selected' : ''; ?>>Home</option>
                                    <option value="Work" <?php echo ($_POST['phone_type'] ?? '') === 'Work' ? 'selected' : ''; ?>>Work</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- Address Information -->
                    <fieldset>
                        <legend><i class="fas fa-map-marker-alt"></i> Shipping Address</legend>
                        
                        <div class="form-group">
                            <label for="address_line1">Address Line 1</label>
                            <input type="text" id="address_line1" name="address_line1" 
                                   value="<?php echo htmlspecialchars($_POST['address_line1'] ?? ''); ?>"
                                   placeholder="Street address">
                        </div>
                        
                        <div class="form-group">
                            <label for="address_line2">Address Line 2</label>
                            <input type="text" id="address_line2" name="address_line2" 
                                   value="<?php echo htmlspecialchars($_POST['address_line2'] ?? ''); ?>"
                                   placeholder="Apartment, suite, unit, etc. (optional)">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>"
                                       placeholder="City">
                            </div>
                            <div class="form-group">
                                <label for="state">State/Province</label>
                                <input type="text" id="state" name="state" 
                                       value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>"
                                       placeholder="State or Province">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country" 
                                       value="<?php echo htmlspecialchars($_POST['country'] ?? 'Egypt'); ?>"
                                       placeholder="Country">
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>"
                                       placeholder="Postal code">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address_type">Address Type</label>
                            <select id="address_type" name="address_type">
                                <option value="Home">Home</option>
                                <option value="Work">Work</option>
                                <option value="Shipping">Shipping</option>
                                <option value="Billing">Billing</option>
                            </select>
                        </div>
                    </fieldset>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <?php endif; ?>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
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
