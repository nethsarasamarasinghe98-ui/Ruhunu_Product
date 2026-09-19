<?php
session_start();

// Database Configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; 
$db_name = 'invoicemgsys';

// Auto-Setup Database & Connection
try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");

    // Create Tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user'
        );
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            address_line_1 VARCHAR(255) NULL,
            address_line_2 VARCHAR(255) NULL,
            textarea_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            free_shipping TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            invoice_date DATE NOT NULL,
            shipping_fee DECIMAL(10,2) DEFAULT 0.00,
            total_amount DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS bank_details (
            id INT PRIMARY KEY DEFAULT 1,
            bank_name VARCHAR(255),
            acc_name VARCHAR(255),
            acc_no VARCHAR(100),
            branch_name VARCHAR(255)
        );
        CREATE TABLE IF NOT EXISTS company_details (
            id INT PRIMARY KEY DEFAULT 1,
            company_name VARCHAR(255),
            address_line_1 VARCHAR(255),
            address_line_2 VARCHAR(255),
            mobile_number VARCHAR(50),
            whatsapp_number VARCHAR(50),
            textarea_1 TEXT,
            textarea_2 TEXT
        );
        CREATE TABLE IF NOT EXISTS invoice_templates (
            id INT PRIMARY KEY DEFAULT 1,
            logo_size VARCHAR(10) DEFAULT 'md',
            payment_box_scale INT DEFAULT 100,
            pos_top INT DEFAULT 0,
            pos_left INT DEFAULT 0,
            company_pos_top INT DEFAULT 0,
            company_pos_left INT DEFAULT 0,
            textarea_swap TINYINT(1) DEFAULT 0,
            watermark_image VARCHAR(255) DEFAULT '',
            watermark_opacity INT DEFAULT 15,
            watermark_size INT DEFAULT 300
        );
    ");

    // Safe Migrations & Seed Defaults
    try { $pdo->exec("ALTER TABLE customers ADD COLUMN address_line_1 VARCHAR(255) NULL, ADD COLUMN address_line_2 VARCHAR(255) NULL, ADD COLUMN textarea_notes TEXT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE products DROP COLUMN category"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN free_shipping TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN shipping_fee DECIMAL(10,2) DEFAULT 0.00"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE invoice_templates ADD COLUMN company_pos_top INT DEFAULT 0, ADD COLUMN company_pos_left INT DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE invoice_templates ADD COLUMN textarea_swap TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE invoice_templates ADD COLUMN watermark_image VARCHAR(255) DEFAULT '', ADD COLUMN watermark_opacity INT DEFAULT 15, ADD COLUMN watermark_size INT DEFAULT 300"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE company_details ADD COLUMN mobile_number VARCHAR(50), ADD COLUMN whatsapp_number VARCHAR(50)"); } catch (PDOException $e) {}

    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
    }

    // Seed default bank details
    $stmt = $pdo->query("SELECT COUNT(*) FROM bank_details WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO bank_details (id, bank_name, acc_name, acc_no, branch_name) 
                    VALUES (1, 'Bank of Ceylon (BOC)', 'Ruhunu Products', '1234567890', 'Matara Main Branch')");
    }

    // Seed default company details
    $stmt = $pdo->query("SELECT COUNT(*) FROM company_details WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO company_details (id, company_name, address_line_1, address_line_2, mobile_number, whatsapp_number, textarea_1, textarea_2) 
                    VALUES (1, 'RUHUNU PRODUCTS', 'Main Street, Matara', 'Southern Province, Sri Lanka', '+94 41 222 3333', '+94 71 234 5678', 'Thank you for your business!', 'Please make cheques payable to Ruhunu Products.')");
    }

    // Seed default template settings
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoice_templates WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO invoice_templates (id, logo_size, payment_box_scale, pos_top, pos_left, company_pos_top, company_pos_left, textarea_swap, watermark_image, watermark_opacity, watermark_size) VALUES (1, 'md', 100, 0, 0, 0, 0, 0, '', 15, 300)");
    }

    // Ensure admin account exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
        $stmt->execute([password_hash('1234', PASSWORD_BCRYPT)]);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch Current Details & Template Settings
$bankDetails = $pdo->query("SELECT * FROM bank_details WHERE id = 1")->fetch();
$companyDetails = $pdo->query("SELECT * FROM company_details WHERE id = 1")->fetch();
$templateSetting = $pdo->query("SELECT * FROM invoice_templates WHERE id = 1")->fetch();

// Save Bank Details Action (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bank_details']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("UPDATE bank_details SET bank_name = ?, acc_name = ?, acc_no = ?, branch_name = ? WHERE id = 1");
    $stmt->execute([$_POST['bank_name'], $_POST['acc_name'], $_POST['acc_no'], $_POST['branch_name']]);
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Save Invoice Template Settings & Company Info Action (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template_settings']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $logo_size = $_POST['logo_size'] ?? 'md';
    $payment_box_scale = intval($_POST['payment_box_scale'] ?? 100);
    $pos_top = intval($_POST['pos_top'] ?? 0);
    $pos_left = intval($_POST['pos_left'] ?? 0);
    $company_pos_top = intval($_POST['company_pos_top'] ?? 0);
    $company_pos_left = intval($_POST['company_pos_left'] ?? 0);
    $textarea_swap = isset($_POST['textarea_swap']) ? 1 : 0;
    
    $watermark_opacity = intval($_POST['watermark_opacity'] ?? 15);
    $watermark_size = intval($_POST['watermark_size'] ?? 300);
    $watermark_image = $templateSetting['watermark_image'];

    // Handle Watermark Image Upload
    if (isset($_FILES['watermark_file']) && $_FILES['watermark_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['watermark_file']['tmp_name'];
        $fileName = $_FILES['watermark_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'watermark_' . time() . '.' . $fileExtension;
            $uploadFileDir = './';
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $watermark_image = $newFileName;
            }
        }
    } elseif (isset($_POST['remove_watermark']) && $_POST['remove_watermark'] == '1') {
        $watermark_image = '';
    }

    // Update Template positions, scales & watermark configs
    $stmt = $pdo->prepare("UPDATE invoice_templates SET logo_size = ?, payment_box_scale = ?, pos_top = ?, pos_left = ?, company_pos_top = ?, company_pos_left = ?, textarea_swap = ?, watermark_image = ?, watermark_opacity = ?, watermark_size = ? WHERE id = 1");
    $stmt->execute([$logo_size, $payment_box_scale, $pos_top, $pos_left, $company_pos_top, $company_pos_left, $textarea_swap, $watermark_image, $watermark_opacity, $watermark_size]);

    // Update Company Details info fields
    $c_name = $_POST['company_name'] ?? '';
    $addr1 = $_POST['address_line_1'] ?? '';
    $addr2 = $_POST['address_line_2'] ?? '';
    $mobile = $_POST['mobile_number'] ?? '';
    $whatsapp = $_POST['whatsapp_number'] ?? '';
    $txt1 = $_POST['textarea_1'] ?? '';
    $txt2 = $_POST['textarea_2'] ?? '';

    $stmtComp = $pdo->prepare("UPDATE company_details SET company_name = ?, address_line_1 = ?, address_line_2 = ?, mobile_number = ?, whatsapp_number = ?, textarea_1 = ?, textarea_2 = ? WHERE id = 1");
    $stmtComp->execute([$c_name, $addr1, $addr2, $mobile, $whatsapp, $txt1, $txt2]);

    $template_success = "Invoice template and company details updated successfully!";
    // Refresh variables
    $templateSetting = $pdo->query("SELECT * FROM invoice_templates WHERE id = 1")->fetch();
    $companyDetails = $pdo->query("SELECT * FROM company_details WHERE id = 1")->fetch();
}

// Authentication & Password Reset Handling
$action = $_GET['action'] ?? '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = !empty($user['role']) ? $user['role'] : 'user';
        header("Location: index.php?page=dashboard");
        exit;
    } else {
        $login_error = "Invalid username or password.";
    }
}

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$new_hash, $user['id']]);
        $login_success = "Password updated successfully! Log in with your new password.";
    } else {
        $login_error = "Username not found.";
    }
}

if ($action === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Check Authentication for Protected Pages
$page = $_GET['page'] ?? 'dashboard';
if (!isset($_SESSION['user_id']) && $page !== 'login') {
    $page = 'login';
}

// POST Action Processors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    
    // Admin Creating a New User Account
    if (isset($_POST['create_user_account']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $new_uname = trim($_POST['new_username']);
        $new_upass = $_POST['new_password'];
        $new_urole = $_POST['new_role'] ?? 'user';

        if (!empty($new_uname) && !empty($new_upass)) {
            try {
                $hash = password_hash($new_upass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$new_uname, $hash, $new_urole]);
                $user_success = "User account created successfully!";
            } catch (PDOException $e) {
                $user_error = "Username already exists or database error.";
            }
        } else {
            $user_error = "Please fill in all fields.";
        }
    }

    // Personal Account Updates
    if (isset($_POST['update_account'])) {
        $new_username = trim($_POST['new_username']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $curr_user = $stmt->fetch();

        if ($curr_user && password_verify($current_password, $curr_user['password'])) {
            if (!empty($new_password)) {
                if ($new_password === $confirm_password) {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $upd->execute([$new_username, $new_hash, $_SESSION['user_id']]);
                    $_SESSION['username'] = $new_username;
                    $settings_success = "Account updated successfully!";
                } else {
                    $settings_error = "New passwords do not match.";
                }
            } else {
                $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $upd->execute([$new_username, $_SESSION['user_id']]);
                $_SESSION['username'] = $new_username;
                $settings_success = "Username updated successfully!";
            }
        } else {
            $settings_error = "Current password is incorrect.";
        }
    }

    // Customer Actions
    if (isset($_POST['customer_action'])) {
        $c_name = trim($_POST['name']);
        $c_addr1 = !empty($_POST['address_line_1']) ? trim($_POST['address_line_1']) : null;
        $c_addr2 = !empty($_POST['address_line_2']) ? trim($_POST['address_line_2']) : null;
        $c_notes = !empty($_POST['textarea_notes']) ? trim($_POST['textarea_notes']) : null;

        if ($_POST['customer_action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO customers (name, address_line_1, address_line_2, textarea_notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$c_name, $c_addr1, $c_addr2, $c_notes]);
        } elseif ($_POST['customer_action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, address_line_1 = ?, address_line_2 = ?, textarea_notes = ? WHERE id = ?");
            $stmt->execute([$c_name, $c_addr1, $c_addr2, $c_notes, $_POST['id']]);
        }
        header("Location: index.php?page=customers");
        exit;
    }

    // Product Actions
    if (isset($_POST['product_action'])) {
        $p_name = trim($_POST['name']);
        $p_desc = trim($_POST['description'] ?? '');
        $p_price = $_POST['price'];
        $p_shipping = isset($_POST['free_shipping']) ? 1 : 0;

        if ($_POST['product_action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, free_shipping) VALUES (?, ?, ?, ?)");
            $stmt->execute([$p_name, $p_desc, $p_price, $p_shipping]);
        } elseif ($_POST['product_action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, free_shipping = ? WHERE id = ?");
            $stmt->execute([$p_name, $p_desc, $p_price, $p_shipping, $_POST['id']]);
        }
        header("Location: index.php?page=products");
        exit;
    }

    // Invoice Actions
    if (isset($_POST['invoice_action'])) {
        $customer_id  = $_POST['customer_id'];
        $invoice_date = $_POST['invoice_date'];
        $shipping_fee = floatval($_POST['shipping_fee'] ?? 0);
        $descriptions = $_POST['description'];
        $quantities   = $_POST['quantity'];
        $prices       = $_POST['price'];

        $subtotal = 0;
        foreach ($quantities as $key => $qty) {
            $subtotal += ($qty * $prices[$key]);
        }
        $total_amount = $subtotal + $shipping_fee;

        if ($_POST['invoice_action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO invoices (customer_id, invoice_date, shipping_fee, total_amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$customer_id, $invoice_date, $shipping_fee, $total_amount]);
            $invoice_id = $pdo->lastInsertId();
        } elseif ($_POST['invoice_action'] === 'edit') {
            $invoice_id = $_POST['invoice_id'];
            $stmt = $pdo->prepare("UPDATE invoices SET customer_id = ?, invoice_date = ?, shipping_fee = ?, total_amount = ? WHERE id = ?");
            $stmt->execute([$customer_id, $invoice_date, $shipping_fee, $total_amount, $invoice_id]);
            
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoice_id]);
        }

        $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
        foreach ($descriptions as $key => $desc) {
            $qty   = $quantities[$key];
            $price = $prices[$key];
            $total = $qty * $price;
            $item_stmt->execute([$invoice_id, $desc, $qty, $price, $total]);
        }

        header("Location: index.php?page=manage-invoices");
        exit;
    }
}

// DELETE GET Handlers
if (isset($_SESSION['user_id'])) {
    if (isset($_GET['delete_customer'])) {
        $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$_GET['delete_customer']]);
        header("Location: index.php?page=customers");
        exit;
    }
    if (isset($_GET['delete_product'])) {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$_GET['delete_product']]);
        header("Location: index.php?page=products");
        exit;
    }
    if (isset($_GET['delete_invoice'])) {
        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$_GET['delete_invoice']]);
        header("Location: index.php?page=manage-invoices");
        exit;
    }
    if (isset($_GET['delete_user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $target_id = $_GET['delete_user'];
        if ($target_id != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
        }
        header("Location: index.php?page=manage-users");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyDetails['company_name']) ?> - Billing System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1e3d2f;
            --sidebar-brand: #142a20;
            --sidebar-link: #a5d6a7;
            --sidebar-hover: #2e5a44;
            --card-border: #2e7d32;
        }
        [data-bs-theme="dark"] {
            --sidebar-bg: #111a15;
            --sidebar-brand: #0a120e;
            --sidebar-link: #81c784;
            --sidebar-hover: #1b3d2b;
            --card-border: #4caf50;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; transition: background-color 0.3s, color 0.3s; }
        
        /* Updated Sidebar Style to allow independent scrolling and keep it sticky */
        .sidebar { 
            position: sticky; 
            top: 0; 
            height: 100vh; 
            overflow-y: auto; 
            background: var(--sidebar-bg); 
            color: #fff; 
        }

        .sidebar .brand { background: var(--sidebar-brand); padding: 15px; text-align: center; }
        .sidebar .brand img { max-height: 50px; width: auto; }
        .sidebar .nav-link { color: var(--sidebar-link); padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--sidebar-hover); color: #fff; }
        .sidebar .nav-link i { width: 25px; }
        .card-stat { border: none; border-left: 5px solid var(--card-border); box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-primary { background-color: #2e7d32; border-color: #2e7d32; }
        .btn-primary:hover { background-color: #1b5e20; border-color: #1b5e20; }
        [data-bs-theme="light"] .table-custom thead { background-color: #e8f5e9; color: #1b5e20; }
        [data-bs-theme="dark"] .table-custom thead { background-color: #1b3d2b; color: #a5d6a7; }
        
        .invoice-logo { width: auto; transition: max-height 0.2s ease-in-out; }
        .logo-sm { max-height: 50px !important; }
        .logo-md { max-height: 80px !important; }
        .logo-lg { max-height: 180px !important; }

        .invoice-card {
            position: relative;
            overflow: hidden;
            isolation: isolate; /* Creates an independent stacking context */
        }

        .invoice-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: -1; /* Keeps the watermark firmly behind all content */
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .invoice-watermark img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .invoice-card > *:not(.invoice-watermark) {
            position: relative;
            z-index: 1;
        }

        #paymentBox { 
            display: inline-block; 
            width: fit-content; 
            max-width: 100%; 
            position: relative;
            transform-origin: top left;
            transition: transform 0.1s ease, top 0.15s ease, left 0.15s ease; 
        }

        #companyHeaderBox {
            position: relative;
            transition: top 0.15s ease, left 0.15s ease;
        }

        @media print { .no-print { display: none !important; } .invoice-card { border: none !important; box-shadow: none !important; } }
    </style>
</head>
<body>

<?php if ($page === 'login'): ?>
    <!-- LOGIN VIEW -->
    <div class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
        <div class="card shadow-sm p-4 text-center" style="width: 400px; border-top: 5px solid #2e7d32;">
            <div class="mb-3">
                <img src="logo.png" alt="Company Logo" style="max-height: 90px; width: auto;" onerror="this.style.display='none'">
                <h4 class="fw-bold text-success mt-2"><?= htmlspecialchars($companyDetails['company_name']) ?></h4>
                <span class="text-muted small">Sign in to Billing Panel</span>
            </div>

            <?php if (isset($login_error)): ?><div class="alert alert-danger py-2 small"><?= $login_error ?></div><?php endif; ?>
            <?php if (isset($login_success)): ?><div class="alert alert-success py-2 small"><?= $login_success ?></div><?php endif; ?>

            <form method="POST" action="index.php?action=login" id="loginForm" class="text-start">
                <div class="mb-3">
                    <label class="form-label text-success fw-bold small">Username</label>
                    <input type="text" name="username" class="form-control" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-success fw-bold small">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password (default: 1234)" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold mb-2">Sign In</button>
                <div class="text-center"><a href="#" class="text-muted small" onclick="toggleForm('reset'); return false;">Change / Reset Password?</a></div>
            </form>

            <form method="POST" action="index.php?action=change_password" id="resetForm" class="text-start d-none">
                <div class="mb-3">
                    <label class="form-label text-success fw-bold small">Username</label>
                    <input type="text" name="username" class="form-control" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-success fw-bold small">New Password</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Enter new password">
                </div>
                <button type="submit" class="btn btn-warning w-100 py-2 fw-bold mb-2">Update Password</button>
                <div class="text-center"><a href="#" class="text-muted small" onclick="toggleForm('login'); return false;">Back to Login</a></div>
            </form>
        </div>
    </div>
    <script>
        function toggleForm(formType) {
            document.getElementById('loginForm').classList.toggle('d-none', formType === 'reset');
            document.getElementById('resetForm').classList.toggle('d-none', formType !== 'reset');
        }
    </script>
<?php else: ?>
    <!-- ADMIN PANEL -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-0 no-print">
                <div class="brand">
                    <img src="logo.png" alt="Company Logo" onerror="this.style.display='none'">
                    <div class="fw-bold text-success mt-1" style="font-size: 0.9rem;"><?= htmlspecialchars($companyDetails['company_name']) ?></div>
                </div>
                <ul class="nav flex-column mt-2">
                    <li class="nav-item"><a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= $page === 'create-invoice' ? 'active' : '' ?>" href="index.php?page=create-invoice"><i class="fa-solid fa-plus-circle"></i> Create Invoice</a></li>
                    <li class="nav-item"><a class="nav-link <?= $page === 'manage-invoices' ? 'active' : '' ?>" href="index.php?page=manage-invoices"><i class="fa-solid fa-list-check"></i> Manage Invoices</a></li>
                    <li class="nav-item"><a class="nav-link <?= $page === 'products' ? 'active' : '' ?>" href="index.php?page=products"><i class="fa-solid fa-box"></i> Products</a></li>
                    <li class="nav-item"><a class="nav-link <?= $page === 'customers' ? 'active' : '' ?>" href="index.php?page=customers"><i class="fa-solid fa-users"></i> Customers</a></li>
                    <li class="nav-item"><a class="nav-link <?= $page === 'user-account' ? 'active' : '' ?>" href="index.php?page=user-account"><i class="fa-solid fa-user-gear"></i> My Account</a></li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link <?= $page === 'manage-template' ? 'active' : '' ?>" href="index.php?page=manage-template"><i class="fa-solid fa-sliders"></i> Manage Template</a></li>
                        <li class="nav-item"><a class="nav-link <?= $page === 'manage-users' ? 'active' : '' ?>" href="index.php?page=manage-users"><i class="fa-solid fa-users-gear"></i> Manage Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#paymentDetailsModal"><i class="fa-solid fa-building-columns"></i> Payment Details</a></li>
                    <?php endif; ?>
                    <li class="nav-item mt-4"><a class="nav-link text-danger" href="index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 p-0">
                <!-- Topbar -->
                <div class="topbar p-3 d-flex justify-content-between align-items-center mb-4 border-bottom no-print">
                    <span class="h5 mb-0">
                        <i class="fa-solid fa-user-circle text-success me-2"></i>Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                        <span class="badge bg-success ms-2 text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
                    </span>
                    <div class="d-flex align-items-center gap-3">
                        <button id="themeToggleBtn" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="toggleTheme()"><i id="themeIcon" class="fa-solid fa-moon me-1"></i> <span id="themeText">Dark Mode</span></button>
                        <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-power-off me-1"></i> Logout</a>
                    </div>
                </div>

                <div class="px-3 pb-5">
                    <?php if ($page === 'dashboard'): ?>
                        <?php
                        $totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
                        $totalBills     = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
                        $totalIncome    = $pdo->query("SELECT SUM(total_amount) FROM invoices")->fetchColumn() ?: 0.00;
                        $recentInvoices = $pdo->query("SELECT invoices.id, invoices.invoice_date, invoices.total_amount, customers.name AS customer_name FROM invoices JOIN customers ON invoices.customer_id = customers.id ORDER BY invoices.id DESC LIMIT 5")->fetchAll();
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-gauge text-success me-2"></i>Dashboard</h2>
                            <a href="index.php?page=create-invoice" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Create Invoice</a>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4"><div class="card card-stat p-3"><span class="text-uppercase text-muted fw-bold small">Total Customers</span><h3 class="mb-0 fw-bold"><?= number_format($totalCustomers) ?></h3></div></div>
                            <div class="col-md-4"><div class="card card-stat p-3"><span class="text-uppercase text-muted fw-bold small">Total Bills</span><h3 class="mb-0 fw-bold"><?= number_format($totalBills) ?></h3></div></div>
                            <div class="col-md-4"><div class="card card-stat p-3"><span class="text-uppercase text-muted fw-bold small">Total Income (LKR)</span><h3 class="mb-0 text-success fw-bold">Rs. <?= number_format($totalIncome, 2) ?></h3></div></div>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3"><h5 class="card-title mb-0 fw-bold text-success"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Invoices</h5></div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-custom mb-0">
                                    <thead><tr><th>Bill #</th><th>Customer</th><th>Amount</th><th class="text-end">Action</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($recentInvoices as $inv): ?>
                                            <tr>
                                                <td><strong>#<?= $inv['id'] ?></strong></td>
                                                <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                                <td class="fw-bold text-success">Rs. <?= number_format($inv['total_amount'], 2) ?></td>
                                                <td class="text-end"><a href="index.php?page=view-invoice&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-print"></i> View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($page === 'manage-template' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- MANAGE TEMPLATE VIEW -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-sliders text-success me-2"></i>Manage Invoice Template & Company Details</h2>
                        </div>

                        <?php if (isset($template_success)): ?><div class="alert alert-success py-2"><?= $template_success ?></div><?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="save_template_settings" value="1">
                            
                            <div class="row g-4">
                                <!-- Configuration Controls Panel -->
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm p-4 h-100">
                                        <h5 class="fw-bold text-success mb-3"><i class="fa-solid fa-building me-1"></i> Company Details</h5>
                                        
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small">Company Name</label>
                                            <input type="text" name="company_name" id="inputCompanyName" class="form-control" value="<?= htmlspecialchars($companyDetails['company_name']) ?>" oninput="syncCompanyDetails()" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small">Address Line 1</label>
                                            <input type="text" name="address_line_1" id="inputAddr1" class="form-control" value="<?= htmlspecialchars($companyDetails['address_line_1']) ?>" oninput="syncCompanyDetails()">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small">Address Line 2</label>
                                            <input type="text" name="address_line_2" id="inputAddr2" class="form-control" value="<?= htmlspecialchars($companyDetails['address_line_2']) ?>" oninput="syncCompanyDetails()">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small"><i class="fa-solid fa-phone text-success me-1"></i> Mobile Number</label>
                                            <input type="text" name="mobile_number" id="inputMobile" class="form-control" value="<?= htmlspecialchars($companyDetails['mobile_number'] ?? '') ?>" oninput="syncCompanyDetails()">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small"><i class="fa-brands fa-whatsapp text-success me-1"></i> WhatsApp Number</label>
                                            <input type="text" name="whatsapp_number" id="inputWhatsapp" class="form-control" value="<?= htmlspecialchars($companyDetails['whatsapp_number'] ?? '') ?>" oninput="syncCompanyDetails()">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small">Text Area 1 (Notes / Footer)</label>
                                            <textarea name="textarea_1" id="inputTxt1" class="form-control" rows="2" oninput="syncCompanyDetails()"><?= htmlspecialchars($companyDetails['textarea_1']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Text Area 2 (Terms / Info)</label>
                                            <textarea name="textarea_2" id="inputTxt2" class="form-control" rows="2" oninput="syncCompanyDetails()"><?= htmlspecialchars($companyDetails['textarea_2']) ?></textarea>
                                        </div>

                                        <!-- Text Area Position Swap Option -->
                                        <div class="mb-3 border-top pt-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="textarea_swap" value="1" id="textareaSwapCheck" <?= !empty($templateSetting['textarea_swap']) ? 'checked' : '' ?> onchange="previewTextareaSwap(this.checked)">
                                                <label class="form-check-label fw-bold small" for="textareaSwapCheck">
                                                    Swap Text Area 1 & 2 Positions (Left / Right)
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Watermark Customization Settings -->
                                        <h5 class="fw-bold text-success mb-3 border-top pt-3"><i class="fa-solid fa-image me-1"></i> Watermark Background</h5>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small">Upload Watermark Image</label>
                                            <input type="file" name="watermark_file" id="watermarkFileInput" class="form-control form-control-sm" accept="image/*" onchange="previewWatermarkFile(event)">
                                        </div>
                                        <?php if (!empty($templateSetting['watermark_image'])): ?>
                                            <div class="mb-2 form-check">
                                                <input class="form-check-input" type="checkbox" name="remove_watermark" value="1" id="removeWatermarkCheck">
                                                <label class="form-check-label text-danger small fw-semibold" for="removeWatermarkCheck">Remove current watermark image</label>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold small">Opacity (<span id="opacityValLabel"><?= $templateSetting['watermark_opacity'] ?? 15 ?></span>%)</label>
                                            <input type="range" class="form-range" id="watermarkOpacitySlider" name="watermark_opacity" min="0" max="100" value="<?= $templateSetting['watermark_opacity'] ?? 15 ?>" oninput="previewWatermarkOpacity(this.value)">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Size Width (<span id="sizeValLabel"><?= $templateSetting['watermark_size'] ?? 300 ?></span>px)</label>
                                            <input type="range" class="form-range" id="watermarkSizeSlider" name="watermark_size" min="100" max="600" step="10" value="<?= $templateSetting['watermark_size'] ?? 300 ?>" oninput="previewWatermarkSize(this.value)">
                                        </div>

                                        <!-- Company Header Position Option -->
                                        <div class="mb-3 border-top pt-3">
                                            <label class="form-label fw-bold small">Company Header Position Offset</label>
                                            <div class="d-flex gap-2 mb-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgeCompanyPos('up')"><i class="fa-solid fa-arrow-up me-1"></i> Up</button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgeCompanyPos('down')"><i class="fa-solid fa-arrow-down me-1"></i> Down</button>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgeCompanyPos('left')"><i class="fa-solid fa-arrow-left me-1"></i> Left</button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgeCompanyPos('right')"><i class="fa-solid fa-arrow-right me-1"></i> Right</button>
                                            </div>
                                            <input type="hidden" id="companyPosTopInput" name="company_pos_top" value="<?= $templateSetting['company_pos_top'] ?>">
                                            <input type="hidden" id="companyPosLeftInput" name="company_pos_left" value="<?= $templateSetting['company_pos_left'] ?>">
                                        </div>

                                        <h5 class="fw-bold text-success mb-3 border-top pt-3"><i class="fa-solid fa-gears me-1"></i> Template & Layout</h5>
                                        
                                        <!-- Logo Size Option -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Logo Size</label>
                                            <select name="logo_size" id="templateLogoSizeSelect" class="form-select" onchange="previewLogoSize(this.value)">
                                                <option value="sm" <?= $templateSetting['logo_size'] === 'sm' ? 'selected' : '' ?>>Small (S)</option>
                                                <option value="md" <?= $templateSetting['logo_size'] === 'md' ? 'selected' : '' ?>>Medium (M)</option>
                                                <option value="lg" <?= $templateSetting['logo_size'] === 'lg' ? 'selected' : '' ?>>Large (L) - Maximum Size</option>
                                            </select>
                                        </div>

                                        <!-- Payment Details Box Size Scale -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Payment Details Size Scale (<span id="scaleValLabel"><?= $templateSetting['payment_box_scale'] ?></span>%)</label>
                                            <input type="range" class="form-range" id="templateSizeSlider" name="payment_box_scale" min="70" max="130" value="<?= $templateSetting['payment_box_scale'] ?>" oninput="previewBankSize(this.value)">
                                        </div>

                                        <!-- Position Controls (Top/Left) for Payment Box -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Payment Details Position Offset</label>
                                            <div class="d-flex gap-2 mb-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgePos('up')"><i class="fa-solid fa-arrow-up me-1"></i> Up</button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgePos('down')"><i class="fa-solid fa-arrow-down me-1"></i> Down</button>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgePos('left')"><i class="fa-solid fa-arrow-left me-1"></i> Left</button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="nudgePos('right')"><i class="fa-solid fa-arrow-right me-1"></i> Right</button>
                                            </div>
                                            <input type="hidden" id="posTopInput" name="pos_top" value="<?= $templateSetting['pos_top'] ?>">
                                            <input type="hidden" id="posLeftInput" name="pos_left" value="<?= $templateSetting['pos_left'] ?>">
                                        </div>

                                        <button type="button" class="btn btn-outline-danger btn-sm mb-4" onclick="resetTemplatePos()"><i class="fa-solid fa-rotate-left me-1"></i> Reset All Positions & Sizes</button>

                                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-auto"><i class="fa-solid fa-floppy-disk me-1"></i> Save Template Settings</button>
                                    </div>
                                </div>

                                <!-- Live Interactive Invoice Preview Panel -->
                                <div class="col-md-8">
                                    <div class="card border-0 shadow-sm p-4">
                                        <h6 class="text-muted fw-bold mb-3"><i class="fa-solid fa-eye me-1"></i> Live Layout Preview</h6>
                                        <div class="card invoice-card shadow-sm p-4 bg-white text-dark">
                                            <!-- Watermark Layer -->
                                            <div class="invoice-watermark" id="previewWatermarkContainer" style="opacity: <?= ($templateSetting['watermark_opacity'] ?? 15) / 100 ?>;">
                                                <?php if (!empty($templateSetting['watermark_image'])): ?>
                                                    <img src="<?= htmlspecialchars($templateSetting['watermark_image']) ?>" id="previewWatermarkImg" alt="Watermark" style="width: <?= $templateSetting['watermark_size'] ?? 300 ?>px;">
                                                <?php else: ?>
                                                    <img src="" id="previewWatermarkImg" alt="Watermark" style="width: <?= $templateSetting['watermark_size'] ?? 300 ?>px; display: none;">
                                                <?php endif; ?>
                                            </div>

                                            <!-- HEADER: Logo + Company Details (Left) | Invoice Number & Date Box (Right) -->
                                            <div id="companyHeaderBox" class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4" style="top: <?= $templateSetting['company_pos_top'] ?>px; left: <?= $templateSetting['company_pos_left'] ?>px;">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="logo.png" id="previewLogo" alt="Logo" class="invoice-logo logo-<?= $templateSetting['logo_size'] ?>" onerror="this.style.display='none'">
                                                    <div>
                                                        <h4 class="fw-bold text-success mb-1" id="prevCompName"><?= htmlspecialchars($companyDetails['company_name']) ?></h4>
                                                        <p class="text-muted small mb-0" id="prevAddr1"><?= htmlspecialchars($companyDetails['address_line_1']) ?></p>
                                                        <p class="text-muted small mb-0" id="prevAddr2"><?= htmlspecialchars($companyDetails['address_line_2']) ?></p>
                                                        <p class="text-muted small mb-0" id="prevMobileContainer" style="<?= empty($companyDetails['mobile_number']) ? 'display:none;' : '' ?>">
                                                            <i class="fa-solid fa-phone text-success me-1"></i><span id="prevMobile"><?= htmlspecialchars($companyDetails['mobile_number'] ?? '') ?></span>
                                                        </p>
                                                        <p class="text-muted small mb-0" id="prevWhatsappContainer" style="<?= empty($companyDetails['whatsapp_number']) ? 'display:none;' : '' ?>">
                                                            <i class="fa-brands fa-whatsapp text-success me-1"></i><span id="prevWhatsapp"><?= htmlspecialchars($companyDetails['whatsapp_number'] ?? '') ?></span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="border rounded p-3 bg-light text-end" style="min-width: 170px;">
                                                    <h5 class="fw-bold text-success mb-1">INVOICE</h5>
                                                    <span class="d-block text-muted small fw-semibold">Invoice #: <strong>#1001</strong></span>
                                                    <span class="d-block text-muted small">Date: <?= date('M d, Y') ?></span>
                                                </div>
                                            </div>
                                            
                                            <table class="table table-bordered align-middle mb-4">
                                                <thead class="table-light"><tr><th>Item Description</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                                                <tbody>
                                                    <tr>
                                                        <td>Sample Product Item</td>
                                                        <td class="text-center">2</td>
                                                        <td class="text-end">Rs. 1,500.00</td>
                                                        <td class="text-end">Rs. 3,000.00</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            
                                            <div class="text-end mb-4">
                                                <h4 class="text-success fw-bold">Total: Rs. 3,000.00</h4>
                                            </div>

                                            <?php 
                                            $isSwapped = !empty($templateSetting['textarea_swap']);
                                            ?>
                                            <div class="row mb-3" id="textareaRow">
                                                <div class="col-md-6" id="textareaCol1">
                                                    <p class="text-muted small mb-1 <?= $isSwapped ? 'text-end' : '' ?>" id="<?= $isSwapped ? 'prevTxt2' : 'prevTxt1' ?>" style="white-space: pre-line;"><?= htmlspecialchars($isSwapped ? $companyDetails['textarea_2'] : $companyDetails['textarea_1']) ?></p>
                                                </div>
                                                <div class="col-md-6 <?= $isSwapped ? '' : 'text-end' ?>" id="textareaCol2">
                                                    <p class="text-muted small mb-1 <?= $isSwapped ? '' : 'text-end' ?>" id="<?= $isSwapped ? 'prevTxt1' : 'prevTxt2' ?>" style="white-space: pre-line;"><?= htmlspecialchars($isSwapped ? $companyDetails['textarea_1'] : $companyDetails['textarea_2']) ?></p>
                                                </div>
                                            </div>

                                            <div class="mt-4 pt-3 border-top">
                                                <div id="previewPaymentBox" class="p-3 bg-light rounded border" style="position: relative; top: <?= $templateSetting['pos_top'] ?>px; left: <?= $templateSetting['pos_left'] ?>px; transform: scale(<?= $templateSetting['payment_box_scale'] / 100 ?>); transform-origin: top left;">
                                                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-building-columns me-1"></i> Bank / Payment Details:</h6>
                                                    <p class="mb-1"><strong>Bank Name:</strong> <?= htmlspecialchars($bankDetails['bank_name']) ?></p>
                                                    <p class="mb-1"><strong>Account Name:</strong> <?= htmlspecialchars($bankDetails['acc_name']) ?></p>
                                                    <p class="mb-1"><strong>Account Number:</strong> <?= htmlspecialchars($bankDetails['acc_no']) ?></p>
                                                    <p class="mb-0"><strong>Branch:</strong> <?= htmlspecialchars($bankDetails['branch_name']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <script>
                        let tTop = <?= $templateSetting['pos_top'] ?>;
                        let tLeft = <?= $templateSetting['pos_left'] ?>;
                        let cTop = <?= $templateSetting['company_pos_top'] ?>;
                        let cLeft = <?= $templateSetting['company_pos_left'] ?>;

                        function syncCompanyDetails() {
                            const cName = document.getElementById('inputCompanyName').value;
                            const addr1 = document.getElementById('inputAddr1').value;
                            const addr2 = document.getElementById('inputAddr2').value;
                            const mobile = document.getElementById('inputMobile').value;
                            const whatsapp = document.getElementById('inputWhatsapp').value;
                            const txt1 = document.getElementById('inputTxt1').value;
                            const txt2 = document.getElementById('inputTxt2').value;

                            document.getElementById('prevCompName').innerText = cName;
                            document.getElementById('prevAddr1').innerText = addr1;
                            document.getElementById('prevAddr2').innerText = addr2;
                            
                            const el1 = document.getElementById('prevTxt1');
                            const el2 = document.getElementById('prevTxt2');
                            if(el1) el1.innerText = txt1;
                            if(el2) el2.innerText = txt2;

                            const mobCont = document.getElementById('prevMobileContainer');
                            if (mobile.trim() !== '') {
                                document.getElementById('prevMobile').innerText = mobile;
                                mobCont.style.display = 'block';
                            } else {
                                mobCont.style.display = 'none';
                            }

                            const waCont = document.getElementById('prevWhatsappContainer');
                            if (whatsapp.trim() !== '') {
                                document.getElementById('prevWhatsapp').innerText = whatsapp;
                                waCont.style.display = 'block';
                            } else {
                                waCont.style.display = 'none';
                            }
                        }

                        function previewWatermarkOpacity(val) {
                            document.getElementById('opacityValLabel').innerText = val;
                            const container = document.getElementById('previewWatermarkContainer');
                            if (container) {
                                container.style.opacity = val / 100;
                            }
                        }

                        function previewWatermarkSize(val) {
                            document.getElementById('sizeValLabel').innerText = val;
                            const img = document.getElementById('previewWatermarkImg');
                            if (img) {
                                img.style.width = val + 'px';
                            }
                        }

                        function previewWatermarkFile(event) {
                            const file = event.target.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    const img = document.getElementById('previewWatermarkImg');
                                    img.src = e.target.result;
                                    img.style.display = 'block';
                                }
                                reader.readAsDataURL(file);
                            }
                        }

                        function previewTextareaSwap(isSwapped) {
                            const col1 = document.getElementById('textareaCol1');
                            const col2 = document.getElementById('textareaCol2');
                            const txt1 = document.getElementById('inputTxt1').value;
                            const txt2 = document.getElementById('inputTxt2').value;

                            if (isSwapped) {
                                col1.className = "col-md-6";
                                col1.innerHTML = `<p class="text-muted small mb-1 text-end" id="prevTxt2" style="white-space: pre-line;">${txt2}</p>`;
                                col2.className = "col-md-6";
                                col2.innerHTML = `<p class="text-muted small mb-1" id="prevTxt1" style="white-space: pre-line;">${txt1}</p>`;
                            } else {
                                col1.className = "col-md-6";
                                col1.innerHTML = `<p class="text-muted small mb-1" id="prevTxt1" style="white-space: pre-line;">${txt1}</p>`;
                                col2.className = "col-md-6 text-end";
                                col2.innerHTML = `<p class="text-muted small mb-1 text-end" id="prevTxt2" style="white-space: pre-line;">${txt2}</p>`;
                            }
                        }

                        function previewLogoSize(size) {
                            const logo = document.getElementById('previewLogo');
                            if(logo) {
                                logo.classList.remove('logo-sm', 'logo-md', 'logo-lg');
                                logo.classList.add('logo-' + size);
                            }
                        }

                        function previewBankSize(val) {
                            document.getElementById('scaleValLabel').innerText = val;
                            const box = document.getElementById('previewPaymentBox');
                            if(box) {
                                const scale = val / 100;
                                box.style.transform = `scale(${scale})`;
                                box.style.transformOrigin = 'top left';
                            }
                        }

                        function nudgePos(dir) {
                            const step = 15;
                            if(dir === 'up') tTop -= step;
                            if(dir === 'down') tTop += step;
                            if(dir === 'left') tLeft -= step;
                            if(dir === 'right') tLeft += step;

                            document.getElementById('posTopInput').value = tTop;
                            document.getElementById('posLeftInput').value = tLeft;

                            const box = document.getElementById('previewPaymentBox');
                            if(box) {
                                box.style.top = tTop + 'px';
                                box.style.left = tLeft + 'px';
                            }
                        }

                        function nudgeCompanyPos(dir) {
                            const step = 15;
                            if(dir === 'up') cTop -= step;
                            if(dir === 'down') cTop += step;
                            if(dir === 'left') cLeft -= step;
                            if(dir === 'right') cLeft += step;

                            document.getElementById('companyPosTopInput').value = cTop;
                            document.getElementById('companyPosLeftInput').value = cLeft;

                            const box = document.getElementById('companyHeaderBox');
                            if(box) {
                                box.style.top = cTop + 'px';
                                box.style.left = cLeft + 'px';
                            }
                        }

                        function resetTemplatePos() {
                            tTop = 0; tLeft = 0;
                            cTop = 0; cLeft = 0;
                            document.getElementById('posTopInput').value = 0;
                            document.getElementById('posLeftInput').value = 0;
                            document.getElementById('companyPosTopInput').value = 0;
                            document.getElementById('companyPosLeftInput').value = 0;
                            document.getElementById('templateSizeSlider').value = 100;
                            previewBankSize(100);
                            document.getElementById('templateLogoSizeSelect').value = 'md';
                            previewLogoSize('md');
                            document.getElementById('textareaSwapCheck').checked = false;
                            previewTextareaSwap(false);
                            
                            const box = document.getElementById('previewPaymentBox');
                            if(box) { box.style.top = '0px'; box.style.left = '0px'; }
                            const cBox = document.getElementById('companyHeaderBox');
                            if(cBox) { cBox.style.top = '0px'; cBox.style.left = '0px'; }
                        }

                        document.addEventListener('DOMContentLoaded', () => {
                            previewBankSize(<?= $templateSetting['payment_box_scale'] ?>);
                        });
                        </script>

                    <?php elseif ($page === 'user-account'): ?>
                        <!-- USER ACCOUNT SETTINGS VIEW -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-user-gear text-success me-2"></i>My Account Settings</h2>
                        </div>
                        <div class="card border-0 shadow-sm" style="max-width: 600px;">
                            <div class="card-body p-4">
                                <?php if (isset($settings_success)): ?><div class="alert alert-success py-2"><?= $settings_success ?></div><?php endif; ?>
                                <?php if (isset($settings_error)): ?><div class="alert alert-danger py-2"><?= $settings_error ?></div><?php endif; ?>

                                <form method="POST">
                                    <input type="hidden" name="update_account" value="1">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Username</label>
                                        <input type="text" name="new_username" class="form-control" value="<?= htmlspecialchars($_SESSION['username']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Current Password <span class="text-danger">*Required to authorize changes</span></label>
                                        <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                                    </div>
                                    <hr class="my-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">New Password <span class="text-muted">(Optional)</span></label>
                                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password">
                                    </div>
                                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($page === 'manage-users' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- ADMIN USER MANAGEMENT VIEW -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-users-gear text-success me-2"></i>Manage User Accounts</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fa-solid fa-user-plus me-1"></i> Create User Account</button>
                        </div>

                        <?php if (isset($user_success)): ?><div class="alert alert-success py-2"><?= $user_success ?></div><?php endif; ?>
                        <?php if (isset($user_error)): ?><div class="alert alert-danger py-2"><?= $user_error ?></div><?php endif; ?>

                        <?php $all_users = $pdo->query("SELECT id, username, role FROM users ORDER BY id ASC")->fetchAll(); ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <table class="table table-hover table-custom mb-0">
                                    <thead><tr><th>ID</th><th>Username</th><th>Role</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($all_users as $u): ?>
                                            <tr>
                                                <td><strong>#<?= $u['id'] ?></strong></td>
                                                <td><?= htmlspecialchars($u['username']) ?></td>
                                                <td><span class="badge bg-<?= ($u['role'] ?? '') === 'admin' ? 'success' : 'secondary' ?>"><?= strtoupper($u['role'] ?? 'user') ?></span></td>
                                                <td class="text-end">
                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <a href="index.php?page=manage-users&delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user account?')"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Current Session</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($page === 'customers'): ?>
                        <?php $customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll(); ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-users text-success me-2"></i>Manage Customers</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal"><i class="fa-solid fa-user-plus me-1"></i> Add Customer</button>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <table class="table table-hover table-custom mb-0">
                                    <thead><tr><th>ID</th><th>Name (Required)</th><th>Address Line 1</th><th>Address Line 2</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($customers as $c): ?>
                                            <tr>
                                                <td><strong>#<?= $c['id'] ?></strong></td>
                                                <td><?= htmlspecialchars($c['name']) ?></td>
                                                <td><?= !empty($c['address_line_1']) ? htmlspecialchars($c['address_line_1']) : '<span class="text-muted fst-italic">N/A</span>' ?></td>
                                                <td><?= !empty($c['address_line_2']) ? htmlspecialchars($c['address_line_2']) : '<span class="text-muted fst-italic">N/A</span>' ?></td>
                                                <td><?= !empty($c['textarea_notes']) ? htmlspecialchars($c['textarea_notes']) : '<span class="text-muted fst-italic">No notes</span>' ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" onclick='editCustomer(<?= json_encode($c) ?>)' data-bs-toggle="modal" data-bs-target="#editCustomerModal"><i class="fa-solid fa-pen"></i> Edit</button>
                                                    <a href="index.php?delete_customer=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete customer?')"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($page === 'products'): ?>
                        <?php $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(); ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-box text-success me-2"></i>Manage Products</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fa-solid fa-plus me-1"></i> Add Product</button>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <table class="table table-hover table-custom mb-0">
                                    <thead><tr><th>ID</th><th>Product Name</th><th>Description</th><th>Unit Price</th><th>Free Shipping</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($products as $p): ?>
                                            <tr>
                                                <td><strong>#<?= $p['id'] ?></strong></td>
                                                <td><?= htmlspecialchars($p['name']) ?></td>
                                                <td><?= htmlspecialchars($p['description']) ?></td>
                                                <td class="fw-bold text-success">Rs. <?= number_format($p['price'], 2) ?></td>
                                                <td>
                                                    <?php if (!empty($p['free_shipping'])): ?>
                                                        <span class="badge bg-success">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" onclick='editProduct(<?= json_encode($p) ?>)' data-bs-toggle="modal" data-bs-target="#editProductModal"><i class="fa-solid fa-pen"></i> Edit</button>
                                                    <a href="index.php?delete_product=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete product?')"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($page === 'create-invoice' || $page === 'edit-invoice'): ?>
                        <?php
                        $isEdit = ($page === 'edit-invoice');
                        $invoice = null;
                        $existingItems = [];
                        if ($isEdit && isset($_GET['id'])) {
                            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
                            $stmt->execute([$_GET['id']]);
                            $invoice = $stmt->fetch();
                            $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
                            $itemStmt->execute([$_GET['id']]);
                            $existingItems = $itemStmt->fetchAll();
                        }
                        $customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
                        $products  = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll();
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-file-circle-plus text-success me-2"></i><?= $isEdit ? 'Edit Invoice #'.$invoice['id'] : 'Create New Invoice' ?></h2>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="invoice_action" value="<?= $isEdit ? 'edit' : 'create' ?>">
                            <?php if ($isEdit): ?><input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>"><?php endif; ?>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">Customer</label>
                                        <select name="customer_id" class="form-select" required>
                                            <option value="">-- Choose Customer --</option>
                                            <?php foreach ($customers as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= ($isEdit && $invoice['customer_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">Invoice Date</label>
                                        <input type="date" name="invoice_date" class="form-control" value="<?= $isEdit ? $invoice['invoice_date'] : date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white py-3"><h5 class="card-title mb-0 fw-bold text-success">Invoice Items</h5></div>
                                <div class="card-body p-0">
                                    <table class="table table-hover table-custom mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:40%;">Description / Select Catalog Product</th>
                                                <th style="width:15%;">Quantity</th>
                                                <th style="width:20%;">Price (Rs.)</th>
                                                <th style="width:20%;">Total (Rs.)</th>
                                                <th style="width:5%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsBody">
                                            <?php if ($isEdit && count($existingItems) > 0): ?>
                                                <?php foreach ($existingItems as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <select class="form-select product-select mb-2" onchange="autoFillProduct(this)">
                                                                <option value="">-- Catalog Product --</option>
                                                                <?php foreach ($products as $p): ?>
                                                                    <option value="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <input type="text" name="description[]" class="form-control item-desc" value="<?= htmlspecialchars($item['description']) ?>" required>
                                                        </td>
                                                        <td><input type="number" name="quantity[]" class="form-control item-qty" value="<?= $item['quantity'] ?>" min="1" oninput="calculateTotal()" required></td>
                                                        <td><input type="number" step="0.01" name="price[]" class="form-control item-price" value="<?= $item['price'] ?>" oninput="calculateTotal()" required></td>
                                                        <td><input type="text" class="form-control item-total" value="<?= number_format($item['total'], 2) ?>" readonly></td>
                                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td>
                                                        <select class="form-select product-select mb-2" onchange="autoFillProduct(this)">
                                                            <option value="">-- Catalog Product --</option>
                                                            <?php foreach ($products as $p): ?>
                                                                <option value="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="text" name="description[]" class="form-control item-desc" required placeholder="Item description...">
                                                    </td>
                                                    <td><input type="number" name="quantity[]" class="form-control item-qty" value="1" min="1" oninput="calculateTotal()" required></td>
                                                    <td><input type="number" step="0.01" name="price[]" class="form-control item-price" value="0.00" oninput="calculateTotal()" required></td>
                                                    <td><input type="text" class="form-control item-total" value="0.00" readonly></td>
                                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-success" onclick="addRow()"><i class="fa-solid fa-plus me-1"></i> Add Row</button>
                                    
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="shippingFee" class="fw-bold small mb-0">Shipping Fee (Rs.):</label>
                                        <input type="number" step="0.01" min="0" id="shippingFee" name="shipping_fee" class="form-control" style="width: 130px;" value="<?= $isEdit ? ($invoice['shipping_fee'] ?? '0.00') : '0.00' ?>" oninput="calculateTotal()">
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4 p-3 text-end">
                                <span class="fs-5 fw-bold me-2">Grand Total:</span>
                                <span class="fs-4 fw-bold text-success" id="grandTotal">Rs. 0.00</span>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg px-5 float-end"><i class="fa-solid fa-check me-1"></i> Save Invoice</button>
                        </form>

                    <?php elseif ($page === 'manage-invoices'): ?>
                        <?php
                        $invoices = $pdo->query("SELECT invoices.*, customers.name AS customer_name FROM invoices JOIN customers ON invoices.customer_id = customers.id ORDER BY invoices.id DESC")->fetchAll();
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fa-solid fa-list-check text-success me-2"></i>Manage Invoices</h2>
                            <a href="index.php?page=create-invoice" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Create Invoice</a>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <table class="table table-hover table-custom mb-0">
                                    <thead><tr><th>Invoice #</th><th>Customer Name</th><th>Date</th><th>Shipping</th><th>Total Amount</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td><strong>#<?= $inv['id'] ?></strong></td>
                                                <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                                <td><?= date('M d, Y', strtotime($inv['invoice_date'])) ?></td>
                                                <td>Rs. <?= number_format($inv['shipping_fee'] ?? 0, 2) ?></td>
                                                <td class="fw-bold text-success">Rs. <?= number_format($inv['total_amount'], 2) ?></td>
                                                <td class="text-end">
                                                    <a href="index.php?page=view-invoice&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-success me-1"><i class="fa-solid fa-print"></i> View</a>
                                                    <a href="index.php?page=edit-invoice&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-pen"></i> Edit</a>
                                                    <a href="index.php?delete_invoice=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete invoice?')"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($page === 'view-invoice'): ?>
                        <?php
                        $stmt = $pdo->prepare("SELECT invoices.*, customers.name AS customer_name, customers.address_line_1, customers.address_line_2, customers.textarea_notes FROM invoices JOIN customers ON invoices.customer_id = customers.id WHERE invoices.id = ?");
                        $stmt->execute([$_GET['id']]);
                        $inv = $stmt->fetch();

                        $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
                        $itemsStmt->execute([$_GET['id']]);
                        $items = $itemsStmt->fetchAll();
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                            <a href="index.php?page=manage-invoices" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                            
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <button onclick="window.print()" class="btn btn-success ms-2"><i class="fa-solid fa-print me-1"></i> Print Invoice</button>
                            </div>
                        </div>

                        <div class="card invoice-card shadow-sm p-4">
                            <!-- Watermark Layer -->
                            <?php if (!empty($templateSetting['watermark_image'])): ?>
                                <div class="invoice-watermark" style="opacity: <?= ($templateSetting['watermark_opacity'] ?? 15) / 100 ?>;">
                                    <img src="<?= htmlspecialchars($templateSetting['watermark_image']) ?>" alt="Watermark" style="width: <?= $templateSetting['watermark_size'] ?? 300 ?>px;">
                                </div>
                            <?php endif; ?>

                            <!-- HEADER: Logo + Company Details (Left) | Invoice Number & Date Box (Right) -->
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4" style="position: relative; top: <?= $templateSetting['company_pos_top'] ?>px; left: <?= $templateSetting['company_pos_left'] ?>px;">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="logo.png" alt="Company Logo" class="invoice-logo logo-<?= $templateSetting['logo_size'] ?>" onerror="this.style.display='none'">
                                    <div>
                                        <h4 class="fw-bold text-success mb-1"><?= htmlspecialchars($companyDetails['company_name']) ?></h4>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($companyDetails['address_line_1']) ?></p>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($companyDetails['address_line_2']) ?></p>
                                        <?php if (!empty($companyDetails['mobile_number'])): ?>
                                            <p class="text-muted small mb-0"><i class="fa-solid fa-phone text-success me-1"></i><?= htmlspecialchars($companyDetails['mobile_number']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($companyDetails['whatsapp_number'])): ?>
                                            <p class="text-muted small mb-0"><i class="fa-brands fa-whatsapp text-success me-1"></i><?= htmlspecialchars($companyDetails['whatsapp_number']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="border rounded p-3 bg-light text-end" style="min-width: 170px;">
                                    <h5 class="fw-bold text-success mb-1">INVOICE</h5>
                                    <span class="d-block text-muted small fw-semibold">Invoice #: <strong>#<?= $inv['id'] ?></strong></span>
                                    <span class="d-block text-muted small">Date: <?= date('M d, Y', strtotime($inv['invoice_date'])) ?></span>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-6">
                                    <strong>Billed To:</strong><br>
                                    <?= htmlspecialchars($inv['customer_name']) ?><br>
                                    <?php if (!empty($inv['address_line_1'])): ?><?= htmlspecialchars($inv['address_line_1']) ?><br><?php endif; ?>
                                    <?php if (!empty($inv['address_line_2'])): ?><?= htmlspecialchars($inv['address_line_2']) ?><br><?php endif; ?>
                                    <?php if (!empty($inv['textarea_notes'])): ?><span class="text-muted small"><?= nl2br(htmlspecialchars($inv['textarea_notes'])) ?></span><br><?php endif; ?>
                                </div>
                            </div>
                            <table class="table table-bordered align-middle mb-4">
                                <thead class="table-light"><tr><th>Item Description</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($it['description']) ?></td>
                                            <td class="text-center"><?= $it['quantity'] ?></td>
                                            <td class="text-end">Rs. <?= number_format($it['price'], 2) ?></td>
                                            <td class="text-end">Rs. <?= number_format($it['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="row mb-4">
                                <div class="col-12 text-end">
                                    <?php if (!empty($inv['shipping_fee']) && $inv['shipping_fee'] > 0): ?>
                                        <p class="mb-1 text-muted">Shipping Fee: Rs. <?= number_format($inv['shipping_fee'], 2) ?></p>
                                    <?php endif; ?>
                                    <h4 class="text-success fw-bold border-top pt-2 d-inline-block">Total: Rs. <?= number_format($inv['total_amount'], 2) ?></h4>
                                </div>
                            </div>

                            <?php 
                            $isSwapped = !empty($templateSetting['textarea_swap']);
                            $leftTxt = $isSwapped ? $companyDetails['textarea_2'] : $companyDetails['textarea_1'];
                            $rightTxt = $isSwapped ? $companyDetails['textarea_1'] : $companyDetails['textarea_2'];
                            ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="text-muted small mb-1 <?= $isSwapped ? 'text-end' : '' ?>" style="white-space: pre-line;"><?= htmlspecialchars($leftTxt) ?></p>
                                </div>
                                <div class="col-md-6 <?= $isSwapped ? '' : 'text-end' ?>">
                                    <p class="text-muted small mb-1 <?= $isSwapped ? '' : 'text-end' ?>" style="white-space: pre-line;"><?= htmlspecialchars($rightTxt) ?></p>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top">
                                <div id="paymentBox" class="p-3 bg-light rounded border" style="top: <?= $templateSetting['pos_top'] ?>px; left: <?= $templateSetting['pos_left'] ?>px; transform: scale(<?= $templateSetting['payment_box_scale'] / 100 ?>); transform-origin: top left;">
                                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-building-columns me-1"></i> Bank / Payment Details:</h6>
                                    <p class="mb-1"><strong>Bank Name:</strong> <?= htmlspecialchars($bankDetails['bank_name']) ?></p>
                                    <p class="mb-1"><strong>Account Name:</strong> <?= htmlspecialchars($bankDetails['acc_name']) ?></p>
                                    <p class="mb-1"><strong>Account Number:</strong> <?= htmlspecialchars($bankDetails['acc_no']) ?></p>
                                    <p class="mb-0"><strong>Branch:</strong> <?= htmlspecialchars($bankDetails['branch_name']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- ADD NEW USER MODAL (Admin Only) -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="create_user_account" value="1">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Create New User Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Username</label>
                        <input type="text" name="new_username" class="form-control" required placeholder="Enter username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Password</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Enter temporary password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Role</label>
                        <select name="new_role" class="form-select">
                            <option value="user">Standard User</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PAYMENT DETAILS MODAL -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true" data-bs-focus="false">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="save_bank_details" value="1">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="paymentDetailsModalLabel"><i class="fa-solid fa-building-columns me-2"></i>Edit & Save Payment Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" class="form-control" value="<?= htmlspecialchars($bankDetails['bank_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Account Name</label>
                        <input type="text" id="acc_name" name="acc_name" class="form-control" value="<?= htmlspecialchars($bankDetails['acc_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Account Number</label>
                        <input type="text" id="acc_no" name="acc_no" class="form-control" value="<?= htmlspecialchars($bankDetails['acc_no']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Branch Name</label>
                        <input type="text" id="branch_name" name="branch_name" class="form-control" value="<?= htmlspecialchars($bankDetails['branch_name']) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-success" onclick="copyPaymentInfo()"><i class="fa-solid fa-copy me-1"></i> Copy</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- CUSTOMER MODALS -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog"><form method="POST" class="modal-content">
            <input type="hidden" name="customer_action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label fw-bold small">Customer Name <span class="text-danger">*Required</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small">Address Line 1 <span class="text-muted">(Optional)</span></label>
                    <input type="text" name="address_line_1" class="form-control" placeholder="Address Line 1">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small">Address Line 2 <span class="text-muted">(Optional)</span></label>
                    <input type="text" name="address_line_2" class="form-control" placeholder="Address Line 2">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small">Notes / Text Area <span class="text-muted">(Optional)</span></label>
                    <textarea name="textarea_notes" class="form-control" placeholder="Additional details or notes"></textarea>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Customer</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="editCustomerModal" tabindex="-1">
        <div class="modal-dialog"><form method="POST" class="modal-content">
            <input type="hidden" name="customer_action" value="edit">
            <input type="hidden" name="id" id="edit_c_id">
            <div class="modal-header"><h5 class="modal-title">Edit Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label fw-bold small">Customer Name <span class="text-danger">*Required</span></label>
                    <input type="text" name="name" id="edit_c_name" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small">Address Line 1 <span class="text-muted">(Optional)</span></label>
                    <input type="text" name="address_line_1" id="edit_c_address_line_1" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small">Address Line 2 <span class="text-muted">(Optional)</span></label>
                    <input type="text" name="address_line_2" id="edit_c_address_line_2" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small">Notes / Text Area <span class="text-muted">(Optional)</span></label>
                    <textarea name="textarea_notes" id="edit_c_textarea_notes" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Update Customer</button></div>
        </form></div>
    </div>

    <!-- PRODUCT MODALS -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog"><form method="POST" class="modal-content">
            <input type="hidden" name="product_action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" name="name" class="form-control mb-2" required placeholder="Product Name">
                <input type="number" step="0.01" name="price" class="form-control mb-2" required placeholder="Price (Rs.)">
                <textarea name="description" class="form-control mb-2" placeholder="Description"></textarea>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="free_shipping" value="1" id="add_free_shipping">
                    <label class="form-check-label fw-bold small" for="add_free_shipping">Free Shipping</label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Product</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog"><form method="POST" class="modal-content">
            <input type="hidden" name="product_action" value="edit">
            <input type="hidden" name="id" id="edit_p_id">
            <div class="modal-header"><h5 class="modal-title">Edit Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" name="name" id="edit_p_name" class="form-control mb-2" required placeholder="Product Name">
                <input type="number" step="0.01" name="price" id="edit_p_price" class="form-control mb-2" required placeholder="Price (Rs.)">
                <textarea name="description" id="edit_p_description" class="form-control mb-2" placeholder="Description"></textarea>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="free_shipping" value="1" id="edit_p_free_shipping">
                    <label class="form-check-label fw-bold small" for="edit_p_free_shipping">Free Shipping</label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Update Product</button></div>
        </form></div>
    </div>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    const icon = document.getElementById('themeIcon');
    const text = document.getElementById('themeText');
    if (icon && text) {
        if (theme === 'dark') {
            icon.className = 'fa-solid fa-sun text-warning me-1';
            text.innerText = 'Light Mode';
        } else {
            icon.className = 'fa-solid fa-moon me-1';
            text.innerText = 'Dark Mode';
        }
    }
    localStorage.setItem('selectedTheme', theme);
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
    applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
}

function copyPaymentInfo() {
    const bank = document.getElementById('bank_name').value;
    const name = document.getElementById('acc_name').value;
    const acc = document.getElementById('acc_no').value;
    const branch = document.getElementById('branch_name').value;
    const details = `Bank Name: ${bank}\nAccount Name: ${name}\nAccount Number: ${acc}\nBranch: ${branch}`;
    navigator.clipboard.writeText(details).then(() => {
        alert("Payment details copied to clipboard!");
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(localStorage.getItem('selectedTheme') || 'light');
    if (document.getElementById('itemsBody')) calculateTotal();
});

function editCustomer(c) {
    document.getElementById('edit_c_id').value = c.id;
    document.getElementById('edit_c_name').value = c.name;
    document.getElementById('edit_c_address_line_1').value = c.address_line_1 || '';
    document.getElementById('edit_c_address_line_2').value = c.address_line_2 || '';
    document.getElementById('edit_c_textarea_notes').value = c.textarea_notes || '';
}

function editProduct(p) {
    document.getElementById('edit_p_id').value = p.id;
    document.getElementById('edit_p_name').value = p.name;
    document.getElementById('edit_p_price').value = p.price;
    document.getElementById('edit_p_description').value = p.description || '';
    document.getElementById('edit_p_free_shipping').checked = (p.free_shipping == 1);
}

function autoFillProduct(select) {
    const row = select.closest('tr');
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (select.value !== "") {
        row.querySelector('.item-desc').value = select.value;
        row.querySelector('.item-price').value = price ? price : 0.00;
        calculateTotal();
    }
}

function calculateTotal() {
    let subtotal = 0;
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const total = qty * price;
        row.querySelector('.item-total').value = total.toFixed(2);
        subtotal += total;
    });

    let shippingFee = parseFloat(document.getElementById('shippingFee').value) || 0;
    let grandTotal = subtotal + shippingFee;

    const gtElem = document.getElementById('grandTotal');
    if (gtElem) gtElem.innerText = 'Rs. ' + grandTotal.toFixed(2);
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const firstRow = tbody.querySelector('tr');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.product-select').selectedIndex = 0;
    newRow.querySelector('.item-desc').value = '';
    newRow.querySelector('.item-qty').value = 1;
    newRow.querySelector('.item-price').value = '0.00';
    newRow.querySelector('.item-total').value = '0.00';
    tbody.appendChild(newRow);
    calculateTotal();
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.querySelectorAll('tr').length > 1) {
        btn.closest('tr').remove();
        calculateTotal();
    } else {
        alert("An invoice must contain at least one line item.");
    }
}
</script>
</body>
</html>