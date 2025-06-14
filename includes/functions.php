<?php
// File: includes/functions.php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Luôn bắt đầu session ở đầu các file cần dùng session
}

define('ADMIN_USERNAME', 'viking'); // Đặt username admin của bạn
define('ADMIN_PASSWORD', '123Robmeveu!'); // Đặt password admin của bạn - HÃY ĐỔI THÀNH MẬT KHẨU MẠNH!

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        // Nếu chưa đăng nhập, chuyển hướng về trang login
        // Đảm bảo SITE_URL đã được định nghĩa trong config.php
        if (defined('SITE_URL')) {
            header('Location: ' . SITE_URL . 'admin/login.php');
        } else {
            // Fallback nếu SITE_URL không có (nên kiểm tra config)
            header('Location: login.php');
        }
        exit;
    }
}

/**
 * Tạo lịch thi đấu vòng tròn một lượt cho một giải đấu.
 * Các cặp đấu được tạo ngẫu nhiên bằng cách xáo trộn danh sách VĐV trước khi áp dụng thuật toán.
 * Ngày thi đấu được xếp vào các ngày trong tuần chỉ định (mặc định Thứ 4, Thứ 7).
 *
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param int $tournament_id ID của giải đấu.
 * @param string $start_date_str Ngày bắt đầu giải đấu (YYYY-MM-DD).
 * @param array $match_days_of_week Mảng các ngày trong tuần cho phép có trận đấu (1=Thứ 2, ..., 7=Chủ Nhật. Mặc định [3,6] cho T4, T7).
 * @param int $max_matches_per_day Số trận tối đa mỗi ngày thi đấu (0 = không giới hạn).
 * @param bool $overwrite_existing True để xóa lịch cũ của giải này và tạo lại, False để báo lỗi nếu đã có lịch.
 * @return array ['success' => bool, 'message' => string]
 */
function generateRoundRobinSchedule($conn, $tournament_id, $start_date_str, $match_days_of_week = [3, 6], $max_matches_per_day = 0, $overwrite_existing = false) {
    // Lấy danh sách VĐV (player_id và điểm đích của họ)
    $players_data = [];
    $sql_players = "SELECT player_id, current_target_score FROM players WHERE is_active = 1 ORDER BY player_id"; // Sắp xếp để ổn định nếu cần debug
    $result_players = $conn->query($sql_players);
    if ($result_players && $result_players->num_rows > 0) {
        while ($row = $result_players->fetch_assoc()) {
            $players_data[] = $row;
        }
    } else {
        return ['success' => false, 'message' => 'Không có VĐV nào để tạo lịch.'];
    }

    if (count($players_data) < 2) {
        return ['success' => false, 'message' => 'Cần ít nhất 2 VĐV để tạo lịch thi đấu.'];
    }

    // Kiểm tra lịch hiện có
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM matches WHERE tournament_id = ?");
    $stmt_check->bind_param("i", $tournament_id);
    $stmt_check->execute();
    $count_result = $stmt_check->get_result()->fetch_assoc()['count'];
    $stmt_check->close();

    if ($count_result > 0) {
        if ($overwrite_existing) {
            $stmt_delete = $conn->prepare("DELETE FROM matches WHERE tournament_id = ?");
            $stmt_delete->bind_param("i", $tournament_id);
            if (!$stmt_delete->execute()) {
                 return ['success' => false, 'message' => 'Lỗi xóa lịch thi đấu cũ: ' . $stmt_delete->error];
            }
            $stmt_delete->close();
        } else {
            return ['success' => false, 'message' => 'Giải đấu này đã có lịch thi đấu. Chọn "Xóa lịch cũ" nếu muốn tạo lại.'];
        }
    }

    // Xáo trộn VĐV để tạo sự ngẫu nhiên cho các cặp đấu
    shuffle($players_data);

    $players = [];
    foreach($players_data as $p_data) $players[] = $p_data['player_id']; // Chỉ lấy ID sau khi shuffle

    $num_players = count($players);
    if ($num_players % 2 != 0) {
        $players[] = null; // Thêm VĐV "ảo" (bye) nếu số VĐV lẻ
        $num_players++;
    }

    $total_rounds = $num_players - 1;
    $matches_per_round = $num_players / 2;
    $all_matches_for_scheduling = [];

    // Thuật toán vòng tròn (Circle method) để tạo các cặp đấu
    for ($round = 0; $round < $total_rounds; $round++) {
        for ($i = 0; $i < $matches_per_round; $i++) {
            $player1_id = $players[$i];
            $player2_id = $players[$num_players - 1 - $i];

            if ($player1_id !== null && $player2_id !== null) { // Bỏ qua các cặp đấu với VĐV "ảo" (bye)
                $p1_score = 0; $p2_score = 0;
                foreach($players_data as $pd) {
                    if($pd['player_id'] == $player1_id) $p1_score = $pd['current_target_score'];
                    if($pd['player_id'] == $player2_id) $p2_score = $pd['current_target_score'];
                }
                $all_matches_for_scheduling[] = [
                    'round' => $round + 1,
                    'p1_id' => $player1_id,
                    'p2_id' => $player2_id,
                    'p1_target_score' => $p1_score,
                    'p2_target_score' => $p2_score
                ];
            }
        }
        
        // ==========================================================
        // SỬA LỖI LOGIC XOAY VÒNG VĐV Ở ĐÂY
        // Thay vì dùng array_pop và array_splice, chúng ta sẽ xoay vòng một cách thủ công và rõ ràng hơn.
        // Giữ VĐV đầu tiên (index 0) cố định.
        // Di chuyển VĐV cuối cùng lên vị trí thứ hai (index 1).
        // Dịch chuyển tất cả các VĐV khác sang phải một vị trí.
        // ==========================================================
        if ($num_players > 2) { // Chỉ cần xoay vòng nếu có nhiều hơn 2 VĐV
            $last_player = $players[$num_players - 1]; // Lấy ra VĐV cuối cùng

            // Dịch các VĐV từ vị trí thứ 2 đến vị trí kế cuối sang phải 1 bậc
            for ($i = $num_players - 1; $i > 1; $i--) {
                $players[$i] = $players[$i-1];
            }

            // Đặt VĐV cuối cùng đã lấy ra vào vị trí thứ 2
            $players[1] = $last_player;
        }
    }

    // Sắp xếp ngày thi đấu
    if (empty($all_matches_for_scheduling)) {
        return ['success' => false, 'message' => 'Không tạo được cặp đấu nào.'];
    }

    usort($all_matches_for_scheduling, function($a, $b) {
        return $a['round'] <=> $b['round'];
    });

    $current_match_day_date = new DateTime($start_date_str);
    while (true) {
        if (in_array((int)$current_match_day_date->format('N'), $match_days_of_week)) {
            break; 
        }
        $current_match_day_date->modify('+1 day');
    }

    $matches_assigned_to_current_day = 0;
    $current_round_being_processed = 0;
    $total_matches_inserted = 0;

    $stmt_insert_match = $conn->prepare("INSERT INTO matches (tournament_id, round_number, player1_id, player2_id, player1_target_score_at_match, player2_target_score_at_match, scheduled_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
    if (!$stmt_insert_match) {
        return ['success' => false, 'message' => 'Lỗi chuẩn bị câu lệnh INSERT match: ' . $conn->error];
    }

    foreach ($all_matches_for_scheduling as $match_info) {
        $new_round_starts = ($current_round_being_processed != $match_info['round']);
        if ($new_round_starts) {
            $current_round_being_processed = $match_info['round'];
            if ($matches_assigned_to_current_day > 0) { 
                 $current_match_day_date->modify('+1 day'); 
                 $matches_assigned_to_current_day = 0; 
                 while (true) {
                    if (in_array((int)$current_match_day_date->format('N'), $match_days_of_week)) {
                        break;
                    }
                    $current_match_day_date->modify('+1 day');
                }
            }
        } elseif ($max_matches_per_day > 0 && $matches_assigned_to_current_day >= $max_matches_per_day) {
            $current_match_day_date->modify('+1 day');
            $matches_assigned_to_current_day = 0;
            while (true) {
                if (in_array((int)$current_match_day_date->format('N'), $match_days_of_week)) {
                    break;
                }
                $current_match_day_date->modify('+1 day');
            }
        }

        $scheduled_date_sql = $current_match_day_date->format('Y-m-d');
        $stmt_insert_match->bind_param("iiiiiss",
            $tournament_id,
            $match_info['round'],
            $match_info['p1_id'],
            $match_info['p2_id'],
            $match_info['p1_target_score'],
            $match_info['p2_target_score'],
            $scheduled_date_sql
        );

        if ($stmt_insert_match->execute()) {
            $total_matches_inserted++;
            $matches_assigned_to_current_day++;
        }
    }
    $stmt_insert_match->close();

    if ($total_matches_inserted > 0) {
        return ['success' => true, 'message' => "Tạo lịch thi đấu thành công với {$total_matches_inserted} trận đấu."];
    } else {
        return ['success' => false, 'message' => 'Không có trận đấu nào được chèn. Có thể không có VĐV hoặc lỗi xảy ra.'];
    }
}

/**
 * Tính toán kết quả trận đấu chung cuộc từ kết quả các set
 * @param array $set_outcomes Mảng kết quả các set, ví dụ: [1 => 'p1_wins', 2 => 'p2_wins', 3 => 'draw']
 * @param int $p1_id ID của player 1
 * @param int $p2_id ID của player 2
 * @return array ['winner_id' => int|null, 'is_draw' => bool, 'p1_sets_won' => int, 'p2_sets_won' => int]
 */
function calculateMatchResult($set_outcomes, $p1_id, $p2_id) {
    $p1_sets_won = 0;
    $p2_sets_won = 0;
    $draw_sets = 0;

    foreach ($set_outcomes as $outcome) {
        if ($outcome === 'p1_wins') $p1_sets_won++;
        elseif ($outcome === 'p2_wins') $p2_sets_won++;
        elseif ($outcome === 'draw') $draw_sets++;
    }

    $result = [
        'winner_id' => null,
        'is_draw' => false,
        'p1_sets_won' => $p1_sets_won,
        'p2_sets_won' => $p2_sets_won
    ];

    // Áp dụng 11 trường hợp bạn đã nêu (đã được tóm gọn và hệ thống hóa)
    if ($p1_sets_won >= 2) {
        // P1 thắng 2-0, 2-1, hoặc 3-0
        $result['winner_id'] = $p1_id;
    } elseif ($p2_sets_won >= 2) {
        // P2 thắng 2-0, 2-1, hoặc 3-0
        $result['winner_id'] = $p2_id;
    } elseif ($p1_sets_won == 1 && $p2_sets_won == 1) { // Tỷ số set là 1-1, set 3 quyết định
        if(isset($set_outcomes[3])) {
            if ($set_outcomes[3] === 'p1_wins') $result['winner_id'] = $p1_id; // Thắng 2-1
            elseif ($set_outcomes[3] === 'p2_wins') $result['winner_id'] = $p2_id; // Thua 1-2
            else $result['is_draw'] = true; // Hòa 1-1 và set 3 hòa -> Trận hòa
        }
    } elseif ($p1_sets_won == 1 && $p2_sets_won == 0 && $draw_sets > 0) { // P1 thắng 1 set, còn lại hòa
        $result['winner_id'] = $p1_id;
    } elseif ($p2_sets_won == 1 && $p1_sets_won == 0 && $draw_sets > 0) { // P2 thắng 1 set, còn lại hòa
        $result['winner_id'] = $p2_id;
    } elseif ($p1_sets_won == 0 && $p2_sets_won == 0 && $draw_sets > 0) { // Cả 2 VĐV không thắng set nào, chỉ có hòa
        // Ví dụ: hòa 2 set đầu, set 3 không đấu -> trận hòa
        // Hoặc hòa cả 3 set -> trận hòa
        $result['is_draw'] = true;
    }
    
    return $result;
}

/**
/**
 * Lấy và tính toán Bảng Xếp Hạng cho một giải đấu cụ thể.
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param int $tournament_id ID của giải đấu.
 * @return array Mảng chứa dữ liệu BXH đã sắp xếp.
 */
function getTournamentStandings($conn, $tournament_id) {
    // 1. Lấy tất cả VĐV đang hoạt động
    $standings = [];
    $sql_players = "SELECT player_id, player_name FROM players WHERE is_active = 1";
    $result_players = $conn->query($sql_players);
    if ($result_players && $result_players->num_rows > 0) {
        while ($player = $result_players->fetch_assoc()) {
            $standings[$player['player_id']] = [
                'player_name' => $player['player_name'],
                'P' => 0, 'W' => 0, 'D' => 0, 'L' => 0,
                'F' => 0, 'A' => 0, 'GD' => 0, 'PTS' => 0,
            ];
        }
    } else {
        return []; // Trả về mảng rỗng nếu không có VĐV
    }

    // 2. Lấy tất cả các trận đã hoàn thành của giải đấu này
    $completed_matches = [];
    $sql_matches = "SELECT match_id, player1_id, player2_id, winner_id, is_draw FROM matches WHERE status = 'completed' AND tournament_id = ?";
    $stmt_matches = $conn->prepare($sql_matches);
    if(!$stmt_matches) return []; // Lỗi prepare
    $stmt_matches->bind_param("i", $tournament_id);
    $stmt_matches->execute();
    $result_matches = $stmt_matches->get_result();
    if ($result_matches->num_rows > 0) {
        while ($match = $result_matches->fetch_assoc()) {
            $completed_matches[] = $match;
        }
    }
    $stmt_matches->close();

    // Nếu không có trận nào hoàn thành, trả về BXH ban đầu (sắp xếp theo tên)
    if (empty($completed_matches)) {
        $final_standings = array_values($standings);
        // SỬA LỖI CÚ PHÁP Ở DÒNG DƯỚI ĐÂY
        usort($final_standings, function($a, $b) {
            return strcmp($a['player_name'], $b['player_name']);
        });
        return $final_standings;
    }

    // 3. Lấy tất cả các set của các trận đã hoàn thành
    $sets_data = [];
    $completed_match_ids = array_column($completed_matches, 'match_id');
    if (!empty($completed_match_ids)) {
        $match_ids_string = implode(',', $completed_match_ids);
        $sql_sets = "SELECT match_id, player1_score_raw, player2_score_raw FROM sets WHERE match_id IN ($match_ids_string)";
        $result_sets = $conn->query($sql_sets);
        if ($result_sets && $result_sets->num_rows > 0) {
            while ($set = $result_sets->fetch_assoc()) {
                $sets_data[$set['match_id']][] = $set;
            }
        }
    }

    // 4. Tính toán chỉ số
    foreach ($completed_matches as $match) {
        $p1_id = $match['player1_id'];
        $p2_id = $match['player2_id'];
        if (!isset($standings[$p1_id]) || !isset($standings[$p2_id])) continue;

        $standings[$p1_id]['P']++;
        $standings[$p2_id]['P']++;

        if ($match['is_draw'] == 1) {
            $standings[$p1_id]['D']++; $standings[$p2_id]['D']++;
            $standings[$p1_id]['PTS'] += 1; $standings[$p2_id]['PTS'] += 1;
        } else {
            if ($match['winner_id'] == $p1_id) {
                $standings[$p1_id]['W']++; $standings[$p2_id]['L']++;
                $standings[$p1_id]['PTS'] += 3;
            } elseif ($match['winner_id'] == $p2_id) {
                $standings[$p2_id]['W']++; $standings[$p1_id]['L']++;
                $standings[$p2_id]['PTS'] += 3;
            }
        }
        if (isset($sets_data[$match['match_id']])) {
            foreach ($sets_data[$match['match_id']] as $set) {
                $standings[$p1_id]['F'] += $set['player1_score_raw']; $standings[$p1_id]['A'] += $set['player2_score_raw'];
                $standings[$p2_id]['F'] += $set['player2_score_raw']; $standings[$p2_id]['A'] += $set['player1_score_raw'];
            }
        }
    }

    // 5. Tính GD và Sắp xếp
    $final_standings = [];
    foreach ($standings as $player_id => $stats) {
        $stats['GD'] = $stats['F'] - $stats['A'];
        $stats['player_id'] = $player_id;
        $final_standings[] = $stats;
    }

    usort($final_standings, function ($a, $b) {
        if ($a['PTS'] != $b['PTS']) return $b['PTS'] <=> $a['PTS'];
        if ($a['GD'] != $b['GD']) return $b['GD'] <=> $a['GD'];
        if ($a['F'] != $b['F']) return $b['F'] <=> $a['F'];
        return strcmp($a['player_name'], $b['player_name']);
    });

    return $final_standings;
}

/**
 * Tự động thêm một VĐV mới vào lịch thi đấu của một giải đang diễn ra.
 * Hàm sẽ tạo các trận đấu cho VĐV mới với tất cả các VĐV khác 
 * và "rắc" chúng vào các ngày thi đấu sắp tới.
 *
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param int $new_player_id ID của VĐV mới được thêm.
 * @param int $tournament_id ID của giải đấu đang diễn ra.
 * @return array Mảng kết quả ['success' => bool, 'message' => string].
 */
function addPlayerToExistingSchedule($conn, $new_player_id, $tournament_id) {
    // 1. Lấy thông tin của VĐV mới và tất cả các VĐV khác đang active
    $new_player_data = $conn->query("SELECT player_id, current_target_score FROM players WHERE player_id = $new_player_id")->fetch_assoc();
    if (!$new_player_data) {
        return ['success' => false, 'message' => 'Không tìm thấy thông tin VĐV mới.'];
    }

    $other_players = [];
    // Chỉ lấy VĐV đã có trong lịch của giải này để tránh tạo trận với VĐV mới khác
    $sql_others = "SELECT p.player_id, p.current_target_score 
                   FROM players p
                   WHERE p.is_active = 1 AND p.player_id != $new_player_id
                   AND (
                       p.player_id IN (SELECT player1_id FROM matches WHERE tournament_id = $tournament_id) OR
                       p.player_id IN (SELECT player2_id FROM matches WHERE tournament_id = $tournament_id)
                   )";
    $result_others = $conn->query($sql_others);
    while($row = $result_others->fetch_assoc()) {
        $other_players[] = $row;
    }

    if (empty($other_players)) {
        return ['success' => true, 'message' => 'Không có VĐV khác để xếp cặp.'];
    }

    // 2. Chuẩn bị tạo các trận đấu bù
    $new_matches_to_schedule = [];
    foreach ($other_players as $opponent) {
        $new_matches_to_schedule[] = [
            'p1_id' => $new_player_data['player_id'],
            'p1_target_score' => $new_player_data['current_target_score'],
            'p2_id' => $opponent['player_id'],
            'p2_target_score' => $opponent['current_target_score'],
        ];
    }
    
    // 3. Sắp xếp ngày cho các trận đấu bù
    // Bắt đầu tìm ngày trống từ "hôm nay"
    $current_date_finder = new DateTime(); 
    $match_days_of_week = [3, 6]; // Thứ 4 và Thứ 7
    $max_matches_per_day = 4; // Tăng giới hạn trận/ngày lên một chút để xếp được nhiều trận bù hơn, bạn có thể thay đổi
    $total_matches_created = 0;

    $stmt_insert = $conn->prepare("INSERT INTO matches (tournament_id, round_number, player1_id, player2_id, player1_target_score_at_match, player2_target_score_at_match, scheduled_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', 'Trận đấu bù')");
    if(!$stmt_insert) {
        return ['success' => false, 'message' => 'Lỗi chuẩn bị câu lệnh INSERT: ' . $conn->error];
    }

    foreach ($new_matches_to_schedule as $match_info) {
        // Tìm ngày thi đấu hợp lệ tiếp theo
        while(true) {
            // Kiểm tra xem ngày này có phải T4/T7 không
            $is_match_day = in_array((int)$current_date_finder->format('N'), $match_days_of_week);
            
            if ($is_match_day) {
                // Nếu đúng, đếm xem ngày này đã có bao nhiêu trận rồi
                $date_str = $current_date_finder->format('Y-m-d');
                $sql_count = "SELECT COUNT(*) as count FROM matches WHERE tournament_id = $tournament_id AND scheduled_date = '$date_str'";
                $count_result = $conn->query($sql_count)->fetch_assoc()['count'];

                // Nếu ngày đó còn slot trống, thì chọn ngày này
                if ($count_result < $max_matches_per_day) {
                    break;
                }
            }
            // Nếu không phải ngày đấu hoặc đã đủ slot, tìm ngày tiếp theo
            $current_date_finder->modify('+1 day');
        }

        $scheduled_date_sql = $current_date_finder->format('Y-m-d');
        
        // Đặt round_number là 0 để dễ dàng nhận biết và nhóm các trận đấu bù
        $round_number_for_rematch = 0; 
        
        $stmt_insert->bind_param("iiiiiss",
            $tournament_id,
            $round_number_for_rematch,
            $match_info['p1_id'],
            $match_info['p2_id'],
            $match_info['p1_target_score'],
            $match_info['p2_target_score'],
            $scheduled_date_sql
        );
        $stmt_insert->execute();
        $total_matches_created++;
    }
    $stmt_insert->close();

    if ($total_matches_created > 0) {
        return ['success' => true, 'message' => "Và đã tự động tạo {$total_matches_created} trận đấu bù cho VĐV này trong giải đang diễn ra."];
    }

    return ['success' => true, 'message' => ''];
}
?>