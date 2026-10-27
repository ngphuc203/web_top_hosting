<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/admin_permissions.php';
if (!isset($_POST['csrf_token'])) {
    http_response_code(403);
    exit('Yêu cầu không hợp lệ.');
}
verifyAdminCsrfToken();
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    echo "Thiếu thông tin đơn hàng hoặc trạng thái.";
    exit();
}
$order_id = $_POST['order_id'];
$new_status = $_POST['status'];
require_once __DIR__ . '/config.php';
requireAdminPagePermission($conn, 'orders.update');
$sql = "UPDATE orders SET status = ? WHERE order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $order_id); 
if ($stmt->execute()) {
    header('Location: manage_orders.php');
    exit();
} else {
    echo "Cập nhật trạng thái thất bại.";
}
$stmt->close();
$conn->close();
?>
