<?php
// File: index.php (trong thư mục gốc league)

require_once __DIR__ . '/includes/db_connect.php';

$allowed_tabs = ['bxh', 'lichthidau', 'ketqua', 'vdv', 'cocaugiaithuong'];
$current_tab = 'bxh'; // Tab mặc định
$is_404 = false;

if (isset($_GET['tab'])) {
    if (in_array($_GET['tab'], $allowed_tabs)) {
        $current_tab = $_GET['tab'];
        // Kiểm tra xem file content của tab có thực sự tồn tại không
        $tab_content_file_check = __DIR__ . '/partials/tabs_content/' . $current_tab . '_content.php';
        if (!file_exists($tab_content_file_check)) {
            $is_404 = true; // File content không tồn tại -> 404
        }
    } else {
        $is_404 = true; // Tab không hợp lệ -> 404
    }
}
// Nếu không có $_GET['tab'], $current_tab vẫn là 'bxh' (mặc định), không phải 404.

if ($is_404) {
    include __DIR__ . '/404.php'; // Nạp trang 404 và thoát (trong 404.php đã có exit)
    // Script sẽ dừng ở đây do exit trong 404.php
}

// Đặt tiêu đề trang dựa trên tab hiện tại (chỉ chạy nếu không phải 404)
$page_title = '';
switch ($current_tab) {
    case 'bxh': $page_title = 'Bảng Xếp Hạng'; break;
    case 'lichthidau': $page_title = 'Lịch Thi Đấu'; break;
    case 'ketqua': $page_title = 'Kết Quả'; break;
    case 'vdv': $page_title = 'Vận Động Viên'; break;
    case 'cocaugiaithuong': $page_title = 'Cơ Cấu Giải Thưởng'; break;
    default: $page_title = 'Trang Chủ'; break;
}

include __DIR__ . '/partials/header.php';

$tab_content_file = __DIR__ . '/partials/tabs_content/' . $current_tab . '_content.php';
// file_exists đã được kiểm tra ở trên để set $is_404, nên ở đây có thể include trực tiếp
include $tab_content_file;

include __DIR__ . '/partials/footer.php';

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>