<?php
/**
 * Admin Books Management
 * Add, edit, delete books with image upload
 * Support for multiple authors (existing or new)
 */

require_once '../config/database.php';
$conn = getConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $isbn = trim($_POST['isbn'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $publisher_id = (int)($_POST['publisher_id'] ?? 0);
        $publication_year = (int)($_POST['publication_year'] ?? date('Y'));
        $selling_price = (float)($_POST['selling_price'] ?? 0);
        $category = $_POST['category'] ?? '';
        $quantity = (int)($_POST['quantity_in_stock'] ?? 0);
        $threshold = (int)($_POST['minimum_threshold'] ?? 10);
        
        // Get authors data
        $existing_authors = $_POST['existing_authors'] ?? [];
        $new_authors = $_POST['new_authors'] ?? [];
        
        // Handle image upload
        $image_url = null;
        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];
            $file_type = $_FILES['book_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $extension = pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION);
                $image_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($title)) . '_' . time() . '.' . $extension;
                $upload_path = '../assets/images/books/' . $image_name;
                
                if (move_uploaded_file($_FILES['book_image']['tmp_name'], $upload_path)) {
                    $image_url = $image_name;
                }
            }
        }
        
        if (empty($isbn) || empty($title) || $publisher_id <= 0 || empty($category)) {
            $error = 'Please fill in all required fields';
        } elseif (empty($existing_authors) && empty(array_filter($new_authors))) {
            $error = 'Please add at least one author';
        } else {
            $conn->begin_transaction();
            try {
                // Insert book
                $insert_book = $conn->prepare("
                    INSERT INTO books (isbn, title, publisher_id, publication_year, selling_price, category, image_url, quantity_in_stock, minimum_threshold)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_book->bind_param("ssiidssis", $isbn, $title, $publisher_id, $publication_year, $selling_price, $category, $image_url, $quantity, $threshold);
                $insert_book->execute();
                
                // Handle existing authors
                if (!empty($existing_authors)) {
                    foreach ($existing_authors as $author_id) {
                        $author_id = (int)$author_id;
                        if ($author_id > 0) {
                            $link_author = $conn->prepare("INSERT INTO book_authors (isbn, author_id) VALUES (?, ?)");
                            $link_author->bind_param("si", $isbn, $author_id);
                            $link_author->execute();
                        }
                    }
                }
                
                // Handle new authors
                if (!empty($new_authors)) {
                    foreach ($new_authors as $author_name) {
                        $author_name = trim($author_name);
                        if (!empty($author_name)) {
                            // Check if author already exists
                            $check_author = $conn->prepare("SELECT author_id FROM authors WHERE author_name = ?");
                            $check_author->bind_param("s", $author_name);
                            $check_author->execute();
                            $author_result = $check_author->get_result();
                            
                            if ($author_result->num_rows > 0) {
                                $author_id = $author_result->fetch_assoc()['author_id'];
                            } else {
                                // Insert new author
                                $insert_author = $conn->prepare("INSERT INTO authors (author_name) VALUES (?)");
                                $insert_author->bind_param("s", $author_name);
                                $insert_author->execute();
                                $author_id = $conn->insert_id;
                            }
                            
                            // Link book to author (check if not already linked)
                            $check_link = $conn->prepare("SELECT * FROM book_authors WHERE isbn = ? AND author_id = ?");
                            $check_link->bind_param("si", $isbn, $author_id);
                            $check_link->execute();
                            if ($check_link->get_result()->num_rows === 0) {
                                $link_author = $conn->prepare("INSERT INTO book_authors (isbn, author_id) VALUES (?, ?)");
                                $link_author->bind_param("si", $isbn, $author_id);
                                $link_author->execute();
                            }
                        }
                    }
                }
                
                $conn->commit();
                $success = 'Book added successfully with ' . (count($existing_authors) + count(array_filter($new_authors))) . ' author(s)';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error adding book: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'edit') {
        $isbn = $_POST['isbn'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $publisher_id = (int)($_POST['publisher_id'] ?? 0);
        $publication_year = (int)($_POST['publication_year'] ?? date('Y'));
        $selling_price = (float)($_POST['selling_price'] ?? 0);
        $category = $_POST['category'] ?? '';
        $quantity = (int)($_POST['quantity_in_stock'] ?? 0);
        $threshold = (int)($_POST['minimum_threshold'] ?? 10);
        
        // Get authors data
        $existing_authors = $_POST['existing_authors'] ?? [];
        $new_authors = $_POST['new_authors'] ?? [];
        
        // Handle image upload
        $image_update = "";
        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];
            $file_type = $_FILES['book_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $extension = pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION);
                $image_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($title)) . '_' . time() . '.' . $extension;
                $upload_path = '../assets/images/books/' . $image_name;
                
                if (move_uploaded_file($_FILES['book_image']['tmp_name'], $upload_path)) {
                    $image_update = ", image_url = '$image_name'";
                }
            }
        }
        
        if (empty($existing_authors) && empty(array_filter($new_authors))) {
            $error = 'Please add at least one author';
        } else {
            $conn->begin_transaction();
            try {
                // Update book
                $update_query = "UPDATE books SET title = ?, publisher_id = ?, publication_year = ?, selling_price = ?, 
                                 category = ?, quantity_in_stock = ?, minimum_threshold = ? $image_update WHERE isbn = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("siidssis", $title, $publisher_id, $publication_year, $selling_price, $category, $quantity, $threshold, $isbn);
                $update_stmt->execute();
                
                // Remove all existing author links
                $delete_links = $conn->prepare("DELETE FROM book_authors WHERE isbn = ?");
                $delete_links->bind_param("s", $isbn);
                $delete_links->execute();
                
                // Add existing authors
                if (!empty($existing_authors)) {
                    foreach ($existing_authors as $author_id) {
                        $author_id = (int)$author_id;
                        if ($author_id > 0) {
                            $link_author = $conn->prepare("INSERT INTO book_authors (isbn, author_id) VALUES (?, ?)");
                            $link_author->bind_param("si", $isbn, $author_id);
                            $link_author->execute();
                        }
                    }
                }
                
                // Add new authors
                if (!empty($new_authors)) {
                    foreach ($new_authors as $author_name) {
                        $author_name = trim($author_name);
                        if (!empty($author_name)) {
                            // Check if author already exists
                            $check_author = $conn->prepare("SELECT author_id FROM authors WHERE author_name = ?");
                            $check_author->bind_param("s", $author_name);
                            $check_author->execute();
                            $author_result = $check_author->get_result();
                            
                            if ($author_result->num_rows > 0) {
                                $author_id = $author_result->fetch_assoc()['author_id'];
                            } else {
                                $insert_author = $conn->prepare("INSERT INTO authors (author_name) VALUES (?)");
                                $insert_author->bind_param("s", $author_name);
                                $insert_author->execute();
                                $author_id = $conn->insert_id;
                            }
                            
                            // Link book to author
                            $check_link = $conn->prepare("SELECT * FROM book_authors WHERE isbn = ? AND author_id = ?");
                            $check_link->bind_param("si", $isbn, $author_id);
                            $check_link->execute();
                            if ($check_link->get_result()->num_rows === 0) {
                                $link_author = $conn->prepare("INSERT INTO book_authors (isbn, author_id) VALUES (?, ?)");
                                $link_author->bind_param("si", $isbn, $author_id);
                                $link_author->execute();
                            }
                        }
                    }
                }
                
                $conn->commit();
                $success = 'Book updated successfully';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error updating book: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $isbn = $_POST['isbn'] ?? '';
        
        if (empty($isbn)) {
            $error = 'No ISBN provided for deletion';
        } else {
            // First check if book has any customer orders
            $check_orders = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE isbn = ?");
            $check_orders->bind_param("s", $isbn);
            $check_orders->execute();
            $order_count = $check_orders->get_result()->fetch_assoc()['count'];
            
            if ($order_count > 0) {
                $error = "Cannot delete this book because it has been ordered $order_count time(s). You can set stock to 0 instead to make it unavailable.";
            } else {
                // Remove from shopping carts first
                $delete_cart = $conn->prepare("DELETE FROM shopping_cart_items WHERE isbn = ?");
                $delete_cart->bind_param("s", $isbn);
                $delete_cart->execute();
                
                // Delete publisher orders
                $delete_pub_orders = $conn->prepare("DELETE FROM publisher_orders WHERE isbn = ?");
                $delete_pub_orders->bind_param("s", $isbn);
                $delete_pub_orders->execute();
                
                // Delete author links
                $delete_links = $conn->prepare("DELETE FROM book_authors WHERE isbn = ?");
                $delete_links->bind_param("s", $isbn);
                $delete_links->execute();
                
                // Finally delete the book
                $delete_stmt = $conn->prepare("DELETE FROM books WHERE isbn = ?");
                $delete_stmt->bind_param("s", $isbn);
                
                if ($delete_stmt->execute()) {
                    if ($delete_stmt->affected_rows > 0) {
                        $success = 'Book deleted successfully';
                    } else {
                        $error = 'Book not found or already deleted';
                    }
                } else {
                    $error = 'Error deleting book: ' . $conn->error;
                }
            }
        }
    }
    
    if ($action === 'update_stock') {
        $isbn = $_POST['isbn'] ?? '';
        $quantity = (int)($_POST['new_quantity'] ?? 0);
        
        $update_stmt = $conn->prepare("UPDATE books SET quantity_in_stock = ? WHERE isbn = ?");
        $update_stmt->bind_param("is", $quantity, $isbn);
        
        if ($update_stmt->execute()) {
            $success = 'Stock updated successfully';
        } else {
            $error = 'Error updating stock';
        }
    }
}

// Get all books with authors
$books_query = "
    SELECT b.*, p.publisher_name,
           GROUP_CONCAT(a.author_id) as author_ids,
           GROUP_CONCAT(a.author_name ORDER BY a.author_name SEPARATOR ', ') as authors
    FROM books b
    LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
    LEFT JOIN book_authors ba ON b.isbn = ba.isbn
    LEFT JOIN authors a ON ba.author_id = a.author_id
    GROUP BY b.isbn
    ORDER BY b.title
";
$books = $conn->query($books_query)->fetch_all(MYSQLI_ASSOC);

// Get all authors for dropdown
$authors = $conn->query("SELECT * FROM authors ORDER BY author_name")->fetch_all(MYSQLI_ASSOC);

// Get publishers for dropdown
$publishers = $conn->query("SELECT * FROM publishers ORDER BY publisher_name")->fetch_all(MYSQLI_ASSOC);

// Get categories
$categories = ['Science', 'Art', 'Religion', 'History', 'Geography'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - BookStore Admin</title>
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
                <a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="books.php" class="active"><i class="fas fa-book"></i> Books</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container admin-container">
        <h1 class="page-title"><i class="fas fa-book"></i> Manage Books</h1>
        
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
        
        <!-- Add Book Button -->
        <div class="actions-bar">
            <button class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Add New Book
            </button>
        </div>
        
        <!-- Books Table -->
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>ISBN</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Threshold</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr class="<?php echo $book['quantity_in_stock'] < $book['minimum_threshold'] ? 'low-stock-row' : ''; ?>">
                                <td class="book-thumb">
                                    <?php 
                                    $img_path = '../assets/images/books/' . ($book['image_url'] ?? 'default.png');
                                    if (!file_exists($img_path) || empty($book['image_url'])) {
                                        $img_path = '../assets/images/books/default.png';
                                    }
                                    ?>
                                    <img src="<?php echo $img_path; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                         style="width: 50px; height: 60px; object-fit: cover;">
                                </td>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['authors'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($book['publisher_name']); ?></td>
                                <td><?php echo $book['category']; ?></td>
                                <td>$<?php echo number_format($book['selling_price'], 2); ?></td>
                                <td class="<?php echo $book['quantity_in_stock'] < $book['minimum_threshold'] ? 'text-danger' : ''; ?>">
                                    <?php echo $book['quantity_in_stock']; ?>
                                </td>
                                <td><?php echo $book['minimum_threshold']; ?></td>
                                <td class="actions">
                                    <button class="btn btn-sm btn-info" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="showStockModal('<?php echo $book['isbn']; ?>', <?php echo $book['quantity_in_stock']; ?>)">
                                        <i class="fas fa-boxes"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this book?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="isbn" value="<?php echo $book['isbn']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Book Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2><i class="fas fa-plus"></i> Add New Book</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ISBN *</label>
                        <input type="text" name="isbn" required maxlength="13" placeholder="13-digit ISBN">
                    </div>
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="Book title">
                    </div>
                </div>
                
                <!-- Authors Section -->
                <div class="form-group">
                    <label><i class="fas fa-users"></i> Authors *</label>
                    <div class="authors-section">
                        <!-- Existing Authors -->
                        <div class="author-select-group">
                            <label class="sub-label">Select from existing authors:</label>
                            <div class="existing-authors-list" id="add-existing-authors">
                                <select class="author-select" name="existing_authors[]">
                                    <option value="">-- Select an author --</option>
                                    <?php foreach ($authors as $author): ?>
                                        <option value="<?php echo $author['author_id']; ?>">
                                            <?php echo htmlspecialchars($author['author_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addExistingAuthorSelect('add')">
                                <i class="fas fa-plus"></i> Add Another Existing Author
                            </button>
                        </div>
                        
                        <!-- New Authors -->
                        <div class="author-new-group">
                            <label class="sub-label">Or add new authors:</label>
                            <div class="new-authors-list" id="add-new-authors">
                                <input type="text" name="new_authors[]" placeholder="New author name">
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addNewAuthorInput('add')">
                                <i class="fas fa-plus"></i> Add Another New Author
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Publisher *</label>
                        <select name="publisher_id" required>
                            <option value="">Select Publisher</option>
                            <?php foreach ($publishers as $pub): ?>
                                <option value="<?php echo $pub['publisher_id']; ?>"><?php echo htmlspecialchars($pub['publisher_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Publication Year</label>
                        <input type="number" name="publication_year" value="<?php echo date('Y'); ?>" min="1000" max="2100">
                    </div>
                    <div class="form-group">
                        <label>Selling Price *</label>
                        <input type="number" name="selling_price" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Initial Stock</label>
                        <input type="number" name="quantity_in_stock" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Minimum Threshold</label>
                        <input type="number" name="minimum_threshold" value="10" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Book Image (PNG/JPEG)</label>
                    <input type="file" name="book_image" accept="image/png,image/jpeg,image/gif">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Add Book
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Book</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="isbn" id="edit_isbn">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                
                <!-- Authors Section -->
                <div class="form-group">
                    <label><i class="fas fa-users"></i> Authors *</label>
                    <div class="authors-section">
                        <!-- Existing Authors -->
                        <div class="author-select-group">
                            <label class="sub-label">Select from existing authors:</label>
                            <div class="existing-authors-list" id="edit-existing-authors">
                                <!-- Will be populated by JavaScript -->
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addExistingAuthorSelect('edit')">
                                <i class="fas fa-plus"></i> Add Another Existing Author
                            </button>
                        </div>
                        
                        <!-- New Authors -->
                        <div class="author-new-group">
                            <label class="sub-label">Or add new authors:</label>
                            <div class="new-authors-list" id="edit-new-authors">
                                <input type="text" name="new_authors[]" placeholder="New author name">
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addNewAuthorInput('edit')">
                                <i class="fas fa-plus"></i> Add Another New Author
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Publisher *</label>
                        <select name="publisher_id" id="edit_publisher" required>
                            <?php foreach ($publishers as $pub): ?>
                                <option value="<?php echo $pub['publisher_id']; ?>"><?php echo htmlspecialchars($pub['publisher_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" id="edit_category" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Publication Year</label>
                        <input type="number" name="publication_year" id="edit_year" min="1000" max="2100">
                    </div>
                    <div class="form-group">
                        <label>Selling Price *</label>
                        <input type="number" name="selling_price" id="edit_price" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="quantity_in_stock" id="edit_stock" min="0">
                    </div>
                    <div class="form-group">
                        <label>Minimum Threshold</label>
                        <input type="number" name="minimum_threshold" id="edit_threshold" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Update Book Image (PNG/JPEG)</label>
                    <input type="file" name="book_image" accept="image/png,image/jpeg,image/gif">
                    <small>Leave empty to keep current image</small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Update Book
                </button>
            </form>
        </div>
    </div>

    <!-- Stock Update Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content modal-small">
            <span class="close" onclick="closeModal('stockModal')">&times;</span>
            <h2><i class="fas fa-boxes"></i> Update Stock</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="isbn" id="stock_isbn">
                
                <div class="form-group">
                    <label>New Quantity</label>
                    <input type="number" name="new_quantity" id="stock_quantity" min="0" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Update Stock
                </button>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 BookStore Admin. Alexandria University - Database Systems Project</p>
        </div>
    </footer>

    <script>
        // Authors data from PHP
        const allAuthors = <?php echo json_encode($authors); ?>;
        
        function showAddModal() {
            // Reset the form
            document.querySelector('#addModal form').reset();
            // Reset existing authors list to single select
            document.getElementById('add-existing-authors').innerHTML = createAuthorSelect('');
            // Reset new authors list to single input
            document.getElementById('add-new-authors').innerHTML = '<input type="text" name="new_authors[]" placeholder="New author name">';
            document.getElementById('addModal').style.display = 'block';
        }
        
        function showEditModal(book) {
            document.getElementById('edit_isbn').value = book.isbn;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_publisher').value = book.publisher_id;
            document.getElementById('edit_category').value = book.category;
            document.getElementById('edit_year').value = book.publication_year;
            document.getElementById('edit_price').value = book.selling_price;
            document.getElementById('edit_stock').value = book.quantity_in_stock;
            document.getElementById('edit_threshold').value = book.minimum_threshold;
            
            // Set existing authors
            const existingAuthorsDiv = document.getElementById('edit-existing-authors');
            existingAuthorsDiv.innerHTML = '';
            
            if (book.author_ids) {
                const authorIds = book.author_ids.split(',');
                authorIds.forEach((authorId, index) => {
                    existingAuthorsDiv.innerHTML += createAuthorSelect(authorId);
                });
            } else {
                existingAuthorsDiv.innerHTML = createAuthorSelect('');
            }
            
            // Reset new authors
            document.getElementById('edit-new-authors').innerHTML = '<input type="text" name="new_authors[]" placeholder="New author name">';
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function createAuthorSelect(selectedId) {
            let html = '<div class="author-select-row"><select class="author-select" name="existing_authors[]">';
            html += '<option value="">-- Select an author --</option>';
            allAuthors.forEach(author => {
                const selected = author.author_id == selectedId ? 'selected' : '';
                html += `<option value="${author.author_id}" ${selected}>${escapeHtml(author.author_name)}</option>`;
            });
            html += '</select>';
            html += '<button type="button" class="btn btn-sm btn-danger" onclick="removeAuthorRow(this)"><i class="fas fa-times"></i></button>';
            html += '</div>';
            return html;
        }
        
        function addExistingAuthorSelect(mode) {
            const container = document.getElementById(mode + '-existing-authors');
            const div = document.createElement('div');
            div.innerHTML = createAuthorSelect('');
            container.appendChild(div.firstElementChild);
        }
        
        function addNewAuthorInput(mode) {
            const container = document.getElementById(mode + '-new-authors');
            const div = document.createElement('div');
            div.className = 'new-author-row';
            div.innerHTML = `
                <input type="text" name="new_authors[]" placeholder="New author name">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeAuthorRow(this)"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(div);
        }
        
        function removeAuthorRow(btn) {
            btn.parentElement.remove();
        }
        
        function showStockModal(isbn, currentQty) {
            document.getElementById('stock_isbn').value = isbn;
            document.getElementById('stock_quantity').value = currentQty;
            document.getElementById('stockModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <style>
        /* Additional styles for authors section */
        .authors-section {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .author-select-group, .author-new-group {
            margin-bottom: 1rem;
        }
        .sub-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.5rem;
            display: block;
        }
        .existing-authors-list, .new-authors-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .author-select-row, .new-author-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .author-select-row select, .new-author-row input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .author-select-group .btn, .author-new-group .btn {
            margin-top: 0.5rem;
        }
    </style>
</body>
</html>
