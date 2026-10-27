<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php'); 
    exit();
}
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
requireAdminPagePermission($conn, 'orders.view');
$sortInput = isset($_POST['sort']) ? $_POST['sort'] : (isset($_GET['sort']) ? $_GET['sort'] : 'newest');
$sort = is_string($sortInput) ? $sortInput : 'newest';
$sortOptions = [
    'newest' => 'order_date DESC, order_id DESC',
    'pending' => "CASE WHEN status = 'Pending' THEN 0 ELSE 1 END, order_date DESC, order_id DESC",
    'oldest' => 'order_date ASC, order_id ASC'
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    requireAdminPagePermission($conn, 'orders.update');
    verifyAdminCsrfToken();
    $order_id = is_scalar($_POST['order_id'])
        ? filter_var($_POST['order_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $new_status = $_POST['status'];
    if (
        $order_id === false
        || !is_string($new_status)
        || !in_array($new_status, ['Pending', 'Confirmed', 'Cancelled'], true)
    ) {
        http_response_code(400);
        exit('Thông tin cập nhật đơn hàng không hợp lệ.');
    }
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    if (!$stmt) {
        die("Không thể chuẩn bị cập nhật trạng thái: " . $conn->error);
    }
    $stmt->bind_param("si", $new_status, $order_id);
    if (!$stmt->execute()) {
        die("Không thể cập nhật trạng thái đơn hàng: " . $stmt->error);
    }
    $stmt->close();
    $_SESSION['admin_orders_flash'] = "Cập nhật trạng thái đơn hàng thành công!";
    header('Location: manage_orders.php?sort=' . urlencode($sort));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    requireAdminPagePermission($conn, 'orders.delete');
    verifyAdminCsrfToken();
    $delete_order_id = is_scalar($_POST['delete_order_id'])
        ? filter_var($_POST['delete_order_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($delete_order_id === false) {
        http_response_code(400);
        exit('Mã đơn hàng không hợp lệ.');
    }
    $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
    if (!$stmt) {
        die("Không thể chuẩn bị xóa đơn hàng: " . $conn->error);
    }
    $stmt->bind_param("i", $delete_order_id);
    if (!$stmt->execute()) {
        die("Không thể xóa đơn hàng: " . $stmt->error);
    }
    $stmt->close();
    $_SESSION['admin_orders_flash'] = "Đơn hàng đã được xóa thành công!";
    header('Location: manage_orders.php?sort=' . urlencode($sort));
    exit();
}

$sql = "SELECT * FROM orders ORDER BY " . $sortOptions[$sort];
$result = $conn->query($sql);
if (!$result) {
    die("Không thể tải danh sách đơn hàng: " . $conn->error);
}
$orders = [];
while ($row = $result->fetch_assoc()) {
    $order_id = (int) $row['order_id'];
    $stmt_items = $conn->prepare(
        "SELECT SUM(oi.price * oi.quantity) AS total_amount
         FROM order_items oi
         WHERE oi.order_id = ?"
    );
    if (!$stmt_items) {
        die("Không thể chuẩn bị tổng tiền đơn hàng: " . $conn->error);
    }
    $stmt_items->bind_param("i", $order_id);
    if (!$stmt_items->execute()) {
        die("Không thể tải tổng tiền đơn hàng: " . $stmt_items->error);
    }
    $item_total = $stmt_items->get_result()->fetch_assoc();
    $stmt_items->close();
    $row['total_amount'] = isset($item_total['total_amount']) ? (float) $item_total['total_amount'] : 0;
    $orders[] = $row;
}
$result->free();
$total_orders = count($orders);
$total_revenue = 0;
$pending_orders = 0;
$confirmed_orders = 0;
$cancelled_orders = 0;
foreach ($orders as $order) {
    $total_revenue += $order['total_amount'];
    if ($order['status'] === 'Pending') {
        $pending_orders++;
    } elseif ($order['status'] === 'Confirmed') {
        $confirmed_orders++;
    } elseif ($order['status'] === 'Cancelled') {
        $cancelled_orders++;
    }
}
$update_message = isset($_SESSION['admin_orders_flash']) ? $_SESSION['admin_orders_flash'] : '';
unset($_SESSION['admin_orders_flash']);
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đơn Hàng</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'orders'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">QUẢN LÝ BÁN HÀNG</span>
                <h1>Đơn hàng</h1>
                <p>Theo dõi doanh thu, cập nhật trạng thái và xem chi tiết đơn hàng.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
        </div>

        <section class="admin-stat-grid admin-order-stats" aria-label="Thống kê đơn hàng">
            <div class="admin-stat-card">
                <span class="admin-stat-icon stat-orders"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Tổng đơn hàng</span><strong><?php echo number_format($total_orders); ?></strong>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-icon stat-products"><i class="fas fa-coins" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Tổng doanh thu</span><strong><?php echo number_format($total_revenue, 0, ',', '.'); ?> đ</strong>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-icon stat-pending"><i class="fas fa-clock" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Chờ xử lý</span><strong><?php echo number_format($pending_orders); ?></strong>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-icon stat-customers"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Đã xác nhận</span><strong><?php echo number_format($confirmed_orders); ?></strong>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-icon stat-cancelled"><i class="fas fa-ban" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Đã hủy</span><strong><?php echo number_format($cancelled_orders); ?></strong>
            </div>
        </section>

        <!-- Thông báo cập nhật trạng thái thành công -->
        <?php if ($update_message): ?>
            <div class="admin-alert admin-alert-success">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <?php echo htmlspecialchars($update_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <section class="admin-panel">
        <div class="admin-section-heading admin-orders-heading">
            <div><span class="admin-eyebrow">GẦN ĐÂY</span><h2>Danh sách đơn hàng</h2></div>
            <div class="admin-order-toolbar">
                <form method="GET" action="manage_orders.php" class="admin-order-sort">
                    <label for="order-sort">Sắp xếp</label>
                    <select id="order-sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Đơn mới nhất</option>
                        <option value="pending" <?php echo $sort === 'pending' ? 'selected' : ''; ?>>Chưa xử lý trước</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Đơn cũ nhất</option>
                    </select>
                    <button class="admin-button admin-button-secondary" type="submit">Áp dụng</button>
                </form>
                <span class="admin-record-count"><?php echo number_format($total_orders); ?> đơn hàng</span>
            </div>
        </div>
        <div class="admin-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mã Đơn Hàng</th>
                    <th>Họ và Tên</th>
                    <th>Địa Chỉ</th>
                    <th>Phương Thức Thanh Toán</th>
                    <th>Tổng Tiền</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Đặt Hàng</th>
                    <th>Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?php echo (int) $order['order_id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($order['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($order['address'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $order['total_amount'], 0, ',', '.') . " đ"; ?></td>
                        <td><span class="admin-status-badge <?php echo $order['status'] === 'Confirmed' ? 'status-confirmed' : ($order['status'] === 'Cancelled' ? 'status-cancelled' : 'status-pending'); ?>"><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars(isset($order['order_date']) ? $order['order_date'] : 'Không có thông tin', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="admin-actions admin-order-actions">
                            <?php if (adminHasPermission('orders.update')): ?>
                            <form method="POST" action="manage_orders.php" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
                                <select name="status">
                                    <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Chưa xử lý</option>
                                    <option value="Confirmed" <?php echo $order['status'] === 'Confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                                    <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                                <button class="admin-button admin-button-primary" type="submit">Cập nhật</button>
                            </form>
                            <?php endif; ?>

                            <form method="POST" action="view_order_details_admin.php" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <button class="admin-button admin-button-secondary" type="submit">Chi tiết</button>
                            </form>

                            <?php if (adminHasPermission('orders.delete')): ?>
                            <form method="POST" action="manage_orders.php" class="admin-inline-form" onsubmit="return confirm('Bạn có chắc chắn muốn xóa đơn hàng này?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="delete_order_id" value="<?php echo $order['order_id']; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="admin-button admin-button-danger" type="submit">Xóa</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </section>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
