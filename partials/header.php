<?php
// File: partials/header.php
require_once __DIR__ . '/../includes/config.php'; // Nạp config để lấy SITE_URL, SITE_NAME
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Trang Chủ'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/match-card.css">
</head>
<body>
    <div class="container"> <header class="my-4">
            <div class="text-center">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>

            <nav class="nav nav-tabs nav-fill mt-3">
                <?php
                // Xác định tab nào đang active
                // Biến $current_tab này sẽ được đặt ở file index.php chính
                $active_tab = $current_tab ?? 'bxh'; // Mặc định là bxh
                ?>
                <a class="nav-link <?php echo ($active_tab == 'bxh') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>?tab=bxh">Bảng Xếp Hạng</a>
                <a class="nav-link <?php echo ($active_tab == 'lichthidau') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>?tab=lichthidau">Lịch Thi Đấu</a>
                <a class="nav-link <?php echo ($active_tab == 'ketqua') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>?tab=ketqua">Kết Quả</a>
                <a class="nav-link <?php echo ($active_tab == 'vdv') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>?tab=vdv">Vận Động Viên</a>
                <a class="nav-link <?php echo ($active_tab == 'cocaugiaithuong') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>?tab=cocaugiaithuong">Cơ Cấu Giải Thưởng</a>
                </nav>
        </header>

        <main>