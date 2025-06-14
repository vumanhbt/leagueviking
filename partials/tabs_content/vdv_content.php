<?php
// File: partials/tabs_content/vdv_content.php

// Nạp file kết nối database.
// Biến $conn sẽ được tạo từ file này.
// Lưu ý: file index.php (public) đã gọi db_connect.php rồi,
// nên $conn đã tồn tại. Nếu không chắc, có thể require_once ở đây.
// require_once __DIR__ . '/../../includes/db_connect.php';

// Nếu $conn chưa được khởi tạo (ví dụ file này được gọi độc lập), thì khởi tạo
if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/../../includes/db_connect.php';
}
$tier_sort_order_string = "'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'Mới'"; // Chuỗi cho FIELD()
$players = [];
$sql = "SELECT player_id, player_name, current_target_score, player_tier_label 
        FROM players 
        WHERE is_active = 1
        ORDER BY FIELD(player_tier_label, " . $tier_sort_order_string . "), current_target_score DESC, player_name ASC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
} else {
    // echo "Không có VĐV nào trong hệ thống."; // Có thể hiển thị thông báo
}
?>

<div class="tab-pane-content"> <h2 class="mb-4">Danh Sách Vận Động Viên</h2>

    <?php if (empty($players)): ?>
        <div class="alert alert-info" role="alert">
            Hiện tại chưa có thông tin vận động viên nào được cập nhật.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Cơ Thủ</th>
                        <th scope="col">Hạng</th>
                        <th scope="col">Điểm Thi Đấu</th>
                        </tr>
                </thead>
                <tbody>
                    <?php $count = 1; ?>
                    <?php foreach ($players as $player): ?>
                        <tr>
                            <th scope="row"><?php echo $count++; ?></th>
                            <td><?php echo htmlspecialchars($player['player_name']); ?></td>
                            <td><?php echo htmlspecialchars($player['player_tier_label'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($player['current_target_score']); ?></td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>