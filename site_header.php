<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_catalog_helpers.php';
ensureProductCatalogSchema($conn);

$headerCharacterCategories = [];
$headerCategoryResult = $conn->query(
    "SELECT category_id, name FROM product_character_categories ORDER BY name"
);
if (!$headerCategoryResult) {
    die('Lỗi lấy danh mục nhân vật: ' . $conn->error);
}
while ($headerCategory = $headerCategoryResult->fetch_assoc()) {
    $headerCharacterCategories[] = $headerCategory;
}

$headerSizeCategories = [];
$headerSizeResult = $conn->query(
    "SELECT category_id, name FROM product_size_categories ORDER BY name"
);
if (!$headerSizeResult) {
    die('Lỗi lấy danh mục kích cỡ: ' . $conn->error);
}
while ($headerCategory = $headerSizeResult->fetch_assoc()) {
    $headerSizeCategories[] = $headerCategory;
}

$headerCategoryProductStmt = $conn->prepare(
    "SELECT product_id, name, image FROM products
    WHERE character_category_id = ? AND image IS NOT NULL AND TRIM(image) <> ''
    ORDER BY RAND() LIMIT 1"
);
if (!$headerCategoryProductStmt) {
    die('Lỗi chuẩn bị ảnh danh mục: ' . $conn->error);
}
$headerSizeProductStmt = $conn->prepare(
    "SELECT product_id, name, image FROM products
    WHERE size_category_id = ? AND image IS NOT NULL AND TRIM(image) <> ''
    ORDER BY RAND() LIMIT 1"
);
if (!$headerSizeProductStmt) {
    die('Lỗi chuẩn bị ảnh kích cỡ: ' . $conn->error);
}
$headerFallbackProductStmt = $conn->prepare(
    "SELECT product_id, name, image FROM products
    WHERE image IS NOT NULL AND TRIM(image) <> ''
    ORDER BY RAND() LIMIT 1"
);
if (!$headerFallbackProductStmt) {
    die('Lỗi chuẩn bị ảnh sản phẩm ngẫu nhiên: ' . $conn->error);
}
$headerCharacterProducts = [];
foreach ($headerCharacterCategories as $category) {
    $categoryId = (int) $category['category_id'];
    $headerCategoryProductStmt->bind_param('i', $categoryId);
    if (!$headerCategoryProductStmt->execute()) {
        die('Lỗi lấy sản phẩm danh mục: ' . $headerCategoryProductStmt->error);
    }
    $headerCharacterProducts[$categoryId] = $headerCategoryProductStmt->get_result()->fetch_assoc();
    if (!$headerCharacterProducts[$categoryId]) {
        if (!$headerFallbackProductStmt->execute()) {
            die('Lỗi lấy ảnh sản phẩm ngẫu nhiên: ' . $headerFallbackProductStmt->error);
        }
        $headerCharacterProducts[$categoryId] = $headerFallbackProductStmt->get_result()->fetch_assoc();
    }
}
$headerSizeProducts = [];
foreach ($headerSizeCategories as $category) {
    $categoryId = (int) $category['category_id'];
    $headerSizeProductStmt->bind_param('i', $categoryId);
    if (!$headerSizeProductStmt->execute()) {
        die('Lỗi lấy sản phẩm kích cỡ: ' . $headerSizeProductStmt->error);
    }
    $headerSizeProducts[$categoryId] = $headerSizeProductStmt->get_result()->fetch_assoc();
    if (!$headerSizeProducts[$categoryId]) {
        if (!$headerFallbackProductStmt->execute()) {
            die('Lỗi lấy ảnh sản phẩm ngẫu nhiên: ' . $headerFallbackProductStmt->error);
        }
        $headerSizeProducts[$categoryId] = $headerFallbackProductStmt->get_result()->fetch_assoc();
    }
}
$headerCategoryProductStmt->close();
$headerSizeProductStmt->close();
$headerFallbackProductStmt->close();
$headerCartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

$headerCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$headerNavAttributes = static function (bool $isCurrent): string {
    return $isCurrent ? ' class="is-current" aria-current="page"' : '';
};
$headerProductsCurrent = in_array($headerCurrentPage, ['products.php', 'product_details.php'], true);
$headerAccountCurrent = in_array($headerCurrentPage, ['login.php', 'signup.php', 'my_account.php'], true);

?>
<section id="header" class="store-header">
    <div class="store-header-inner">
        <button type="button" class="store-mobile-menu-toggle" aria-expanded="false" aria-controls="storeNavigation" aria-label="Mở menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <a class="store-logo-link" href="./index.php">
            <img src="./img/logo.png" class="logo" alt="POP_POP">
            <span class="store-brand-name" aria-label="Ủa Store"><span aria-hidden="true">U</span><span aria-hidden="true">a</span><span class="store-brand-space" aria-hidden="true">&nbsp;</span><span aria-hidden="true">S</span><span aria-hidden="true">t</span><span aria-hidden="true">o</span><span aria-hidden="true">r</span><span aria-hidden="true">e</span></span>
        </a>
        <div class="store-mobile-header-actions">
            <button type="button" class="store-nav-search-toggle store-mobile-search-toggle" aria-expanded="false" aria-controls="storeSearchPanel" aria-label="Tìm kiếm">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <a class="store-mobile-cart" href="./cart.php" aria-label="Giỏ hàng">
                <svg class="store-cart-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 4h2l2.1 10.1a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 8H6"></path>
                    <circle cx="10" cy="20" r="1.25"></circle>
                    <circle cx="18" cy="20" r="1.25"></circle>
                </svg>
                <span class="store-cart-count<?php echo $headerCartCount === 0 ? ' is-empty' : ''; ?>"><?php echo $headerCartCount; ?></span>
            </a>
            <a class="store-mobile-account" href="<?php echo isset($_SESSION['username']) ? './my_account.php' : './login.php'; ?>" aria-label="Tài khoản">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.4"></circle>
                    <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
                </svg>
            </a>
        </div>
        <button type="button" class="store-menu-backdrop" aria-label="Đóng menu" hidden></button>
        <button type="button" class="store-search-backdrop" aria-label="Đóng tìm kiếm" hidden></button>
        <nav id="storeNavigation" class="store-navigation" aria-label="Điều hướng chính">
            <div class="store-mobile-menu-heading">
                <span>MENU</span>
                <button type="button" class="store-mobile-menu-close" aria-label="Đóng menu">&times;</button>
            </div>
            <ul class="store-navigation-list">
                <li class="store-navigation-item">
                    <a href="./index.php"<?php echo $headerNavAttributes($headerCurrentPage === 'index.php'); ?>><i class="fas fa-house" aria-hidden="true"></i> TRANG CHỦ</a>
                </li>
                <li class="store-navigation-item"><a href="./products.php"<?php echo $headerNavAttributes($headerProductsCurrent); ?>><i class="fas fa-box-open" aria-hidden="true"></i> SẢN PHẨM</a></li>
                <li class="store-navigation-item store-nav-disclosure-item store-nav-character-item">
                    <a href="./products.php"><i class="fas fa-user-group" aria-hidden="true"></i> NHÂN VẬT</a>
                    <button type="button" class="store-nav-submenu-toggle" data-submenu-label="danh mục nhân vật" aria-expanded="false" aria-label="Mở danh mục nhân vật">
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="store-nav-dropdown store-nav-categories">
                        <?php if (empty($headerCharacterCategories)): ?>
                            <span class="store-nav-empty">Chưa có danh mục nhân vật</span>
                        <?php else: ?>
                            <?php foreach ($headerCharacterCategories as $category): ?>
                                <?php
                                $categoryId = (int) $category['category_id'];
                                $categoryProduct = $headerCharacterProducts[$categoryId];
                                ?>
                                <a class="store-nav-category" href="./products.php?category[]=<?php echo $categoryId; ?>">
                                    <?php if ($categoryProduct): ?>
                                        <img src="<?php echo htmlspecialchars(productImageUrl($categoryProduct['image']), ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="" loading="lazy">
                                    <?php else: ?>
                                        <span class="store-nav-no-image">POP</span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </li>
                <li class="store-navigation-item store-nav-disclosure-item store-nav-mega-item">
                    <a href="./products.php"><i class="fas fa-cubes" aria-hidden="true"></i> MEGA</a>
                    <button type="button" class="store-nav-submenu-toggle" data-submenu-label="danh mục MEGA" aria-expanded="false" aria-label="Mở danh mục MEGA">
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="store-nav-dropdown store-nav-categories">
                        <?php if (empty($headerSizeCategories)): ?>
                            <span class="store-nav-empty">Chưa có danh mục kích cỡ</span>
                        <?php else: ?>
                            <?php foreach ($headerSizeCategories as $category): ?>
                                <?php
                                $categoryId = (int) $category['category_id'];
                                $categoryProduct = $headerSizeProducts[$categoryId];
                                ?>
                                <a class="store-nav-category" href="./products.php?size_category=<?php echo $categoryId; ?>">
                                    <?php if ($categoryProduct): ?>
                                        <img src="<?php echo htmlspecialchars(productImageUrl($categoryProduct['image']), ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="" loading="lazy">
                                    <?php else: ?>
                                        <span class="store-nav-no-image">MEGA</span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </li>
                <li class="store-navigation-item store-nav-search-item">
                    <button type="button" class="store-nav-search-toggle" aria-expanded="false" aria-controls="storeSearchPanel">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i> TÌM KIẾM
                    </button>
                    <div id="storeSearchPanel" class="store-nav-dropdown store-search-panel">
                        <div class="store-search-panel-heading">
                            <span>TÌM KIẾM</span>
                            <button type="button" class="store-search-close" aria-label="Đóng tìm kiếm">&times;</button>
                        </div>
                        <form action="./search.php" method="get" class="store-search-form" role="search">
                            <label class="visually-hidden" for="storeSearchInput">Tìm kiếm sản phẩm</label>
                            <input id="storeSearchInput" type="search" name="search" placeholder="TÌM KIẾM SẢN PHẨM..."
                                autocomplete="off">
                            <button type="submit" aria-label="Tìm kiếm"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></button>
                        </form>
                        <div id="storeSearchContent" class="store-search-content">
                            <section>
                                <h3>Phổ biến</h3>
                                <ul id="storePopularProducts" class="store-search-products">
                                    <li class="store-search-message">Đang tải sản phẩm...</li>
                                </ul>
                            </section>
                            <section id="storeSearchHistorySection" hidden>
                                <div class="store-search-heading">
                                    <h3>Lịch sử tìm kiếm</h3>
                                    <button type="button" id="clearStoreSearchHistory">Xóa</button>
                                </div>
                                <ul id="storeSearchHistory" class="store-search-history"></ul>
                            </section>
                            <ul id="storeSearchResults" class="store-search-products" hidden></ul>
                        </div>
                    </div>
                </li>
                <li class="store-navigation-item"><a href="./contact.php"<?php echo $headerNavAttributes($headerCurrentPage === 'contact.php'); ?>><i class="fas fa-envelope" aria-hidden="true"></i> LIÊN HỆ</a></li>
                <?php if (isset($_SESSION['username'])): ?>
                    <li class="store-navigation-item store-mobile-logout-item">
                        <a href="./logout.php"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> ĐĂNG XUẤT</a>
                    </li>
                <?php endif; ?>
                <li class="store-navigation-item store-cart-nav-item">
                    <a href="./cart.php"<?php echo $headerNavAttributes($headerCurrentPage === 'cart.php'); ?>>
                        <svg class="store-cart-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 4h2l2.1 10.1a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 8H6"></path>
                            <circle cx="10" cy="20" r="1.25"></circle>
                            <circle cx="18" cy="20" r="1.25"></circle>
                        </svg>
                        GIỎ HÀNG
                        <span class="store-cart-count<?php echo $headerCartCount === 0 ? ' is-empty' : ''; ?>"><?php echo $headerCartCount; ?></span>
                    </a>
                </li>
                <li class="store-navigation-item store-account-nav-item">
                    <a class="store-account-button<?php echo $headerAccountCurrent ? ' is-current' : ''; ?>" href="<?php echo isset($_SESSION['username']) ? './my_account.php' : './login.php'; ?>" aria-label="Tài khoản"<?php echo $headerAccountCurrent ? ' aria-current="page"' : ''; ?>>
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <span class="store-action-label">TÀI KHOẢN</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="store-header-actions">
            <?php if (isset($_SESSION['username'])): ?>
                <a class="store-logout-button" href="./logout.php" aria-label="Đăng xuất" title="Đăng xuất">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                    <span class="store-action-label">ĐĂNG XUẤT</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="./site_header.js" defer></script>
