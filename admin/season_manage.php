<?php
// File: admin/season_manage.php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = "Quản Lý Mùa Giải";
$current_page = "seasons"; // Đặt tên mới cho trang này trong navbar

$message = '';
$error = '';

// --- PHẦN 1: XỬ LÝ CÁC HÀNH ĐỘNG POST TỪ FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- Hành động: Tạo giải đấu mới ---
    if ($_POST['action'] === 'create_tournament') {
        $new_name = trim($_POST['new_tournament_name'] ?? '');
        $new_date = trim($_POST['new_start_date'] ?? null);

        if (!empty($new_name)) {
            $start_date_to_db = !empty($new_date) ? $new_date : null;
            $stmt = $conn->prepare("INSERT INTO tournaments (tournament_name, start_date, status) VALUES (?, ?, 'upcoming')");
            $stmt->bind_param("ss", $new_name, $start_date_to_db);
            if ($stmt->execute()) {
                $message = "Tạo giải đấu mới '{$new_name}' thành công!";
            } else {
                $error = "Lỗi khi tạo giải đấu mới: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Vui lòng nhập tên cho giải đấu mới.";
        }
    }
    // --- Hành động: Xác nhận kết thúc giải đấu ---
    elseif ($_POST['action'] === 'confirm_complete_tournament') {
        $t_id_to_complete = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);
        if ($t_id_to_complete) {
            $sql_check = "SELECT COUNT(*) as count FROM matches WHERE tournament_id = ? AND status NOT IN ('completed', 'cancelled')";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("i", $t_id_to_complete);
            $stmt_check->execute();
            $pending_matches_count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_check->close();

            if ($pending_matches_count > 0) {
                $error = "Lỗi: Vẫn còn trận đấu chưa hoàn thành.";
            } else {
                $stmt_update = $conn->prepare("UPDATE tournaments SET status = 'completed' WHERE tournament_id = ?");
                $stmt_update->bind_param("i", $t_id_to_complete);
                if ($stmt_update->execute()) {
                    $message = "Đã kết thúc giải đấu thành công!";
                } else {
                    $error = "Lỗi khi cập nhật trạng thái giải đấu.";
                }
                $stmt_update->close();
            }
        }
    }
    // --- Hành động: Áp dụng tăng/giảm điểm ---
    elseif ($_POST['action'] === 'apply_adjustments') {
        $t_id = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);
        $adjustment_value = filter_input(INPUT_POST, 'adjustment_value', FILTER_VALIDATE_INT);
        $increase_ids = $_POST['increase_ids'] ?? [];
        $decrease_ids = $_POST['decrease_ids'] ?? [];

        if ($t_id && $adjustment_value) {
            $conn->begin_transaction();
            try {
                // Tăng điểm
                if (!empty($increase_ids)) {
                    $sql_increase = "UPDATE players SET current_target_score = current_target_score + ? WHERE player_id IN (". implode(',', array_fill(0, count($increase_ids), '?')) .")";
                    $stmt_inc = $conn->prepare($sql_increase);
                    $types = 'i' . str_repeat('i', count($increase_ids));
                    $params = array_merge([$adjustment_value], $increase_ids);
                    $stmt_inc->bind_param($types, ...$params);
                    $stmt_inc->execute();
                    $stmt_inc->close();
                }
                // Giảm điểm
                if (!empty($decrease_ids)) {
                    $sql_decrease = "UPDATE players SET current_target_score = current_target_score - ? WHERE player_id IN (". implode(',', array_fill(0, count($decrease_ids), '?')) .")";
                    $stmt_dec = $conn->prepare($sql_decrease);
                    $types = 'i' . str_repeat('i', count($decrease_ids));
                    $params = array_merge([$adjustment_value], $decrease_ids);
                    $stmt_dec->bind_param($types, ...$params);
                    $stmt_dec->execute();
                    $stmt_dec->close();
                }
                // Đánh dấu giải đấu đã áp dụng tăng/giảm hạng
                $conn->query("UPDATE tournaments SET promotions_applied = 1 WHERE tournament_id = $t_id");
                
                $conn->commit();
                $message = "Đã cập nhật điểm đích cho các VĐV thành công!";

            } catch (Exception $e) {
                $conn->rollback();
                $error = "Lỗi khi cập nhật điểm: " . $e->getMessage();
            }
        } else {
            $error = "Thiếu dữ liệu để cập nhật điểm.";
        }
    }
} // Kết thúc khối xử lý POST

// --- PHẦN 2: LẤY DỮ LIỆU ĐỂ HIỂN THỊ ---
// Lấy danh sách tất cả các giải đấu
$tournaments = [];
$result_tournaments = $conn->query("SELECT * FROM tournaments ORDER BY start_date DESC, tournament_id DESC");
while ($row = $result_tournaments->fetch_assoc()) {
    $tournaments[] = $row;
}

// Bắt đầu Ouput HTML
include __DIR__ . '/partials/header_admin.php';
?>

<div class="row">
    <div class="col-md-8">
        <h3>Danh Sách Các Mùa Giải</h3>
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Tên Giải Đấu</th>
                        <th>Ngày Bắt Đầu</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tournaments)): ?>
                        <tr><td colspan="5" class="text-center">Chưa có giải đấu nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tournaments as $t): ?>
                        <tr>
                            <td><?php echo $t['tournament_id']; ?></td>
                            <td><?php echo htmlspecialchars($t['tournament_name']); ?></td>
                            <td><?php echo $t['start_date'] ? date('d/m/Y', strtotime($t['start_date'])) : 'N/A'; ?></td>
                            <td>
                                <?php 
                                    if ($t['status'] === 'ongoing') echo '<span class="badge bg-primary">Đang diễn ra</span>';
                                    elseif ($t['status'] === 'completed') echo '<span class="badge bg-success">Đã kết thúc</span>';
                                    elseif ($t['status'] === 'upcoming') echo '<span class="badge bg-info">Sắp diễn ra</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($t['status'] === 'ongoing'): ?>
                                    <a href="season_manage.php?action=view_complete&id=<?php echo $t['tournament_id']; ?>" class="btn btn-sm btn-danger">Kết thúc giải</a>
                                <?php elseif ($t['status'] === 'completed' && $t['promotions_applied'] == 0): ?>
                                    <a href="season_manage.php?action=view_prepare&id=<?php echo $t['tournament_id']; ?>" class="btn btn-sm btn-warning">Chuẩn bị mùa sau</a>
                                <?php elseif ($t['status'] === 'completed' && $t['promotions_applied'] == 1): ?>
                                    <span class="text-muted fst-italic">Đã xử lý</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Tạo Mùa Giải Mới</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="season_manage.php">
                    <input type="hidden" name="action" value="create_tournament">
                    <div class="mb-3">
                        <label for="new_tournament_name" class="form-label">Tên Giải Đấu:</label>
                        <input type="text" name="new_tournament_name" id="new_tournament_name" class="form-control" placeholder="Ví dụ: Giải GMN Mùa 2" required>
                    </div>
                     <div class="mb-3">
                        <label for="new_start_date" class="form-label">Ngày Bắt Đầu:</label>
                        <input type="date" name="new_start_date" id="new_start_date" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Tạo Mới</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// --- PHẦN 4: HIỂN THỊ CÁC KHUNG HÀNH ĐỘNG (DỰA TRÊN THAM SỐ GET) ---
$action = $_GET['action'] ?? '';
$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tournament_id > 0) {
    // --- Giao diện: Xác nhận kết thúc giải ---
    if ($action === 'view_complete') {
        $t_name = '';
        $res_t = $conn->query("SELECT tournament_name FROM tournaments WHERE tournament_id = $tournament_id");
        if ($res_t) $t_name = $res_t->fetch_assoc()['tournament_name'] ?? '';

        $pending_matches_count = 0;
        $sql_check = "SELECT COUNT(*) as count FROM matches WHERE tournament_id = ? AND status NOT IN ('completed', 'cancelled')";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $tournament_id);
        $stmt_check->execute();
        $pending_matches_count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt_check->close();
?>
    <div class="mt-5 p-4 border rounded bg-light">
        <h4>Xác Nhận Kết Thúc Giải Đấu: "<?php echo htmlspecialchars($t_name); ?>"</h4>
        <?php if ($pending_matches_count > 0): ?>
            <div class="alert alert-danger">
                <strong>Không thể kết thúc!</strong> Vẫn còn <strong><?php echo $pending_matches_count; ?></strong> trận đấu chưa hoàn thành.
                Vui lòng vào trang "Nhập Kết Quả" để xử lý các trận đấu còn lại trước khi kết thúc giải.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                Hành động này sẽ "khóa" giải đấu và Bảng Xếp Hạng cuối cùng. Bạn có chắc chắn?
            </div>
            <form method="POST" action="season_manage.php">
                <input type="hidden" name="action" value="confirm_complete_tournament">
                <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                <button type="submit" class="btn btn-danger">Vâng, Tôi chắc chắn & Kết thúc giải</button>
                <a href="season_manage.php" class="btn btn-secondary">Hủy</a>
            </form>
        <?php endif; ?>
    </div>
<?php
    // --- Giao diện: Chuẩn bị mùa sau ---
    } elseif ($action === 'view_prepare') {
        $tournament_info = $conn->query("SELECT tournament_name, promotions_applied FROM tournaments WHERE tournament_id = $tournament_id")->fetch_assoc();

        if ($tournament_info && $tournament_info['promotions_applied'] == 1) {
            echo '<div class="alert alert-info mt-5">Việc tăng/giảm điểm cho mùa giải này đã được thực hiện trước đó.</div>';
        } elseif ($tournament_info) {
            $standings = getTournamentStandings($conn, $tournament_id);
            $total_players = count($standings);
            $top_3_players = array_slice($standings, 0, 3);
            $bottom_3_players = ($total_players > 6) ? array_slice($standings, -3, 3) : [];
?>
    <div class="mt-5 p-4 border rounded bg-light">
        <h4>Chuẩn Bị Mùa Sau cho Giải: "<?php echo htmlspecialchars($tournament_info['tournament_name']); ?>"</h4>
        <p>Hệ thống đề xuất tăng/giảm điểm cho các VĐV Top đầu và cuối bảng. Vui lòng xác nhận để cập nhật điểm đích cho mùa giải tiếp theo.</p>
        
        <form method="POST" action="season_manage.php">
            <input type="hidden" name="action" value="apply_adjustments">
            <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
            
            <div class="mb-3">
                <label for="adjustment_value" class="form-label"><b>Mức điểm tăng/giảm:</b></label>
                <input type="number" name="adjustment_value" id="adjustment_value" class="form-control" style="width: 100px;" value="10" required>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Top 3 (Đề xuất Tăng điểm)</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <?php foreach ($top_3_players as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['player_name']); ?></td>
                                <td><?php echo $p['PTS']; ?> PTS</td>
                                <td><input type="checkbox" name="increase_ids[]" value="<?php echo $p['player_id']; ?>" class="form-check-input" checked> Tăng điểm</td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php if (!empty($bottom_3_players)): ?>
                <div class="col-md-6">
                    <h5>Cuối bảng 3 (Đề xuất Giảm điểm)</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <?php foreach ($bottom_3_players as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['player_name']); ?></td>
                                <td><?php echo $p['PTS']; ?> PTS</td>
                                <td><input type="checkbox" name="decrease_ids[]" value="<?php echo $p['player_id']; ?>" class="form-check-input" checked> Giảm điểm</td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-warning" onclick="return confirm('Bạn có chắc chắn muốn cập nhật điểm đích của các VĐV đã chọn không?')">Xác Nhận Cập Nhật Điểm</button>
            <a href="season_manage.php" class="btn btn-secondary">Hủy</a>
        </form>
    </div>
<?php
        } // end else of promotions_applied check
    } // end elseif view_prepare
} // end if $tournament_id > 0
?>

<?php
include __DIR__ . '/partials/footer_admin.php';
?>