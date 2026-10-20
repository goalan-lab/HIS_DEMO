<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user'])) { redirect('index.php'); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    // 內建 demo 帳號 (install.php 會寫入 users 表, 沒裝也能登入)
    if ($u === 'admin' && $p === 'his1234') {
        $_SESSION['user'] = ['username' => 'admin', 'role' => '管理'];
        redirect('index.php');
    }
    try {
        $st = db()->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
        $st->execute([$u]);
        $row = $st->fetch();
        if ($row && password_verify($p, $row['password_hash'])) {
            $_SESSION['user'] = ['username' => $row['username'], 'role' => $row['role']];
            redirect('index.php');
        } else { $err = '帳號或密碼錯誤 (預設 admin / his1234)'; }
    } catch (Throwable $e) { $err = '資料庫尚未安裝，請先到 install.php；或用 admin / his1234 預覽登入。'; }
}
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>登入 - <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-primary bg-opacity-10">
<div class="container" style="max-width:420px;margin-top:10vh">
<div class="card shadow p-4">
<h4 class="fw-bold mb-1">🏥 <?= h(APP_NAME) ?></h4>
<p class="text-muted">預設帳號 <code>admin</code> / <code>his1234</code></p>
<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<form method="post">
<div class="mb-3"><label class="form-label">帳號</label><input class="form-control" name="username" value="admin"></div>
<div class="mb-3"><label class="form-label">密碼</label><input class="form-control" type="password" name="password" value="his1234"></div>
<button class="btn btn-primary w-100">登入</button>
</form>
<div class="mt-3 small"><a href="install.php">① 先到 install.php 一鍵建表</a></div>
</div></div></body></html>
