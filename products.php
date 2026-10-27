<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_catalog_helpers.php';
ensureProductCatalogSchema($conn);
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $product_id = $_POST['product_id'];
    $quantity = (int) $_POST['quantity'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $_SESSION['cart'][] = [
            'id' => $product['product_id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'image' => $product['image'] 
        ];
    }
    $stmt->close();
    header('Location: products.php');
    exit();
}
$sql = "SELECT p.*, character_category.name AS character_category_name, size_category.name AS size_category_name
    FROM products p
    LEFT JOIN product_character_categories AS character_category
        ON character_category.category_id = p.character_category_id
    LEFT JOIN product_size_categories AS size_category
        ON size_category.category_id = p.size_category_id";

$categoryResult = $conn->query(
    "SELECT category_id, name FROM product_character_categories ORDER BY name"
);
if (!$categoryResult) {
    die('Lỗi lấy danh mục sản phẩm: ' . $conn->error);
}
$productCategories = [];
$validCategoryIds = [];
while ($category = $categoryResult->fetch_assoc()) {
    $productCategories[] = $category;
    $validCategoryIds[] = (int) $category['category_id'];
}

$requestedCategoryIds = isset($_GET['category']) && is_array($_GET['category'])
    ? $_GET['category']
    : [];
$selectedCategoryIds = [];
foreach ($requestedCategoryIds as $requestedCategoryId) {
    if (is_scalar($requestedCategoryId) && ctype_digit((string) $requestedCategoryId)) {
        $selectedCategoryIds[] = (int) $requestedCategoryId;
    }
}
$selectedCategoryIds = array_values(array_unique(array_filter($selectedCategoryIds, function ($categoryId) {
    return $categoryId > 0;
})));
$selectedCategoryIds = array_values(array_intersect($selectedCategoryIds, $validCategoryIds));
$sortOrder = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'hot';
$allowedSortOrders = [
    'hot' => 'p.product_id DESC',
    'popular' => '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.product_id) DESC, p.product_id DESC',
    'price_asc' => 'p.price ASC, p.product_id DESC',
    'price_desc' => 'p.price DESC, p.product_id DESC'
];
if (!isset($allowedSortOrders[$sortOrder])) {
    $sortOrder = 'hot';
}

if (!empty($selectedCategoryIds)) {
    $sql .= ' WHERE p.character_category_id IN (' . implode(',', $selectedCategoryIds) . ')';
}
$selectedSizeCategoryId = isset($_GET['size_category']) && is_scalar($_GET['size_category'])
    ? (int) $_GET['size_category']
    : 0;
if ($selectedSizeCategoryId > 0) {
    $validSizeResult = $conn->query('SELECT category_id FROM product_size_categories');
    if (!$validSizeResult) {
        die('Lỗi kiểm tra danh mục kích cỡ: ' . $conn->error);
    }
    $validSizeCategoryIds = [];
    while ($sizeCategory = $validSizeResult->fetch_assoc()) {
        $validSizeCategoryIds[] = (int) $sizeCategory['category_id'];
    }
    if (in_array($selectedSizeCategoryId, $validSizeCategoryIds, true)) {
        $sql .= empty($selectedCategoryIds) ? ' WHERE' : ' AND';
        $sql .= ' p.size_category_id = ' . $selectedSizeCategoryId;
    }
}
$sql .= ' ORDER BY ' . $allowedSortOrders[$sortOrder];

$result = $conn->query($sql);
if (!$result) {
    die('Lỗi lấy danh sách sản phẩm: ' . $conn->error);
}
$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$recommendedProducts = $products;
shuffle($recommendedProducts);
$recommendedProducts = array_slice($recommendedProducts, 0, 4);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <noscript>
        <style>
            .product-catalog-mobile-bar,
            .product-filter-close {
                display: none !important;
            }
        </style>
    </noscript>
</head>

<body class="product-catalog-page">
    <?php include __DIR__ . '/site_header.php'; ?>

    <div class="header-mid-bar">
        <div class="header-marquee position-relative font-weight-bold text-light bg-dark">
            <div class="marquee d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
            <div class="marquee marquee2 d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAUU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
        </div>
    </div>

    <nav class="product-breadcrumb product-catalog-breadcrumb" aria-label="Điều hướng">
        <a href="./index.php">Trang chủ</a>
        <span>/</span>
        <span class="current">Sản phẩm</span>
    </nav>
    <form class="product-catalog-filter-form" method="GET" action="products.php">
        <div class="product-catalog-mobile-bar">
            <button type="button" class="product-filter-toggle" aria-expanded="false" aria-controls="productCatalogFilters">
                <i class="fas fa-sliders" aria-hidden="true"></i>
                <span>Bộ lọc</span>
                <?php if (!empty($selectedCategoryIds)): ?>
                    <span class="product-filter-badge"><?php echo count($selectedCategoryIds); ?></span>
                <?php endif; ?>
            </button>
            <span class="product-catalog-count-mobile"><?php echo count($products); ?> sản phẩm</span>
        </div>

        <button type="button" class="product-filter-backdrop" aria-label="Đóng bộ lọc" tabindex="-1" hidden></button>

        <div class="product-catalog-layout">
            <aside id="productCatalogFilters" class="product-catalog-filters" aria-label="Bộ lọc sản phẩm">
                <div class="product-catalog-filters-head">
                    <h2>Bộ lọc sản phẩm</h2>
                    <button type="button" class="product-filter-close" aria-label="Đóng bộ lọc">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="product-catalog-filters-group">
                    <p class="product-catalog-filters-label">Danh mục</p>
                    <?php foreach ($productCategories as $category): ?>
                        <label class="product-category-option">
                            <input
                                type="checkbox"
                                name="category[]"
                                value="<?php echo (int) $category['category_id']; ?>"
                                <?php echo in_array((int) $category['category_id'], $selectedCategoryIds, true) ? 'checked' : ''; ?>
                            >
                            <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="product-filter-footer">
                    <?php if (!empty($selectedCategoryIds)): ?>
                        <a class="product-filter-clear" href="products.php">Xoá bộ lọc</a>
                    <?php endif; ?>
                    <button type="submit" class="product-filter-apply">Áp dụng</button>
                </div>
            </aside>

            <main class="product-catalog-results">
                <div class="product-catalog-toolbar">
                    <h2 class="product-catalog-result-count"><?php echo count($products); ?> sản phẩm</h2>
                    <label for="product-sort">Sắp xếp:</label>
                    <select id="product-sort" name="sort">
                        <option value="hot" <?php echo $sortOrder === 'hot' ? 'selected' : ''; ?>>Hot - Mới nhất</option>
                        <option value="popular" <?php echo $sortOrder === 'popular' ? 'selected' : ''; ?>>Bán chạy</option>
                        <option value="price_asc" <?php echo $sortOrder === 'price_asc' ? 'selected' : ''; ?>>Giá: thấp đến cao</option>
                        <option value="price_desc" <?php echo $sortOrder === 'price_desc' ? 'selected' : ''; ?>>Giá: cao đến thấp</option>
                    </select>
                    <?php if ($selectedSizeCategoryId > 0): ?>
                        <input type="hidden" name="size_category" value="<?php echo $selectedSizeCategoryId; ?>">
                    <?php endif; ?>
                </div>

                <div class="product-list product-catalog">

        <?php if (empty($products)): ?>
            <p>Không có sản phẩm nào!</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <section class="product-catalog-item">
                    <div class="pro product-catalog-card">
                        <a href="product_details.php?id=<?php echo $product['product_id']; ?>">
                            <?php $mainImageUrl = productImageUrl($product['image']); ?>
                            <?php if ($mainImageUrl !== ''): ?>
                                <img class="product-card-image" src="<?php echo htmlspecialchars($mainImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Hình ảnh sản phẩm">
                            <?php else: ?>
                                <img class="product-card-image" src="uploads/default.jpg" alt="Hình ảnh sản phẩm">
                            <?php endif; ?>
                            <p class="product-catalog-description">
                                <?php echo htmlspecialchars(trim(
                                    (string) $product['character_category_name'] . ' ' .
                                    (string) $product['size_category_name'] . ' ' .
                                    (string) $product['size_value']
                                ) ?: 'Chưa phân loại', ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <span class="product-catalog-name"><?php echo htmlspecialchars($product['name']); ?></span>
                            <p class="product-catalog-price"><?php echo number_format($product['price'], 0, ',', '.'); ?> đ</p>
                        </a>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
                </div>
            </main>
        </div>
    </form>
    
    <!-- ===================================================================================== -->
    <?php include __DIR__ . '/site_footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="scripts.js"></script>
    <script>
        document.querySelectorAll('.product-category-option input').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                if (window.matchMedia('(max-width: 860px)').matches) {
                    return; // Trên mobile chờ bấm "Áp dụng"
                }
                checkbox.form.submit();
            });
        });

        var productSortSelect = document.getElementById('product-sort');
        if (productSortSelect) {
            productSortSelect.addEventListener('change', function () {
                this.form.submit();
            });
        }

        (function () {
            var form = document.querySelector('.product-catalog-filter-form');
            if (!form) {
                return;
            }

            form.classList.add('is-filter-enabled');

            var panel = form.querySelector('.product-catalog-filters');
            var toggle = form.querySelector('.product-filter-toggle');
            var backdrop = form.querySelector('.product-filter-backdrop');
            var closeButtons = form.querySelectorAll('.product-filter-close');

            if (!panel || !toggle) {
                return;
            }

            function setOpen(open) {
                form.classList.toggle('is-filter-open', open);
                document.body.classList.toggle('product-filters-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (backdrop) {
                    backdrop.hidden = !open;
                }
            }

            toggle.addEventListener('click', function () {
                setOpen(!form.classList.contains('is-filter-open'));
            });

            closeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    setOpen(false);
                });
            });

            if (backdrop) {
                backdrop.addEventListener('click', function () {
                    setOpen(false);
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && form.classList.contains('is-filter-open')) {
                    setOpen(false);
                }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 860) {
                    setOpen(false);
                }
            });
        })();
    </script>
</body>

</html>