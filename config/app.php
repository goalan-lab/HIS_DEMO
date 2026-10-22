<?php
// ===== 環境開關（切本機/線上只改這一行）=====
// 'local'  = 本機 XAMPP（127.0.0.1 / his_demo / root 無密碼）
// 'online' = InfinityFree（sql210.infinityfree.com / if0_43222797_his_demo）
// 共享設定（站名/費用/診別/Telegram 常數）在 app_common.php；連線資訊在各環境檔。
if (!defined('HIS_ENV')) define('HIS_ENV', 'local');
//if (!defined('HIS_ENV')) define('HIS_ENV', 'online');
require_once __DIR__ . '/app_' . HIS_ENV . '.php';
