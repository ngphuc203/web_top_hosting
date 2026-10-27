<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/product_catalog_helpers.php';

require_once __DIR__ . '/config.php';
requireAdminPagePermission($conn, 'orders.view');

$orderIdInput = isset($_POST['order_id']) ? $_POST['order_id'] : (isset($_GET['order_id']) ? $_GET['order_id'] : '');
$orderId = is_scalar($orderIdInput)
    ? filter_var($orderIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$orderActionNotice = isset($_SESSION['admin_order_detail_flash']) ? $_SESSION['admin_order_detail_flash'] : null;
unset($_SESSION['admin_order_detail_flash']);
if (!isset($_SESSION['admin_order_csrf_token'])) {
    $_SESSION['admin_order_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order']) && $orderId !== false) {
    requireAdminPagePermission($conn, 'orders.update');
    if (
        !isset($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['admin_order_csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Yêu cầu xác nhận đơn hàng không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }

    $confirmStmt = $conn->prepare(
        "UPDATE orders SET status = 'Confirmed' WHERE order_id = ? AND status = 'Pending'"
    );
    if (!$confirmStmt) {
        die('Không thể chuẩn bị xác nhận đơn hàng: ' . $conn->error);
    }
    $confirmStmt->bind_param('i', $orderId);
    if (!$confirmStmt->execute()) {
        die('Không thể xác nhận đơn hàng: ' . $confirmStmt->error);
    }
    $wasConfirmed = $confirmStmt->affected_rows === 1;
    $confirmStmt->close();

    $_SESSION['admin_order_detail_flash'] = [
        'success' => $wasConfirmed,
        'message' => $wasConfirmed
            ? 'Đơn hàng đã được xác nhận thành công.'
            : 'Đơn hàng không còn ở trạng thái chờ xử lý nên chưa được thay đổi.'
    ];
    header('Location: view_order_details_admin.php?order_id=' . $orderId);
    exit();
}

if ($orderId === false) {
    $conn->close();
    http_response_code(400);
    $pageError = 'Mã đơn hàng không hợp lệ.';
    $orderDetails = null;
    $items = [];
    $totalAmount = 0;
} else {
    $orderStmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ?');
    if (!$orderStmt) {
        die('Không thể chuẩn bị thông tin đơn hàng: ' . $conn->error);
    }
    $orderStmt->bind_param('i', $orderId);
    if (!$orderStmt->execute()) {
        die('Không thể tải thông tin đơn hàng: ' . $orderStmt->error);
    }
    $orderDetails = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();

    $items = [];
    $totalAmount = 0;
    $pageError = '';
    if (!$orderDetails) {
        http_response_code(404);
        $pageError = 'Không tìm thấy đơn hàng này.';
    } else {
        $itemsStmt = $conn->prepare(
            'SELECT oi.*, p.image
             FROM order_items oi
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.order_id, oi.product_id'
        );
        if (!$itemsStmt) {
            die('Không thể chuẩn bị danh sách sản phẩm: ' . $conn->error);
        }
        $itemsStmt->bind_param('i', $orderId);
        if (!$itemsStmt->execute()) {
            die('Không thể tải danh sách sản phẩm: ' . $itemsStmt->error);
        }
        $itemsResult = $itemsStmt->get_result();
        while ($item = $itemsResult->fetch_assoc()) {
            $item['quantity'] = (int) $item['quantity'];
            $item['price'] = (float) $item['price'];
            $item['line_total'] = $item['price'] * $item['quantity'];
            $totalAmount += $item['line_total'];
            $items[] = $item;
        }
        $itemsStmt->close();
    }
    $conn->close();
}

$adminActivePage = 'orders';
$statusClass = isset($orderDetails['status']) && $orderDetails['status'] === 'Confirmed'
    ? 'status-confirmed'
    : (isset($orderDetails['status']) && $orderDetails['status'] === 'Cancelled' ? 'status-cancelled' : 'status-pending');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng | POP_POP</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main admin-order-detail-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">QUẢN LÝ BÁN HÀNG</span>
                <h1>Chi tiết đơn hàng</h1>
                <p>Xem thông tin người nhận, trạng thái và sản phẩm trong đơn.</p>
            </div>
            <a class="admin-button admin-button-secondary" href="./manage_orders.php">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Danh sách đơn hàng
            </a>
        </div>

        <?php if ($pageError !== ''): ?>
            <section class="admin-panel admin-order-empty">
                <span class="admin-heading-icon"><i class="fas fa-circle-exclamation" aria-hidden="true"></i></span>
                <h2><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></h2>
                <a class="admin-button admin-button-primary" href="./manage_orders.php">Quay lại đơn hàng</a>
            </section>
        <?php else: ?>
            <?php if ($orderActionNotice): ?>
                <div class="admin-alert <?php echo !empty($orderActionNotice['success']) ? '' : 'admin-alert-error'; ?>" role="status">
                    <i class="fas <?php echo !empty($orderActionNotice['success']) ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($orderActionNotice['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <section class="admin-order-summary">
                <div>
                    <span class="admin-eyebrow">MÃ ĐƠN HÀNG</span>
                    <h2>#<?php echo (int) $orderDetails['order_id']; ?></h2>
                    <p><?php echo htmlspecialchars(isset($orderDetails['order_date']) ? $orderDetails['order_date'] : 'Không có thông tin ngày đặt', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="admin-order-total">
                    <span>Tổng giá trị đơn hàng</span>
                    <strong><?php echo number_format($totalAmount, 0, ',', '.'); ?> đ</strong>
                </div>
                <div class="admin-order-status-actions">
                    <span class="admin-status-badge <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($orderDetails['status'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php if ($orderDetails['status'] === 'Pending' && adminHasPermission('orders.update')): ?>
                        <form method="POST" action="view_order_details_admin.php" class="admin-inline-form">
                            <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_order_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="admin-button admin-button-primary" type="submit" name="confirm_order" value="1">
                                <i class="fas fa-check" aria-hidden="true"></i> Xác nhận đơn
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <div class="admin-order-detail-grid">
                <section class="admin-panel">
                    <div class="admin-section-heading">
                        <div><span class="admin-eyebrow">NGƯỜI NHẬN</span><h2>Thông tin khách hàng</h2></div>
                        <span class="admin-detail-icon"><i class="fas fa-user" aria-hidden="true"></i></span>
                    </div>
                    <dl class="admin-order-info">
                        <div><dt>Họ và tên</dt><dd><?php echo htmlspecialchars($orderDetails['fullname'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div><dt>Số điện thoại</dt><dd><?php echo htmlspecialchars($orderDetails['phone'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div><dt>Địa chỉ giao hàng</dt><dd><?php echo htmlspecialchars($orderDetails['address'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div><dt>Phương thức thanh toán</dt><dd><?php echo htmlspecialchars($orderDetails['payment_method'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    </dl>
                </section>

                <section class="admin-panel admin-order-items-panel">
                    <div class="admin-section-heading">
                        <div><span class="admin-eyebrow">SẢN PHẨM</span><h2>Danh sách sản phẩm</h2></div>
                        <span class="admin-record-count"><?php echo count($items); ?> sản phẩm</span>
                    </div>
                    <?php if (empty($items)): ?>
                        <p class="admin-order-no-items">Đơn hàng chưa có sản phẩm chi tiết.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-order-items-table">
                                <thead>
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Đơn giá</th>
                                        <th>Số lượng</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="admin-order-product">
                                                    <?php $itemImage = productImageUrl(isset($item['image']) ? $item['image'] : ''); ?>
                                                    <?php if ($itemImage !== ''): ?>
                                                        <img src="<?php echo htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8'); ?>"
                                                            alt="<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php else: ?>
                                                        <span class="admin-order-product-placeholder"><i class="fas fa-cube" aria-hidden="true"></i></span>
                                                    <?php endif; ?>
                                                    <strong><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($item['price'], 0, ',', '.'); ?> đ</td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td><strong><?php echo number_format($item['line_total'], 0, ',', '.'); ?> đ</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">Tổng cộng</th>
                                        <th><?php echo number_format($totalAmount, 0, ',', '.'); ?> đ</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
