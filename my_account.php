<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';
$username = $_SESSION['username'];
$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username); 
$stmt->execute();
$result = $stmt->get_result();
$current_customer = $result->fetch_assoc();
if (!$current_customer) {
    echo "Không tìm thấy thông tin người dùng!";
    exit();
}
$dateOfBirth = (string) $current_customer['dob'];
$dateOfBirthObject = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
$dateOfBirthErrors = DateTimeImmutable::getLastErrors();
$formattedDateOfBirth = $dateOfBirthObject
    && ($dateOfBirthErrors === false
        || ($dateOfBirthErrors['warning_count'] === 0 && $dateOfBirthErrors['error_count'] === 0))
    ? $dateOfBirthObject->format('d/m/Y')
    : $dateOfBirth;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Tin Tài Khoản</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="account-page">
<?php include __DIR__ . '/site_header.php'; ?>

    <div class="header-mid-bar">
        <div class="header-marquee position-relative font-weight-bold text-light bg-dark">
            <div class="marquee d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
            <div class="marquee marquee2 d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAUU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
        </div>
    </div>
    <main class="account-page-shell">
        <nav class="product-breadcrumb product-catalog-breadcrumb" aria-label="Điều hướng">
            <a href="./index.php">Trang chủ</a>
            <span>/</span>
            <span class="current">Tài khoản</span>
        </nav>

        <section class="account-container" aria-labelledby="account-details-title">
            <div class="account-card-heading">
                <div>
                    <h2 id="account-details-title">Thông tin cá nhân</h2>
                    <p>Thông tin hồ sơ đang liên kết với tài khoản của bạn.</p>
                </div>
                <span class="account-status"><i class="fas fa-check-circle" aria-hidden="true"></i> Thành viên</span>
            </div>
            <div class="account-details">
                <div class="info-group">
                    <span class="account-field-label">Tên khách hàng</span>
                    <p><?php echo htmlspecialchars($current_customer['fullname'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="info-group">
                    <span class="account-field-label">Tên tài khoản</span>
                    <p><?php echo htmlspecialchars($current_customer['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="info-group">
                    <span class="account-field-label">Giới tính</span>
                    <p><?php echo htmlspecialchars($current_customer['gender'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="info-group">
                    <span class="account-field-label">Ngày sinh</span>
                    <p><?php echo htmlspecialchars($formattedDateOfBirth, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="account-security-note">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                <span>Mật khẩu được bảo vệ và không hiển thị trên trang hồ sơ.</span>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/site_footer.php'; ?>
    </body>
</html>
