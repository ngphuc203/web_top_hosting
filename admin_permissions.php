<?php

function ensureStaffSchema(mysqli $conn)
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS staff_accounts (
            staff_id INT NOT NULL AUTO_INCREMENT,
            fullname VARCHAR(150) NOT NULL,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (staff_id),
            UNIQUE KEY unique_staff_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS staff_permissions (
            staff_id INT NOT NULL,
            permission_key VARCHAR(80) NOT NULL,
            PRIMARY KEY (staff_id, permission_key),
            CONSTRAINT fk_staff_permissions_account
                FOREIGN KEY (staff_id) REFERENCES staff_accounts (staff_id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS staff_permission_groups (
            staff_id INT NOT NULL,
            group_key VARCHAR(40) NOT NULL,
            PRIMARY KEY (staff_id, group_key),
            CONSTRAINT fk_staff_permission_groups_account
                FOREIGN KEY (staff_id) REFERENCES staff_accounts (staff_id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS staff_schema_migrations (
            version INT NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($statements as $statement) {
        if (!$conn->query($statement)) {
            die('Lỗi tạo cấu trúc quản lý nhân viên: ' . $conn->error);
        }
    }

    $migrationResult = $conn->query(
        'SELECT version FROM staff_schema_migrations WHERE version = 1'
    );
    if (!$migrationResult) {
        die('Lỗi kiểm tra cập nhật nhóm quyền nhân viên: ' . $conn->error);
    }
    $migrationApplied = $migrationResult->num_rows > 0;
    $migrationResult->free();

    if (!$migrationApplied) {
        $legacyGroupMigrations = [
            "INSERT IGNORE INTO staff_permission_groups (staff_id, group_key)
             SELECT DISTINCT staff_id, 'inventory' FROM staff_permissions
             WHERE permission_key IN ('products.view', 'products.create', 'products.update', 'products.delete',
                                      'categories.view', 'categories.create', 'categories.update', 'categories.delete')",
            "INSERT IGNORE INTO staff_permission_groups (staff_id, group_key)
             SELECT DISTINCT staff_id, 'orders' FROM staff_permissions
             WHERE permission_key IN ('orders.view', 'orders.update', 'orders.delete')",
            "INSERT IGNORE INTO staff_permission_groups (staff_id, group_key)
             SELECT DISTINCT staff_id, 'customers' FROM staff_permissions
             WHERE permission_key IN ('customers.view', 'customers.create', 'customers.update', 'customers.delete')",
            "INSERT IGNORE INTO staff_permission_groups (staff_id, group_key)
             SELECT DISTINCT staff_id, 'contacts' FROM staff_permissions
             WHERE permission_key IN ('contacts.view', 'contacts.update')",
            'INSERT IGNORE INTO staff_schema_migrations (version) VALUES (1)'
        ];

        foreach ($legacyGroupMigrations as $statement) {
            if (!$conn->query($statement)) {
                die('Lỗi chuyển quyền nhân viên sang nhóm: ' . $conn->error);
            }
        }
    }
}

function ensureContactSchema(mysqli $conn)
{
    $statement = "CREATE TABLE IF NOT EXISTS contact_messages (
        contact_id INT NOT NULL AUTO_INCREMENT,
        fullname VARCHAR(150) NOT NULL,
        email VARCHAR(254) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (contact_id),
        KEY contact_messages_status_created (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($statement)) {
        die('Lỗi tạo cấu trúc quản lý liên hệ: ' . $conn->error);
    }
}

function ensureUserPasswordSchema(mysqli $conn)
{
    $columnResult = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
    if (!$columnResult) {
        die('Lỗi kiểm tra cấu trúc mật khẩu người dùng: ' . $conn->error);
    }
    $passwordColumn = $columnResult->fetch_assoc();
    $columnResult->free();

    if (!$passwordColumn) {
        die('Không tìm thấy cột mật khẩu trong bảng người dùng.');
    }
    if (stripos($passwordColumn['Type'], 'varchar(255)') !== 0) {
        if (!$conn->query('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL')) {
            die('Không thể mở rộng cột mật khẩu người dùng: ' . $conn->error);
        }
    }
}

function adminPermissionCatalog()
{
    return [
        'dashboard.view' => 'Xem tổng quan',
        'products.view' => 'Xem danh sách và chi tiết sản phẩm',
        'products.create' => 'Thêm sản phẩm',
        'products.update' => 'Sửa sản phẩm',
        'products.delete' => 'Xóa sản phẩm',
        'categories.view' => 'Xem danh mục',
        'categories.create' => 'Thêm danh mục',
        'categories.update' => 'Đổi tên danh mục',
        'categories.delete' => 'Xóa danh mục',
        'orders.view' => 'Xem đơn hàng và thông tin đơn',
        'orders.update' => 'Cập nhật trạng thái đơn hàng',
        'orders.delete' => 'Xóa đơn hàng',
        'customers.view' => 'Xem khách hàng',
        'customers.create' => 'Tạo tài khoản khách hàng',
        'customers.update' => 'Sửa khách hàng',
        'customers.delete' => 'Xóa khách hàng',
        'contacts.view' => 'Xem tin nhắn liên hệ',
        'contacts.update' => 'Đổi trạng thái tin nhắn liên hệ'
    ];
}

function requireAdminPagePermission(mysqli $conn, $permissionKey)
{
    if (!isset($_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }

    if ($_SESSION['role'] === 'admin') {
        return;
    }

    if ($_SESSION['role'] !== 'staff' || empty($_SESSION['staff_id'])) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }

    ensureStaffSchema($conn);
    $staffId = (int) $_SESSION['staff_id'];
    $accountStmt = $conn->prepare(
        'SELECT is_active FROM staff_accounts WHERE staff_id = ?'
    );
    if (!$accountStmt) {
        die('Không thể xác minh tài khoản nhân viên: ' . $conn->error);
    }
    $accountStmt->bind_param('i', $staffId);
    if (!$accountStmt->execute()) {
        die('Không thể xác minh tài khoản nhân viên: ' . $accountStmt->error);
    }
    $staffAccount = $accountStmt->get_result()->fetch_assoc();
    $accountStmt->close();

    if (!$staffAccount || (int) $staffAccount['is_active'] !== 1) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $permissionStmt = $conn->prepare(
        'SELECT permission_key FROM staff_permissions WHERE staff_id = ?'
    );
    if (!$permissionStmt) {
        die('Không thể tải quyền nhân viên: ' . $conn->error);
    }
    $permissionStmt->bind_param('i', $staffId);
    if (!$permissionStmt->execute()) {
        die('Không thể tải quyền nhân viên: ' . $permissionStmt->error);
    }
    $permissionResult = $permissionStmt->get_result();
    $permissions = [];
    while ($row = $permissionResult->fetch_assoc()) {
        $permissions[] = $row['permission_key'];
    }
    $permissionStmt->close();
    $_SESSION['admin_permissions'] = $permissions;

    if (!in_array($permissionKey, $permissions, true)) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }
}

function adminHasPermission($permissionKey)
{
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    return isset($_SESSION['admin_permissions'])
        && in_array($permissionKey, $_SESSION['admin_permissions'], true);
}

function adminCsrfToken()
{
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function verifyAdminCsrfToken()
{
    if (
        !isset($_POST['csrf_token'], $_SESSION['admin_csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}
