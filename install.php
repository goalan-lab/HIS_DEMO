<?php
require_once __DIR__ . '/config/app.php';
$msg = '';
$ok = false;
if (isset($_GET['run'])) {
    try {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        // 切出 CREATE/INSERT 逐句執行
        $pdo->exec("CREATE DATABASE IF NOT EXISTS alan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE alan");
        // 簡單切分
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($stmts as $s) {
            if ($s === '' || stripos($s, 'CREATE DATABASE') === 0 || stripos($s, 'USE ') === 0) continue;
            $pdo->exec($s);
        }
        // 重置 admin 密碼 his1234
        $hash = password_hash('his1234', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username,password_hash,role) VALUES ('admin','$hash','管理') ON DUPLICATE KEY UPDATE password_hash='$hash'");
        $msg = '安裝成功！預設帳號 admin / his1234';
        $ok = true;
    } catch (Throwable $e) { $msg = '安裝失敗：' . $e->getMessage(); }
}
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>安裝 - <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><div class="container py-5" style="max-width:640px">
<h3>🏥 <?= h(APP_NAME) ?> 一鍵安裝</h3>
<p class="text-muted">會建立 <code>alan</code> 資料庫與 病人/掛號/批價/處方/藥品/使用者 表，並寫入 demo 資料。請先在 XAMPP Control Panel 啟動 Apache + MySQL。</p>
<?php if ($msg): ?><div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?>"><?= h($msg) ?></div>
<?php if ($ok): ?><a class="btn btn-primary" href="login.php">前往登入</a><?php endif; ?><?php endif; ?>
<a class="btn btn-success" href="install.php?run=1">執行安裝 / 重置 demo 資料</a>
<a class="btn btn-link" href="login.php">回登入</a>
</div></body></html>
