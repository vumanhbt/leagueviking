<?php
// File: admin/auth_check.php
// File này sẽ được include ở đầu các trang admin cần bảo vệ
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin(); // Gọi hàm kiểm tra và chuyển hướng nếu chưa đăng nhập
?>