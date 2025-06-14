<?php
// File: partials/tabs_content/lichthidau_content.php

if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/../../includes/db_connect.php';
}

// --- TOÀN BỘ LOGIC PHP LẤY DỮ LIỆU VÀ FILTER CỦA BẠN ĐƯỢC GIỮ NGUYÊN ---

// Lấy tournament_id đầu tiên đang diễn ra hoặc sắp diễn ra làm mặc định
$default_tournament_id = 0;
$tournaments_for_select = [];
$res_tournaments = $conn->query("SELECT tournament_id, tournament_name FROM tournaments WHERE status='ongoing' OR status='upcoming' ORDER BY start_date DESC, tournament_id DESC");
if ($res_tournaments && $res_tournaments->num_rows > 0) {
    while($row_t = $res_tournaments->fetch_assoc()){
        $tournaments_for_select[] = $row_t;
    }
    if(!empty($tournaments_for_select)) $default_tournament_id = $tournaments_for_select[0]['tournament_id'];
}

$view_tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : $default_tournament_id;
$view_round = isset($_GET['round']) ? (int)$_GET['round'] : null;
$view_player_id = isset($_GET['player_id']) ? (int)$_GET['player_id'] : null;
$view_date = isset($_GET['date']) ? trim($_GET['date']) : null;

$matches = [];
$distinct_rounds = [];
$distinct_players = []; // Players involved in schedule
$distinct_dates = [];

if ($view_tournament_id > 0) {
    $sql_base = "SELECT m.match_id, m.round_number, m.scheduled_date, m.status, m.rematch_of_match_id,
                    p1.player_id as p1_id, p1.player_name as p1_name, m.player1_target_score_at_match as p1_score,
                    p2.player_id as p2_id, p2.player_name as p2_name, m.player2_target_score_at_match as p2_score
                FROM matches m
                JOIN players p1 ON m.player1_id = p1.player_id
                JOIN players p2 ON m.player2_id = p2.player_id
                WHERE m.tournament_id = ?";

    $conditions = [];
    $params = [$view_tournament_id];
    $types = "i";

    // Chỉ lấy các trận chưa hoàn thành
    $conditions[] = "m.status NOT IN ('completed', 'cancelled')";

    if ($view_round) {
        $conditions[] = "m.round_number = ?";
        $params[] = $view_round;
        $types .= "i";
    }
    if ($view_player_id) {
        $conditions[] = "(m.player1_id = ? OR m.player2_id = ?)";
        $params[] = $view_player_id;
        $params[] = $view_player_id;
        $types .= "ii";
    }
    if ($view_date) {
        $conditions[] = "m.scheduled_date = ?";
        $params[] = $view_date;
        $types .= "s";
    }

    if (!empty($conditions)) {
        $sql_base .= " AND " . implode(" AND ", $conditions);
    }
    $sql_base .= " ORDER BY m.round_number ASC, m.scheduled_date ASC, m.match_id ASC";

    $stmt = $conn->prepare($sql_base);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
        $stmt->close();
    }

    // Get distinct rounds, players, dates for filters for the selected tournament
    $res_rounds = $conn->query("SELECT DISTINCT round_number FROM matches WHERE tournament_id = $view_tournament_id AND status NOT IN ('completed', 'cancelled') ORDER BY round_number ASC");
    while($r = $res_rounds->fetch_assoc()) $distinct_rounds[] = $r['round_number'];

    $res_dates = $conn->query("SELECT DISTINCT scheduled_date FROM matches WHERE tournament_id = $view_tournament_id AND status NOT IN ('completed', 'cancelled') ORDER BY scheduled_date ASC");
    while($d = $res_dates->fetch_assoc()) $distinct_dates[] = $d['scheduled_date'];

    $res_players = $conn->query("SELECT p.player_id, p.player_name FROM players p JOIN matches m ON (p.player_id = m.player1_id OR p.player_id = m.player2_id) WHERE m.tournament_id = $view_tournament_id AND p.is_active = 1 AND m.status NOT IN ('completed', 'cancelled') GROUP BY p.player_id ORDER BY p.player_name ASC");
    while($p = $res_players->fetch_assoc()) $distinct_players[$p['player_id']] = $p['player_name'];
}
?>

<div class="tab-pane-content">
    <h2 class="mb-4">Lịch Thi Đấu</h2>

    <form method="GET" action="<?php echo SITE_URL; ?>index.php" class="mb-4 p-3 border rounded bg-light-subtle">
        <input type="hidden" name="tab" value="lichthidau">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="filter_tournament_id" class="form-label">Giải đấu:</label>
                <select name="tournament_id" id="filter_tournament_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($tournaments_for_select as $t): ?>
                        <option value="<?php echo $t['tournament_id']; ?>" <?php echo ($view_tournament_id == $t['tournament_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['tournament_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter_round" class="form-label">Vòng đấu:</label>
                <select name="round" id="filter_round" class="form-select">
                    <option value="">Tất cả các vòng</option>
                    <?php foreach ($distinct_rounds as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo ($view_round == $r) ? 'selected' : ''; ?>>Vòng <?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter_player" class="form-label">Vận động viên:</label>
                <select name="player_id" id="filter_player" class="form-select">
                    <option value="">Tất cả VĐV</option>
                    <?php foreach ($distinct_players as $pid => $pname): ?>
                        <option value="<?php echo $pid; ?>" <?php echo ($view_player_id == $pid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter_date" class="form-label">Ngày thi đấu:</label>
                <select name="date" id="filter_date" class="form-select">
                    <option value="">Tất cả các ngày</option>
                     <?php foreach ($distinct_dates as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo ($view_date == $d) ? 'selected' : ''; ?>><?php echo date('d/m/Y', strtotime($d)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12 mt-3">
                <button type="submit" class="btn btn-primary">Lọc Lịch Đấu</button>
                <a href="<?php echo SITE_URL; ?>index.php?tab=lichthidau&tournament_id=<?php echo $view_tournament_id; ?>" class="btn btn-secondary">Bỏ lọc</a>
            </div>
        </div>
    </form>

    <?php
    // PHẦN LOGIC PHÂN LOẠI CỦA BẠN ĐƯỢC GIỮ NGUYÊN
    $matches_by_round = [];
    if (!empty($matches)) {
        foreach ($matches as $match) {
            $matches_by_round[$match['round_number']][] = $match;
        }
    }
    ?>

    <?php if (empty($matches) && $view_tournament_id > 0): ?>
        <div class="alert alert-info">Không có trận đấu nào phù hợp với tiêu chí lọc hoặc chưa có lịch thi đấu cho giải này.</div>
    <?php elseif ($view_tournament_id == 0): ?>
        <div class="alert alert-info">Vui lòng chọn một giải đấu để xem lịch.</div>
    <?php else: ?>
        <?php foreach ($matches_by_round as $round_number => $round_matches): ?>
            
            <div class="round-header">
                <?php 
                if ($round_number == 0) {
                    echo "Các Trận Đấu Bù (VĐV mới)";
                } else {
                    echo "Vòng " . htmlspecialchars($round_number);
                }
                ?>
            </div>

            <div class="row g-3">
                <?php foreach ($round_matches as $match): ?>
                    <div class="col-lg-6">
                        <div class="match-card">
                            <div class="match-card-status">
                                <?php echo date('d/m', strtotime($match['scheduled_date'])); ?><br>
                                <small>
                                <?php
                                    // Hiển thị trạng thái
                                    switch ($match['status']) {
                                        case 'scheduled': echo 'Dự kiến'; break;
                                        case 'postponed': echo 'Bị hoãn'; break;
                                        case 'pending_result': echo 'Chờ kết quả'; break;
                                        default: echo htmlspecialchars($match['status']);
                                    }
                                    if ($match['rematch_of_match_id'] > 0) { echo ' (Bù)'; }
                                ?>
                                </small>
                            </div>

                            <div class="match-card-teams">
                                <div class="team-row">
                                    <div class="team-name">
                                        <?php echo htmlspecialchars($match['p1_name']); ?>
                                        <span class="target-score">(<?php echo htmlspecialchars($match['p1_score']); ?>đ)</span>
                                    </div>
                                </div>
                                <div class="team-row">
                                    <div class="team-name">
                                        <?php echo htmlspecialchars($match['p2_name']); ?>
                                        <span class="target-score">(<?php echo htmlspecialchars($match['p2_score']); ?>đ)</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="match-card-score">
                                <span>-</span>
                                <span>-</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>