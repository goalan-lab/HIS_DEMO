<?php
// HIS_DEMO 全域設定
define('APP_NAME', 'HIS 門診醫療資訊系統 (MVP)');
define('APP_VER', '1.0.0');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'alan');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP 預設 root 無密碼
define('DB_CHARSET', 'utf8mb4');

// 掛號費 / 診察費預設
define('DEFAULT_REG_FEE', 150);
define('DEFAULT_EXAM_FEE', 350);

// 診別
function slot_options() {
    return ['早診' => '早診 08:30-12:00', '午診' => '午診 14:00-17:30', '晚診' => '晚診 18:00-21:00'];
}
