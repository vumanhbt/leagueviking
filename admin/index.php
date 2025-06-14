<?php
// File: admin/index.php
// Đảm bảo file này nằm trong thư mục /admin/

require_once __DIR__ . '/auth_check.php'; // Kiểm tra đăng nhập trước tiên
                                      // auth_check.php đã gọi config.php và functions.php

$page_title = "Admin Dashboard";
$current_page = "dashboard"; // Biến này để làm sáng (active) link trên thanh menu admin

// Nạp header của trang Admin
include __DIR__ . '/partials/header_admin.php';
?>

<div class="p-5 mb-4 bg-light rounded-3 border">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Chào mừng đến Trang Quản Trị!</h1>
        <p class="col-md-8 fs-4">
            Đây là nơi bạn có thể quản lý các thông tin của giải đấu như Vận Động Viên, Lịch Thi Đấu và Kết Quả.
        </p>
        <hr class="my-4">
        <p>Bắt đầu bằng cách chọn một mục quản lý từ thanh điều hướng bên trên.</p>
        <a href="players_manage.php" class="btn btn-primary btn-lg" role="button">Quản Lý Vận Động Viên &raquo;</a>
        </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Thống Kê Nhanh</h5>
                <p class="card-text">
                    <?php
                    // require_once __DIR__ . '/../includes/db_connect.php'; // Mở kết nối DB nếu chưa có
                    // Ví dụ: Đếm số VĐV
                    // Biến $conn đã được khởi tạo trong db_connect.php (được gọi từ nơi khác hoặc gọi trực tiếp)
                    // Nếu header_admin.php hoặc auth_check.php chưa gọi db_connect.php thì cần gọi ở đây
                    // Để an toàn, ta có thể kiểm tra và gọi lại nếu cần
                    if (!isset($conn) || !$conn) { // Kiểm tra nếu $conn chưa được thiết lập hoặc là null/false
                         require_once __DIR__ . '/../includes/db_connect.php';
                    }

                    $sql_count_players = "SELECT COUNT(*) as total_players FROM players";
                    $result_count = $conn->query($sql_count_players);
                    $total_players = 0;
                    if ($result_count && $result_count->num_rows > 0) {
                        $total_players = $result_count->fetch_assoc()['total_players'];
                    }
                    echo "Tổng số Vận Động Viên hiện tại: <strong>" . $total_players . "</strong>";
                    ?>
                </p>
                </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Hướng Dẫn</h5>
                <p class="card-text">Sử dụng menu điều hướng ở trên để truy cập các chức năng quản lý.</p>
                <ul>
                    <li><strong>Quản Lý VĐV:</strong> Thêm, sửa, xóa thông tin vận động viên.</li>
                    <li><strong>Quản Lý Trận Đấu:</strong> Sắp xếp lịch thi đấu.</li>
                    <li><strong>Nhập Kết Quả:</strong> Cập nhật kết quả các trận đấu đã diễn ra.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Nạp footer của trang Admin
include __DIR__ . '/partials/footer_admin.php';

// Đóng kết nối DB nếu nó được mở trong file này
// Tuy nhiên, nếu $conn được mở ở auth_check.php hoặc header_admin.php và dùng chung,
// thì nên có cơ chế đóng ở footer_admin.php hoặc file chính (index.php của admin)
// Hiện tại, db_connect.php không tự đóng, nên nếu mở thì nên đóng.
// footer_admin.php chưa có lệnh đóng. Ta có thể thêm vào đó hoặc đóng ở đây.
// if (isset($conn) && $conn instanceof mysqli) { // $conn instanceof mysqli để chắc chắn $conn là đối tượng mysqli
//     $conn->close();
// }
?>