<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_permissions.php';
requireAdminPagePermission($conn, 'dashboard.view');

$canManageStaff = adminHasPermission('staff.manage');
if ($canManageStaff) {
    ensureStaffSchema($conn);
}

$dashboardStats = [];
$statQueries = [
    'products' => 'SELECT COUNT(*) AS total FROM products',
    'customers' => 'SELECT COUNT(*) AS total FROM users',
    'orders' => 'SELECT COUNT(*) AS total FROM orders',
    'pending_orders' => "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'"
];
foreach ($statQueries as $key => $query) {
    $result = $conn->query($query);
    if (!$result) {
        die('Không thể tải thống kê quản trị: ' . $conn->error);
    }
    $dashboardStats[$key] = (int) $result->fetch_assoc()['total'];
    $result->free();
}
if ($canManageStaff) {
    $staffStatsResult = $conn->query(
        'SELECT COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active
         FROM staff_accounts'
    );
    if (!$staffStatsResult) {
        die('Không thể tải số liệu nhân viên: ' . $conn->error);
    }
    $staffStats = $staffStatsResult->fetch_assoc();
    $dashboardStats['staff'] = (int) $staffStats['total'];
    $dashboardStats['active_staff'] = (int) $staffStats['active'];
    $staffStatsResult->free();
}

$chartStart = (new DateTimeImmutable('first day of this month'))
    ->modify('-5 months')
    ->setTime(0, 0, 0);
$chartEnd = (new DateTimeImmutable('first day of next month'))->setTime(0, 0, 0);
$chartMonths = [];
for ($monthOffset = 0; $monthOffset < 6; $monthOffset++) {
    $month = $chartStart->modify('+' . $monthOffset . ' months');
    $chartMonths[$month->format('Y-m')] = [
        'label' => $month->format('m/Y'),
        'orders' => 0,
        'revenue' => 0.0
    ];
}

$chartStmt = $conn->prepare(
    "SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS month_key,
            COUNT(DISTINCT o.order_id) AS order_count,
            COALESCE(SUM(CASE WHEN o.status = 'Confirmed' THEN o.total_amount ELSE 0 END), 0) AS revenue
     FROM orders o
     WHERE o.order_date >= ? AND o.order_date < ?
     GROUP BY month_key
     ORDER BY month_key"
);
if (!$chartStmt) {
    die('Không thể chuẩn bị dữ liệu biểu đồ: ' . $conn->error);
}
$chartStartValue = $chartStart->format('Y-m-d H:i:s');
$chartEndValue = $chartEnd->format('Y-m-d H:i:s');
$chartStmt->bind_param('ss', $chartStartValue, $chartEndValue);
if (!$chartStmt->execute()) {
    die('Không thể tải dữ liệu biểu đồ: ' . $chartStmt->error);
}
$chartResult = $chartStmt->get_result();
while ($chartRow = $chartResult->fetch_assoc()) {
    if (isset($chartMonths[$chartRow['month_key']])) {
        $chartMonths[$chartRow['month_key']]['orders'] = (int) $chartRow['order_count'];
        $chartMonths[$chartRow['month_key']]['revenue'] = (float) $chartRow['revenue'];
    }
}
$chartStmt->close();
$chartMaximumOrders = max(1, max(array_column($chartMonths, 'orders')));
$chartMaximumRevenue = max(1, max(array_column($chartMonths, 'revenue')));
$chartRevenuePoints = [];
$chartOrderBars = [];
$chartRevenueMarkers = [];
$chartLabels = [];
$chartIndex = 0;
foreach ($chartMonths as $monthData) {
    $x = 96 + ($chartIndex * 128);
    $barHeight = (int) $monthData['orders'] / $chartMaximumOrders * 126;
    $barY = 210 - $barHeight;
    $revenueY = 210 - ((float) $monthData['revenue'] / $chartMaximumRevenue * 126);
    $chartRevenuePoints[] = $x . ',' . number_format($revenueY, 1, '.', '');
    $chartOrderBars[] = '<rect class="admin-chart-bar" x="' . ($x - 22) . '" y="' .
        number_format($barY, 1, '.', '') . '" width="44" height="' .
        number_format($barHeight, 1, '.', '') . '" rx="8"><title>' .
        htmlspecialchars($monthData['label'] . ': ' . $monthData['orders'] . ' đơn', ENT_QUOTES, 'UTF-8') .
        '</title></rect>';
    $chartRevenueMarkers[] = '<circle class="admin-chart-revenue-point" cx="' . $x . '" cy="' .
        number_format($revenueY, 1, '.', '') . '" r="5"><title>' .
        htmlspecialchars($monthData['label'] . ': ' . number_format((float) $monthData['revenue'], 0, ',', '.') . ' đ', ENT_QUOTES, 'UTF-8') .
        '</title></circle>';
    $chartLabels[] = '<text x="' . $x . '" y="242">' .
        htmlspecialchars($monthData['label'], ENT_QUOTES, 'UTF-8') . '</text>';
    $chartIndex++;
}
$chartRevenuePath = implode(' ', $chartRevenuePoints);
$conn->close();

$adminActivePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng quan quản trị | POP_POP</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page admin-dashboard-page">
    <?php $adminActivePage = 'dashboard'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">TRUNG TÂM ĐIỀU HÀNH</span>
                <h1>Xin chào, <?php echo htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'Quản trị viên', ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Theo dõi hoạt động cửa hàng và truy cập nhanh các công cụ quản lý.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
        </div>

        <?php if (adminHasPermission('products.view') || adminHasPermission('customers.view') || adminHasPermission('orders.view') || $canManageStaff): ?>
        <section class="admin-stat-grid <?php echo $canManageStaff ? 'admin-stat-grid-with-staff' : ''; ?>" aria-label="Tổng quan cửa hàng">
            <?php if (adminHasPermission('products.view')): ?>
            <a class="admin-stat-card" href="./manage_products.php">
                <span class="admin-stat-icon stat-products"><i class="fas fa-box-open" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Sản phẩm</span>
                <strong><?php echo number_format($dashboardStats['products']); ?></strong>
                <span class="admin-stat-link">Quản lý sản phẩm <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <?php endif; ?>
            <?php if (adminHasPermission('customers.view')): ?>
            <a class="admin-stat-card" href="./manage_customers.php">
                <span class="admin-stat-icon stat-customers"><i class="fas fa-users" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Khách hàng</span>
                <strong><?php echo number_format($dashboardStats['customers']); ?></strong>
                <span class="admin-stat-link">Xem khách hàng <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <?php endif; ?>
            <?php if ($canManageStaff): ?>
            <a class="admin-stat-card" href="./manage_staff.php">
                <span class="admin-stat-icon stat-staff"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Nhân viên</span>
                <strong><?php echo number_format($dashboardStats['staff']); ?></strong>
                <span class="admin-stat-link"><?php echo number_format($dashboardStats['active_staff']); ?> đang hoạt động <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <?php endif; ?>
            <?php if (adminHasPermission('orders.view')): ?>
            <a class="admin-stat-card" href="./manage_orders.php">
                <span class="admin-stat-icon stat-orders"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Tổng đơn hàng</span>
                <strong><?php echo number_format($dashboardStats['orders']); ?></strong>
                <span class="admin-stat-link">Xem đơn hàng <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a class="admin-stat-card" href="./manage_orders.php">
                <span class="admin-stat-icon stat-pending"><i class="fas fa-clock" aria-hidden="true"></i></span>
                <span class="admin-stat-label">Đơn chờ xử lý</span>
                <strong><?php echo number_format($dashboardStats['pending_orders']); ?></strong>
                <span class="admin-stat-link">Cần chú ý <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (adminHasPermission('orders.view')): ?>
        <section class="admin-panel admin-chart-panel">
            <div class="admin-section-heading">
                <div>
                    <span class="admin-eyebrow">HIỆU SUẤT CỬA HÀNG</span>
                    <h2>Đơn hàng &amp; doanh thu</h2>
                </div>
                <div class="admin-chart-legend" aria-label="Chú giải biểu đồ">
                    <span><i class="legend-orders" aria-hidden="true"></i> Số đơn hàng</span>
                    <span><i class="legend-revenue" aria-hidden="true"></i> Doanh thu</span>
                </div>
            </div>
            <p class="admin-chart-caption">Theo dõi kết quả theo tháng trong 6 tháng gần nhất.</p>
            <div class="admin-chart-wrap">
                <svg class="admin-orders-chart" viewBox="0 0 850 270" role="img" aria-labelledby="ordersChartTitle ordersChartDescription">
                    <title id="ordersChartTitle">Đơn hàng và doanh thu theo tháng</title>
                    <desc id="ordersChartDescription">Biểu đồ cột số đơn hàng và đường doanh thu trong sáu tháng gần nhất.</desc>
                    <line x1="48" y1="84" x2="820" y2="84"></line>
                    <line x1="48" y1="126" x2="820" y2="126"></line>
                    <line x1="48" y1="168" x2="820" y2="168"></line>
                    <line x1="48" y1="210" x2="820" y2="210"></line>
                    <?php echo implode('', $chartOrderBars); ?>
                    <polyline class="admin-chart-revenue-line" points="<?php echo htmlspecialchars($chartRevenuePath, ENT_QUOTES, 'UTF-8'); ?>"></polyline>
                    <?php echo implode('', $chartRevenueMarkers); ?>
                    <?php echo implode('', $chartLabels); ?>
                </svg>
            </div>
        </section>
        <?php endif; ?>

        <?php if (adminHasPermission('products.view') || adminHasPermission('categories.view') || adminHasPermission('customers.view') || adminHasPermission('orders.view') || adminHasPermission('contacts.view') || adminHasPermission('staff.manage')): ?>
        <section class="admin-quick-actions">
            <div class="admin-section-heading">
                <div>
                    <span class="admin-eyebrow">LỐI TẮT</span>
                    <h2>Quản lý cửa hàng</h2>
                </div>
                <p>Chọn một khu vực để tiếp tục.</p>
            </div>
            <div class="admin-action-grid">
                <?php if (adminHasPermission('products.view')): ?>
                <a href="./manage_products.php">
                    <i class="fas fa-cube" aria-hidden="true"></i>
                    <span><strong>Quản lý sản phẩm</strong><small>Thêm sản phẩm và cập nhật thông tin kho hàng.</small></span>
                    <i class="fas fa-arrow-right action-arrow" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <?php if (adminHasPermission('categories.view')): ?>
                <a href="./manage_categories.php">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    <span><strong>Quản lý danh mục</strong><small>Thêm, đổi tên và xóa danh mục sản phẩm.</small></span>
                    <i class="fas fa-arrow-right action-arrow" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <?php if (adminHasPermission('customers.view')): ?>
                <a href="./manage_customers.php">
                    <i class="fas fa-user-group" aria-hidden="true"></i>
                    <span><strong>Quản lý khách hàng</strong><small>Xem hồ sơ và quản lý danh sách khách hàng.</small></span>
                    <i class="fas fa-arrow-right action-arrow" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <?php if (adminHasPermission('orders.view')): ?>
                <a href="./manage_orders.php">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <span><strong>Quản lý đơn hàng</strong><small>Theo dõi trạng thái và xử lý các đơn đã đặt.</small></span>
                    <i class="fas fa-arrow-right action-arrow" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <?php if (adminHasPermission('contacts.view')): ?>
                <a href="./manage_contacts.php">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <span><strong>Quản lý liên hệ</strong><small>Xem và xử lý tin nhắn khách hàng gửi đến.</small></span>
                    <i class="fas fa-arrow-right action-arrow" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <?php if (adminHasPermission('staff.manage')): ?>
                <a href="./manage_staff.php">
                    <i class="fas fa-user-shield" aria-hidden="true"></i>
                    <span><strong>Quản lý nhân viên</strong><small>Tạo tài khoản và phân quyền truy cập.</small></span>
                    <i class="fas fa-arrow-right action-arrow" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
