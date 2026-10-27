<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_catalog_helpers.php';
$searchQuery = isset($_GET['search']) && is_string($_GET['search'])
    ? trim($_GET['search'])
    : '';
if ($searchQuery !== '') {
    $searchTerm = '%' . $searchQuery . '%';
    $stmt = $conn->prepare(
        "SELECT product_id, name, price, image FROM products
        WHERE name LIKE ? OR description LIKE ?
        ORDER BY product_id DESC LIMIT 8"
    );
    if (!$stmt) {
        http_response_code(500);
        die('Lỗi chuẩn bị tìm kiếm: ' . $conn->error);
    }
    $stmt->bind_param('ss', $searchTerm, $searchTerm);
} else {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.name, p.price, p.image
        FROM products p
        LEFT JOIN (
            SELECT product_id, SUM(quantity) AS sold_quantity
            FROM order_items
            GROUP BY product_id
        ) sales ON sales.product_id = p.product_id
        ORDER BY COALESCE(sales.sold_quantity, 0) DESC, p.product_id DESC
        LIMIT 6"
    );
    if (!$stmt) {
        http_response_code(500);
        die('Lỗi lấy sản phẩm phổ biến: ' . $conn->error);
    }
}
if (!$stmt->execute()) {
    http_response_code(500);
    die('Lỗi thực hiện tìm kiếm: ' . $stmt->error);
}
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $row['image'] = productImageUrl($row['image']);
    $products[] = $row;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$stmt->close();
$conn->close();
?>
