<?php
// File: admin/matches_manage.php

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = "Quản Lý Lịch Thi Đấu";
$current_page = "matches";

$message = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$match_status_map = [
    'scheduled'      => 'Chưa diễn ra',
    'postponed'      => 'Bị hoãn (Postp.)',
    'pending_result' => 'Chờ kết quả',
    'completed'      => 'Đã hoàn thành',
    'cancelled'      => 'Bị hủy'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Lỗi xác thực bảo mật (CSRF token không hợp lệ). Vui lòng tải lại trang và thử lại.";
    } 
    elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
        switch ($action) {
            case 'generate_schedule':
                $tournament_id = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);
                $start_date_str = trim($_POST['start_date'] ?? '');
                $matches_per_day_option = trim($_POST['matches_per_day_option'] ?? 'unlimited');
                $max_matches_per_day = filter_input(INPUT_POST, 'max_matches_per_day', FILTER_VALIDATE_INT);
                $overwrite_existing = isset($_POST['overwrite_existing']);
                if (!$tournament_id) {
                    $error = "Vui lòng chọn giải đấu.";
                } elseif (empty($start_date_str)) {
                    $error = "Vui lòng nhập ngày bắt đầu.";
                } else {
                    $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date_str);
                    if (!$start_date_obj || $start_date_obj->format('Y-m-d') !== $start_date_str) {
                        $error = "Ngày bắt đầu không hợp lệ. Định dạng YYYY-MM-DD.";
                    } else {
                        $limit_matches = ($matches_per_day_option === 'limited' && $max_matches_per_day > 0) ? $max_matches_per_day : 0;
                        $generation_result = generateRoundRobinSchedule($conn, $tournament_id, $start_date_str, [3, 6], $limit_matches, $overwrite_existing);
                        if ($generation_result['success']) {
                            $message = $generation_result['message'];
                        } else {
                            $error = $generation_result['message'];
                        }
                    }
                }
                break;
            case 'delete_schedule':
                $tournament_id_to_delete = filter_input(INPUT_POST, 'tournament_id_delete', FILTER_VALIDATE_INT);
                if ($tournament_id_to_delete) {
                    $stmt_delete = $conn->prepare("DELETE FROM matches WHERE tournament_id = ?");
                    if ($stmt_delete) {
                        $stmt_delete->bind_param("i", $tournament_id_to_delete);
                        if ($stmt_delete->execute()) {
                            $message = "Đã xóa toàn bộ lịch thi đấu cho giải đấu ID #{$tournament_id_to_delete}.";
                        } else {
                            $error = "Lỗi xóa lịch thi đấu: " . $stmt_delete->error;
                        }
                        $stmt_delete->close();
                    } else {
                        $error = "Lỗi chuẩn bị câu lệnh DELETE: " . $conn->error;
                    }
                } else {
                    $error = "Vui lòng chọn giải đấu để xóa lịch.";
                }
                break;
            case 'update_match':
                $match_id_update = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
                $new_scheduled_date = trim($_POST['scheduled_date'] ?? '');
                $new_status = trim($_POST['status'] ?? '');
                $new_round_number = filter_input(INPUT_POST, 'round_number', FILTER_VALIDATE_INT);
                if (!$match_id_update || empty($new_scheduled_date) || empty($new_status) || $new_round_number === false) {
                    $error = "Dữ liệu cập nhật trận đấu không hợp lệ.";
                } else {
                    $date_obj = DateTime::createFromFormat('Y-m-d', $new_scheduled_date);
                    if (!$date_obj || $date_obj->format('Y-m-d') !== $new_scheduled_date) {
                        $error = "Ngày thi đấu mới không hợp lệ.";
                    } elseif (!array_key_exists($new_status, $match_status_map)) {
                         $error = "Trạng thái không hợp lệ.";
                    } else {
                        $stmt = $conn->prepare("UPDATE matches SET scheduled_date = ?, status = ?, round_number = ? WHERE match_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("ssii", $new_scheduled_date, $new_status, $new_round_number, $match_id_update);
                            if ($stmt->execute()) {
                                $message = "Cập nhật trận đấu ID #{$match_id_update} thành công!";
                            } else {
                                $error = "Lỗi cập nhật trận đấu: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $error = "Lỗi chuẩn bị câu lệnh UPDATE: " . $conn->error;
                        }
                    }
                }
                break;
            case 'confirm_create_rematch':
                $original_match_id = filter_input(INPUT_POST, 'original_match_id', FILTER_VALIDATE_INT);
                $rematch_date = trim($_POST['rematch_date'] ?? '');
                if ($original_match_id && !empty($rematch_date)) {
                    $conn->begin_transaction();
                    try {
                        $stmt_get = $conn->prepare("SELECT * FROM matches WHERE match_id = ?");
                        $stmt_get->bind_param("i", $original_match_id);
                        $stmt_get->execute();
                        $original_match_data = $stmt_get->get_result()->fetch_assoc();
                        $stmt_get->close();

                        if (!$original_match_data) { throw new Exception("Không tìm thấy trận đấu gốc."); }

                        $stmt_update_orig = $conn->prepare("UPDATE matches SET status = 'postponed', notes = CONCAT(IFNULL(notes, ''), ' (Đã tạo trận đấu bù)') WHERE match_id = ?");
                        $stmt_update_orig->bind_param("i", $original_match_id);
                        $stmt_update_orig->execute();
                        $stmt_update_orig->close();

                        $sql_insert_rematch = "INSERT INTO matches (tournament_id, round_number, player1_id, player2_id, player1_target_score_at_match, player2_target_score_at_match, scheduled_date, status, rematch_of_match_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)";
                        $stmt_insert_new = $conn->prepare($sql_insert_rematch);
                        $stmt_insert_new->bind_param("iiiiissi",
                            $original_match_data['tournament_id'], $original_match_data['round_number'],
                            $original_match_data['player1_id'], $original_match_data['player2_id'],
                            $original_match_data['player1_target_score_at_match'], $original_match_data['player2_target_score_at_match'],
                            $rematch_date, $original_match_id
                        );
                        $stmt_insert_new->execute();
                        $stmt_insert_new->close();
                        $conn->commit();
                        $message = "Đã tạo trận đấu bù thành công!";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Lỗi khi tạo trận đấu bù: " . $e->getMessage();
                    }
                } else {
                    $error = "Thiếu dữ liệu để tạo trận đấu bù.";
                }
                break;
        }
    }
}

$tournaments = [];
$result_tournaments = $conn->query("SELECT tournament_id, tournament_name FROM tournaments WHERE status = 'ongoing' OR status = 'upcoming' ORDER BY tournament_name");
if ($result_tournaments) {
    while ($row = $result_tournaments->fetch_assoc()) {
        $tournaments[] = $row;
    }
}

$selected_tournament_id_view = 0;
if (isset($_GET['view_tournament_id'])) {
    $selected_tournament_id_view = filter_var($_GET['view_tournament_id'], FILTER_VALIDATE_INT);
} elseif (!empty($tournaments)) {
    $selected_tournament_id_view = $tournaments[0]['tournament_id'];
}

$matches = [];
if ($selected_tournament_id_view > 0) {
    $sql_matches = "SELECT m.match_id, m.round_number, m.scheduled_date, m.status,
                           m.player1_target_score_at_match as p1_score,
                           m.player2_target_score_at_match as p2_score,
                           p1.player_name as p1_name,
                           p2.player_name as p2_name
                    FROM matches m
                    JOIN players p1 ON m.player1_id = p1.player_id
                    JOIN players p2 ON m.player2_id = p2.player_id
                    WHERE m.tournament_id = ?
                    ORDER BY m.round_number ASC, m.scheduled_date ASC, m.match_id ASC";
    $stmt_matches = $conn->prepare($sql_matches);
    if ($stmt_matches) {
        $stmt_matches->bind_param("i", $selected_tournament_id_view);
        $stmt_matches->execute();
        $result_matches = $stmt_matches->get_result();
        while ($row = $result_matches->fetch_assoc()) {
            $matches[] = $row;
        }
        $stmt_matches->close();
    }
}

$edit_match_details = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_match' && isset($_GET['match_id'])) {
    $match_id_to_edit = filter_var($_GET['match_id'], FILTER_VALIDATE_INT);
    if ($match_id_to_edit) {
        foreach ($matches as $m) {
            if ($m['match_id'] == $match_id_to_edit) {
                $edit_match_details = $m;
                break;
            }
        }
        if (!$edit_match_details) $error = "Không tìm thấy trận đấu để sửa hoặc trận đấu không thuộc giải đang xem.";
    }
}

// Lấy thông tin cho các action đặc biệt từ GET
$action_get = $_GET['action'] ?? '';
$original_match_id_get = isset($_GET['original_match_id']) ? (int)$_GET['original_match_id'] : 0;
$is_creating_rematch = ($action_get === 'create_rematch' && $original_match_id_get > 0);
$is_editing_match = ($edit_match_details !== null);
$column_class_ratio = ($is_editing_match || $is_creating_rematch) ? '6' : '5';

include __DIR__ . '/partials/header_admin.php';
?>

<div class="row mb-4">
    <div class="col-md-<?php echo $column_class_ratio; ?>">

        <?php // --- HIỂN THỊ FORM SỬA TRẬN ĐẤU ---
        if ($is_editing_match): ?>
            <h4>Chỉnh Sửa Trận Đấu</h4>
            <form method="POST" action="matches_manage.php?view_tournament_id=<?php echo $selected_tournament_id_view; ?>">
                <input type="hidden" name="action" value="update_match">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="match_id" value="<?php echo $edit_match_details['match_id']; ?>">
                <p><strong>Trận đấu:</strong> <?php echo htmlspecialchars($edit_match_details['p1_name']) . " (" . htmlspecialchars($edit_match_details['p1_score']) . "đ) vs " . htmlspecialchars($edit_match_details['p2_name']) . " (" . htmlspecialchars($edit_match_details['p2_score']) . "đ)"; ?></p>
                <div class="mb-3">
                    <label for="edit_round_number" class="form-label">Vòng đấu:</label>
                    <input type="number" name="round_number" id="edit_round_number" class="form-control" value="<?php echo htmlspecialchars($edit_match_details['round_number']); ?>" required min="1">
                </div>
                <div class="mb-3">
                    <label for="edit_scheduled_date" class="form-label">Ngày Thi Đấu Mới:</label>
                    <input type="date" name="scheduled_date" id="edit_scheduled_date" class="form-control" value="<?php echo htmlspecialchars($edit_match_details['scheduled_date']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="edit_status" class="form-label">Trạng Thái:</label>
                    <select name="status" id="edit_status" class="form-select" required>
                        <?php foreach ($match_status_map as $val => $text): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($edit_match_details['status'] == $val) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($text); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Cập Nhật Trận Đấu</button>
                <a href="matches_manage.php?view_tournament_id=<?php echo $selected_tournament_id_view; ?>" class="btn btn-secondary">Hủy</a>
            </form>

        <?php // --- HIỂN THỊ FORM TẠO ĐẤU BÙ ---
        elseif ($is_creating_rematch): 
            $sql_orig_match = "SELECT m.*, p1.player_name as p1_name, p2.player_name as p2_name FROM matches m JOIN players p1 ON m.player1_id = p1.player_id JOIN players p2 ON m.player2_id = p2.player_id WHERE m.match_id = ?";
            $stmt_orig = $conn->prepare($sql_orig_match);
            $stmt_orig->bind_param("i", $original_match_id_get);
            $stmt_orig->execute();
            $original_match = $stmt_orig->get_result()->fetch_assoc();
            $stmt_orig->close();
        ?>
            <h4>Tạo Trận Đấu Bù</h4>
            <div class="p-3 border rounded bg-light">
                <p>Hành động này sẽ đánh dấu trận đấu gốc là "Bị hoãn" và tạo một trận đấu mới với ngày bạn chọn.</p>
                <?php if ($original_match): ?>
                    <p><strong>Trận đấu gốc:</strong> Vòng <?php echo $original_match['round_number']; ?> - <?php echo htmlspecialchars($original_match['p1_name'] . ' vs ' . $original_match['p2_name']); ?></p>
                    <p><strong>Ngày dự kiến cũ:</strong> <?php echo date('d/m/Y', strtotime($original_match['scheduled_date'])); ?></p>
                    
                    <form method="POST" action="matches_manage.php?view_tournament_id=<?php echo $_GET['view_tournament_id'] ?? $selected_tournament_id_view; ?>">
                        <input type="hidden" name="action" value="confirm_create_rematch">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="original_match_id" value="<?php echo $original_match_id_get; ?>">
                        <div class="mb-3">
                            <label for="rematch_date" class="form-label"><b>Ngày thi đấu bù mới:</b></label>
                            <input type="date" name="rematch_date" id="rematch_date" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Xác Nhận Tạo Đấu Bù</button>
                        <a href="matches_manage.php?view_tournament_id=<?php echo $_GET['view_tournament_id'] ?? $selected_tournament_id_view; ?>" class="btn btn-secondary">Hủy</a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">Không tìm thấy thông tin trận đấu gốc.</div>
                <?php endif; ?>
            </div>
        
        <?php // --- HIỂN THỊ CÁC FORM MẶC ĐỊNH ---
        else: ?>
            <h4>Tạo Lịch Thi Đấu Mới</h4>
            <form method="POST" action="matches_manage.php" id="generateScheduleForm">
                <input type="hidden" name="action" value="generate_schedule">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="mb-3">
                    <label for="tournament_id" class="form-label">Chọn Giải Đấu:</label>
                    <select name="tournament_id" id="tournament_id" class="form-select" required>
                        <option value="">-- Chọn Giải Đấu --</option>
                        <?php foreach ($tournaments as $tournament): ?>
                        <option value="<?php echo $tournament['tournament_id']; ?>" <?php if($tournament['tournament_id'] == $selected_tournament_id_view) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($tournament['tournament_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="start_date" class="form-label">Ngày Bắt Đầu Dự Kiến:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    <small class="form-text text-muted">Mặc định các trận sẽ được xếp vào Thứ 4 & Thứ 7.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Số trận mỗi ngày thi đấu:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="matches_per_day_option" id="matches_unlimited" value="unlimited" checked>
                        <label class="form-check-label" for="matches_unlimited">Không giới hạn</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="matches_per_day_option" id="matches_limited" value="limited">
                        <label class="form-check-label" for="matches_limited">Giới hạn:</label>
                        <input type="number" name="max_matches_per_day" id="max_matches_per_day" class="form-control form-control-sm d-inline-block" style="width: 80px;" min="1" value="2" disabled>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="overwrite_existing" id="overwrite_existing" class="form-check-input">
                    <label for="overwrite_existing" class="form-check-label">Xóa lịch cũ (nếu có) và tạo lại?</label>
                </div>
                <button type="submit" class="btn btn-primary">Tạo Lịch Thi Đấu</button>
            </form>
            
            <hr class="my-4">
            <h4>Xóa Lịch Thi Đấu</h4>
            <form method="POST" action="matches_manage.php" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA TOÀN BỘ lịch thi đấu cho giải đã chọn? Hành động này không thể hoàn tác!');">
                <input type="hidden" name="action" value="delete_schedule">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="mb-3">
                    <label for="tournament_id_delete" class="form-label">Chọn Giải Đấu để Xóa Lịch:</label>
                    <select name="tournament_id_delete" id="tournament_id_delete" class="form-select" required>
                        <option value="">-- Chọn Giải Đấu --</option>
                        <?php foreach ($tournaments as $tournament): ?>
                        <option value="<?php echo $tournament['tournament_id']; ?>">
                            <?php echo htmlspecialchars($tournament['tournament_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger">Xóa Toàn Bộ Lịch Giải Này</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-md-<?php echo ($column_class_ratio == '6' ? '6' : '7'); ?>">
        <h4>Danh Sách Trận Đấu</h4>
        <form method="GET" action="matches_manage.php" class="mb-3">
            <div class="input-group">
                <select name="view_tournament_id" class="form-select">
                    <option value="">-- Xem lịch cho giải đấu --</option>
                    <?php foreach ($tournaments as $tournament): ?>
                    <option value="<?php echo $tournament['tournament_id']; ?>" <?php echo ($selected_tournament_id_view == $tournament['tournament_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tournament['tournament_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-secondary" type="submit">Xem</button>
            </div>
        </form>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if (empty($matches) && $selected_tournament_id_view > 0): ?>
            <p>Chưa có lịch thi đấu nào được tạo cho giải đấu này.</p>
        <?php elseif ($selected_tournament_id_view == 0): ?>
            <p>Vui lòng chọn một giải đấu để xem lịch thi đấu.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Vòng</th>
                        <th>Cặp Đấu (Điểm mục tiêu)</th>
                        <th>Ngày Dự Kiến</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                        <th>ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($match['round_number']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($match['p1_name']); ?> (<?php echo htmlspecialchars($match['p1_score']); ?>đ)
                            <br>vs<br>
                            <?php echo htmlspecialchars($match['p2_name']); ?> (<?php echo htmlspecialchars($match['p2_score']); ?>đ)
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($match['scheduled_date'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($match_status_map[$match['status']] ?? $match['status']); ?>
                        </td>
                        <td>
                            <a href="matches_manage.php?action=edit_match&match_id=<?php echo $match['match_id']; ?>&view_tournament_id=<?php echo $selected_tournament_id_view; ?>" class="btn btn-sm btn-warning mb-1">Sửa</a>
                            <?php if ($match['status'] === 'scheduled' || $match['status'] === 'postponed'): ?>
                            <a href="matches_manage.php?action=create_rematch&original_match_id=<?php echo $match['match_id']; ?>&view_tournament_id=<?php echo $selected_tournament_id_view; ?>" class="btn btn-sm btn-info mb-1">Tạo Đấu Bù</a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $match['match_id']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const matchesLimitedRadio = document.getElementById('matches_limited');
    const matchesUnlimitedRadio = document.getElementById('matches_unlimited');
    const maxMatchesInput = document.getElementById('max_matches_per_day');
    function toggleMaxMatchesInput() {
        if(maxMatchesInput){
             maxMatchesInput.disabled = !matchesLimitedRadio.checked;
        }
    }
    if(matchesLimitedRadio && matchesUnlimitedRadio){
        matchesLimitedRadio.addEventListener('change', toggleMaxMatchesInput);
        matchesUnlimitedRadio.addEventListener('change', toggleMaxMatchesInput);
        toggleMaxMatchesInput();
    }
});
</script>

<?php
include __DIR__ . '/partials/footer_admin.php';
?>