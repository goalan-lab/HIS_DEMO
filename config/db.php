<?php
require_once __DIR__ . '/app.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // 資料庫不存在時給出友善訊息
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            die('<h3>尚未安裝資料庫</h3><p>請先開啟 <a href="install.php">install.php</a> 一鍵建表。</p><p>若 MySQL 未啟動，請先到 XAMPP Control Panel 啟動 Apache + MySQL。</p>');
        }
        die('資料庫連線失敗：' . htmlspecialchars($e->getMessage()));
    }
    return $pdo;
}
