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
        $_SESSION['user'] = ['username' => 'admin', 'role' => '主管', 'uid' => 0];
        require_once __DIR__ . '/includes/telegram.php'; // 內建保底路徑也要通知（TG_TOKEN 空＝零副作用）
        try { tg_login_notify($u, ['username' => 'admin', 'role' => '主管'], true); }
        catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
        redirect('index.php');
    }
    require_once __DIR__ . '/includes/telegram.php';
    try {
        $st = db()->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
        $st->execute([$u]);
        $row = $st->fetch();
        if ($row && password_verify($p, $row['password_hash'])) {
            $_SESSION['user'] = ['username' => $row['username'], 'role' => $row['role'], 'uid' => (int)$row['id']];
            // 成功：通知後再跳轉（try/catch 雙保險，通知炸掉照樣登入）
            try { tg_login_notify($u, $row, true); }
            catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
            redirect('index.php');
        } else {
            $err = '帳號或密碼錯誤 (預設 admin / his1234)';
            try { tg_login_notify($u, null, false); }
            catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
        }
    } catch (Throwable $e) {
        $err = '資料庫尚未安裝，請先到 install.php；或用 admin / his1234 預覽登入。';
        // DB 掛了也算一次登入嘗試失敗，通知照送
        try { tg_login_notify($u, null, false); }
        catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
    }
}
// 將路徑逐段 percent-encode 但保留斜線（PHP 的 urlencode/rawurlencode 會把 / 編成 %2F，
// Apache 預設 AllowEncodedSlashes=Off 會因此 404；瀏覽器送出時斜線必須保留）
function url_encode_keep_slash(string $path): string {
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>登入 - <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-primary bg-opacity-10">
<div class="container" style="max-width:420px;margin-top:10vh">
<div class="card shadow p-4">
<h4 class="fw-bold mb-1">🏥 <?= h(APP_NAME) ?></h4>
<p class="text-muted">預設帳號 <code>admin</code> / <code>his1234</code>（主管）</p>
<table class="table table-sm small"><tr><th>角色</th><th>帳號</th></tr>
<tr><td>主管</td><td>admin / his1234</td></tr>
<tr><td>醫師</td><td>doctor / his1234</td></tr>
<tr><td>護理</td><td>nurse / his1234</td></tr>
<tr><td>收銀</td><td>cashier / his1234</td></tr></table>
<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<form method="post">
<div class="mb-3"><label class="form-label">帳號</label><input class="form-control" name="username" value="admin"></div>
<div class="mb-3"><label class="form-label">密碼</label><input class="form-control" type="password" name="password" value="his1234"></div>
<button class="btn btn-primary w-100">登入</button>
</form>
<div class="mt-3 small"><a href="install.php">① 先到 install.php 一鍵建表</a></div>
<div class="mt-3 pt-3 border-top small">
<span class="fw-bold">操作手冊</span>
<?php foreach (['主管', '收銀', '醫師', '護理'] as $r): ?>
<div class="mt-1"><a href="<?= url_encode_keep_slash('技術文件/系統操作手冊(' . $r . ').docx') ?>" target="_blank">📄 系統操作手冊（<?= h($r) ?>）</a></div>
<?php endforeach; ?>
</div>
</div></div></body></html>
