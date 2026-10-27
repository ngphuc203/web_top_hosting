<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_permissions.php';
ensureUserPasswordSchema($conn);

$errorMessage = '';
$formValues = [
    'fullname' => '',
    'username' => '',
    'gender' => '',
    'dob' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formValues as $field => $defaultValue) {
        $formValues[$field] = isset($_POST[$field]) && is_string($_POST[$field])
            ? trim($_POST[$field])
            : $defaultValue;
    }
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $passwordConfirm = isset($_POST['password_confirm']) && is_string($_POST['password_confirm'])
        ? $_POST['password_confirm']
        : '';

    if ($password !== $passwordConfirm) {
        $errorMessage = 'Mật khẩu và xác nhận mật khẩu không khớp.';
    } else {
        $checkStmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
        if (!$checkStmt) {
            die('Không thể kiểm tra tên tài khoản: ' . $conn->error);
        }
        $checkStmt->bind_param('s', $formValues['username']);
        if (!$checkStmt->execute()) {
            die('Không thể kiểm tra tên tài khoản: ' . $checkStmt->error);
        }
        $checkStmt->store_result();
        $usernameExists = $checkStmt->num_rows > 0;
        $checkStmt->close();

        if ($usernameExists) {
            $errorMessage = 'Tên tài khoản đã tồn tại. Vui lòng chọn tên khác.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userId = uniqid('user_');
            $insertStmt = $conn->prepare(
                'INSERT INTO users (id, fullname, username, password, gender, dob) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$insertStmt) {
                die('Không thể chuẩn bị tạo tài khoản: ' . $conn->error);
            }
            $insertStmt->bind_param(
                'ssssss',
                $userId,
                $formValues['fullname'],
                $formValues['username'],
                $hashedPassword,
                $formValues['gender'],
                $formValues['dob']
            );

            if ($insertStmt->execute()) {
                $insertStmt->close();
                $conn->close();
                $_SESSION['auth_success_message'] = 'Đăng ký thành công. Bạn có thể đăng nhập bằng tài khoản vừa tạo.';
                header('Location: ./login.php');
                exit();
            }

            $errorMessage = 'Không thể tạo tài khoản: ' . $insertStmt->error;
            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký | Ủa Store</title>
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

    <main class="auth-main auth-main-signup">
        <section class="auth-card auth-card-signup" aria-labelledby="signup-title">
            <div class="auth-card-intro">
                <span class="auth-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
                <span class="auth-eyebrow">THÀNH VIÊN ỦA STORE</span>
                <h1 id="signup-title">Tạo tài khoản</h1>
                <p>Đăng ký để theo dõi đơn hàng và mua sắm thuận tiện hơn.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="auth-message auth-message-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form auth-form-grid" method="POST" action="./signup.php">
                <div class="auth-field">
                    <label for="fullname">Họ và tên</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-id-card" aria-hidden="true"></i>
                        <input type="text" id="fullname" name="fullname" autocomplete="name"
                            placeholder="Nhập họ và tên"
                            value="<?php echo htmlspecialchars($formValues['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="username">Tên đăng nhập</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input type="text" id="username" name="username" autocomplete="username"
                            placeholder="Tạo tên đăng nhập"
                            value="<?php echo htmlspecialchars($formValues['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="dob">Ngày sinh</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-calendar-days" aria-hidden="true"></i>
                        <input type="date" id="dob" name="dob"
                            value="<?php echo htmlspecialchars($formValues['dob'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <fieldset class="auth-gender-field">
                    <legend>Giới tính</legend>
                    <div class="auth-gender-options">
                        <label><input type="radio" name="gender" value="Nam" <?php echo $formValues['gender'] === 'Nam' ? 'checked' : ''; ?> required> Nam</label>
                        <label><input type="radio" name="gender" value="Nữ" <?php echo $formValues['gender'] === 'Nữ' ? 'checked' : ''; ?> required> Nữ</label>
                    </div>
                </fieldset>
                <div class="auth-field">
                    <label for="password">Mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" autocomplete="new-password"
                            placeholder="Tạo mật khẩu" required>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="password_confirm">Xác nhận mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"
                            placeholder="Nhập lại mật khẩu" required>
                    </div>
                </div>
                <button class="auth-submit auth-submit-wide" type="submit">Tạo tài khoản <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="auth-switch">Đã có tài khoản? <a href="./login.php">Đăng nhập</a></p>
        </section>
    </main>

    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
