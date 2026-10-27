<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/admin_permissions.php';
require_once __DIR__ . '/config.php';
requireAdminPagePermission($conn, 'categories.view');

require_once __DIR__ . '/product_catalog_helpers.php';
ensureProductCatalogSchema($conn);

$categoryTypes = [
    'character' => [
        'table' => 'product_character_categories',
        'product_column' => 'character_category_id',
        'label' => 'nhân vật',
        'heading' => 'Danh mục nhân vật'
    ],
    'size' => [
        'table' => 'product_size_categories',
        'product_column' => 'size_category_id',
        'label' => 'kích cỡ',
        'heading' => 'Danh mục kích cỡ'
    ]
];
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken();
    $categoryType = isset($_POST['category_type']) && is_string($_POST['category_type'])
        ? $_POST['category_type']
        : '';
    $categoryAction = isset($_POST['category_action']) && is_string($_POST['category_action'])
        ? $_POST['category_action']
        : 'add';
    $categoryName = isset($_POST['category_name']) && is_string($_POST['category_name'])
        ? trim($_POST['category_name'])
        : '';

    if (!isset($categoryTypes[$categoryType])) {
        $formError = 'Loại danh mục không hợp lệ.';
    } else {
        $requiredPermission = $categoryAction === 'add'
            ? 'categories.create'
            : ($categoryAction === 'update' ? 'categories.update' : 'categories.delete');
        requireAdminPagePermission($conn, $requiredPermission);
        $categoryConfig = $categoryTypes[$categoryType];
        $categoryTable = $categoryConfig['table'];

        if ($categoryAction === 'add') {
            if ($categoryName === '') {
                $formError = 'Vui lòng nhập tên danh mục.';
            } else {
                $categoryStmt = $conn->prepare("INSERT INTO " . $categoryTable . " (name) VALUES (?)");
                if (!$categoryStmt) {
                    die('Không thể chuẩn bị thêm danh mục: ' . $conn->error);
                }
                $categoryStmt->bind_param('s', $categoryName);
                if ($categoryStmt->execute()) {
                    header('Location: manage_categories.php?category_added=1');
                    exit();
                }
                $formError = $categoryStmt->errno === 1062
                    ? 'Tên danh mục này đã tồn tại.'
                    : 'Không thể thêm danh mục: ' . $categoryStmt->error;
                $categoryStmt->close();
            }
        } elseif ($categoryAction === 'update' || $categoryAction === 'delete') {
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            if ($categoryId < 1) {
                $formError = 'Danh mục cần thao tác không hợp lệ.';
            } elseif ($categoryAction === 'update') {
                if ($categoryName === '') {
                    $formError = 'Tên danh mục không được để trống.';
                } else {
                    $updateStmt = $conn->prepare(
                        "UPDATE " . $categoryTable . " SET name = ? WHERE category_id = ?"
                    );
                    if (!$updateStmt) {
                        die('Không thể chuẩn bị cập nhật danh mục: ' . $conn->error);
                    }
                    $updateStmt->bind_param('si', $categoryName, $categoryId);
                    if ($updateStmt->execute()) {
                        header('Location: manage_categories.php?category_updated=1');
                        exit();
                    }
                    $formError = $updateStmt->errno === 1062
                        ? 'Tên danh mục này đã tồn tại.'
                        : 'Không thể cập nhật danh mục: ' . $updateStmt->error;
                    $updateStmt->close();
                }
            } else {
                $conn->begin_transaction();
                $clearStmt = $conn->prepare(
                    "UPDATE products SET " . $categoryConfig['product_column'] .
                    " = NULL WHERE " . $categoryConfig['product_column'] . " = ?"
                );
                $deleteStmt = $conn->prepare(
                    "DELETE FROM " . $categoryTable . " WHERE category_id = ?"
                );
                if (!$clearStmt || !$deleteStmt) {
                    $conn->rollback();
                    die('Không thể chuẩn bị xóa danh mục: ' . $conn->error);
                }
                $clearStmt->bind_param('i', $categoryId);
                $deleteStmt->bind_param('i', $categoryId);
                if (!$clearStmt->execute()) {
                    $formError = 'Không thể gỡ danh mục khỏi sản phẩm: ' . $clearStmt->error;
                } elseif (!$deleteStmt->execute()) {
                    $formError = 'Không thể xóa danh mục: ' . $deleteStmt->error;
                }
                $clearStmt->close();
                $deleteStmt->close();

                if ($formError === '') {
                    $conn->commit();
                    header('Location: manage_categories.php?category_deleted=1');
                    exit();
                }
                $conn->rollback();
            }
        } else {
            $formError = 'Thao tác danh mục không hợp lệ.';
        }
    }
}

$categoriesByType = [];
foreach ($categoryTypes as $type => $categoryConfig) {
    $categoryResult = $conn->query(
        "SELECT c.category_id, c.name, COUNT(p.product_id) AS product_count
         FROM " . $categoryConfig['table'] . " c
         LEFT JOIN products p ON p." . $categoryConfig['product_column'] . " = c.category_id
         GROUP BY c.category_id, c.name
         ORDER BY c.name"
    );
    if (!$categoryResult) {
        die('Lỗi lấy danh mục ' . $categoryConfig['label'] . ': ' . $conn->error);
    }
    $categoriesByType[$type] = [];
    while ($category = $categoryResult->fetch_assoc()) {
        $categoriesByType[$type][] = $category;
    }
    $categoryResult->free();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
    <?php $adminActivePage = 'categories'; include __DIR__ . '/admin_navigation.php'; ?>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div>
                <span class="admin-eyebrow">PHÂN LOẠI SẢN PHẨM</span>
                <h1>Danh mục</h1>
                <p>Thêm, đổi tên và quản lý danh mục nhân vật, kích cỡ độc lập với kho sản phẩm.</p>
            </div>
            <span class="admin-heading-icon"><i class="fas fa-tags" aria-hidden="true"></i></span>
        </div>

        <?php if ($formError !== ''): ?>
            <p class="admin-alert admin-alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (isset($_GET['category_added'])): ?>
            <p class="admin-alert admin-alert-success">Đã thêm danh mục thành công.</p>
        <?php elseif (isset($_GET['category_updated'])): ?>
            <p class="admin-alert admin-alert-success">Đã cập nhật danh mục thành công.</p>
        <?php elseif (isset($_GET['category_deleted'])): ?>
            <p class="admin-alert admin-alert-success">Đã xóa danh mục. Sản phẩm vẫn được giữ lại.</p>
        <?php endif; ?>

        <?php foreach ($categoryTypes as $type => $categoryConfig): ?>
            <section class="product-category-manager">
                <div class="admin-section-heading">
                    <div>
                        <span class="admin-eyebrow">PHÂN LOẠI</span>
                        <h2><?php echo htmlspecialchars($categoryConfig['heading'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>
                    <span class="admin-record-count"><?php echo count($categoriesByType[$type]); ?> danh mục</span>
                </div>

                <form method="POST" class="admin-category-add-form">
                    <label for="<?php echo $type; ?>_category_name">Thêm danh mục <?php echo htmlspecialchars($categoryConfig['label'], ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="category_type" value="<?php echo $type; ?>">
                    <input type="text" id="<?php echo $type; ?>_category_name" name="category_name" maxlength="100" placeholder="Nhập tên danh mục mới..." required>
                    <?php if (adminHasPermission('categories.create')): ?><button class="admin-button admin-button-primary" type="submit">
                        <i class="fas fa-plus" aria-hidden="true"></i> Thêm danh mục
                    </button><?php endif; ?>
                </form>

                <?php if (empty($categoriesByType[$type])): ?>
                    <p>Chưa có danh mục <?php echo htmlspecialchars($categoryConfig['label'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                <?php else: ?>
                    <div class="admin-table-wrap admin-category-table-wrap">
                        <table class="admin-category-table">
                            <thead>
                                <tr>
                                    <th>Tên danh mục</th>
                                    <th class="col-center">Sản phẩm</th>
                                    <th class="col-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoriesByType[$type] as $category): ?>
                                    <tr>
                                        <td class="category-name-cell">
                                            <strong><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </td>
                                        <td class="category-count-cell col-center">
                                            <span class="admin-record-count"><?php echo (int) $category['product_count']; ?></span>
                                        </td>
                                        <td class="category-action-cell col-right">
                                            <form method="POST" class="category-delete-form"
                                                onsubmit="return confirm('Xóa danh mục này? Sản phẩm vẫn được giữ lại nhưng sẽ không còn thuộc danh mục này.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="category_action" value="delete">
                                                <input type="hidden" name="category_type" value="<?php echo $type; ?>">
                                                <input type="hidden" name="category_id" value="<?php echo (int) $category['category_id']; ?>">
                                                <?php if (adminHasPermission('categories.delete')): ?>
                                                    <button class="admin-button admin-button-danger" type="submit" title="Xóa danh mục">
                                                        <i class="fas fa-trash" aria-hidden="true"></i> <span>Xóa</span>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <p class="admin-form-wide"><a href="./manage_products.php">Quay lại quản lý sản phẩm</a></p>
    </main>
</body>
</html>
