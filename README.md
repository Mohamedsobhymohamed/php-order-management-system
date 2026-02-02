# BookStore Order Processing System

## 📚 Project Overview

A complete online bookstore management system.

### Features

**Customer Features:**
- Browse and search books by title, category, ISBN, author
- User registration with multi-valued attributes (phones, addresses)
- Shopping cart management
- Checkout with credit card payment
- View order history
- Profile management

**Admin Features:**
- Dashboard with statistics
- Book management (CRUD with image upload)
- Publisher order management (confirm/cancel)
- Sales reports using stored procedures
- Low stock alerts

## 🚀 Installation

### Prerequisites
- XAMPP/WAMP/MAMP with PHP 7.4+ and MySQL 5.7+
- Web browser

### Step 1: Database Setup

1. Start MySQL server
2. Create database and import schema:

```sql
mysql -u root -p
CREATE DATABASE bookstore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookstore_db;
SOURCE bookstore_schema.sql;
```

### Step 2: Configure Database Connection

Edit `config/database.php`:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');  // Change to 3306 if using default MySQL port
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bookstore_db');
```

### Step 3: Deploy Files

Copy the entire `bookstore` folder to your web server's document root:
- XAMPP: `C:\xampp\htdocs\order_management_system\`
- WAMP: `C:\wamp64\www\order_management_system\`

### Step 4: Access the Application

Open your browser and navigate to:
```
http://localhost/order_management_system/
```

## 🔑 Demo Credentials

### Customer Account
- **Username:** john_doe
- **Password:** password123

### Admin Account
- **Username:** admin
- **Password:** admin123

## 📁 File Structure

```
order_management_system/
├── config/
│   └── database.php          # Database configuration
├── api/
│   └── cart.php              # Cart AJAX API
├── assets/
│   ├── css/style.css         # Main stylesheet
│   ├── js/main.js            # JavaScript functions
│   └── images/books/         # Book images
├── admin/
│   ├── login.php             # Admin login
│   ├── logout.php            # Admin logout
│   ├── dashboard.php         # Admin dashboard
│   ├── books.php             # Book management
│   ├── orders.php            # Order management
│   └── reports.php           # Sales reports
├── index.php                 # Home page / Book listing
├── login.php                 # Customer login
├── logout.php                # Customer logout (clears cart)
├── register.php              # Customer registration
├── profile.php               # Customer profile
├── cart.php                  # Shopping cart
├── checkout.php              # Checkout process
├── orders.php                # Order history
└── bookstore_schema.sql      # Complete database schema
```

## 📊 Database Schema

### Tables
- `publishers` - Publisher information
- `authors` - Author information
- `books` - Book catalog
- `book_authors` - Book-Author junction table
- `customers` - Customer accounts
- `customer_phones` - Customer phone numbers (multi-valued)
- `customer_addresses` - Customer addresses (multi-valued)
- `payment_methods` - Saved payment cards
- `shopping_cart` - Shopping carts
- `shopping_cart_items` - Cart items
- `customer_orders` - Customer orders
- `order_items` - Order line items
- `publisher_orders` - Restock orders from publishers
- `administrators` - Admin accounts

### Views
- `vw_books_full_details` - Books with authors and publishers
- `vw_customer_order_history` - Complete order history
- `vw_low_stock_books` - Books below threshold

### Triggers
1. `trg_before_book_update` - Prevents negative stock
2. `trg_after_book_update_reorder` - Auto-creates publisher orders
3. `trg_before_order_item_insert` - Calculates subtotals
4. `trg_after_order_item_insert` - Updates stock after sale
5. `trg_after_publisher_order_confirm` - Adds stock on confirmation

### Stored Procedures
1. `get_sales_previous_month()` - Monthly sales report
2. `get_sales_for_date(date)` - Daily sales report
3. `get_top_customers(months)` - Top customers report
4. `get_top_selling_books(months)` - Best sellers report
5. `get_book_order_count(isbn)` - Publisher order count

## ✅ Testing Checklist

### Registration Flow
- [ ] Register new account with phone and address
- [ ] Login with new credentials
- [ ] View profile page

### Shopping Flow
- [ ] Browse books on home page
- [ ] Search by title, category, author
- [ ] Add items to cart
- [ ] Update cart quantities
- [ ] Remove items from cart

### Checkout Flow
- [ ] Proceed to checkout
- [ ] Add new payment method
- [ ] Select shipping address
- [ ] Complete order
- [ ] View order confirmation

### Logout
- [ ] Logout clears cart items

### Admin Flow
- [ ] Login as admin
- [ ] View dashboard statistics
- [ ] Add new book with image
- [ ] Update book stock
- [ ] Confirm publisher order (stock increases)
- [ ] Generate reports

## 📝 Notes

1. **Image Upload:** When adding books, upload PNG/JPEG images. They're stored in `assets/images/books/`.

2. **Logout:** Per requirements, logging out removes all items from the cart.

3. **Stock Management:** Stock automatically decreases on order and increases when publisher orders are confirmed.

4. **Reports:** All reports use stored procedures for efficient data retrieval.



