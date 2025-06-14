<?php
// File: admin/players_manage.php
require_once __DIR__ . '/auth_check.php'; // Kiểm tra đăng nhập
require_once __DIR__ . '/../includes/db_connect.php'; // Kết nối DB

// ... (sau các require_once)
$allowed_tiers = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'Mới']; // Thêm hạng 'Mới' hoặc các hạng khác nếu cần

$page_title = "Quản Lý Vận Động Viên";
$current_page = "players"; // Để active link trên navbar

// Xử lý thêm/sửa VĐV
$message = ''; // Thông báo thành công hoặc lỗi
$error = ''; // Thông báo lỗi chi tiết
$edit_player = null; // Biến lưu thông tin VĐV đang sửa

// --- XỬ LÝ THÊM MỚI HOẶC CẬP NHẬT VĐV ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_name = trim($_POST['player_name'] ?? '');
    $current_target_score = filter_input(INPUT_POST, 'current_target_score', FILTER_VALIDATE_INT);
    // ... (sau khi lấy $current_target_score)
    $player_tier_label = trim($_POST['player_tier_label'] ?? '');
    $player_id_to_update = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);

    if (empty($player_name) || $current_target_score === false || $current_target_score <= 0 || empty($player_tier_label) || !in_array($player_tier_label, $allowed_tiers)) {
    $error = 'Vui lòng nhập đầy đủ tên VĐV, điểm đích hợp lệ, và chọn hạng hợp lệ.';
    } else {
        if ($player_id_to_update) { // Cập nhật VĐV
            $stmt = $conn->prepare("UPDATE players SET player_name = ?, current_target_score = ?, player_tier_label = ? WHERE player_id = ?");
            if ($stmt) {
                $stmt->bind_param("sisi", $player_name, $current_target_score, $player_tier_label, $player_id_to_update);
                if ($stmt->execute()) {
                    $message = 'Cập nhật thông tin VĐV thành công!';
                } else {
                    $error = 'Lỗi cập nhật VĐV: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Lỗi chuẩn bị câu lệnh UPDATE: ' . $conn->error;
            }
        } else { // Thêm VĐV mới
            $stmt_check = $conn->prepare("SELECT player_id FROM players WHERE player_name = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("s", $player_name);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $error = 'Tên VĐV đã tồn tại. Vui lòng chọn tên khác.';
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO players (player_name, current_target_score, player_tier_label) VALUES (?, ?, ?)");
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sis", $player_name, $current_target_score, $player_tier_label);
                    if ($stmt_insert->execute()) {
                        $new_player_id = $stmt_insert->insert_id; // Lấy ID của VĐV vừa được thêm
                        $message = 'Thêm VĐV mới thành công!';
                    
                        // ==========================================================
                        // LOGIC MỚI: TỰ ĐỘNG THÊM VĐV VÀO GIẢI ĐANG DIỄN RA
                        // ==========================================================
                        // Kiểm tra xem có giải đấu nào đang 'ongoing' không
                        $res_ongoing = $conn->query("SELECT tournament_id FROM tournaments WHERE status = 'ongoing' LIMIT 1");
                        if ($res_ongoing && $res_ongoing->num_rows > 0) {
                            $ongoing_tournament_id = $res_ongoing->fetch_assoc()['tournament_id'];
                            
                            // Gọi hàm để thêm VĐV vào lịch (hàm này sẽ được tạo ở bước 2)
                            // Hàm này sẽ trả về một mảng chứa thông báo thành công hoặc lỗi
                            $add_to_schedule_result = addPlayerToExistingSchedule($conn, $new_player_id, $ongoing_tournament_id);
                            
                            if ($add_to_schedule_result['success']) {
                                // Nối thêm thông báo thành công vào message hiện tại
                                $message .= ' ' . $add_to_schedule_result['message'];
                            } else {
                                // Nếu có lỗi, hiển thị nó
                                $error .= ' ' . $add_to_schedule_result['message'];
                            }
                        }
                        // ==========================================================
                        // KẾT THÚC LOGIC MỚI
                        // ==========================================================
                        } else {
                            $error = 'Lỗi thêm VĐV: ' . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                        $error = 'Lỗi chuẩn bị câu lệnh INSERT: ' . $conn->error;
                    }
                }
                $stmt_check->close();
            } else {
                $error = 'Lỗi chuẩn bị câu lệnh SELECT: ' . $conn->error;
            }
        }
    }
}

// --- XỬ LÝ YÊU CẦU SỬA VĐV (HIỂN THỊ FORM ĐỂ SỬA) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $player_id_to_edit = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($player_id_to_edit) {
        $stmt = $conn->prepare("SELECT player_id, player_name, current_target_score, player_tier_label FROM players WHERE player_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $player_id_to_edit);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $edit_player = $result->fetch_assoc();
            } else {
                $error = "Không tìm thấy VĐV để sửa.";
            }
            $stmt->close();
        } else {
             $error = 'Lỗi chuẩn bị câu lệnh SELECT để sửa: ' . $conn->error;
        }
    }
}

// --- XỬ LÝ YÊU CẦU XÓA VĐV ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $player_id_to_deactivate = filter_var($_GET['id'], FILTER_VALIDATE_INT); // Đổi tên biến cho rõ nghĩa
    if ($player_id_to_deactivate) {

        $conn->begin_transaction(); 

        try {
            // 1. Hủy các trận đấu CHƯA HOÀN THÀNH liên quan đến VĐV này TRƯỚC
            $cancelled_matches_count = 0;
            // Giữ nguyên $notes_for_cancelled_match nhưng có thể đổi chữ "bị xóa" thành "bị vô hiệu hóa"
            $notes_for_cancelled_match = " (VĐV ID #{$player_id_to_deactivate} đã bị vô hiệu hóa)"; 

            $stmt_cancel_matches = $conn->prepare("UPDATE matches SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), ?) WHERE (player1_id = ? OR player2_id = ?) AND status NOT IN ('completed')");
            if (!$stmt_cancel_matches) {
                throw new Exception("Lỗi chuẩn bị câu lệnh hủy trận đấu: " . $conn->error);
            }
            $stmt_cancel_matches->bind_param("sii", $notes_for_cancelled_match, $player_id_to_deactivate, $player_id_to_deactivate);

            if (!$stmt_cancel_matches->execute()) {
                throw new Exception("Lỗi khi hủy các trận đấu liên quan: " . $stmt_cancel_matches->error);
            }
            $cancelled_matches_count = $stmt_cancel_matches->affected_rows;
            $stmt_cancel_matches->close();

            // 2. Sau đó, "Vô hiệu hóa" VĐV (thay vì xóa hẳn)
            $stmt_deactivate_player = $conn->prepare("UPDATE players SET is_active = 0 WHERE player_id = ?"); // THAY ĐỔI Ở ĐÂY
            if (!$stmt_deactivate_player) {
                throw new Exception("Lỗi chuẩn bị câu lệnh vô hiệu hóa VĐV: " . $conn->error);
            }
            $stmt_deactivate_player->bind_param("i", $player_id_to_deactivate);

            if (!$stmt_deactivate_player->execute()) {
                throw new Exception("Lỗi khi vô hiệu hóa VĐV: " . $stmt_deactivate_player->error);
            }

            if ($stmt_deactivate_player->affected_rows > 0) {
                $message = "Vô hiệu hóa VĐV thành công."; // THAY ĐỔI THÔNG BÁO
                if ($cancelled_matches_count > 0) {
                    $message .= " Đồng thời, {$cancelled_matches_count} trận đấu chưa hoàn thành của VĐV này đã được cập nhật trạng thái là 'hủy'.";
                }
                $conn->commit(); 
            } else {
                $error = "Không tìm thấy VĐV để vô hiệu hóa (ID: {$player_id_to_deactivate}), hoặc VĐV đã được vô hiệu hóa."; // THAY ĐỔI THÔNG BÁO
                $conn->rollback(); 
            }
            $stmt_deactivate_player->close();

        } catch (Exception $e) {
            $conn->rollback(); 
            // Lỗi ở đây không còn là do foreign key của DELETE nữa, mà có thể là lỗi khác.
            $error = "Đã xảy ra lỗi trong quá trình xử lý: " . htmlspecialchars($e->getMessage());
        }
        // Chuyển hướng sau khi thực hiện xong (thành công hoặc thất bại)
        // Điều này giúp tránh việc người dùng F5 và thực hiện lại hành động
        $redirect_url = "players_manage.php";
        if ($message) {
            $redirect_url .= "?message=" . urlencode($message);
        } elseif ($error) {
            $redirect_url .= "?error=" . urlencode($error);
        }
        header("Location: " . $redirect_url);
        exit;
    } else {
        $error = "ID VĐV không hợp lệ để xử lý.";
        header("Location: players_manage.php?error=" . urlencode($error));
        exit;
    }
}

// Lấy message và error từ URL, LẤY DANH SÁCH VĐV, HTML... giữ nguyên
if(isset($_GET['message'])) $message = htmlspecialchars($_GET['message']);
if(isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);


// --- LẤY DANH SÁCH VĐV ĐỂ HIỂN THỊ ---
$players = [];
// Thêm cột is_active vào SELECT
$sql_select_players = "SELECT player_id, player_name, current_target_score, player_tier_label, registration_date, is_active 
                       FROM players 
                       ORDER BY is_active DESC, SUBSTRING(player_tier_label, 1, 1), CASE WHEN player_tier_label LIKE '%+' THEN 1 ELSE 2 END, current_target_score DESC, player_name ASC";
// Sắp xếp theo is_active DESC để VĐV active lên đầu
$result_select_players = $conn->query($sql_select_players);
if ($result_select_players && $result_select_players->num_rows > 0) {
    while ($row = $result_select_players->fetch_assoc()) {
        $players[] = $row;
    }
}

include __DIR__ . '/partials/header_admin.php';
?>

<div class="row">
    <div class="col-md-4">
        <h3><?php echo $edit_player ? 'Sửa Vận Động Viên' : 'Thêm Vận Động Viên Mới'; ?></h3>
        <form method="POST" action="players_manage.php">
            <?php if ($edit_player): ?>
                <input type="hidden" name="player_id" value="<?php echo htmlspecialchars($edit_player['player_id']); ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="player_name" class="form-label">Tên Vận Động Viên:</label>
                <input type="text" class="form-control" id="player_name" name="player_name" 
                       value="<?php echo htmlspecialchars($edit_player['player_name'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
            <label for="player_tier_label" class="form-label">Hạng:</label>
            <select class="form-select" id="player_tier_label" name="player_tier_label" required>
                <option value="">-- Chọn Hạng --</option>
                <?php foreach ($allowed_tiers as $tier): ?>
                    <option value="<?php echo htmlspecialchars($tier); ?>" 
                            <?php echo (isset($edit_player['player_tier_label']) && $edit_player['player_tier_label'] === $tier) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tier); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
            <div class="mb-3">
                <label for="current_target_score" class="form-label">Điểm Đích Hiện Tại (Hạng):</label>
                <input type="number" class="form-control" id="current_target_score" name="current_target_score" 
                       value="<?php echo htmlspecialchars($edit_player['current_target_score'] ?? ''); ?>" required min="1">
            </div>
            <button type="submit" class="btn btn-primary">
                <?php echo $edit_player ? 'Cập Nhật VĐV' : 'Thêm VĐV'; ?>
            </button>
            <?php if ($edit_player): ?>
                <a href="players_manage.php" class="btn btn-secondary">Hủy Sửa</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="col-md-8">
        <h3>Danh Sách Vận Động Viên</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($players)): ?>
            <p>Chưa có vận động viên nào.</p>
        <?php else: ?>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Vận Động Viên</th>
                        <th>Hạng</th>
                        <th>Điểm Đích</th>
                        <th>Ngày Đăng Ký</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($player['player_id']); ?></td>
                        <td><?php echo htmlspecialchars($player['player_name']); ?></td>
                        <td><?php echo htmlspecialchars($player['player_tier_label'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($player['current_target_score']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($player['registration_date'])); ?></td>
                        <td><?php if ($player['is_active'] == 1): ?>
                                <span class="badge bg-success">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Vô hiệu hóa</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="players_manage.php?action=edit&id=<?php echo $player['player_id']; ?>" class="btn btn-sm btn-warning">Sửa</a>
                            <?php if ($player['is_active'] == 1): ?>
                                <a href="players_manage.php?action=delete&id=<?php echo $player['player_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Bạn có chắc chắn muốn VÔ HIỆU HÓA VĐV này không? Các trận đấu chưa hoàn thành của họ sẽ bị hủy.');">Vô hiệu hóa</a>
                            <?php else: ?>
                                <span class="text-muted">Đã vô hiệu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($conn)) {
    $conn->close();
}
include __DIR__ . '/partials/footer_admin.php';
?>