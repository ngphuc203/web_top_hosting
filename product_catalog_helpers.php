<?php

function ensureProductCatalogSchema(mysqli $conn)
{
    $columns = [
        'character_category_id' => 'INT NULL',
        'size_category_id' => 'INT NULL',
        'size_value' => 'VARCHAR(100) NULL'
    ];

    foreach ($columns as $column => $definition) {
        $columnResult = $conn->query("SHOW COLUMNS FROM products LIKE '" . $column . "'");
        if (!$columnResult) {
            die('Lỗi kiểm tra cấu trúc sản phẩm: ' . $conn->error);
        }

        if ($columnResult->num_rows === 0 && !$conn->query(
            "ALTER TABLE products ADD COLUMN " . $column . " " . $definition
        )) {
            die('Lỗi cập nhật cấu trúc sản phẩm: ' . $conn->error);
        }
    }

    $tableStatements = [
        "CREATE TABLE IF NOT EXISTS product_character_categories (
            category_id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            PRIMARY KEY (category_id),
            UNIQUE KEY unique_character_category_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS product_size_categories (
            category_id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            PRIMARY KEY (category_id),
            UNIQUE KEY unique_size_category_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS product_images (
            image_id INT NOT NULL AUTO_INCREMENT,
            product_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (image_id),
            KEY product_images_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tableStatements as $statement) {
        if (!$conn->query($statement)) {
            die('Lỗi tạo cấu trúc danh mục/ảnh sản phẩm: ' . $conn->error);
        }
    }
}

function productImageUrl($imagePath)
{
    $imageName = basename(str_replace('\\', '/', trim((string) $imagePath)));
    return $imageName === '' ? '' : 'uploads/' . rawurlencode($imageName);
}

function saveProductImageUpload(array $file, &$error, $required = false)
{
    $error = '';
    $uploadError = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

    if ($uploadError === UPLOAD_ERR_NO_FILE && !$required) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        $error = $uploadError === UPLOAD_ERR_NO_FILE
            ? 'Vui lòng chọn ảnh chính cho sản phẩm.'
            : 'Tải ảnh lên thất bại. Hãy kiểm tra dung lượng và thử lại.';
        return false;
    }

    if (!isset($file['tmp_name'], $file['size']) || (int) $file['size'] > 10 * 1024 * 1024) {
        $error = 'Mỗi ảnh tải lên không được vượt quá 10 MB.';
        return false;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $error = 'Tệp tải lên không phải ảnh hợp lệ.';
        return false;
    }

    $extensionsByMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $mimeType = isset($imageInfo['mime']) ? $imageInfo['mime'] : '';
    if (!isset($extensionsByMime[$mimeType])) {
        $error = 'Chỉ chấp nhận ảnh JPG, PNG, GIF hoặc WebP.';
        return false;
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        $error = 'Không thể tạo thư mục lưu ảnh sản phẩm.';
        return false;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensionsByMime[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        $error = 'Không thể lưu ảnh sản phẩm lên máy chủ.';
        return false;
    }

    return $fileName;
}

function saveProductImageUploads(array $files, &$error)
{
    $savedImages = [];
    $fileCount = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;

    for ($index = 0; $index < $fileCount; $index++) {
        if ($files['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $file = [
            'name' => $files['name'][$index],
            'type' => $files['type'][$index],
            'tmp_name' => $files['tmp_name'][$index],
            'error' => $files['error'][$index],
            'size' => $files['size'][$index]
        ];
        $imageName = saveProductImageUpload($file, $error);

        if ($imageName === false) {
            deleteProductImageFiles($savedImages);
            return false;
        }

        if ($imageName !== null) {
            $savedImages[] = $imageName;
        }
    }

    return $savedImages;
}

function deleteProductImageFiles(array $imageNames)
{
    foreach ($imageNames as $imageName) {
        $safeName = basename((string) $imageName);
        $imagePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $safeName;
        if ($safeName !== '' && is_file($imagePath)) {
            unlink($imagePath);
        }
    }
}
