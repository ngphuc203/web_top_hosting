<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_catalog_helpers.php';
ensureProductCatalogSchema($conn);

$searchQuery = isset($_GET['search']) && is_string($_GET['search'])
    ? trim($_GET['search'])
    : '';
$products = [];
if ($searchQuery !== '') {
    $searchTerm = '%' . $searchQuery . '%';
    $stmt = $conn->prepare(
        "SELECT product_id, name, price, image
        FROM products
        WHERE name LIKE ? OR description LIKE ?
        ORDER BY product_id DESC"
    );
    if (!$stmt) {
        die('Lỗi chuẩn bị tìm kiếm: ' . $conn->error);
    }
    $stmt->bind_param('ss', $searchTerm, $searchTerm);
    if (!$stmt->execute()) {
        die('Lỗi tìm kiếm sản phẩm: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả tìm kiếm</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="product-catalog-page">
    <?php include __DIR__ . '/site_header.php'; ?>
    <div class="header-mid-bar">
        <div class="header-marquee position-relative font-weight-bold text-light bg-dark">
            <div class="marquee d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
        </div>
    </div>
    <main class="store-search-page">
        <h1>
            <?php echo $searchQuery === '' ? 'Tìm kiếm sản phẩm' : 'Kết quả tìm kiếm'; ?>
            <?php if ($searchQuery !== ''): ?>
                <span>“<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>”</span>
            <?php endif; ?>
        </h1>
        <?php if ($searchQuery === ''): ?>
            <p>Nhập tên sản phẩm vào thanh tìm kiếm để bắt đầu.</p>
        <?php elseif (empty($products)): ?>
            <p>Không tìm thấy sản phẩm nào phù hợp.</p>
        <?php else: ?>
            <div class="product-list product-catalog">
                <?php foreach ($products as $product): ?>
                    <section class="product-catalog-item">
                        <div class="pro product-catalog-card">
                            <a href="./product_details.php?id=<?php echo (int) $product['product_id']; ?>">
                                <?php $imageUrl = productImageUrl($product['image']); ?>
                                <img class="product-card-image"
                                    src="<?php echo htmlspecialchars($imageUrl !== '' ? $imageUrl : './uploads/default.jpg', ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="product-catalog-name"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <p class="product-catalog-price"><?php echo number_format((float) $product['price'], 0, ',', '.'); ?> đ</p>
                            </a>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
