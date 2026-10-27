<?php
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
ensureUserPasswordSchema($conn);
requireAdminPagePermission($conn, 'customers.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken();
}

$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fullname'], $_POST['username'], $_POST['password'], $_POST['gender'], $_POST['dob'])) {
    requireAdminPagePermission($conn, 'customers.create');
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $rawPassword = is_string($_POST['password']) ? $_POST['password'] : '';
    if (strlen($rawPassword) < 8) {
        $formError = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } else {
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);
    }
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $id = uniqid();
    if ($formError === '') {
        $stmt = $conn->prepare("INSERT INTO users (id, fullname, username, password, gender, dob) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die('Không thể chuẩn bị tạo khách hàng: ' . $conn->error);
        }
        $stmt->bind_param("ssssss", $id, $fullname, $username, $password, $gender, $dob);
        if (!$stmt->execute()) {
            $formError = $stmt->errno === 1062
                ? 'Tên tài khoản này đã được sử dụng.'
                : 'Không thể tạo khách hàng: ' . $stmt->error;
        } else {
            header('Location: manage_customers.php');
            exit();
        }
        $stmt->close();
    }
}

$sql = "SELECT id, fullname FROM users ORDER BY fullname ASC, id ASC";
$result = $conn->query($sql);
if (!$result) {
    die('Không thể tải danh sách khách hàng: ' . $conn->error);
}
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách hàng</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'customers'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">QUẢN LÝ TÀI KHOẢN</span>
                <h1>Khách hàng</h1>
                <p>Nhấn vào khách hàng để xem và chỉnh sửa hồ sơ chi tiết.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
        </div>

        <?php if (isset($_GET['customer_updated'])): ?>
            <p class="admin-alert admin-alert-success" role="status">Đã cập nhật thông tin khách hàng.</p>
        <?php elseif (isset($_GET['customer_deleted'])): ?>
            <p class="admin-alert admin-alert-success" role="status">Đã xóa khách hàng.</p>
        <?php endif; ?>

        <?php if (adminHasPermission('customers.create')): ?>
        <section class="admin-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">TẠO TÀI KHOẢN</span><h2>Thêm khách hàng</h2></div>
            </div>
            <?php if ($formError !== ''): ?><p class="admin-alert admin-alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <form method="POST" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <label for="fullname">Tên khách hàng<input type="text" id="fullname" name="fullname" required></label>
                <label for="username">Tên tài khoản<input type="text" id="username" name="username" required></label>
                <label for="password">Mật khẩu<input type="password" id="password" name="password" minlength="8" required></label>
                <label for="dob">Ngày sinh<input type="date" id="dob" name="dob" required></label>
                <fieldset class="admin-radio-group">
                    <legend>Giới tính</legend>
                    <label for="male"><input type="radio" id="male" name="gender" value="Nam" required> Nam</label>
                    <label for="female"><input type="radio" id="female" name="gender" value="Nữ" required> Nữ</label>
                </fieldset>
                <button class="admin-button admin-button-primary" type="submit"><i class="fas fa-user-plus" aria-hidden="true"></i> Thêm khách hàng</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">HỒ SƠ</span><h2>Danh sách khách hàng</h2></div>
                <span class="admin-record-count"><?php echo number_format(count($customers)); ?> khách hàng</span>
            </div>

            <?php if (empty($customers)): ?>
                <div class="admin-staff-empty">
                    <span class="admin-staff-empty-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
                    <h3>Chưa có khách hàng</h3>
                    <p>Khách hàng đăng ký tài khoản sẽ xuất hiện tại đây.</p>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap admin-customer-table-wrap">
                    <table class="admin-customer-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th class="col-customer">Tên khách hàng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <?php $editUrl = 'edit_customer.php?id=' . rawurlencode($customer['id']); ?>
                                <tr class="admin-clickable-row" onclick="window.location.href='<?php echo $editUrl; ?>'" title="Nhấn để chỉnh sửa thông tin khách hàng">
                                    <td class="col-id">#<?php echo htmlspecialchars($customer['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="col-customer">
                                        <a href="<?php echo $editUrl; ?>" class="admin-row-title-link">
                                            <strong><?php echo htmlspecialchars($customer['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
