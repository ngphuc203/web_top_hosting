<?php
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['username'])) {
    header('Location: login.php'); // Chuyển hướng nếu chưa đăng nhập
    exit();
}

// Lấy giỏ hàng từ session
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total = 0;

// Tính tổng tiền nếu giỏ hàng không rỗng
if (!empty($cart)) {
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} else {
    echo "<p style='color: red;'>Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm vào giỏ trước khi thanh toán.</p>";
    exit();
}
$success_message = "";

// Kết nối cơ sở dữ liệu
require_once __DIR__ . '/../config.php';

// Xử lý gửi thông tin đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    // Lưu thông tin đơn hàng vào cơ sở dữ liệu
    $username = $_SESSION['username'];
    $fullname = $_POST['fullname'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $payment_method = $_POST['payment_method'];
    $status = 'Pending';  // Trạng thái đơn hàng là "Chờ xử lý"

    // Thêm đơn hàng vào bảng orders
    $stmt = $conn->prepare("INSERT INTO orders (username, fullname, address, phone, payment_method, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $username, $fullname, $address, $phone, $payment_method, $total, $status);
    $stmt->execute();
    $order_id = $stmt->insert_id;  // Lấy ID đơn hàng vừa tạo
    $stmt->close();

    // Thêm các sản phẩm vào bảng order_items
    foreach ($cart as $item) {
        $product_id = $item['id'];
        $product_name = $item['name'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $total_item = $price * $quantity;

        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdd", $order_id, $product_id, $product_name, $quantity, $price, $total_item);
        $stmt->execute();
        $stmt->close();
    }

    // Xóa giỏ hàng sau khi thanh toán
    unset($_SESSION['cart']);

    // Thiết lập thông báo thành công
    $success_message = "Đơn hàng của bạn đã được thanh toán thành công! Mã đơn hàng: <strong>$order_id</strong>";
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <h3>Chào mừng, <?php echo htmlspecialchars($_SESSION['username']); ?></h3>
    <section id="header">
        <div>
            <ul id="navbar">
                <a href="./index.php"><img src="./img/logov2.png" class="logo" style="width:12%; height: 12%;"></a>
                <li><a href="./index.php">Trang Chủ</a></li>
                <li><a href="./products.php">Sản Phẩm</a></li>
                <li><a href="./cart.php">Giỏ Hàng</a></li>
                <li><a href="./login.php">Tài Khoản</a></li>
                <li><a href="./logout.php">Đăng Xuất</a></li>
            </ul>
        </div>
    </section>

    <div class="checkout-form">
        <h2>Thông tin thanh toán</h2>

        <!-- Hiển thị thông báo thành công -->
        <?php if ($success_message): ?>
            <div class="success-message" style="color: green; font-size: 1.2em;">
                <?php echo $success_message; ?>
            </div>
            <p>Cảm ơn bạn đã mua sắm tại cửa hàng của chúng tôi!</p>
            <a href="./index.php" style="color: blue; text-decoration: underline;">Quay lại Trang Chủ</a>
        <?php else: ?>
            <form method="POST">
                <label for="fullname">Họ và tên:</label><br>
                <input type="text" name="fullname" id="fullname" required><br>

                <label for="address">Địa chỉ:</label><br>
                <input type="text" name="address" id="address" required><br>

                <label for="phone">Số điện thoại:</label><br>
                <input type="text" name="phone" id="phone" required><br>

                <label for="payment_method">Phương thức thanh toán:</label><br>
                <select name="payment_method" id="payment_method" required>
                    <option value="Cash on Delivery">Thanh toán khi nhận hàng</option>
                    <option value="Online Payment">Thanh toán trực tuyến</option>
                </select>

                <h3>Danh sách sản phẩm trong giỏ hàng</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Số lượng</th>
                            <th>Giá</th>
                            <th>Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo number_format($item['price'], 0, ',', '.') . " đ"; ?></td>
                                <td><?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.') . " đ"; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Tổng tiền: <?php echo number_format($total, 0, ',', '.') . " đ"; ?></h3>

                <button type="submit" name="submit_order">Xác nhận đơn hàng</button>
            </form>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../site_footer.php'; ?>
</body>
</html>
