<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/staff_helpers.php';

ensureStaffSchema($conn);
$permissionGroups = staffPermissionGroups();

$isEdit = false;
$staff = [
    'staff_id' => 0,
    'fullname' => '',
    'username' => '',
    'is_active' => 1,
    'created_at' => null
];
$selectedGroups = [];
$formError = '';

if (isset($_GET['id']) && $_GET['id'] !== '') {
    $requestedId = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($requestedId === false) {
        header('Location: manage_staff.php');
        exit();
    }

    $staffId = (int) $requestedId;
    $staffStmt = $conn->prepare(
        'SELECT staff_id, fullname, username, is_active, created_at FROM staff_accounts WHERE staff_id = ?'
    );
    if (!$staffStmt) {
        die('Không thể tải thông tin nhân viên: ' . $conn->error);
    }
    $staffStmt->bind_param('i', $staffId);
    if (!$staffStmt->execute()) {
        die('Không thể tải thông tin nhân viên: ' . $staffStmt->error);
    }
    $staffRow = $staffStmt->get_result()->fetch_assoc();
    $staffStmt->close();

    if (!$staffRow) {
        header('Location: manage_staff.php');
        exit();
    }

    $staff = $staffRow;
    $isEdit = true;

    $groupStmt = $conn->prepare('SELECT group_key FROM staff_permission_groups WHERE staff_id = ?');
    if (!$groupStmt) {
        die('Không thể tải nhóm quyền nhân viên: ' . $conn->error);
    }
    $groupStmt->bind_param('i', $staffId);
    if (!$groupStmt->execute()) {
        die('Không thể tải nhóm quyền nhân viên: ' . $groupStmt->error);
    }
    $groupResult = $groupStmt->get_result();
    while ($groupRow = $groupResult->fetch_assoc()) {
        $selectedGroups[] = $groupRow['group_key'];
    }
    $groupStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $staffId = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
    $fullname = isset($_POST['fullname']) && is_string($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $selectedGroups = staffGroupKeysFromRequest(
        isset($_POST['permission_groups']) ? $_POST['permission_groups'] : [],
        $permissionGroups
    );
    $permissions = staffPermissionsFromGroups($selectedGroups, $permissionGroups);

    $staff['fullname'] = $fullname;
    $staff['username'] = $username;
    $staff['is_active'] = $isActive;

    if ($action === 'create') {
        if ($fullname === '' || $username === '' || strlen($password) < 8) {
            $formError = 'Vui lòng nhập tên, tài khoản và mật khẩu tối thiểu 8 ký tự.';
        }
    } elseif ($action === 'update') {
        if (!$isEdit || $staffId < 1 || $staffId !== (int) $staff['staff_id']) {
            $formError = 'Nhân viên không hợp lệ.';
        } elseif ($fullname === '' || $username === '' || ($password !== '' && strlen($password) < 8)) {
            $formError = 'Thông tin nhân viên không hợp lệ. Mật khẩu mới phải có ít nhất 8 ký tự.';
        } elseif (
            $isActive === 0
            && isset($_SESSION['staff_id'])
            && (int) $_SESSION['staff_id'] === $staffId
        ) {
            $formError = 'Không thể tự khóa tài khoản đang đăng nhập.';
        }
    } else {
        $formError = 'Thao tác nhân viên không hợp lệ.';
    }

    if ($formError === '' && $action === 'create') {
        $conn->begin_transaction();
        $staffStmt = $conn->prepare(
            'INSERT INTO staff_accounts (fullname, username, password_hash, is_active) VALUES (?, ?, ?, ?)'
        );
        if (!$staffStmt) {
            $conn->rollback();
            die('Không thể chuẩn bị tạo tài khoản nhân viên: ' . $conn->error);
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $staffStmt->bind_param('sssi', $fullname, $username, $passwordHash, $isActive);
        if (!$staffStmt->execute()) {
            $formError = $staffStmt->errno === 1062
                ? 'Tên tài khoản nhân viên này đã tồn tại.'
                : 'Không thể tạo tài khoản nhân viên: ' . $staffStmt->error;
        } else {
            $newStaffId = (int) $staffStmt->insert_id;
            $formError = saveStaffPermissions($conn, $newStaffId, $permissions);
            if ($formError === '') {
                $formError = saveStaffPermissionGroups($conn, $newStaffId, $selectedGroups);
            }
        }
        $staffStmt->close();

        if ($formError === '') {
            $conn->commit();
            header('Location: manage_staff.php?staff_added=1');
            exit();
        }
        $conn->rollback();
    }

    if ($formError === '' && $action === 'update') {
        $conn->begin_transaction();
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $staffStmt = $conn->prepare(
                'UPDATE staff_accounts SET fullname = ?, username = ?, password_hash = ?, is_active = ? WHERE staff_id = ?'
            );
            if (!$staffStmt) {
                $conn->rollback();
                die('Không thể chuẩn bị cập nhật nhân viên: ' . $conn->error);
            }
            $staffStmt->bind_param('sssii', $fullname, $username, $passwordHash, $isActive, $staffId);
        } else {
            $staffStmt = $conn->prepare(
                'UPDATE staff_accounts SET fullname = ?, username = ?, is_active = ? WHERE staff_id = ?'
            );
            if (!$staffStmt) {
                $conn->rollback();
                die('Không thể chuẩn bị cập nhật nhân viên: ' . $conn->error);
            }
            $staffStmt->bind_param('ssii', $fullname, $username, $isActive, $staffId);
        }

        if (!$staffStmt->execute()) {
            $formError = $staffStmt->errno === 1062
                ? 'Tên tài khoản nhân viên này đã tồn tại.'
                : 'Không thể cập nhật nhân viên: ' . $staffStmt->error;
        }
        $staffStmt->close();

        if ($formError === '') {
            $deletePermissionsStmt = $conn->prepare('DELETE FROM staff_permissions WHERE staff_id = ?');
            if (!$deletePermissionsStmt) {
                $conn->rollback();
                die('Không thể chuẩn bị cập nhật quyền: ' . $conn->error);
            }
            $deletePermissionsStmt->bind_param('i', $staffId);
            if (!$deletePermissionsStmt->execute()) {
                $formError = 'Không thể cập nhật quyền nhân viên: ' . $deletePermissionsStmt->error;
            }
            $deletePermissionsStmt->close();
        }

        if ($formError === '') {
            $deleteGroupsStmt = $conn->prepare('DELETE FROM staff_permission_groups WHERE staff_id = ?');
            if (!$deleteGroupsStmt) {
                $conn->rollback();
                die('Không thể chuẩn bị cập nhật nhóm quyền: ' . $conn->error);
            }
            $deleteGroupsStmt->bind_param('i', $staffId);
            if (!$deleteGroupsStmt->execute()) {
                $formError = 'Không thể cập nhật nhóm quyền nhân viên: ' . $deleteGroupsStmt->error;
            }
            $deleteGroupsStmt->close();
        }

        if ($formError === '') {
            $formError = saveStaffPermissions($conn, $staffId, $permissions);
        }
        if ($formError === '') {
            $formError = saveStaffPermissionGroups($conn, $staffId, $selectedGroups);
        }

        if ($formError === '') {
            $conn->commit();
            header('Location: manage_staff.php?staff_updated=1');
            exit();
        }
        $conn->rollback();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Chỉnh sửa nhân viên' : 'Thêm nhân viên'; ?></title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'staff'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">TÀI KHOẢN &amp; PHÂN QUYỀN</span>
                <h1><?php echo $isEdit ? 'Chỉnh sửa nhân viên' : 'Thêm nhân viên'; ?></h1>
                <p><?php echo $isEdit ? 'Cập nhật thông tin tài khoản và phân quyền chi tiết cho nhân viên.' : 'Tạo tài khoản nhân viên mới và chọn nhóm nghiệp vụ được phép quản lý.'; ?></p>
            </div>
            <span class="admin-heading-icon"><i class="fas <?php echo $isEdit ? 'fa-user-pen' : 'fa-user-plus'; ?>" aria-hidden="true"></i></span>
        </div>

        <?php if ($formError !== ''): ?>
            <p class="admin-alert admin-alert-error" role="alert"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="admin-section-heading">
                <div>
                    <span class="admin-eyebrow">THÔNG TIN TÀI KHOẢN</span>
                    <h2><?php echo $isEdit ? 'Hồ sơ nhân viên' : 'Tài khoản mới'; ?></h2>
                </div>
                <?php if ($isEdit): ?>
                    <span class="admin-record-count">#<?php echo (int) $staff['staff_id']; ?></span>
                <?php endif; ?>
            </div>

            <form method="POST" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="staff_id" value="<?php echo (int) $staff['staff_id']; ?>">
                <?php endif; ?>

                <div class="admin-staff-identity-grid admin-form-wide">
                    <label for="staff-fullname">Họ và tên
                        <input id="staff-fullname" name="fullname" type="text" maxlength="150" autocomplete="name" value="<?php echo htmlspecialchars($staff['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label for="staff-username">Tên đăng nhập
                        <input id="staff-username" name="username" type="text" maxlength="80" autocomplete="username" value="<?php echo htmlspecialchars($staff['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label for="staff-password"><?php echo $isEdit ? 'Mật khẩu mới (không bắt buộc)' : 'Mật khẩu ban đầu'; ?>
                        <input id="staff-password" name="password" type="password" minlength="8" autocomplete="new-password" <?php echo $isEdit ? '' : 'required'; ?>>
                    </label>
                </div>

                <label class="admin-staff-active admin-form-wide">
                    <input type="checkbox" name="is_active" value="1" <?php echo (int) $staff['is_active'] === 1 ? 'checked' : ''; ?>>
                    Tài khoản đang hoạt động
                </label>

                <div class="admin-staff-permissions admin-form-wide">
                    <div class="admin-staff-subheading">
                        <div>
                            <h3>Nhóm quyền</h3>
                            <p>Chọn nhóm nghiệp vụ nhân viên này được phép quản lý. Có thể chọn nhiều nhóm.</p>
                        </div>
                    </div>
                    <div class="admin-staff-group-grid">
                        <?php foreach ($permissionGroups as $groupKey => $group): ?>
                            <?php $groupSelected = in_array($groupKey, $selectedGroups, true); ?>
                            <label class="admin-staff-group-option">
                                <input type="checkbox" name="permission_groups[]" value="<?php echo htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $groupSelected ? 'checked' : ''; ?>>
                                <span class="admin-staff-group-option-icon"><i class="fas <?php echo htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
                                <span class="admin-staff-group-option-copy">
                                    <strong><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small><?php echo htmlspecialchars($group['description'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </span>
                                <span class="admin-staff-group-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="admin-staff-form-actions admin-form-wide">
                    <button class="admin-button admin-button-primary admin-form-submit" type="submit">
                        <i class="fas <?php echo $isEdit ? 'fa-floppy-disk' : 'fa-user-plus'; ?>" aria-hidden="true"></i>
                        <?php echo $isEdit ? 'Lưu thay đổi' : 'Tạo nhân viên'; ?>
                    </button>
                    <a class="admin-button admin-button-secondary" href="./manage_staff.php">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Quay lại danh sách
                    </a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
