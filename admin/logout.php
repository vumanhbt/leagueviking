<?php
// File: admin/logout.php
require_once __DIR__ . '/../includes/config.php'; // Để dùng SITE_URL
require_once __DIR__ . '/../includes/functions.php'; // Để bắt đầu session

// Hủy tất cả các biến session.
$_SESSION = array();

// Nếu muốn hủy session hoàn toàn, hãy xóa cả cookie session.
// Lưu ý: Điều này sẽ hủy session, không chỉ dữ liệu session!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Cuối cùng, hủy session.
session_destroy();

header('Location: login.php'); // Chuyển hướng về trang đăng nhập
exit;
?>