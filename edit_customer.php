<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
ensureUserPasswordSchema($conn);
requireAdminPagePermission($conn, 'customers.update');
if (!isset($_GET['id'])) {
    header('Location: manage_customers.php');
    exit();
}
$user_id = is_string($_GET['id']) ? $_GET['id'] : '';
$user_to_edit = null;

$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Không thể chuẩn bị tải thông tin khách hàng: ' . $conn->error);
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_to_edit = $result->fetch_assoc();
if (!$user_to_edit) {
    header('Location: manage_customers.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    verifyAdminCsrfToken();
    requireAdminPagePermission($conn, 'customers.delete');
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$deleteStmt) {
        die('Không thể chuẩn bị xóa khách hàng: ' . $conn->error);
    }
    $deleteStmt->bind_param("s", $user_id);
    if (!$deleteStmt->execute()) {
        die('Không thể xóa khách hàng: ' . $deleteStmt->error);
    }
    $deleteStmt->close();
    header('Location: manage_customers.php?customer_deleted=1');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fullname'], $_POST['username'])) {
    verifyAdminCsrfToken();
    $fullname = is_string($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $username = is_string($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $formError = '';
    if ($fullname === '' || $username === '' || ($password !== '' && strlen($password) < 8)) {
        $formError = 'Vui lòng nhập tên hợp lệ; mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt_update = $conn->prepare("UPDATE users SET fullname = ?, username = ?, password = ? WHERE id = ?");
        if (!$stmt_update) {
            die('Không thể chuẩn bị cập nhật khách hàng: ' . $conn->error);
        }
        $stmt_update->bind_param("ssss", $fullname, $username, $passwordHash, $user_id);
        if (!$stmt_update->execute()) {
            $formError = $stmt_update->errno === 1062
                ? 'Tên tài khoản này đã được sử dụng.'
                : 'Không thể cập nhật khách hàng: ' . $stmt_update->error;
        }
        $stmt_update->close();
    } else {
        $stmt_update = $conn->prepare("UPDATE users SET fullname = ?, username = ? WHERE id = ?");
        if (!$stmt_update) {
            die('Không thể chuẩn bị cập nhật khách hàng: ' . $conn->error);
        }
        $stmt_update->bind_param("sss", $fullname, $username, $user_id);
        if (!$stmt_update->execute()) {
            $formError = $stmt_update->errno === 1062
                ? 'Tên tài khoản này đã được sử dụng.'
                : 'Không thể cập nhật khách hàng: ' . $stmt_update->error;
        }
        $stmt_update->close();
    }
    if ($formError === '') {
        header('Location: manage_customers.php?customer_updated=1');
        exit();
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa khách hàng</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'customers'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">KHÁCH HÀNG #<?php echo htmlspecialchars($user_to_edit['id'], ENT_QUOTES, 'UTF-8'); ?></span>
                <h1>Sửa khách hàng: <?php echo htmlspecialchars($user_to_edit['fullname'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Cập nhật họ tên, tên đăng nhập hoặc đổi mật khẩu cho tài khoản.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-user-pen" aria-hidden="true"></i></span>
        </div>

        <?php if (isset($formError) && $formError !== ''): ?>
            <p class="admin-alert admin-alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">THÔNG TIN</span><h2>Hồ sơ khách hàng</h2></div>
                <span class="admin-record-count">#<?php echo htmlspecialchars($user_to_edit['id'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <form method="POST" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                
                <label for="fullname">Họ và tên
                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_to_edit['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                
                <label for="username">Tên tài khoản (Username)
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_to_edit['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                
                <label class="admin-form-wide" for="password">Mật khẩu mới
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder="Nhập mật khẩu mới nếu muốn đổi...">
                    <small>Để trống nếu bạn không muốn thay đổi mật khẩu hiện tại.</small>
                </label>
                
                <button class="admin-button admin-button-primary admin-form-submit" type="submit">
                    <i class="fas fa-check" aria-hidden="true"></i> Cập Nhật Thông Tin
                </button>
            </form>
        </section>

        <?php if (adminHasPermission('customers.delete')): ?>
        <section class="admin-panel admin-danger-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">VÙNG NGUY HIỂM</span><h2>Xóa khách hàng</h2></div>
            </div>
            <p class="admin-danger-copy">Tài khoản khách hàng sẽ bị xóa vĩnh viễn. Thao tác này không thể hoàn tác.</p>
            <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn xóa khách hàng này?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="delete_customer" value="1">
                <button class="admin-button admin-button-danger" type="submit">
                    <i class="fas fa-trash" aria-hidden="true"></i> Xóa khách hàng
                </button>
            </form>
        </section>
        <?php endif; ?>

        <p class="admin-form-wide" style="margin-top: 20px;">
            <a href="./manage_customers.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Quay lại danh sách khách hàng</a>
        </p>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
