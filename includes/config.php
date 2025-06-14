<?php
// File: includes/config.php

// Cấu hình Database
define('DB_SERVER', 'localhost'); // Thường là localhost
define('DB_USERNAME', 'database2vikingleague');    // Username MySQL của XAMPP mặc định là root
define('DB_PASSWORD', '123Robmeveu!');        // Password MySQL của XAMPP mặc định là rỗng
                                  // KHI LÊN HOSTING: thay bằng username và password bạn tạo trên cPanel
                                  // Ví dụ: define('DB_USERNAME', 'database2vikingleague');
                                  //       define('DB_PASSWORD', 'MẬT KHẨU MỚI CỦA BẠN');
define('DB_NAME', 'database2vikingleague');

// Cấu hình website (tùy chọn)
define('SITE_URL', 'https://league.2vikingbilliards.com/');
define('SITE_NAME', '2Viking Billiards League');

// Cấu hình múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bật hiển thị lỗi (chỉ khi đang phát triển - development)
// TẮT ĐI KHI ĐƯA LÊN PRODUCTION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>