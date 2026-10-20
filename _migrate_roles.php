<?php
// 一鍵把既有 his_demo 資料庫升級到「分角色」版本：補 cashier/nurse/doctor demo 帳號，
// 並把 admin 角色改為「主管」。重複執行安全。
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php'; // h()
$pdo = db();
$hash = password_hash('his1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (username,password_hash,role) VALUES ('admin','$hash','主管') ON DUPLICATE KEY UPDATE role='主管'");
$n = 0;
foreach ([['cashier','收銀'],['nurse','護理'],['doctor','醫師']] as [$u, $r]) {
    $st = $pdo->prepare('INSERT INTO users (username,password_hash,role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)');
    $st->execute([$u, $hash, $r]);
    $n++;
}
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta http-equiv="refresh" content="2;url=login.php">
<title>升級 - <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-primary bg-opacity-10"><div class="container" style="max-width:480px;margin-top:14vh">
<div class="card shadow p-4 text-center">
<h4 class="fw-bold">✅ 角色帳號已準備完成</h4>
<p class="text-muted">已補/更新 <?= $n + 1 ?> 個帳號：admin(主管)、doctor(醫師)、nurse(護理)、cashier(收銀)，密碼皆為 <code>his1234</code>。<br>2 秒後跳轉登入…</p>
<a class="btn btn-primary" href="login.php">立即前往登入</a>
</div></div></body></html>