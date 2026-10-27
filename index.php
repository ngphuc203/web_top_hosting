<?php
session_start();

require_once __DIR__ . '/config.php';

$featuredProductsResult = $conn->query(
    "SELECT product_id, name, price, image
    FROM products
    WHERE image IS NOT NULL AND TRIM(image) <> ''
    ORDER BY RAND()"
);

if (!$featuredProductsResult) {
    die("Lỗi lấy danh sách sản phẩm: " . $conn->error);
}

$featuredProducts = [];
while ($product = $featuredProductsResult->fetch_assoc()) {
    $imageName = basename(str_replace('\\', '/', trim($product['image'])));
    $imageFile = __DIR__ . '/uploads/' . $imageName;

    if ($imageName === '' || !is_file($imageFile)) {
        continue;
    }

    $product['image_url'] = 'uploads/' . rawurlencode($imageName);
    $featuredProducts[] = $product;

    if (count($featuredProducts) === 4) {
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Chủ</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="home-page">
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
    <nav class="product-breadcrumb product-catalog-breadcrumb" aria-label="Điều hướng">
        <span class="current">Trang chủ</span>
    </nav>


    <div class="slider-container">
    <div class="slider-images">
        <!-- Add your images here -->
        <img src="img/banner4.jpg" alt=" #">
        <img src="img/banner3.jpg" alt="#">
        <img src="img/banner2.jpg" alt="#">
        <img src="img/banner1.jpg" alt="#">  


    </div>
    <button class="slider-button prev" onclick="moveSlide(-1)">&#10094;</button>
    <button class="slider-button next" onclick="moveSlide(1)">&#10095;</button>
</div>

    <section class="featured-products">
        <h2>Sản phẩm nổi bật</h2>
        <?php if (empty($featuredProducts)): ?>
            <p>Hiện chưa có sản phẩm kèm hình ảnh.</p>
        <?php else: ?>
            <div class="product-list product-catalog recommendation-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <section class="product-catalog-item">
                        <div class="pro product-catalog-card">
                            <a href="product_details.php?id=<?php echo (int) $product['product_id']; ?>">
                                <img
                                    class="product-card-image"
                                    src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <span class="product-catalog-name"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <p class="product-catalog-price"><?php echo number_format((float) $product['price'], 0, ',', '.'); ?> đ</p>
                            </a>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<script>
    let currentSlide = 0;

    const slides = document.querySelectorAll('.slider-images img');
    const totalSlides = slides.length;

    function moveSlide(step) {
        currentSlide += step;

        if (currentSlide < 0) {
            currentSlide = totalSlides - 1;
        } else if (currentSlide >= totalSlides) {
            currentSlide = 0;
        }

        updateSlidePosition();
    }

    function updateSlidePosition() {
        const slider = document.querySelector('.slider-images');
        slider.style.transform = `translateX(-${currentSlide * 100}%)`;
    }

    // Auto slide functionality
    setInterval(() => {
        moveSlide(1);
    }, 3000); // Changes slide every 3 seconds
</script>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>