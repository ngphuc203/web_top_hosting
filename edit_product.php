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

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$productStmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$productStmt->bind_param("i", $productId);
$productStmt->execute();
$productResult = $productStmt->get_result();
$product = $productResult->fetch_assoc();
$productStmt->close();
if (!$product) {
    http_response_code(404);
    exit('Sản phẩm không tồn tại.');
}

$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gallery_image_id'])) {
    requireAdminPagePermission($conn, 'products.update');
    verifyAdminCsrfToken();
    $galleryImageId = (int) $_POST['delete_gallery_image_id'];
    $imageStmt = $conn->prepare(
        "SELECT image_path FROM product_images WHERE image_id = ? AND product_id = ?"
    );
    $imageStmt->bind_param("ii", $galleryImageId, $productId);
    $imageStmt->execute();
    $galleryImage = $imageStmt->get_result()->fetch_assoc();
    $imageStmt->close();

    if ($galleryImage) {
        $deleteImageStmt = $conn->prepare(
            "DELETE FROM product_images WHERE image_id = ? AND product_id = ?"
        );
        $deleteImageStmt->bind_param("ii", $galleryImageId, $productId);
        if (!$deleteImageStmt->execute()) {
            die('Lỗi xóa ảnh chi tiết: ' . $deleteImageStmt->error);
        }
        $deleteImageStmt->close();
        deleteProductImageFiles([basename(str_replace('\\', '/', $galleryImage['image_path']))]);
        header('Location: edit_product.php?id=' . $productId . '&gallery_image_deleted=1');
        exit();
    }
    $formError = 'Không tìm thấy ảnh chi tiết cần xóa.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset(
    $_POST['product_name'],
    $_POST['product_desc'],
    $_POST['product_price'],
    $_POST['product_qty']
)) {
    requireAdminPagePermission($conn, 'products.update');
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
    $newMainImage = saveProductImageUpload(
        isset($_FILES['product_image']) ? $_FILES['product_image'] : [],
        $uploadError
    );

    if ($productName === '' || $productDescription === '' || $productPrice < 0 || $productQuantity < 0) {
        $formError = 'Vui lòng kiểm tra tên, mô tả, giá và số lượng sản phẩm.';
        if ($newMainImage !== false && $newMainImage !== null) {
            deleteProductImageFiles([$newMainImage]);
        }
    } elseif ($newMainImage === false) {
        $formError = $uploadError;
    } else {
        $galleryImages = saveProductImageUploads(
            isset($_FILES['product_gallery']) ? $_FILES['product_gallery'] : [],
            $uploadError
        );

        if ($galleryImages === false) {
            $formError = $uploadError;
            if ($newMainImage !== null) {
                deleteProductImageFiles([$newMainImage]);
            }
        } else {
            $imageName = $newMainImage !== null ? $newMainImage : $product['image'];
            $updateStmt = $conn->prepare(
                "UPDATE products
                 SET name = ?, description = ?, price = ?, quantity = ?, image = ?,
                     character_category_id = ?, size_category_id = ?, size_value = ?
                 WHERE product_id = ?"
            );
            $updateStmt->bind_param(
                "ssdisiisi",
                $productName,
                $productDescription,
                $productPrice,
                $productQuantity,
                $imageName,
                $characterCategoryId,
                $sizeCategoryId,
                $sizeValue,
                $productId
            );

            if ($updateStmt->execute()) {
                $updateStmt->close();
                $imageStmt = $conn->prepare(
                    "INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)"
                );
                $galleryCountStmt = $conn->prepare(
                    "SELECT COUNT(*) AS image_count FROM product_images WHERE product_id = ?"
                );
                $galleryCountStmt->bind_param("i", $productId);
                $galleryCountStmt->execute();
                $galleryCountResult = $galleryCountStmt->get_result();
                $galleryOrder = (int) $galleryCountResult->fetch_assoc()['image_count'];
                $galleryCountStmt->close();
                foreach ($galleryImages as $galleryImage) {
                    $galleryOrder++;
                    $imageStmt->bind_param("isi", $productId, $galleryImage, $galleryOrder);
                    if (!$imageStmt->execute()) {
                        $formError = 'Thông tin đã cập nhật nhưng không thể lưu đầy đủ ảnh chi tiết: ' . $imageStmt->error;
                        break;
                    }
                }
                $imageStmt->close();
                if ($formError === '') {
                    header('Location: manage_products.php?product_updated=1');
                    exit();
                }
            } else {
                $formError = 'Không thể cập nhật sản phẩm: ' . $updateStmt->error;
                $updateStmt->close();
                if ($newMainImage !== null) {
                    deleteProductImageFiles([$newMainImage]);
                }
                deleteProductImageFiles($galleryImages);
            }
        }
    }

    if ($formError !== '') {
        $product = array_merge($product, [
            'name' => $productName,
            'description' => $productDescription,
            'price' => $productPrice,
            'quantity' => $productQuantity,
            'character_category_id' => $characterCategoryId,
            'size_category_id' => $sizeCategoryId,
            'size_value' => $sizeValue
        ]);
    }
}

$characterCategories = [];
$categoryResult = $conn->query("SELECT category_id, name FROM product_character_categories ORDER BY name");
while ($category = $categoryResult->fetch_assoc()) {
    $characterCategories[] = $category;
}
$sizeCategories = [];
$categoryResult = $conn->query("SELECT category_id, name FROM product_size_categories ORDER BY name");
while ($category = $categoryResult->fetch_assoc()) {
    $sizeCategories[] = $category;
}
$galleryImages = [];
$galleryStmt = $conn->prepare(
    "SELECT image_id, image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, image_id"
);
$galleryStmt->bind_param("i", $productId);
$galleryStmt->execute();
$galleryResult = $galleryStmt->get_result();
while ($galleryImage = $galleryResult->fetch_assoc()) {
    $galleryImages[] = $galleryImage;
}
$galleryStmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Sản Phẩm</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'products'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">KHO HÀNG</span>
                <h1>Sửa sản phẩm: <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Cập nhật thông tin chi tiết, giá, số lượng và hình ảnh sản phẩm.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-boxes-stacked" aria-hidden="true"></i></span>
        </div>

        <?php if ($formError !== ''): ?>
            <p class="admin-alert admin-alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (isset($_GET['gallery_image_deleted'])): ?>
            <p class="admin-alert admin-alert-success">Đã xóa ảnh chi tiết thành công.</p>
        <?php endif; ?>

        <?php if (adminHasPermission('products.update')): ?>
        <section class="admin-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">CHỈNH SỬA</span><h2>Thông tin sản phẩm</h2></div>
            </div>
            <form method="POST" enctype="multipart/form-data" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                
                <label for="product_name">Tên sản phẩm
                    <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>

                <label for="character_category_id">Danh mục nhân vật
                    <select id="character_category_id" name="character_category_id">
                        <option value="">Chọn nhân vật</option>
                        <?php foreach ($characterCategories as $category): ?>
                            <option value="<?php echo (int) $category['category_id']; ?>"
                                <?php echo (int) $product['character_category_id'] === (int) $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="size_category_id">Danh mục kích cỡ
                    <select id="size_category_id" name="size_category_id">
                        <option value="">Chọn nhóm kích cỡ</option>
                        <?php foreach ($sizeCategories as $category): ?>
                            <option value="<?php echo (int) $category['category_id']; ?>"
                                <?php echo (int) $product['size_category_id'] === (int) $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="size_value">Kích cỡ sản phẩm
                    <input type="text" id="size_value" name="size_value" maxlength="100"
                        value="<?php echo htmlspecialchars((string) $product['size_value'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>

                <label class="admin-form-wide" for="product_desc">Mô tả chi tiết
                    <textarea id="product_desc" name="product_desc" required><?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </label>

                <label for="product_price">Giá (VNĐ)
                    <input type="number" id="product_price" name="product_price" min="0" step="1" value="<?php echo htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>

                <label for="product_qty">Số lượng trong kho
                    <input type="number" id="product_qty" name="product_qty" min="0" value="<?php echo (int) $product['quantity']; ?>" required>
                </label>

                <label for="product_image">Thay đổi ảnh chính
                    <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php $mainImageUrl = productImageUrl($product['image']); ?>
                    <?php if ($mainImageUrl !== ''): ?>
                        <div style="margin-top: 8px;">
                            <img src="<?php echo htmlspecialchars($mainImageUrl, ENT_QUOTES, 'UTF-8'); ?>" style="width: 80px; height: 80px; object-fit: contain; border: 1px solid #edf0f5; border-radius: 8px;" alt="Ảnh chính hiện tại">
                        </div>
                    <?php endif; ?>
                </label>

                <label for="product_gallery">Thêm ảnh chi tiết mới
                    <input type="file" id="product_gallery" name="product_gallery[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                    <small>Có thể chọn nhiều tệp ảnh cùng lúc.</small>
                </label>

                <div class="admin-form-wide" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                    <button class="admin-button admin-button-primary admin-form-submit" type="submit" style="margin: 0; flex: 1; min-width: 180px;">
                        <i class="fas fa-check" aria-hidden="true"></i> Cập Nhật Sản Phẩm
                    </button>
                    <?php if (adminHasPermission('products.delete')): ?>
                    <button class="admin-button admin-button-danger" type="submit" form="delete-product-form" style="min-height: 42px; padding: 10px 16px;">
                        <i class="fas fa-trash" aria-hidden="true"></i> Xóa sản phẩm này
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if (adminHasPermission('products.delete')): ?>
            <form id="delete-product-form" method="POST" action="manage_products.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này không?');" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="delete_product_id" value="<?php echo (int) $productId; ?>">
            </form>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="admin-section-heading">
                <div><span class="admin-eyebrow">HÌNH ẢNH</span><h2>Ảnh chi tiết đang có</h2></div>
                <span class="admin-record-count"><?php echo count($galleryImages); ?> ảnh</span>
            </div>
            <div class="product-image-admin-gallery">
                <?php if (empty($galleryImages)): ?>
                    <p style="color: #8590a2; font-size: 13px;">Chưa có ảnh chi tiết bổ sung nào cho sản phẩm này.</p>
                <?php else: ?>
                    <?php foreach ($galleryImages as $galleryImage): ?>
                        <?php $galleryImageUrl = productImageUrl($galleryImage['image_path']); ?>
                        <div style="border: 1px solid #edf0f5; border-radius: 10px; padding: 8px; text-align: center; background: #fafbfc;">
                            <img src="<?php echo htmlspecialchars($galleryImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                style="width: 100px; height: 100px; object-fit: contain; border-radius: 6px; display: block; margin: 0 auto 8px;"
                                alt="Ảnh chi tiết sản phẩm">
                            <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa ảnh này?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="delete_gallery_image_id" value="<?php echo (int) $galleryImage['image_id']; ?>">
                                <button class="admin-button admin-button-danger" type="submit" style="padding: 4px 8px; font-size: 11px;">
                                    <i class="fas fa-trash" aria-hidden="true"></i> Xóa
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
            <p>Bạn chỉ có quyền xem sản phẩm, không thể chỉnh sửa thông tin hoặc hình ảnh.</p>
        <?php endif; ?>

        <p class="admin-form-wide" style="margin-top: 20px;">
            <a href="./manage_products.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Quay lại danh sách sản phẩm</a>
        </p>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
</body>
</html>
