<?php
// File: admin/partials/header_admin.php
// File auth_check.php sẽ được gọi ở file chính của trang admin (ví dụ: index.php, players_manage.php)
// require_once __DIR__ . '/../auth_check.php'; // Đảm bảo người dùng đã đăng nhập
// $current_page được đặt ở file gọi header này
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/admin_style.css"> </head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo SITE_URL; ?>admin/index.php"><?php echo SITE_NAME; ?> - Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>admin/index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'seasons') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>admin/season_manage.php">Quản Lý Mùa Giải</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'players') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>admin/players_manage.php">Quản Lý VĐV</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'matches') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>admin/matches_manage.php">Quản Lý Trận Đấu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'results') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>admin/results_entry.php">Nhập Kết Quả</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['admin_username'])): ?>
                <li class="nav-item">
                    <span class="navbar-text me-3">
                        Chào, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!
                    </span>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>admin/logout.php">Đăng Xuất</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    ```