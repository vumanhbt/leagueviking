<?php
// File: includes/db_connect.php
require_once 'config.php'; // Nạp file cấu hình

// Tạo kết nối MySQLi
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Kiểm tra kết nối
if ($conn->connect_error) {
    // Không nên hiển thị lỗi chi tiết cho người dùng cuối trên production
    // Ghi log lỗi hoặc hiển thị thông báo chung chung
    die("Kết nối Database thất bại. Vui lòng thử lại sau. Lỗi: " . $conn->connect_error);
}

// Thiết lập charset UTF-8 cho kết nối (quan trọng để hiển thị tiếng Việt)
if (!$conn->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
    // Ghi log hoặc xử lý lỗi nếu cần
}

// echo "Kết nối thành công!"; // Bỏ comment để kiểm tra nếu cần
?>