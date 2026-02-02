<?php
/**
 * Cart API
 * Handles AJAX requests for cart operations
 */

require_once '../config/database.php';

header('Content-Type: application/json');

$conn = getConnection();

// Check if logged in
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$customer_id = $_SESSION['customer_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addToCart($conn, $customer_id);
        break;
    case 'update':
        updateCartQuantity($conn, $customer_id);
        break;
    case 'remove':
        removeFromCart($conn, $customer_id);
        break;
    case 'get_count':
        getCartCount($conn, $customer_id);
        break;
    case 'get_total':
        getCartTotal($conn, $customer_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getOrCreateCart($conn, $customer_id) {
    $result = $conn->query("SELECT cart_id FROM shopping_cart WHERE customer_id = $customer_id");
    if ($result->num_rows === 0) {
        $conn->query("INSERT INTO shopping_cart (customer_id) VALUES ($customer_id)");
        return $conn->insert_id;
    }
    return $result->fetch_assoc()['cart_id'];
}

function addToCart($conn, $customer_id) {
    $isbn = $_POST['isbn'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if (empty($isbn)) {
        echo json_encode(['success' => false, 'message' => 'Invalid ISBN']);
        return;
    }
    
    // Check if book exists and has stock
    $book_stmt = $conn->prepare("SELECT title, quantity_in_stock FROM books WHERE isbn = ?");
    $book_stmt->bind_param("s", $isbn);
    $book_stmt->execute();
    $book = $book_stmt->get_result()->fetch_assoc();
    
    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        return;
    }
    
    if ($book['quantity_in_stock'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
        return;
    }
    
    $cart_id = getOrCreateCart($conn, $customer_id);
    
    // Check if item already in cart
    $check_stmt = $conn->prepare("SELECT quantity FROM shopping_cart_items WHERE cart_id = ? AND isbn = ?");
    $check_stmt->bind_param("is", $cart_id, $isbn);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update quantity
        $new_qty = $existing['quantity'] + $quantity;
        if ($new_qty > $book['quantity_in_stock']) {
            $new_qty = $book['quantity_in_stock'];
        }
        $update_stmt = $conn->prepare("UPDATE shopping_cart_items SET quantity = ? WHERE cart_id = ? AND isbn = ?");
        $update_stmt->bind_param("iis", $new_qty, $cart_id, $isbn);
        $update_stmt->execute();
    } else {
        // Insert new item
        $insert_stmt = $conn->prepare("INSERT INTO shopping_cart_items (cart_id, isbn, quantity) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("isi", $cart_id, $isbn, $quantity);
        $insert_stmt->execute();
    }
    
    // Get new cart count
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM shopping_cart_items WHERE cart_id = ?");
    $count_stmt->bind_param("i", $cart_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Added to cart',
        'cart_count' => $count
    ]);
}

function updateCartQuantity($conn, $customer_id) {
    $isbn = $_POST['isbn'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if (empty($isbn) || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    $cart_id = getOrCreateCart($conn, $customer_id);
    
    // Check stock
    $book_stmt = $conn->prepare("SELECT quantity_in_stock FROM books WHERE isbn = ?");
    $book_stmt->bind_param("s", $isbn);
    $book_stmt->execute();
    $book = $book_stmt->get_result()->fetch_assoc();
    
    if ($quantity > $book['quantity_in_stock']) {
        $quantity = $book['quantity_in_stock'];
    }
    
    $update_stmt = $conn->prepare("UPDATE shopping_cart_items SET quantity = ? WHERE cart_id = ? AND isbn = ?");
    $update_stmt->bind_param("iis", $quantity, $cart_id, $isbn);
    $update_stmt->execute();
    
    // Get new totals
    $totals = getCartTotals($conn, $cart_id);
    
    echo json_encode([
        'success' => true,
        'cart_count' => $totals['count'],
        'cart_total' => $totals['total']
    ]);
}

function removeFromCart($conn, $customer_id) {
    $isbn = $_POST['isbn'] ?? '';
    
    if (empty($isbn)) {
        echo json_encode(['success' => false, 'message' => 'Invalid ISBN']);
        return;
    }
    
    $cart_id = getOrCreateCart($conn, $customer_id);
    
    $delete_stmt = $conn->prepare("DELETE FROM shopping_cart_items WHERE cart_id = ? AND isbn = ?");
    $delete_stmt->bind_param("is", $cart_id, $isbn);
    $delete_stmt->execute();
    
    $totals = getCartTotals($conn, $cart_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item removed',
        'cart_count' => $totals['count'],
        'cart_total' => $totals['total']
    ]);
}

function getCartCount($conn, $customer_id) {
    $cart_id = getOrCreateCart($conn, $customer_id);
    $totals = getCartTotals($conn, $cart_id);
    
    echo json_encode([
        'success' => true,
        'cart_count' => $totals['count']
    ]);
}

function getCartTotal($conn, $customer_id) {
    $cart_id = getOrCreateCart($conn, $customer_id);
    $totals = getCartTotals($conn, $cart_id);
    
    echo json_encode([
        'success' => true,
        'cart_count' => $totals['count'],
        'cart_total' => $totals['total']
    ]);
}

function getCartTotals($conn, $cart_id) {
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as count FROM shopping_cart_items WHERE cart_id = ?");
    $count_stmt->bind_param("i", $cart_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    $total_stmt = $conn->prepare("
        SELECT SUM(b.selling_price * sci.quantity) as total 
        FROM shopping_cart_items sci 
        JOIN books b ON sci.isbn = b.isbn 
        WHERE sci.cart_id = ?
    ");
    $total_stmt->bind_param("i", $cart_id);
    $total_stmt->execute();
    $total = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    return ['count' => $count, 'total' => number_format($total, 2)];
}
?>
