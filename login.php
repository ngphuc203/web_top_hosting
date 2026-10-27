<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_permissions.php';
ensureStaffSchema($conn);
ensureUserPasswordSchema($conn);

$errorMessage = '';
$successMessage = isset($_SESSION['auth_success_message']) ? $_SESSION['auth_success_message'] : '';
unset($_SESSION['auth_success_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if ($username === 'ngphuc203' && $password === '1') {
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = $username;
        header('Location: admin_dashboard.php');
        exit();
    }

    $staffStmt = $conn->prepare(
        'SELECT staff_id, fullname, password_hash FROM staff_accounts
         WHERE username = ? AND is_active = 1'
    );
    if (!$staffStmt) {
        die('Không thể chuẩn bị đăng nhập nhân viên: ' . $conn->error);
    }
    $staffStmt->bind_param('s', $username);
    if (!$staffStmt->execute()) {
        die('Không thể xác minh tài khoản nhân viên: ' . $staffStmt->error);
    }
    $staffAccount = $staffStmt->get_result()->fetch_assoc();
    $staffStmt->close();

    if ($staffAccount && password_verify($password, $staffAccount['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'staff';
        $_SESSION['username'] = $username;
        $_SESSION['fullname'] = $staffAccount['fullname'];
        $_SESSION['staff_id'] = (int) $staffAccount['staff_id'];
        header('Location: admin_dashboard.php');
        exit();
    }

    $stmt = $conn->prepare('SELECT id, username, fullname, password FROM users WHERE username = ?');
    if (!$stmt) {
        die('Không thể chuẩn bị đăng nhập: ' . $conn->error);
    }
    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        die('Không thể xác minh tài khoản: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    $passwordMatches = false;
    if ($customer) {
        $storedPassword = (string) $customer['password'];
        $passwordMatches = password_verify($password, $storedPassword)
            || hash_equals($storedPassword, $password);
    }

    if ($customer && $passwordMatches) {
        $_SESSION['username'] = $customer['username'];
        $_SESSION['fullname'] = $customer['fullname'];
        $_SESSION['role'] = 'user';
        header('Location: index.php');
        exit();
    }

    $errorMessage = 'Tên đăng nhập hoặc mật khẩu không chính xác. Vui lòng thử lại.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Ủa Store</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="auth-page">
    <?php include __DIR__ . '/site_header.php'; ?>
    <div class="header-mid-bar">
        <div class="header-marquee position-relative font-weight-bold text-light bg-dark">
            <div class="marquee d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
            <div class="marquee marquee2 d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
        </div>
    </div>

    <main class="auth-main">
        <section class="auth-card auth-card-login" aria-labelledby="login-title">
            <div class="auth-card-intro">
                <span class="auth-icon"><i class="fas fa-user-lock" aria-hidden="true"></i></span>
                <span class="auth-eyebrow">CHÀO MỪNG TRỞ LẠI</span>
                <h1 id="login-title">Đăng nhập</h1>
                <p>Đăng nhập để tiếp tục trải nghiệm và quản lý đơn hàng của bạn.</p>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="auth-message auth-message-success" role="status">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="auth-message auth-message-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form auth-form-login" method="POST" action="./login.php">
                <div class="auth-field">
                    <label for="username">Tên đăng nhập</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input type="text" id="username" name="username" autocomplete="username"
                            placeholder="Nhập tên đăng nhập" value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" autocomplete="current-password"
                            placeholder="Nhập mật khẩu" required>
                    </div>
                </div>

                <button class="auth-submit auth-submit-wide" type="submit">Đăng nhập <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="auth-switch">Chưa có tài khoản? <a href="./signup.php">Tạo tài khoản mới</a></p>
        </section>
    </main>

    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
