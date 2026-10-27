<?php
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['username'])) {
    header('Location: login.php'); // Chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
    exit();
}

// Kết nối cơ sở dữ liệu
require_once __DIR__ . '/../config.php';

// Giỏ hàng được lưu trong session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Thêm sản phẩm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    // Kiểm tra sự tồn tại của product_id và quantity
    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        echo "Lỗi: Thiếu thông tin sản phẩm hoặc số lượng!";
        exit();
    }

    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    // Lấy sản phẩm từ cơ sở dữ liệu dựa trên product_id
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
            'quantity' => $quantity
        ];
    }
    $stmt->close();
    header('Location: products.php');
    exit();
}

// Lấy danh sách sản phẩm từ cơ sở dữ liệu
$sql = "SELECT * FROM products";
$result = $conn->query($sql);
$products = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Sản Phẩm</title>
    <link rel="stylesheet" href="./main.css">
</head>
<body>
    <h3>Chào mừng, <?php echo $_SESSION['username']; ?></h3>
    <section id="header">
        <ul id="navbar">
            <a href="./index.php">
                <img src="./img/logov2.png" class="logo" style="width:12%; height:12%;">
            </a>
            <li><a class="active" href="./index.php">Trang Chủ</a></li>
            <li><a href="./products.php">Sản Phẩm</a></li>
            <li><a href="./cart.php">Giỏ Hàng (<?php echo count($_SESSION['cart']); ?>)</a></li>
            <li><a href="./my_account.php">Tài Khoản</a></li>
            <li><a href="./logout.php">Đăng Xuất</a></li>
        </ul>
    </section>

    <h1>Danh Sách Sản Phẩm</h1>
    <div class="product-list product-catalog">
        <?php if (empty($products)): ?>
            <p>Không có sản phẩm nào!</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <section id="product1" class="product-catalog-item">
                    <div class="pro product-catalog-card">
                        <?php if (!empty($product['image'])): ?>
                            <img class="product-card-image" src="<?php echo $product['image']; ?>" alt="Hình ảnh sản phẩm">
                        <?php else: ?>
                            <div class="product-image-placeholder" aria-hidden="true"></div>
                        <?php endif; ?>
                        <span class="product-card-name" style="color:blueviolet;"><?php echo htmlspecialchars($product['name']); ?></span>
                        <p class="product-card-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <p class="product-card-price" style="color:red;">Giá: <?php echo number_format($product['price'], 0, ',', '.'); ?> VND</p>
                        <form method="POST" action="products.php">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>"> <!-- Sửa 'id' thành 'product_id' -->
                            <label for="quantity">Số lượng:</label>
                            <input type="number" name="quantity" id="quantity" min="1" max="<?php echo $product['quantity']; ?>" value="1" required>
                            <button type="submit">Thêm vào giỏ hàng</button>
                        </form>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>






<!-- ============================================================= -->
<?php
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['username'])) {
    header('Location: login.php'); // Chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
    exit();
}

// Kết nối cơ sở dữ liệu
require_once __DIR__ . '/../config.php';

// Giỏ hàng được lưu trong session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Thêm sản phẩm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    // Kiểm tra sự tồn tại của product_id và quantity
    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        echo "Lỗi: Thiếu thông tin sản phẩm hoặc số lượng!";
        exit();
    }

    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    // Lấy sản phẩm từ cơ sở dữ liệu dựa trên product_id
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
            'quantity' => $quantity
        ];
    }
    $stmt->close();
    header('Location: products.php');
    exit();
}

// Lấy danh sách sản phẩm từ cơ sở dữ liệu
$sql = "SELECT * FROM products";
$result = $conn->query($sql);
$products = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$recommendedProducts = $products;
shuffle($recommendedProducts);
$recommendedProducts = array_slice($recommendedProducts, 0, 4);
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Sản Phẩm</title>
    <link rel="stylesheet" href="./main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
        <section id="header">
        <ul id="navbar">
            <a href="./index.php"><img src="./img/logo.png"class="logo" style="width:30%; height: 10%;"></a>
            <li><a class="active" href="./index.php">Trang Chủ</a></li>
            <li><a href="./products.php">Sản Phẩm</a></li>
            <li><a href="./cart.php">Giỏ Hàng (<?php echo count($_SESSION['cart']); ?>)</a></li>
            <li><a href="./my_account.php">Tài Khoản</a></li>
            <li><a href="./logout.php">Đăng Xuất</a></li>
        </ul>      
    </section>
    <h1>Danh Sách Sản Phẩm</h1>

    
    <div class="product-list product-catalog">
        <?php if (empty($products)): ?>
            <p>Không có sản phẩm nào!</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <section id="product1" class="product-catalog-item">
                    <div class="pro product-catalog-card">
                        <?php if (!empty($product['image'])): ?>
                            <img class="product-card-image" src="<?php echo $product['image']; ?>" alt="Hình ảnh sản phẩm">
                        <?php else: ?>
                            <div class="product-image-placeholder" aria-hidden="true"></div>
                        <?php endif; ?>
                        <span class="product-card-name" style="color:blueviolet;"><?php echo htmlspecialchars($product['name']); ?></span>
                        <p class="product-card-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <p class="product-card-price" style="color:red;">Giá: <?php echo number_format($product['price'], 0, ',', '.'); ?> VND</p>
                        <form method="POST" action="products.php">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>"> <!-- Sửa 'id' thành 'product_id' -->
                            <label for="quantity">Số lượng:</label>
                            <input type="number" name="quantity" id="quantity" min="1" max="<?php echo $product['quantity']; ?>" value="1" required>
                            <button type="submit">Thêm vào giỏ hàng</button>
                        </form>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <section class="featured-products">
        <h2>Sản phẩm bạn có thể thích</h2>
        <div class="product-list product-catalog">
            <?php if (empty($recommendedProducts)): ?>
                <p>Hiện chưa có sản phẩm để gợi ý.</p>
            <?php else: ?>
                <?php foreach ($recommendedProducts as $product): ?>
                    <section id="product1" class="product-catalog-item">
                        <div class="pro product-catalog-card">
                            <?php if (!empty($product['image'])): ?>
                                <img class="product-card-image" src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Hình ảnh sản phẩm">
                            <?php else: ?>
                                <div class="product-image-placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                            <span class="product-card-name" style="color:blueviolet;"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <p class="product-card-description"><?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="product-card-price" style="color:red;">Giá: <?php echo number_format((float) $product['price'], 0, ',', '.'); ?> VND</p>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php include __DIR__ . '/../site_footer.php'; ?>
    </div>
</body>
</html>
