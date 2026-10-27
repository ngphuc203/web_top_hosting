<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
requireAdminPagePermission($conn, 'products.view');
require_once __DIR__ . '/product_catalog_helpers.php';
ensureProductCatalogSchema($conn);

$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset(
    $_POST['product_name'],
    $_POST['product_desc'],
    $_POST['product_price'],
    $_POST['product_qty']
)) {
    requireAdminPagePermission($conn, 'products.create');
    verifyAdminCsrfToken();
    $productName = trim($_POST['product_name']);
    $productDescription = trim($_POST['product_desc']);
    $productPrice = (float) $_POST['product_price'];
    $productQuantity = (int) $_POST['product_qty'];
    $characterCategoryId = isset($_POST['character_category_id']) && $_POST['character_category_id'] !== ''
        ? (int) $_POST['character_category_id']
        : null;
    $sizeCategoryId = isset($_POST['size_category_id']) && $_POST['size_category_id'] !== ''
        ? (int) $_POST['size_category_id']
        : null;
    $sizeValue = trim(isset($_POST['size_value']) ? $_POST['size_value'] : '');
    $uploadError = '';
    $mainImage = saveProductImageUpload(
        isset($_FILES['product_image']) ? $_FILES['product_image'] : [],
        $uploadError,
        true
    );

    if ($productName === '' || $productDescription === '' || $productPrice < 0 || $productQuantity < 0) {
        $formError = 'Vui lòng kiểm tra tên, mô tả, giá và số lượng sản phẩm.';
        if ($mainImage !== false && $mainImage !== null) {
            deleteProductImageFiles([$mainImage]);
        }
    } elseif ($mainImage === false) {
        $formError = $uploadError;
    } else {
        $galleryImages = saveProductImageUploads(
            isset($_FILES['product_gallery']) ? $_FILES['product_gallery'] : [],
            $uploadError
        );

        if ($galleryImages === false) {
            $formError = $uploadError;
            deleteProductImageFiles([$mainImage]);
        } else {
            $insertStmt = $conn->prepare(
                "INSERT INTO products
                    (name, description, price, quantity, image, character_category_id, size_category_id, size_value)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertStmt->bind_param(
                "ssdisiis",
                $productName,
                $productDescription,
                $productPrice,
                $productQuantity,
                $mainImage,
                $characterCategoryId,
                $sizeCategoryId,
                $sizeValue
            );

            if ($insertStmt->execute()) {
                $newProductId = $insertStmt->insert_id;
                $insertStmt->close();
                $imageStmt = $conn->prepare(
                    "INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)"
                );
                foreach ($galleryImages as $imageOrder => $galleryImage) {
                    $sortOrder = $imageOrder + 1;
                    $imageStmt->bind_param("isi", $newProductId, $galleryImage, $sortOrder);
                    if (!$imageStmt->execute()) {
                        $formError = 'Sản phẩm đã được tạo nhưng không thể lưu đầy đủ ảnh chi tiết: ' . $imageStmt->error;
                        break;
                    }
                }
                $imageStmt->close();
                if ($formError === '') {
                    header('Location: manage_products.php?product_added=1');
                    exit();
                }
            } else {
                $formError = 'Không thể thêm sản phẩm: ' . $insertStmt->error;
                $insertStmt->close();
                deleteProductImageFiles(array_merge([$mainImage], $galleryImages));
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    requireAdminPagePermission($conn, 'products.delete');
    verifyAdminCsrfToken();
    $deleteProductId = (int) $_POST['delete_product_id'];
    $imageStmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $imageStmt->bind_param("i", $deleteProductId);
    $imageStmt->execute();
    $imageResult = $imageStmt->get_result();
    $galleryImageNames = [];
    while ($galleryImage = $imageResult->fetch_assoc()) {
        $galleryImageNames[] = basename(str_replace('\\', '/', $galleryImage['image_path']));
    }
    $imageStmt->close();

    $deleteImagesStmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
    $deleteImagesStmt->bind_param("i", $deleteProductId);
    if (!$deleteImagesStmt->execute()) {
        die('Lỗi xóa ảnh sản phẩm: ' . $deleteImagesStmt->error);
    }
    $deleteImagesStmt->close();

    $deleteStmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $deleteStmt->bind_param("i", $deleteProductId);
    if ($deleteStmt->execute()) {
        deleteProductImageFiles($galleryImageNames);
        header('Location: manage_products.php');
        exit();
    }
    die('Lỗi xóa sản phẩm: ' . $deleteStmt->error);
}

$characterCategories = [];
$characterResult = $conn->query(
    "SELECT category_id, name FROM product_character_categories ORDER BY name"
);
if (!$characterResult) {
    die('Lỗi lấy danh mục nhân vật: ' . $conn->error);
}
while ($category = $characterResult->fetch_assoc()) {
    $characterCategories[] = $category;
}
$sizeCategories = [];
$sizeResult = $conn->query(
    "SELECT category_id, name FROM product_size_categories ORDER BY name"
);
if (!$sizeResult) {
    die('Lỗi lấy danh mục kích cỡ: ' . $conn->error);
}
while ($category = $sizeResult->fetch_assoc()) {
    $sizeCategories[] = $category;
}

$sql = "SELECT p.*, character_category.name AS character_category_name, size_category.name AS size_category_name
    FROM products p
    LEFT JOIN product_character_categories AS character_category
        ON character_category.category_id = p.character_category_id
    LEFT JOIN product_size_categories AS size_category
        ON size_category.category_id = p.size_category_id
    ORDER BY p.product_id DESC";
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'products'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">QUẢN LÝ KHO HÀNG</span>
                <h1>Sản phẩm</h1>
                <p>Thêm sản phẩm, cập nhật thông tin và quản lý hình ảnh trong cửa hàng.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-box-open" aria-hidden="true"></i></span>
        </div>

    <?php if ($formError !== ''): ?>
        <p class="admin-alert admin-alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif (isset($_GET['product_added'])): ?>
        <p class="admin-alert admin-alert-success">Đã thêm sản phẩm và ảnh thành công.</p>
    <?php elseif (isset($_GET['product_updated'])): ?>
        <p class="admin-alert admin-alert-success">Đã cập nhật sản phẩm thành công.</p>
    <?php endif; ?>

    <?php if (adminHasPermission('products.create')): ?>
    <section class="admin-panel">
    <div class="admin-section-heading">
        <div><span class="admin-eyebrow">KHO HÀNG</span><h2>Thêm sản phẩm</h2></div>
    </div>
    <form method="POST" enctype="multipart/form-data" class="admin-form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <label for="product_name">Tên sản phẩm<input type="text" id="product_name" name="product_name" required></label>

        <label for="character_category_id">Danh mục nhân vật
        <select id="character_category_id" name="character_category_id">
            <option value="">Chọn nhân vật</option>
            <?php foreach ($characterCategories as $category): ?>
                <option value="<?php echo (int) $category['category_id']; ?>">
                    <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select><br>
        </label>

        <label for="size_category_id">Danh mục kích cỡ
        <select id="size_category_id" name="size_category_id">
            <option value="">Chọn nhóm kích cỡ</option>
            <?php foreach ($sizeCategories as $category): ?>
                <option value="<?php echo (int) $category['category_id']; ?>">
                    <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select><br>
        </label>

        <label class="admin-form-wide" for="product_desc">Mô tả chi tiết
            <textarea id="product_desc" name="product_desc" required></textarea>
            <small>Phần mô tả sẽ hiển thị trên trang chi tiết sản phẩm.</small>
        </label>

        <label for="product_price">Giá<input type="number" id="product_price" name="product_price" min="0" step="1" required></label>

        <label for="product_qty">Số lượng<input type="number" id="product_qty" name="product_qty" min="0" required></label>

        <label for="product_image">Ảnh chính<input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp" required></label>

        <label for="product_gallery">Ảnh chi tiết<input type="file" id="product_gallery" name="product_gallery[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple></label>

        <button class="admin-button admin-button-primary admin-form-submit" type="submit"><i class="fas fa-plus" aria-hidden="true"></i> Thêm sản phẩm</button>
    </form>
    </section>
    <?php endif; ?>

    <section class="admin-panel">
    <div class="admin-section-heading">
        <div><span class="admin-eyebrow">DANH SÁCH HÀNG HÓA</span><h2>Sản phẩm trong cửa hàng</h2></div>
        <span class="admin-record-count"><?php echo count($products); ?> sản phẩm</span>
    </div>
    <div class="admin-table-wrap admin-product-table-wrap">
    <table class="admin-product-table">
        <thead>
            <tr>
                <th class="col-id">ID</th>
                <th class="col-name">Tên sản phẩm</th>
                <th class="col-character">Nhân vật</th>
                <th class="col-qty col-center">Số lượng</th>
                <th class="col-action col-right">Xóa</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr class="admin-clickable-row" onclick="window.location.href='edit_product.php?id=<?php echo (int) $product['product_id']; ?>'" title="Chạm để chỉnh sửa sản phẩm này">
                    <td class="col-id">#<?php echo (int) $product['product_id']; ?></td>
                    <td class="col-name">
                        <a href="edit_product.php?id=<?php echo (int) $product['product_id']; ?>" class="admin-row-title-link">
                            <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </a>
                    </td>
                    <td class="col-character"><?php echo htmlspecialchars($product['character_category_name'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="col-qty col-center">
                        <span class="admin-stock-badge <?php echo (int) $product['quantity'] === 0 ? 'stock-empty' : ((int) $product['quantity'] < 5 ? 'stock-low' : ''); ?>">
                            <?php echo (int) $product['quantity']; ?>
                        </span>
                    </td>
                    <td class="col-action col-right" onclick="event.stopPropagation();">
                        <?php if (adminHasPermission('products.delete')): ?>
                        <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn xóa sản phẩm này?');" style="margin: 0; display: inline-block;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="delete_product_id" value="<?php echo (int) $product['product_id']; ?>">
                            <button class="admin-button admin-button-danger admin-btn-icon" type="submit" title="Xóa sản phẩm">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
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
