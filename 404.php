<?php
// File: leaguegmn/404.php
// Đặt header HTTP cho trang 404
header("HTTP/1.0 404 Not Found");

// Nạp config để lấy SITE_NAME, SITE_URL nếu cần
require_once __DIR__ . '/includes/config.php';
$page_title = "404 - Không Tìm Thấy Trang";

// Bạn có thể nạp header chung của trang public nếu muốn giao diện nhất quán
// Hoặc tạo một header đơn giản cho trang 404
// include __DIR__ . '/partials/header.php'; // Nếu muốn dùng header chung
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; text-align: center; }
        .status-code { font-size: 120px; font-weight: bold; }
        .status-message { font-size: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-code">404</div>
        <div class="status-message">Oops! Trang bạn truy cập không tồn tại.</div>
        <p class="mt-3">Có vẻ như bạn đã truy cập một đường dẫn không đúng hoặc trang đã bị xóa.</p>
        <a href="<?php echo SITE_URL; ?>" class="btn btn-primary mt-3">Quay Về Trang Chủ</a>
    </div>
</body>
</html>
<?php
// include __DIR__ . '/partials/footer.php'; // Nếu muốn dùng footer chung
exit; // Dừng thực thi script sau khi hiển thị trang 404
?>