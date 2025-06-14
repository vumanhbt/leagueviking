<?php
// File: partials/tabs_content/bxh_content.php

if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/../../includes/db_connect.php';
}
// `functions.php` cũng nên được gọi ở file index chính, hoặc gọi ở đây nếu cần
if (!function_exists('getTournamentStandings')) {
    require_once __DIR__ . '/../../includes/functions.php';
}

// Lấy tournament_id đầu tiên đang diễn ra hoặc đã hoàn thành gần nhất làm mặc định
$default_tournament_id = 0;
$res_t = $conn->query("SELECT tournament_id FROM tournaments ORDER BY status ASC, start_date DESC, tournament_id DESC LIMIT 1");
if($res_t) $default_tournament_id = $res_t->fetch_assoc()['tournament_id'] ?? 0;

// Logic lấy BXH giờ chỉ là một dòng gọi hàm
$final_standings = getTournamentStandings($conn, $default_tournament_id);
?>

<div class="tab-pane-content">
    <h2 class="mb-4">Bảng Xếp Hạng</h2>
    <?php if (empty($final_standings)): ?>
        <div class="alert alert-info">Chưa có thông tin vận động viên nào để hiển thị bảng xếp hạng.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered text-center">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col" class="text-start">VĐV</th>
                        <th scope="col" title="Điểm">PTS</th>
                        <th scope="col" title="Số trận đã đấu">P</th>
                        <th scope="col" title="Số trận thắng">W</th>
                        <th scope="col" title="Số trận hòa">D</th>
                        <th scope="col" title="Số trận thua">L</th>
                        <th scope="col" title="Tổng điểm ghi được">F</th>
                        <th scope="col" title="Tổng điểm bị ghi">A</th>
                        <th scope="col" title="Hiệu số">GD</th>
                        </tr>
                </thead>
                <tbody>
                    <?php foreach ($final_standings as $index => $stats): ?>
                    <tr>
                        <th scope="row"><?php echo $index + 1; ?></th>
                        <td class="text-start"><?php echo htmlspecialchars($stats['player_name']); ?></td>
                        <td class="fw-bold"><?php echo $stats['PTS']; ?></td>
                        <td><?php echo $stats['P']; ?></td>
                        <td><?php echo $stats['W']; ?></td>
                        <td><?php echo $stats['D']; ?></td>
                        <td><?php echo $stats['L']; ?></td>
                        <td><?php echo $stats['F']; ?></td>
                        <td><?php echo $stats['A']; ?></td>
                        <td><?php echo $stats['GD']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4 p-3 border rounded bg-light">
            <h5 class="mb-3">Chú Thích Bảng Xếp Hạng</h5>
            <table class="table table-sm table-borderless w-auto">
                <tbody>
                    <tr><td class="fw-bold">PTS</td><td>Điểm (Points: Thắng 3đ, Hòa 1đ)</td></tr>
                    <tr><td class="fw-bold">P</td><td>Số trận đã đấu (Played)</td></tr>
                    <tr><td class="fw-bold">W</td><td>Số trận thắng (Wins)</td></tr>
                    <tr><td class="fw-bold">D</td><td>Số trận hòa (Draws)</td></tr>
                    <tr><td class="fw-bold">L</td><td>Số trận thua (Losses)</td></tr>
                    <tr><td class="fw-bold">F</td><td>Tổng điểm ghi được (For)</td></tr>
                    <tr><td class="fw-bold">A</td><td>Tổng điểm bị ghi (Against)</td></tr>
                    <tr><td class="fw-bold">GD</td><td>Hiệu số (Goal Difference: F - A)</td></tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>