<?php
// File: partials/tabs_content/ketqua_content.php

if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/../../includes/db_connect.php';
}

// --- LOGIC PHP LẤY KẾT QUẢ CỦA BẠN ĐƯỢC GIỮ NGUYÊN ---

// Lấy các trận đã hoàn thành
$completed_matches = [];
$sql = "SELECT m.match_id, m.played_datetime, m.is_draw, m.player1_sets_won, m.player2_sets_won,
            p1.player_name as p1_name, p2.player_name as p2_name
        FROM matches m
        JOIN players p1 ON m.player1_id = p1.player_id
        JOIN players p2 ON m.player2_id = p2.player_id
        WHERE m.status = 'completed'
        ORDER BY m.played_datetime DESC, m.match_id DESC";
$result_matches = $conn->query($sql);
if($result_matches && $result_matches->num_rows > 0) {
    while($row = $result_matches->fetch_assoc()) {
        $completed_matches[$row['match_id']] = $row;
    }
}

// Lấy điểm các set của các trận đã hoàn thành
$sets_data = [];
if(!empty($completed_matches)) {
    $match_ids = array_keys($completed_matches);
    $match_ids_string = implode(',', $match_ids);
    $sql_sets = "SELECT match_id, set_number, player1_score_raw, player2_score_raw FROM sets WHERE match_id IN ($match_ids_string) ORDER BY set_number ASC";
    $result_sets = $conn->query($sql_sets);
    if($result_sets && $result_sets->num_rows > 0) {
        while($set = $result_sets->fetch_assoc()) {
            if(!isset($sets_data[$set['match_id']])) $sets_data[$set['match_id']] = [];
            $sets_data[$set['match_id']][] = $set;
        }
    }
}
?>
<div class="tab-pane-content">
    <h2 class="mb-4">Kết Quả Các Trận Đấu</h2>
    <p><i style="font-size: 11px;">(Bấm vào các trận đấu để xem chi tiết điểm các set)</i></p>
    <?php if (empty($completed_matches)): ?>
        <div class="alert alert-info">Chưa có trận đấu nào hoàn thành.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($completed_matches as $match_id => $match): 
                // Xác định người thắng
                $p1_is_winner = $match['player1_sets_won'] > $match['player2_sets_won'];
                $p2_is_winner = $match['player2_sets_won'] > $match['player1_sets_won'];
            ?>
                <div class="col-lg-6">
                    <div class="match-card is-clickable" data-bs-toggle="collapse" href="#details-<?php echo $match_id; ?>" role="button">
                        <div class="match-card-status">
                            FT <br>
                            <small><?php echo date('d/m', strtotime($match['played_datetime'])); ?></small>
                        </div>
                        
                        <div class="match-card-teams">
                            <div class="team-row <?php echo $p1_is_winner ? 'winner' : ''; ?>">
                                <div class="team-name"><?php echo htmlspecialchars($match['p1_name']); ?></div>
                            </div>
                            <div class="team-row <?php echo $p2_is_winner ? 'winner' : ''; ?>">
                                <div class="team-name"><?php echo htmlspecialchars($match['p2_name']); ?></div>
                            </div>
                        </div>

                        <div class="match-card-score">
                            <span class="score <?php echo $p1_is_winner ? 'winner' : ''; ?>"><?php echo $match['player1_sets_won']; ?></span>
                            <span class="score <?php echo $p2_is_winner ? 'winner' : ''; ?>"><?php echo $match['player2_sets_won']; ?></span>
                        </div>
                    </div>

                    <div class="collapse" id="details-<?php echo $match_id; ?>">
                        <div class="match-details-card">
                            <?php if (isset($sets_data[$match_id])): 
                                // Logic xử lý điểm các set của bạn được đặt vào đây
                                $p1_scores_str = [];
                                $p2_scores_str = [];
                                foreach ($sets_data[$match_id] as $set) {
                                    $p1_scores_str[] = $set['player1_score_raw'];
                                    $p2_scores_str[] = $set['player2_score_raw'];
                                }
                            ?>
                                <strong>Tỷ số các set:</strong>
                                <table>
                                    <tbody>
                                        <tr>
                                <td><?php echo htmlspecialchars($match['p1_name']); ?>:</td>
                                <td style="padding-left: 10px;"><?php echo implode(' / ', $p1_scores_str); ?></td><br>
                                        </tr>
                                        <tr>    
                                <td><?php echo htmlspecialchars($match['p2_name']); ?>:</td>
                                <td style="padding-left: 10px;"><?php echo implode(' / ', $p2_scores_str); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <span>Không có dữ liệu chi tiết về các set.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>