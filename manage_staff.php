<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';

ensureStaffSchema($conn);
$formError = '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}
$searchSuffix = $search !== '' ? '&q=' . rawurlencode($search) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'toggle_active') {
        $staffId = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
        $currentStaffId = isset($_SESSION['staff_id']) ? (int) $_SESSION['staff_id'] : 0;

        if ($staffId < 1) {
            $formError = 'Nhân viên không hợp lệ.';
        } elseif ($staffId === $currentStaffId) {
            $formError = 'Không thể khóa tài khoản đang đăng nhập.';
        } else {
            $toggleStmt = $conn->prepare(
                'UPDATE staff_accounts SET is_active = IF(is_active = 1, 0, 1) WHERE staff_id = ?'
            );
            if (!$toggleStmt) {
                die('Không thể chuẩn bị cập nhật trạng thái nhân viên: ' . $conn->error);
            }
            $toggleStmt->bind_param('i', $staffId);
            if (!$toggleStmt->execute()) {
                $formError = 'Không thể cập nhật trạng thái nhân viên: ' . $toggleStmt->error;
            }
            $toggleStmt->close();

            if ($formError === '') {
                header('Location: manage_staff.php?staff_toggled=1' . $searchSuffix);
                exit();
            }
        }
    } else {
        $formError = 'Thao tác nhân viên không hợp lệ.';
    }
}

$sql = 'SELECT staff_id, fullname, username, is_active FROM staff_accounts';
if ($search !== '') {
    $sql .= ' WHERE fullname LIKE ?';
}
$sql .= ' ORDER BY fullname ASC, staff_id ASC';

$staffStmt = $conn->prepare($sql);
if (!$staffStmt) {
    die('Không thể tải danh sách nhân viên: ' . $conn->error);
}
if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $staffStmt->bind_param('s', $searchLike);
}
if (!$staffStmt->execute()) {
    die('Không thể tải danh sách nhân viên: ' . $staffStmt->error);
}
$staffResult = $staffStmt->get_result();
$staffAccounts = [];
while ($staff = $staffResult->fetch_assoc()) {
    $staffAccounts[] = $staff;
}
$staffStmt->close();
$totalStaff = count($staffAccounts);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nhân viên</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'staff'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">TÀI KHOẢN &amp; PHÂN QUYỀN</span>
                <h1>Nhân viên</h1>
                <p>Nhấn vào nhân viên để chỉnh sửa chi tiết và phân quyền tài khoản.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
        </div>

        <?php if ($formError !== ''): ?>
            <p class="admin-alert admin-alert-error" role="alert"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (isset($_GET['staff_added'])): ?>
            <p class="admin-alert admin-alert-success" role="status">Đã tạo tài khoản nhân viên.</p>
        <?php elseif (isset($_GET['staff_updated'])): ?>
            <p class="admin-alert admin-alert-success" role="status">Đã cập nhật tài khoản và quyền nhân viên.</p>
        <?php elseif (isset($_GET['staff_toggled'])): ?>
            <p class="admin-alert admin-alert-success" role="status">Đã cập nhật trạng thái tài khoản nhân viên.</p>
        <?php endif; ?>

        <section class="admin-panel" aria-label="Danh sách nhân viên">
            <div class="admin-section-heading">
                <div>
                    <span class="admin-eyebrow">ĐỘI NGŨ</span>
                    <h2>Danh sách nhân viên</h2>
                </div>
                <div class="admin-staff-list-actions">
                    <span class="admin-record-count"><?php echo number_format($totalStaff); ?> <?php echo $search !== '' ? 'kết quả' : 'tài khoản'; ?></span>
                    <a class="admin-button admin-button-primary" href="./edit_staff.php">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> Thêm nhân viên
                    </a>
                </div>
            </div>

            <form method="GET" class="admin-search-form" role="search">
                <label class="admin-search-field" for="staff-search">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="staff-search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tìm theo tên nhân viên..." autocomplete="off">
                </label>
                <button class="admin-button admin-button-primary" type="submit">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i> Tìm kiếm
                </button>
                <?php if ($search !== ''): ?>
                    <a class="admin-search-clear" href="./manage_staff.php">
                        <i class="fas fa-xmark" aria-hidden="true"></i> Xóa lọc
                    </a>
                <?php endif; ?>
            </form>

            <?php if (empty($staffAccounts)): ?>
                <div class="admin-staff-empty">
                    <span class="admin-staff-empty-icon"><i class="fas fa-user-group" aria-hidden="true"></i></span>
                    <h3><?php echo $search !== '' ? 'Không tìm thấy nhân viên' : 'Chưa có nhân viên'; ?></h3>
                    <p><?php echo $search !== '' ? 'Thử tìm với từ khóa khác hoặc xóa bộ lọc.' : 'Tạo tài khoản đầu tiên để bắt đầu phân quyền quản lý cửa hàng.'; ?></p>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap admin-staff-table-wrap">
                    <table class="admin-staff-table">
                        <thead>
                            <tr>
                                <th class="col-id">Mã nhân viên</th>
                                <th class="col-name">Tên nhân viên</th>
                                <th class="col-username">Tài khoản</th>
                                <th class="col-status">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffAccounts as $staff): ?>
                                <?php
                                $staffId = (int) $staff['staff_id'];
                                $isActive = (int) $staff['is_active'] === 1;
                                $isSelf = isset($_SESSION['staff_id']) && (int) $_SESSION['staff_id'] === $staffId;
                                $editUrl = 'edit_staff.php?id=' . $staffId;
                                ?>
                                <tr class="admin-clickable-row" onclick="window.location.href='<?php echo $editUrl; ?>'" title="Nhấn để chỉnh sửa nhân viên">
                                    <td class="col-id">#<?php echo $staffId; ?></td>
                                    <td class="col-name">
                                        <a href="<?php echo $editUrl; ?>" class="admin-row-title-link">
                                            <strong><?php echo htmlspecialchars($staff['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </a>
                                    </td>
                                    <td class="col-username">
                                        <span class="admin-staff-username"><i class="fas fa-at" aria-hidden="true"></i><?php echo htmlspecialchars($staff['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td class="col-status" onclick="event.stopPropagation();">
                                        <?php if ($isSelf): ?>
                                            <span class="admin-staff-status <?php echo $isActive ? 'is-active' : 'is-locked'; ?>">
                                                <i class="fas <?php echo $isActive ? 'fa-circle-check' : 'fa-circle-minus'; ?>" aria-hidden="true"></i>
                                                <?php echo $isActive ? 'Đang hoạt động' : 'Đã khóa'; ?>
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" class="admin-staff-toggle-form" onsubmit="return confirm('<?php echo $isActive ? 'Khóa tài khoản nhân viên này? Nhân viên sẽ không thể đăng nhập.' : 'Kích hoạt lại tài khoản nhân viên này?'; ?>');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="staff_id" value="<?php echo $staffId; ?>">
                                                <button class="admin-staff-status admin-staff-status-button <?php echo $isActive ? 'is-active' : 'is-locked'; ?>" type="submit" title="<?php echo $isActive ? 'Nhấn để khóa tài khoản' : 'Nhấn để kích hoạt tài khoản'; ?>">
                                                    <i class="fas <?php echo $isActive ? 'fa-circle-check' : 'fa-circle-minus'; ?>" aria-hidden="true"></i>
                                                    <?php echo $isActive ? 'Đang hoạt động' : 'Đã khóa'; ?>
                                                </button>
                                            </form>
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
