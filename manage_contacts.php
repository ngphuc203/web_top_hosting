<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
ensureContactSchema($conn);
requireAdminPagePermission($conn, 'contacts.view');

$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminPagePermission($conn, 'contacts.update');
    verifyAdminCsrfToken();

    $contactId = isset($_POST['contact_id']) ? (int) $_POST['contact_id'] : 0;
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    if ($contactId < 1 || !in_array($status, ['new', 'in_progress', 'resolved'], true)) {
        $formError = 'Thông tin cập nhật liên hệ không hợp lệ.';
    } else {
        $updateStmt = $conn->prepare(
            'UPDATE contact_messages SET status = ? WHERE contact_id = ?'
        );
        if (!$updateStmt) {
            die('Không thể chuẩn bị cập nhật liên hệ: ' . $conn->error);
        }
        $updateStmt->bind_param('si', $status, $contactId);
        if (!$updateStmt->execute()) {
            die('Không thể cập nhật trạng thái liên hệ: ' . $updateStmt->error);
        }
        $updateStmt->close();
        header('Location: manage_contacts.php?updated=1');
        exit();
    }
}

$contactResult = $conn->query(
    "SELECT contact_id, fullname, email, subject, message, status, created_at
     FROM contact_messages
     ORDER BY CASE status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,
              created_at DESC"
);
if (!$contactResult) {
    die('Không thể tải tin nhắn liên hệ: ' . $conn->error);
}
$contacts = [];
while ($contact = $contactResult->fetch_assoc()) {
    $contacts[] = $contact;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý liên hệ</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'contacts'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">CHĂM SÓC KHÁCH HÀNG</span>
                <h1>Liên hệ</h1>
                <p>Tiếp nhận và theo dõi tin nhắn khách hàng gửi qua trang liên hệ.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
        </div>

        <?php if ($formError !== ''): ?>
            <p class="admin-alert admin-alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (isset($_GET['updated'])): ?>
            <p class="admin-alert admin-alert-success">Đã cập nhật trạng thái liên hệ.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">HỘP THƯ ĐẾN</span><h2>Tin nhắn khách hàng</h2></div>
                <span class="admin-record-count"><?php echo count($contacts); ?> tin nhắn</span>
            </div>
            <?php if (empty($contacts)): ?>
                <p>Chưa có tin nhắn nào. Tin nhắn mới từ trang liên hệ sẽ xuất hiện tại đây.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-contact-table">
                        <thead>
                            <tr><th>Người gửi</th><th>Chủ đề</th><th>Nội dung</th><th>Ngày gửi</th><th>Trạng thái</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contact['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <a href="mailto:<?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($contact['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="admin-contact-message"><?php echo nl2br(htmlspecialchars($contact['message'], ENT_QUOTES, 'UTF-8')); ?></td>
                                    <td><?php echo htmlspecialchars($contact['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if (adminHasPermission('contacts.update')): ?>
                                            <form method="POST" class="admin-inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="contact_id" value="<?php echo (int) $contact['contact_id']; ?>">
                                                <select name="status" aria-label="Trạng thái tin nhắn">
                                                    <option value="new" <?php echo $contact['status'] === 'new' ? 'selected' : ''; ?>>Mới</option>
                                                    <option value="in_progress" <?php echo $contact['status'] === 'in_progress' ? 'selected' : ''; ?>>Đang xử lý</option>
                                                    <option value="resolved" <?php echo $contact['status'] === 'resolved' ? 'selected' : ''; ?>>Đã xử lý</option>
                                                </select>
                                                <button class="admin-button admin-button-primary" type="submit">Lưu</button>
                                            </form>
                                        <?php else: ?>
                                            <?php echo $contact['status'] === 'new' ? 'Mới' : ($contact['status'] === 'in_progress' ? 'Đang xử lý' : 'Đã xử lý'); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
