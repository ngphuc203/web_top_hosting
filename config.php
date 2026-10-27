<?php
/**
 * ============================================================================
 * CẤU HÌNH KẾT NỐI CƠ SỞ DỮ LIỆU (DATABASE CONFIGURATION)
 * ============================================================================
 */

// Tự động nhận diện môi trường (Localhost WAMP hay Hosting InfinityFree):
if (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)) {
    // Cấu hình khi chạy trên máy tính cá nhân (WampServer)
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $database   = "doanv2";
} else {
    // Cấu hình kết nối MySQL trên hosting InfinityFree
    $servername = "sql311.infinityfree.com"; // MySQL Hostname
    $username   = "if0_43256056";            // MySQL Username
    $password   = "PoWYgvz53m";             // MySQL Password
    $database   = "if0_43256056_doan_v2";   // Database Name
}

// Khởi tạo kết nối duy nhất (tránh kết nối lặp lại nhiều lần trong cùng request)
if (!isset($conn) || !($conn instanceof mysqli)) {
    // Tắt chế độ ném ngoại lệ mysqli để xử lý lỗi gọn gàng qua connect_error
    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = @new mysqli($servername, $username, $password, $database);

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error . "<br>Vui lòng kiểm tra lại thông số trong file <b>config.php</b>.");
    }

    // Thiết lập bảng mã UTF-8 (utf8mb4 hỗ trợ đầy đủ tiếng Việt có dấu và emoji)
    if (!$conn->set_charset("utf8mb4")) {
        $conn->set_charset("utf8");
    }
}

// Cấu hình báo cáo lỗi PHP:
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
