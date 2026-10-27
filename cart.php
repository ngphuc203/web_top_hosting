<?php
session_start();
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_catalog_helpers.php';
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total = 0;
$product_ids = array_column($cart, 'id');
$products = [];
if (count($product_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[$row['product_id']] = $row;
    }
    $stmt->close();
}
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $product_id = $_GET['remove'];
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['id'] == $product_id) {
            unset($_SESSION['cart'][$key]);
            break;
        }
    }
    header('Location: cart.php'); 
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (isset($_POST['quantity'])) {
        foreach ($_POST['quantity'] as $product_id => $quantity) {
            foreach ($_SESSION['cart'] as $key => $item) {
                if ($item['id'] == $product_id) {
                    $_SESSION['cart'][$key]['quantity'] = $quantity;
                    break;
                }
            }
        }
    }
    header('Location: cart.php'); 
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit();
    }
    if (empty($cart)) {
        echo "<script>alert('Giỏ hàng của bạn trống, vui lòng thêm sản phẩm vào giỏ hàng trước khi thanh toán.');</script>";
        header('Location: cart.php');
        exit();
    }
    $username = $_SESSION['username'];
    $fullname = $_POST['fullname'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $payment_method = $_POST['payment_method'];
    $status = 'Pending';  
    foreach ($cart as $item) {
        $total += $products[$item['id']]['price'] * $item['quantity'];
    }
    $stmt = $conn->prepare("INSERT INTO orders (username, fullname, address, phone, payment_method, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $username, $fullname, $address, $phone, $payment_method, $total, $status);
    $stmt->execute();
    $order_id = $stmt->insert_id;  
    $stmt->close();
    foreach ($cart as $item) {
        $product_id = $item['id'];
        $product_name = $item['name'];
        $quantity = $item['quantity'];
        $price = $products[$item['id']]['price'];
        $total_item = $price * $quantity;
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdd", $order_id, $product_id, $product_name, $quantity, $price, $total_item);
        $stmt->execute();
        $stmt->close();
    }
    unset($_SESSION['cart']);
    echo "<script>alert('Đơn hàng của bạn đã được thanh toán thành công!'); window.location.href='products.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ Hàng</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
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
    <main class="cart-page">
        <nav class="product-breadcrumb product-catalog-breadcrumb" aria-label="Điều hướng">
            <a href="./index.php">Trang chủ</a>
            <span>/</span>
            <span class="current">Giỏ hàng</span>
        </nav>

        <?php if (count($cart) > 0): ?>
            <div class="cart-layout">
                <section class="cart-items-panel" aria-labelledby="cart-items-title">
                    <div class="cart-panel-heading">
                        <div>
                            <h2 id="cart-items-title">Sản phẩm trong giỏ</h2>
                            <p><?php echo count($cart); ?> sản phẩm</p>
                        </div>
                        <a href="./products.php">Tiếp tục mua sắm</a>
                    </div>
                    <form method="POST" action="cart.php" class="cart-update-form">
                        <div class="cart-items-list">
                        <?php foreach ($cart as $item):
                            if (isset($item['id']) && isset($products[$item['id']])) {
                                $product = $products[$item['id']];
                                $total += $product['price'] * $item['quantity'];
                        ?>
                            <article class="cart-item">
                                <a class="cart-item-image-link" href="product_details.php?id=<?php echo (int) $product['product_id']; ?>">
                                    <img
                                        class="cart-item-image"
                                        src="<?php echo htmlspecialchars(productImageUrl($product['image'] ?? 'default.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </a>
                                <div class="cart-item-details">
                                    <h3>
                                        <a class="cart-item-product-link" href="product_details.php?id=<?php echo (int) $product['product_id']; ?>">
                                            <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </h3>
                                    <span class="cart-item-unit-price"><?php echo number_format((float) $product['price'], 0, ',', '.'); ?> VND / sản phẩm</span>
                                    <a class="cart-remove-link" href="cart.php?remove=<?php echo (int) $item['id']; ?>">Xóa sản phẩm</a>
                                </div>
                                <label class="cart-quantity">
                                    <span>Số lượng</span>
                                    <input
                                        type="number"
                                        name="quantity[<?php echo (int) $item['id']; ?>]"
                                        value="<?php echo (int) $item['quantity']; ?>"
                                        min="1"
                                        max="100"
                                    >
                                </label>
                                <strong class="cart-item-total"><?php echo number_format((float) $product['price'] * (int) $item['quantity'], 0, ',', '.'); ?> VND</strong>
                            </article>
                        <?php
                            }
                        endforeach; ?>
                        </div>
                        <div class="cart-update-actions">
                            <button type="submit" name="update_cart" value="1" class="cart-secondary-button">Cập nhật giỏ hàng</button>
                        </div>
                    </form>
                </section>

                <aside class="cart-checkout-column">
                    <section class="cart-summary-panel" aria-labelledby="cart-summary-title">
                        <h2 id="cart-summary-title">Tóm tắt đơn hàng</h2>
                        <div class="cart-summary-line">
                            <span>Tạm tính</span>
                            <span><?php echo number_format($total, 0, ',', '.'); ?> VND</span>
                        </div>
                        <div class="cart-summary-line">
                            <span>Phí vận chuyển</span>
                            <span class="cart-shipping-note">Tính khi xác nhận</span>
                        </div>
                        <div class="cart-summary-total">
                            <span>Tổng cộng</span>
                            <strong><?php echo number_format($total, 0, ',', '.'); ?> VND</strong>
                        </div>
                        <p class="cart-summary-note">Tổng tiền chưa bao gồm phí vận chuyển (nếu có).</p>
                    </section>

                    <section class="cart-checkout-panel" aria-labelledby="checkout-title">
                        <h2 id="checkout-title">Thông tin thanh toán</h2>
                        <?php if (isset($_SESSION['username'])): ?>
                            <p>Vui lòng điền thông tin nhận hàng của bạn.</p>
                            <form method="POST" action="cart.php" class="cart-checkout-form">
                                <label for="fullname">Họ và tên</label>
                                <input type="text" id="fullname" name="fullname" autocomplete="name" required>

                                <label for="address">Địa chỉ nhận hàng</label>
                                <input type="text" id="address" name="address" autocomplete="street-address" required>

                                <label for="phone">Số điện thoại</label>
                                <input type="tel" id="phone" name="phone" autocomplete="tel" required>

                                <label for="payment_method">Phương thức thanh toán</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="cash_on_delivery">Thanh toán khi nhận hàng</option>
                                    <option value="online_payment">Thanh toán trực tuyến</option>
                                </select>

                                <button type="submit" name="checkout" value="1" class="cart-checkout-button">
                                    Đặt hàng · <?php echo number_format($total, 0, ',', '.'); ?> VND
                                </button>
                            </form>
                            <div class="cart-secure-note"><i class="fas fa-lock" aria-hidden="true"></i> Thông tin của bạn được sử dụng để xử lý đơn hàng.</div>
                        <?php else: ?>
                            <p>Đăng nhập để tiếp tục đặt hàng.</p>
                            <a href="./login.php" class="cart-checkout-button">Đăng nhập để đặt hàng</a>
                        <?php endif; ?>
                    </section>
                </aside>
            </div>
        <?php else: ?>
            <section class="cart-empty-state">
                <div class="cart-empty-icon"><i class="fas fa-bag-shopping" aria-hidden="true"></i></div>
                <h2>Giỏ hàng đang trống</h2>
                <p>Hãy khám phá các sản phẩm và tìm món đồ yêu thích của bạn.</p>
                <a href="./products.php" class="cart-checkout-button">Khám phá sản phẩm</a>
            </section>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/site_footer.php'; ?>
    </body>
</html>
