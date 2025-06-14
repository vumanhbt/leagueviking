<?php
// File: admin/results_entry.php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php'; // Sẽ thêm hàm tính kết quả ở đây

$page_title = "Nhập Kết Quả Trận Đấu";
$current_page = "results";
$message = '';
$error = '';

$action = $_GET['action'] ?? 'list';
$match_id_to_edit = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;

// --- PHẦN XỬ LÝ KHI SUBMIT FORM NHẬP KẾT QUẢ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_results') {
    $match_id = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
    $p1_id = filter_input(INPUT_POST, 'p1_id', FILTER_VALIDATE_INT);
    $p2_id = filter_input(INPUT_POST, 'p2_id', FILTER_VALIDATE_INT);
    $p1_target_score = filter_input(INPUT_POST, 'p1_target_score', FILTER_VALIDATE_INT);
    $p2_target_score = filter_input(INPUT_POST, 'p2_target_score', FILTER_VALIDATE_INT);
    $sets_input = $_POST['set'] ?? [];

    if (!$match_id || !$p1_id || !$p2_id) {
        $error = "Dữ liệu trận đấu không hợp lệ.";
    } else {
        $conn->begin_transaction();
        try {
            // Xóa các set cũ của trận này để nhập lại
            $stmt_delete_sets = $conn->prepare("DELETE FROM sets WHERE match_id = ?");
            $stmt_delete_sets->bind_param("i", $match_id);
            $stmt_delete_sets->execute();
            $stmt_delete_sets->close();

            $set_outcomes = [];
            $stmt_insert_set = $conn->prepare("INSERT INTO sets (match_id, set_number, player1_score_raw, player2_score_raw) VALUES (?, ?, ?, ?)");

            // Xử lý từng set
            foreach ($sets_input as $set_number => $scores) {
                $p1_raw = isset($scores['p1_score']) && $scores['p1_score'] !== '' ? (int)$scores['p1_score'] : -1;
                $p2_raw = isset($scores['p2_score']) && $scores['p2_score'] !== '' ? (int)$scores['p2_score'] : -1;

                if ($p1_raw >= 0 && $p2_raw >= 0) { // Chỉ xử lý set có nhập điểm
                    // Lưu điểm raw vào DB
                    $stmt_insert_set->bind_param("iiii", $match_id, $set_number, $p1_raw, $p2_raw);
                    $stmt_insert_set->execute();

                    // Tính toán thắng/thua/hòa cho set này theo luật chấp điểm
                    $p1_adjusted_score = $p1_raw;
                    $p2_adjusted_score = $p2_raw;
                    $handicap = abs($p1_target_score - $p2_target_score);

                    if ($p1_target_score > $p2_target_score) {
                        $p1_adjusted_score -= $handicap;
                    } elseif ($p2_target_score > $p1_target_score) {
                        $p2_adjusted_score -= $handicap;
                    }

                    if ($p1_adjusted_score > $p2_adjusted_score) {
                        $set_outcomes[$set_number] = 'p1_wins';
                    } elseif ($p2_adjusted_score > $p1_adjusted_score) {
                        $set_outcomes[$set_number] = 'p2_wins';
                    } else {
                        $set_outcomes[$set_number] = 'draw';
                    }
                }
            }
            $stmt_insert_set->close();

            // Tính kết quả chung cuộc cho cả trận đấu
            if (!empty($set_outcomes)) {
                $match_final_result = calculateMatchResult($set_outcomes, $p1_id, $p2_id);

                // Cập nhật bảng matches
                $stmt_update_match = $conn->prepare("UPDATE matches SET winner_id = ?, is_draw = ?, player1_sets_won = ?, player2_sets_won = ?, status = 'completed', played_datetime = NOW() WHERE match_id = ?");
                $is_draw_db = $match_final_result['is_draw'] ? 1 : 0;
                $stmt_update_match->bind_param("iiiii", $match_final_result['winner_id'], $is_draw_db, $match_final_result['p1_sets_won'], $match_final_result['p2_sets_won'], $match_id);
                $stmt_update_match->execute();
                $stmt_update_match->close();
            } else {
                // Nếu không có set nào được nhập, có thể chỉ cập nhật trạng thái
                $conn->query("UPDATE matches SET status = 'pending_result' WHERE match_id = $match_id");
            }

            $conn->commit();
            $message = "Lưu kết quả cho trận đấu ID #{$match_id} thành công!";
            // Thông báo sẽ hiển thị trên trang danh sách sau khi chuyển hướng
            $redirect_url = "results_entry.php?tournament_id=" . ($_GET['tournament_id'] ?? '') . "&message=" . urlencode($message);
            header("Location: " . $redirect_url);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi khi lưu kết quả: " . $e->getMessage();
        }
    }
}
// Gán $message, $error từ GET để hiển thị
if(isset($_GET['message'])) $message = htmlspecialchars($_GET['message']);
if(isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

// Lấy danh sách giải đấu để lọc
$tournaments = [];
$res_tournaments = $conn->query("SELECT tournament_id, tournament_name FROM tournaments ORDER BY tournament_name");
while ($row = $res_tournaments->fetch_assoc()) $tournaments[] = $row;
$view_tournament_id = $_GET['tournament_id'] ?? ($tournaments[0]['tournament_id'] ?? 0);

include __DIR__ . '/partials/header_admin.php';

if ($action === 'enter' && $match_id_to_edit > 0) {
    // --- GIAO DIỆN FORM NHẬP KẾT QUẢ CHI TIẾT ---
    // Lấy thông tin chi tiết của trận đấu để nhập kết quả
    $sql_match_details = "SELECT m.*, 
                                 p1.player_name as p1_name, m.player1_target_score_at_match as p1_score,
                                 p2.player_name as p2_name, m.player2_target_score_at_match as p2_score
                           FROM matches m
                           JOIN players p1 ON m.player1_id = p1.player_id
                           JOIN players p2 ON m.player2_id = p2.player_id
                           WHERE m.match_id = ?";
    $stmt_details = $conn->prepare($sql_match_details);
    $stmt_details->bind_param("i", $match_id_to_edit);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    $match_details = $result_details->fetch_assoc();
    $stmt_details->close();

    if (!$match_details) {
        echo "<div class='alert alert-danger'>Không tìm thấy trận đấu!</div>";
    } else {
        // Lấy kết quả các set đã nhập trước đó (nếu có, để sửa)
        $existing_sets = [];
        $sql_sets = "SELECT set_number, player1_score_raw, player2_score_raw FROM sets WHERE match_id = ? ORDER BY set_number ASC";
        $stmt_sets = $conn->prepare($sql_sets);
        $stmt_sets->bind_param("i", $match_id_to_edit);
        $stmt_sets->execute();
        $result_sets = $stmt_sets->get_result();
        while($set_row = $result_sets->fetch_assoc()){
            $existing_sets[$set_row['set_number']] = $set_row;
        }
        $stmt_sets->close();
    ?>
    <a href="results_entry.php?tournament_id=<?php echo $view_tournament_id; ?>" class="btn btn-secondary mb-3">&laquo; Quay Lại Danh Sách</a>
    <h3>Nhập Kết Quả</h3>
    <h4>
        <?php echo htmlspecialchars($match_details['p1_name']); ?> (<?php echo $match_details['p1_score']; ?>đ)
        <span class="text-danger"> vs </span>
        <?php echo htmlspecialchars($match_details['p2_name']); ?> (<?php echo $match_details['p2_score']; ?>đ)
    </h4>
    <p>Vòng <?php echo $match_details['round_number']; ?> - Ngày dự kiến: <?php echo date('d/m/Y', strtotime($match_details['scheduled_date'])); ?></p>
    
    <form method="POST" action="results_entry.php?action=list&tournament_id=<?php echo $view_tournament_id; // Để quay về đúng trang danh sách sau khi lưu ?>">
        <input type="hidden" name="action" value="save_results">
        <input type="hidden" name="match_id" value="<?php echo $match_details['match_id']; ?>">
        <input type="hidden" name="p1_id" value="<?php echo $match_details['player1_id']; ?>">
        <input type="hidden" name="p1_target_score" value="<?php echo $match_details['player1_target_score_at_match']; ?>">
        <input type="hidden" name="p2_id" value="<?php echo $match_details['player2_id']; ?>">
        <input type="hidden" name="p2_target_score" value="<?php echo $match_details['player2_target_score_at_match']; ?>">
        
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Set</th>
                    <th>Điểm của <?php echo htmlspecialchars($match_details['p1_name']); ?></th>
                    <th>Điểm của <?php echo htmlspecialchars($match_details['p2_name']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <tr>
                    <td><strong>Set <?php echo $i; ?></strong></td>
                    <td>
                        <input type="number" name="set[<?php echo $i; ?>][p1_score]" class="form-control" 
                               value="<?php echo htmlspecialchars($existing_sets[$i]['player1_score_raw'] ?? ''); ?>" min="0" 
                               placeholder="Điểm P1 - Set <?php echo $i; ?>">
                    </td>
                    <td>
                        <input type="number" name="set[<?php echo $i; ?>][p2_score]" class="form-control" 
                               value="<?php echo htmlspecialchars($existing_sets[$i]['player2_score_raw'] ?? ''); ?>" min="0"
                               placeholder="Điểm P2 - Set <?php echo $i; ?>">
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <p class="form-text text-muted">
            Lưu ý: Chỉ cần nhập điểm cho các set đã diễn ra. Nếu trận đấu kết thúc sau 2 set, bỏ trống set 3.
            Hệ thống sẽ tự động tính toán thắng/thua/hòa cho từng set và cho cả trận đấu dựa trên luật chấp điểm.
        </p>
        <button type="submit" class="btn btn-success btn-lg">Lưu Kết Quả</button>
    </form>
    <?php } // Kết thúc if $match_details
} else {
    // --- GIAO DIỆN DANH SÁCH CÁC TRẬN ĐẤU CẦN NHẬP KẾT QUẢ ---
    $matches_to_enter = [];
    $sql = "SELECT m.match_id, m.round_number, m.scheduled_date, 
                    p1.player_name as p1_name, p2.player_name as p2_name
            FROM matches m
            JOIN players p1 ON m.player1_id = p1.player_id
            JOIN players p2 ON m.player2_id = p2.player_id
            WHERE m.tournament_id = ? AND m.status IN ('scheduled', 'pending_result', 'postponed')
            ORDER BY m.scheduled_date ASC, m.round_number ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $view_tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $matches_to_enter[] = $row;
    }
    $stmt->close();
?>
    <h4>Chọn Trận Đấu để Nhập/Sửa Kết Quả</h4>

    <form method="GET" action="results_entry.php" class="mb-3">
        <input type="hidden" name="action" value="list">
        <div class="input-group">
            <select name="tournament_id" class="form-select">
                <?php foreach ($tournaments as $t): ?>
                    <option value="<?php echo $t['tournament_id']; ?>" <?php echo ($view_tournament_id == $t['tournament_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['tournament_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary" type="submit">Xem</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Vòng</th>
                    <th>Cặp Đấu</th>
                    <th>Ngày Dự Kiến</th>
                    <th>Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matches_to_enter)): ?>
                    <tr><td colspan="4" class="text-center">Không có trận đấu nào đang chờ nhập kết quả cho giải này.</td></tr>
                <?php else: ?>
                    <?php foreach ($matches_to_enter as $match): ?>
                    <tr>
                        <td><?php echo $match['round_number']; ?></td>
                        <td><?php echo htmlspecialchars($match['p1_name']) . ' vs ' . htmlspecialchars($match['p2_name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($match['scheduled_date'])); ?></td>
                        <td>
                            <a href="results_entry.php?action=enter&match_id=<?php echo $match['match_id']; ?>&tournament_id=<?php echo $view_tournament_id; ?>" class="btn btn-primary btn-sm">
                                Nhập Kết Quả
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
} // kết thúc else của $action === 'enter'

include __DIR__ . '/partials/footer_admin.php';
?>