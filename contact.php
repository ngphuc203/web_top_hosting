<?php
session_start();
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
ensureContactSchema($conn);

$contactSubjects = [
    'Tu van san pham' => 'Tư vấn sản phẩm',
    'Don hang' => 'Đơn hàng',
    'Doi tra' => 'Đổi trả sản phẩm',
    'Khac' => 'Nội dung khác'
];
$contactError = '';
$contactValues = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$contactSuccess = isset($_SESSION['contact_success']);
unset($_SESSION['contact_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken();
    foreach ($contactValues as $field => $defaultValue) {
        $contactValues[$field] = isset($_POST[$field]) && is_string($_POST[$field])
            ? trim($_POST[$field])
            : $defaultValue;
    }
    if (
        $contactValues['name'] === ''
        || strlen($contactValues['name']) > 150
        || !filter_var($contactValues['email'], FILTER_VALIDATE_EMAIL)
        || strlen($contactValues['email']) > 254
        || !isset($contactSubjects[$contactValues['subject']])
        || $contactValues['message'] === ''
        || strlen($contactValues['message']) > 10000
    ) {
        $contactError = 'Vui lòng nhập đầy đủ thông tin và kiểm tra địa chỉ email.';
    } else {
        $subjectLabel = $contactSubjects[$contactValues['subject']];
        $contactStmt = $conn->prepare(
            'INSERT INTO contact_messages (fullname, email, subject, message) VALUES (?, ?, ?, ?)'
        );
        if (!$contactStmt) {
            die('Không thể chuẩn bị lưu tin nhắn liên hệ: ' . $conn->error);
        }
        $contactStmt->bind_param(
            'ssss',
            $contactValues['name'],
            $contactValues['email'],
            $subjectLabel,
            $contactValues['message']
        );
        if (!$contactStmt->execute()) {
            die('Không thể lưu tin nhắn liên hệ: ' . $contactStmt->error);
        }
        $contactStmt->close();
        $_SESSION['contact_success'] = true;
        header('Location: contact.php?sent=1');
        exit();
    }
}
$contactSuccess = $contactSuccess || isset($_GET['sent']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên hệ | POP_POP</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="contact-page">
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

    <main class="contact-page-shell">
        <nav class="product-breadcrumb product-catalog-breadcrumb" aria-label="Điều hướng">
            <a href="./index.php">Trang chủ</a>
            <span>/</span>
            <span class="current">Liên hệ</span>
        </nav>

        <div class="contact-content-grid">
            <section class="contact-info-panel" aria-labelledby="contact-info-title">
                <div class="contact-section-heading">
                    <span class="contact-eyebrow">KẾT NỐI VỚI CHÚNG TAO</span>
                    <h2 id="contact-info-title">Thông tin liên hệ</h2>
                    <p>Chọn kênh thuận tiện để được hỗ trợ.</p>
                </div>

                <a class="contact-method" href="tel:0389101040">
                    <span class="contact-method-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
                    <span><small>Hotline 1</small><strong>0389 101 040</strong></span>
                    <i class="fas fa-arrow-up-right-from-square contact-method-arrow" aria-hidden="true"></i>
                </a>
                <a class="contact-method" href="tel:0345048132">
                    <span class="contact-method-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
                    <span><small>Hotline 2</small><strong>0345 048 132</strong></span>
                    <i class="fas fa-arrow-up-right-from-square contact-method-arrow" aria-hidden="true"></i>
                </a>
                <a class="contact-method" href="mailto:ngphuc175@uastore.com">
                    <span class="contact-method-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                    <span><small>Email</small><strong>ngphuc175@uastore.com</strong></span>
                    <i class="fas fa-arrow-up-right-from-square contact-method-arrow" aria-hidden="true"></i>
                </a>

                <div class="contact-branches">
                    <h3><i class="  " aria-hidden="true"></i>THEO CHÂN ANH</h3>

                </div>

                <div class="contact-social-links" aria-label="Mạng xã hội">
                    <a href="https://www.facebook.com/ngphuc203" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/szismee" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.tiktok.com/@szismee" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                </div>
            </section>

            <section class="contact-form-panel" aria-labelledby="contact-form-title">
                <span class="contact-eyebrow">GỬI LỜI NHẮN</span>
                <h2 id="contact-form-title">Mày cần hỗ trợ?</h2>
                <p>Gửi lời nhắn đến cửa hàng. Đội ngũ hỗ trợ sẽ phản hồi qua email bạn cung cấp.</p>

                <?php if ($contactSuccess): ?>
                    <p class="admin-alert admin-alert-success" role="status">Đã nhận được lời nhắn. Cảm ơn bạn đã liên hệ!</p>
                <?php endif; ?>
                <?php if ($contactError !== ''): ?>
                    <p class="admin-alert admin-alert-error" role="alert"><?php echo htmlspecialchars($contactError, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <form class="contact-form" id="contactForm" action="contact.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="contact-form-row">
                        <label for="contact-name">Họ và tên</label>
                        <input id="contact-name" name="name" type="text" maxlength="150" autocomplete="name" placeholder="Tên của mày" value="<?php echo htmlspecialchars($contactValues['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="contact-form-row">
                        <label for="contact-email">Email</label>
                        <input id="contact-email" name="email" type="email" maxlength="254" autocomplete="email" placeholder="email@example.com" value="<?php echo htmlspecialchars($contactValues['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="contact-form-row">
                        <label for="contact-subject">Chủ đề</label>
                        <select id="contact-subject" name="subject" required>
                            <option value="" disabled <?php echo $contactValues['subject'] === '' ? 'selected' : ''; ?>>Chọn nội dung cần hỗ trợ</option>
                            <?php foreach ($contactSubjects as $subjectValue => $subjectLabel): ?>
                                <option value="<?php echo htmlspecialchars($subjectValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $contactValues['subject'] === $subjectValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="contact-form-row">
                        <label for="contact-message">Lời nhắn</label>
                        <textarea id="contact-message" name="message" rows="5" maxlength="10000" placeholder="sủa đi..." required><?php echo htmlspecialchars($contactValues['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <button class="contact-submit-button" type="submit">
                        Gửi lời nhắn <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                    <p class="contact-form-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Nội dung sẽ được lưu để cửa hàng xử lý. Bạn cũng có thể gửi email đến <a href="mailto:ngphuc1753@gmail.com">ngphuc1753@gmail.com</a>.</p>
                </form>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/site_footer.php'; ?>

</body>
</html>
