
-- ALL DDL COLLECTED


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

DELIMITER $$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_book_order_count` (IN `p_isbn` VARCHAR(13))   BEGIN
    SELECT 
        b.isbn,
        b.title,
        COUNT(po.order_id) AS times_ordered,
        SUM(po.quantity_ordered) AS total_quantity_ordered
    FROM books b
    LEFT JOIN publisher_orders po ON b.isbn = po.isbn
    WHERE b.isbn = p_isbn
    GROUP BY b.isbn;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_sales_for_date` (IN `p_date` DATE)   BEGIN
    SELECT 
        b.title,
        SUM(oi.quantity) AS copies_sold,
        SUM(oi.subtotal) AS total_revenue
    FROM customer_orders co
    JOIN order_items oi ON co.order_id = oi.order_id
    JOIN books b ON oi.isbn = b.isbn
    WHERE DATE(co.order_date) = p_date
      AND co.order_status = 'Completed'
    GROUP BY b.isbn
    ORDER BY total_revenue DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_sales_previous_month` ()   BEGIN
    SELECT 
        DATE(co.order_date) AS sale_date,
        COUNT(DISTINCT co.order_id) AS total_orders,
        SUM(oi.subtotal) AS total_sales
    FROM customer_orders co
    JOIN order_items oi ON co.order_id = oi.order_id
    WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
      AND co.order_status = 'Completed'
    GROUP BY DATE(co.order_date)
    ORDER BY sale_date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_top_customers` (IN `p_months` INT)   BEGIN
    SELECT 
        c.customer_id,
        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
        c.email,
        COUNT(DISTINCT co.order_id) AS total_orders,
        SUM(co.total_amount) AS total_spent
    FROM customers c
    JOIN customer_orders co ON c.customer_id = co.customer_id
    WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL p_months MONTH)
      AND co.order_status = 'Completed'
    GROUP BY c.customer_id
    ORDER BY total_spent DESC
    LIMIT 5;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_top_selling_books` (IN `p_months` INT)   BEGIN
    SELECT 
        b.isbn,
        b.title,
        GROUP_CONCAT(DISTINCT a.author_name SEPARATOR ', ') AS authors,
        SUM(oi.quantity) AS copies_sold,
        SUM(oi.subtotal) AS total_revenue
    FROM books b
    JOIN order_items oi ON b.isbn = oi.isbn
    JOIN customer_orders co ON oi.order_id = co.order_id
    LEFT JOIN book_authors ba ON b.isbn = ba.isbn
    LEFT JOIN authors a ON ba.author_id = a.author_id
    WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL p_months MONTH)
      AND co.order_status = 'Completed'
    GROUP BY b.isbn
    ORDER BY copies_sold DESC
    LIMIT 10;
END$$

DELIMITER ;

CREATE TABLE `administrators` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `authors` (
  `author_id` int(11) NOT NULL,
  `author_name` varchar(200) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `books` (
  `isbn` varchar(13) NOT NULL,
  `title` varchar(300) NOT NULL,
  `publisher_id` int(11) NOT NULL,
  `publication_year` int(11) DEFAULT NULL CHECK (`publication_year` between 1000 and 2100),
  `selling_price` decimal(10,2) DEFAULT NULL CHECK (`selling_price` > 0),
  `category` enum('Science','Art','Religion','History','Geography') NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `quantity_in_stock` int(11) DEFAULT 0 CHECK (`quantity_in_stock` >= 0),
  `minimum_threshold` int(11) DEFAULT 10 CHECK (`minimum_threshold` >= 0),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER `trg_after_book_update_reorder` AFTER UPDATE ON `books` FOR EACH ROW BEGIN
    IF OLD.quantity_in_stock >= OLD.minimum_threshold 
       AND NEW.quantity_in_stock < NEW.minimum_threshold THEN
        INSERT INTO publisher_orders (isbn, quantity_ordered, order_status)
        VALUES (NEW.isbn, 50, 'Pending');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_before_book_update` BEFORE UPDATE ON `books` FOR EACH ROW BEGIN
    IF NEW.quantity_in_stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stock cannot be negative';
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `book_authors` (
  `isbn` varchar(13) NOT NULL,
  `author_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_addresses` (
  `address_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `address_line1` varchar(200) NOT NULL,
  `address_line2` varchar(200) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Egypt',
  `postal_code` varchar(20) DEFAULT NULL,
  `address_type` enum('Home','Work','Billing','Shipping') DEFAULT 'Home',
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `shipping_address_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) DEFAULT NULL CHECK (`total_amount` >= 0),
  `order_status` enum('Completed','Cancelled','Refunded') DEFAULT 'Completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_phones` (
  `phone_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `phone_type` enum('Mobile','Home','Work') DEFAULT 'Mobile',
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `isbn` varchar(13) NOT NULL,
  `quantity` int(11) DEFAULT NULL CHECK (`quantity` > 0),
  `unit_price` decimal(10,2) DEFAULT NULL CHECK (`unit_price` >= 0),
  `subtotal` decimal(10,2) DEFAULT NULL CHECK (`subtotal` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER `trg_after_order_item_insert` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    UPDATE books 
    SET quantity_in_stock = quantity_in_stock - NEW.quantity
    WHERE isbn = NEW.isbn;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_before_order_item_insert` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
    IF NEW.subtotal IS NULL THEN
        SET NEW.subtotal = NEW.quantity * NEW.unit_price;
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `payment_methods` (
  `payment_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `card_number` varchar(16) NOT NULL,
  `card_holder_name` varchar(200) NOT NULL,
  `expiry_date` date NOT NULL,
  `card_type` enum('Visa','MasterCard','AmEx','Other') DEFAULT 'Visa',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publishers` (
  `publisher_id` int(11) NOT NULL,
  `publisher_name` varchar(200) NOT NULL,
  `address` text NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publisher_orders` (
  `order_id` int(11) NOT NULL,
  `isbn` varchar(13) NOT NULL,
  `quantity_ordered` int(11) DEFAULT NULL CHECK (`quantity_ordered` > 0),
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_status` enum('Pending','Confirmed','Cancelled') DEFAULT 'Pending',
  `confirmed_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER `trg_after_publisher_order_confirm` AFTER UPDATE ON `publisher_orders` FOR EACH ROW BEGIN
    IF OLD.order_status = 'Pending' AND NEW.order_status = 'Confirmed' THEN
        UPDATE books 
        SET quantity_in_stock = quantity_in_stock + NEW.quantity_ordered
        WHERE isbn = NEW.isbn;
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `shopping_cart` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shopping_cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `isbn` varchar(13) NOT NULL,
  `quantity` int(11) DEFAULT NULL CHECK (`quantity` > 0),
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vw_books_full_details` (
`isbn` varchar(13)
,`title` varchar(300)
,`authors` mediumtext
,`publisher_name` varchar(200)
,`publication_year` int(11)
,`selling_price` decimal(10,2)
,`category` enum('Science','Art','Religion','History','Geography')
,`image_url` varchar(255)
,`quantity_in_stock` int(11)
,`minimum_threshold` int(11)
);

CREATE TABLE `vw_customer_order_history` (
`order_id` int(11)
,`customer_id` int(11)
,`customer_name` varchar(201)
,`customer_email` varchar(150)
,`order_date` timestamp
,`total_amount` decimal(10,2)
,`order_status` enum('Completed','Cancelled','Refunded')
,`isbn` varchar(13)
,`book_title` varchar(300)
,`quantity` int(11)
,`unit_price` decimal(10,2)
,`subtotal` decimal(10,2)
,`address_line1` varchar(200)
,`city` varchar(100)
,`country` varchar(100)
);

CREATE TABLE `vw_low_stock_books` (
`isbn` varchar(13)
,`title` varchar(300)
,`authors` mediumtext
,`publisher_name` varchar(200)
,`quantity_in_stock` int(11)
,`minimum_threshold` int(11)
,`reorder_quantity` bigint(12)
);

DROP TABLE IF EXISTS `vw_books_full_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_books_full_details`  AS SELECT `b`.`isbn` AS `isbn`, `b`.`title` AS `title`, group_concat(`a`.`author_name` order by `a`.`author_name` ASC separator ', ') AS `authors`, `p`.`publisher_name` AS `publisher_name`, `b`.`publication_year` AS `publication_year`, `b`.`selling_price` AS `selling_price`, `b`.`category` AS `category`, `b`.`image_url` AS `image_url`, `b`.`quantity_in_stock` AS `quantity_in_stock`, `b`.`minimum_threshold` AS `minimum_threshold` FROM (((`books` `b` left join `book_authors` `ba` on(`b`.`isbn` = `ba`.`isbn`)) left join `authors` `a` on(`ba`.`author_id` = `a`.`author_id`)) left join `publishers` `p` on(`b`.`publisher_id` = `p`.`publisher_id`)) GROUP BY `b`.`isbn` ;

DROP TABLE IF EXISTS `vw_customer_order_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_customer_order_history`  AS SELECT `co`.`order_id` AS `order_id`, `c`.`customer_id` AS `customer_id`, concat(`c`.`first_name`,' ',`c`.`last_name`) AS `customer_name`, `c`.`email` AS `customer_email`, `co`.`order_date` AS `order_date`, `co`.`total_amount` AS `total_amount`, `co`.`order_status` AS `order_status`, `oi`.`isbn` AS `isbn`, `b`.`title` AS `book_title`, `oi`.`quantity` AS `quantity`, `oi`.`unit_price` AS `unit_price`, `oi`.`subtotal` AS `subtotal`, `ca`.`address_line1` AS `address_line1`, `ca`.`city` AS `city`, `ca`.`country` AS `country` FROM ((((`customer_orders` `co` join `customers` `c` on(`co`.`customer_id` = `c`.`customer_id`)) join `order_items` `oi` on(`co`.`order_id` = `oi`.`order_id`)) join `books` `b` on(`oi`.`isbn` = `b`.`isbn`)) left join `customer_addresses` `ca` on(`co`.`shipping_address_id` = `ca`.`address_id`)) ;

DROP TABLE IF EXISTS `vw_low_stock_books`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_low_stock_books`  AS SELECT `b`.`isbn` AS `isbn`, `b`.`title` AS `title`, group_concat(`a`.`author_name` separator ', ') AS `authors`, `p`.`publisher_name` AS `publisher_name`, `b`.`quantity_in_stock` AS `quantity_in_stock`, `b`.`minimum_threshold` AS `minimum_threshold`, `b`.`minimum_threshold`- `b`.`quantity_in_stock` AS `reorder_quantity` FROM (((`books` `b` left join `book_authors` `ba` on(`b`.`isbn` = `ba`.`isbn`)) left join `authors` `a` on(`ba`.`author_id` = `a`.`author_id`)) left join `publishers` `p` on(`b`.`publisher_id` = `p`.`publisher_id`)) WHERE `b`.`quantity_in_stock` < `b`.`minimum_threshold` GROUP BY `b`.`isbn` ;

ALTER TABLE `administrators`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `authors`
  ADD PRIMARY KEY (`author_id`),
  ADD UNIQUE KEY `author_name` (`author_name`);

ALTER TABLE `books`
  ADD PRIMARY KEY (`isbn`),
  ADD KEY `publisher_id` (`publisher_id`);

ALTER TABLE `book_authors`
  ADD PRIMARY KEY (`isbn`,`author_id`),
  ADD KEY `author_id` (`author_id`);

ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `customer_id` (`customer_id`);

ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `shipping_address_id` (`shipping_address_id`);

ALTER TABLE `customer_phones`
  ADD PRIMARY KEY (`phone_id`),
  ADD KEY `customer_id` (`customer_id`);

ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `isbn` (`isbn`);

ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `customer_id` (`customer_id`);

ALTER TABLE `publishers`
  ADD PRIMARY KEY (`publisher_id`),
  ADD UNIQUE KEY `publisher_name` (`publisher_name`);

ALTER TABLE `publisher_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `isbn` (`isbn`);

ALTER TABLE `shopping_cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

ALTER TABLE `shopping_cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD UNIQUE KEY `cart_id` (`cart_id`,`isbn`),
  ADD KEY `isbn` (`isbn`);

ALTER TABLE `administrators`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `authors`
  MODIFY `author_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `customer_addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `customer_orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `customer_phones`
  MODIFY `phone_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `payment_methods`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `publishers`
  MODIFY `publisher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `publisher_orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `shopping_cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `shopping_cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`publisher_id`);

ALTER TABLE `book_authors`
  ADD CONSTRAINT `book_authors_ibfk_1` FOREIGN KEY (`isbn`) REFERENCES `books` (`isbn`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_authors_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `authors` (`author_id`) ON DELETE CASCADE;

ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `customer_addresses_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

ALTER TABLE `customer_orders`
  ADD CONSTRAINT `customer_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `customer_orders_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payment_methods` (`payment_id`),
  ADD CONSTRAINT `customer_orders_ibfk_3` FOREIGN KEY (`shipping_address_id`) REFERENCES `customer_addresses` (`address_id`);

ALTER TABLE `customer_phones`
  ADD CONSTRAINT `customer_phones_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`isbn`) REFERENCES `books` (`isbn`);

ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

ALTER TABLE `publisher_orders`
  ADD CONSTRAINT `publisher_orders_ibfk_1` FOREIGN KEY (`isbn`) REFERENCES `books` (`isbn`);

ALTER TABLE `shopping_cart`
  ADD CONSTRAINT `shopping_cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

ALTER TABLE `shopping_cart_items`
  ADD CONSTRAINT `shopping_cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `shopping_cart` (`cart_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shopping_cart_items_ibfk_2` FOREIGN KEY (`isbn`) REFERENCES `books` (`isbn`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
