<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_catalog_helpers.php';
ensureProductCatalogSchema($conn);
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $sql = "SELECT p.*, character_category.name AS character_category_name, size_category.name AS size_category_name
        FROM products p
        LEFT JOIN product_character_categories AS character_category
            ON character_category.category_id = p.character_category_id
        LEFT JOIN product_size_categories AS size_category
            ON size_category.category_id = p.size_category_id
        WHERE p.product_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        echo "Sản phẩm không tồn tại!";
        exit();
    }
    $stmt->close();
} else {
    echo "Không có id sản phẩm!";
    exit();
}
$productImages = [];
$primaryImageUrl = productImageUrl($product['image']);
if ($primaryImageUrl !== '') {
    $productImages[] = [
        'url' => $primaryImageUrl,
        'alt' => $product['name']
    ];
}

$galleryStmt = $conn->prepare(
    "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, image_id"
);
$galleryStmt->bind_param("i", $product_id);
$galleryStmt->execute();
$galleryResult = $galleryStmt->get_result();
while ($galleryImage = $galleryResult->fetch_assoc()) {
    $galleryImageUrl = productImageUrl($galleryImage['image_path']);
    if ($galleryImageUrl !== '' && !in_array($galleryImageUrl, array_column($productImages, 'url'), true)) {
        $productImages[] = [
            'url' => $galleryImageUrl,
            'alt' => $product['name']
        ];
    }
}
$galleryStmt->close();
$productImageUrl = isset($productImages[0]) ? $productImages[0]['url'] : '';
$recommendedProducts = [];
$recommendationStmt = $conn->prepare(
    "SELECT product_id, name, price, image
    FROM products
    WHERE product_id <> ?
    ORDER BY RAND()
    LIMIT 4"
);
$recommendationStmt->bind_param("i", $product_id);
$recommendationStmt->execute();
$recommendationResult = $recommendationStmt->get_result();
while ($recommendedProduct = $recommendationResult->fetch_assoc()) {
    $recommendedProducts[] = $recommendedProduct;
}
$recommendationStmt->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> | POP_POP</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="product-detail-page">
    <?php include __DIR__ . '/site_header.php'; ?>
    <div class="header-mid-bar">
        <div class="header-marquee position-relative font-weight-bold text-light bg-dark">
            <div class="marquee d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
            <div class="marquee marquee2 d-flex justify-content-around position-absolute">
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
                <span>GIẢM GIÁ TỚI 100% MỌI SẢN PHẨM TẠI WEBSITE VÀ NHIỀU TRÒ HAY ĐANG CHỜ PHÍA SAU</span>
            </div>
        </div>
    </div>
    <main class="product-detail-shell">
        <nav class="product-breadcrumb product-catalog-breadcrumb" aria-label="Điều hướng">
            <a href="./index.php">Trang chủ</a>
            <span>/</span>
            <a href="./products.php">Sản phẩm</a>
            <span>/</span>
            <span class="current"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>

        <section class="product-detail-layout">
            <div class="product-gallery">
                <div class="product-thumbnail-gallery">
                    <div class="product-thumbnails-viewport" aria-label="Ảnh sản phẩm">
                        <div class="product-thumbnails">
                            <?php foreach ($productImages as $imageIndex => $productImage): ?>
                                <button class="product-thumbnail<?php echo $imageIndex === 0 ? ' is-active' : ''; ?>"
                                    type="button"
                                    data-image-src="<?php echo htmlspecialchars($productImage['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="Xem ảnh <?php echo $imageIndex + 1; ?>"
                                    aria-pressed="<?php echo $imageIndex === 0 ? 'true' : 'false'; ?>">
                                    <img src="<?php echo htmlspecialchars($productImage['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars($productImage['alt'], ENT_QUOTES, 'UTF-8'); ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($productImages) > 5): ?>
                        <div class="product-thumbnail-controls" aria-label="Điều khiển ảnh thu nhỏ">
                            <button class="product-thumbnail-scroll" type="button" data-direction="-1"
                                aria-label="Cuộn ảnh lên" disabled>&#8963;</button>
                            <button class="product-thumbnail-scroll" type="button" data-direction="1"
                                aria-label="Cuộn ảnh xuống">&#8964;</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="product-main-image">
                    <?php if ($productImageUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($productImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <span>Chưa có hình ảnh sản phẩm</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="product-detail-info">
                <div class="product-shipping-label">✦ GIAO HÀNG TOÀN QUỐC</div>
                <div class="product-actions" aria-label="Thao tác sản phẩm">
                    <button type="button" aria-label="Chia sẻ sản phẩm" title="Chia sẻ">↗</button>
                    <button type="button" aria-label="Yêu thích sản phẩm" title="Yêu thích">♡</button>
                </div>
                <h1><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="product-category-details">
                    <?php
                    $categoryParts = [
                        $product['character_category_name'],
                        trim((string) $product['size_category_name'] . ' ' . (string) $product['size_value'])
                    ];
                    $categoryParts = array_filter($categoryParts, function ($categoryPart) {
                        return trim((string) $categoryPart) !== '';
                    });
                    $productCategoryDetails = implode(' · ', $categoryParts);
                    echo htmlspecialchars($productCategoryDetails, ENT_QUOTES, 'UTF-8');
                    ?>
                </p>
                <p class="product-detail-price"><?php echo number_format((float) $product['price'], 0, ',', '.'); ?><small>đ</small></p>
                <p class="product-stock">
                    <?php echo (int) $product['quantity'] > 0 ? 'Còn hàng' : 'Tạm hết hàng'; ?>
                </p>

                <form class="product-buy-form" method="POST" action="products.php">
                    <input type="hidden" name="product_id" value="<?php echo (int) $product['product_id']; ?>">
                    <label for="quantity">Số lượng</label>
                    <div class="quantity-control">
                        <button type="button" class="quantity-step" data-step="-1" aria-label="Giảm số lượng"
                            <?php echo (int) $product['quantity'] > 0 ? '' : 'disabled'; ?>>−</button>
                        <input
                            type="number"
                            name="quantity"
                            id="quantity"
                            min="1"
                            max="<?php echo max(1, (int) $product['quantity']); ?>"
                            value="<?php echo (int) $product['quantity'] > 0 ? '1' : '0'; ?>"
                            <?php echo (int) $product['quantity'] > 0 ? 'required' : 'disabled'; ?>
                        >
                        <button type="button" class="quantity-step" data-step="1" aria-label="Tăng số lượng"
                            <?php echo (int) $product['quantity'] > 0 ? '' : 'disabled'; ?>>+</button>
                    </div>
                    <button class="product-add-button" type="submit" <?php echo (int) $product['quantity'] > 0 ? '' : 'disabled'; ?>>
                        <?php echo (int) $product['quantity'] > 0 ? 'Thêm vào giỏ hàng' : 'Thông báo khi có hàng'; ?>
                    </button>
                </form>

                <div class="product-accordions">
                    <details open>
                        <summary>Chi tiết sản phẩm</summary>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </details>
                    <details>
                        <summary>Dịch vụ giao hàng &amp; sau bán hàng</summary>
                        <p>Liên hệ với cửa hàng để được tư vấn về giao hàng và hỗ trợ sau khi mua.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="featured-products">
            <h2>Sản phẩm bạn có thể thích</h2>
            <?php if (empty($recommendedProducts)): ?>
                <p>Hiện chưa có sản phẩm khác để gợi ý.</p>
            <?php else: ?>
                <div class="product-list product-catalog recommendation-grid">
                    <?php foreach ($recommendedProducts as $recommendedProduct): ?>
                        <?php
                        $recommendedImageName = basename(str_replace('\\', '/', trim((string) $recommendedProduct['image'])));
                        $recommendedImageUrl = $recommendedImageName !== ''
                            ? 'uploads/' . rawurlencode($recommendedImageName)
                            : '';
                        ?>
                        <section class="product-catalog-item">
                            <div class="pro product-catalog-card">
                                <a href="product_details.php?id=<?php echo (int) $recommendedProduct['product_id']; ?>">
                                    <?php if ($recommendedImageUrl !== ''): ?>
                                        <img class="product-card-image" src="<?php echo htmlspecialchars($recommendedImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?php echo htmlspecialchars($recommendedProduct['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php else: ?>
                                        <img class="product-card-image" src="uploads/default.jpg" alt="Chưa có ảnh sản phẩm">
                                    <?php endif; ?>
                                    <span class="product-catalog-name"><?php echo htmlspecialchars($recommendedProduct['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <p class="product-catalog-price"><?php echo number_format((float) $recommendedProduct['price'], 0, ',', '.'); ?> đ</p>
                                </a>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . '/site_footer.php'; ?>
    <script>
        document.querySelectorAll('.quantity-step').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById('quantity');
                var nextValue = Number(input.value) + Number(button.dataset.step);
                input.value = Math.min(Number(input.max), Math.max(Number(input.min), nextValue));
            });
        });

        var mainImageFrame = document.querySelector('.product-main-image');
        var mainProductImage = mainImageFrame
            ? mainImageFrame.querySelector('img')
            : null;
        var thumbnailViewport = document.querySelector('.product-thumbnails-viewport');
        var thumbnailScrollButtons = document.querySelectorAll('.product-thumbnail-scroll');

        function updateThumbnailScrollButtons() {
            if (!thumbnailViewport || thumbnailScrollButtons.length === 0) {
                return;
            }

            var maxScrollTop = thumbnailViewport.scrollHeight - thumbnailViewport.clientHeight;
            thumbnailScrollButtons.forEach(function (button) {
                var direction = Number(button.dataset.direction);
                button.disabled = direction < 0
                    ? thumbnailViewport.scrollTop <= 0
                    : thumbnailViewport.scrollTop >= maxScrollTop - 1;
            });
        }

        thumbnailScrollButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (!thumbnailViewport) {
                    return;
                }

                var firstThumbnail = thumbnailViewport.querySelector('.product-thumbnail');
                if (!firstThumbnail) {
                    return;
                }

                var thumbnailStyles = window.getComputedStyle(firstThumbnail.parentElement);
                var gap = parseFloat(thumbnailStyles.rowGap) || 0;
                var scrollDistance = firstThumbnail.getBoundingClientRect().height + gap;

                thumbnailViewport.scrollBy({
                    top: Number(button.dataset.direction) * scrollDistance,
                    behavior: 'smooth'
                });
            });
        });

        if (thumbnailViewport) {
            thumbnailViewport.addEventListener('scroll', updateThumbnailScrollButtons);
            updateThumbnailScrollButtons();
        }

        document.querySelectorAll('.product-thumbnail').forEach(function (thumbnail) {
            thumbnail.addEventListener('click', function () {
                if (!mainProductImage) {
                    return;
                }
                mainProductImage.src = thumbnail.dataset.imageSrc;
                mainProductImage.alt = thumbnail.querySelector('img').alt;
                document.querySelectorAll('.product-thumbnail').forEach(function (item) {
                    item.classList.remove('is-active');
                    item.setAttribute('aria-pressed', 'false');
                });
                thumbnail.classList.add('is-active');
                thumbnail.setAttribute('aria-pressed', 'true');
                mainImageFrame.classList.remove('is-zoomed');
                mainProductImage.style.transformOrigin = 'center center';
            });
        });

        var supportsHoverZoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

        if (mainImageFrame && mainProductImage && supportsHoverZoom) {
            function positionImageZoom(event) {
                var imageBounds = mainProductImage.getBoundingClientRect();
                var x = Math.max(0, Math.min(imageBounds.width, event.clientX - imageBounds.left));
                var y = Math.max(0, Math.min(imageBounds.height, event.clientY - imageBounds.top));

                mainProductImage.style.transformOrigin =
                    (x / imageBounds.width * 100) + '% ' +
                    (y / imageBounds.height * 100) + '%';
            }

            mainImageFrame.addEventListener('pointerenter', function (event) {
                positionImageZoom(event);
                mainImageFrame.classList.add('is-zoomed');
            });

            mainImageFrame.addEventListener('pointermove', positionImageZoom);

            mainImageFrame.addEventListener('pointerleave', function () {
                mainImageFrame.classList.remove('is-zoomed');
                mainProductImage.style.transformOrigin = 'center center';
            });
        }
    </script>

</body>

</html>