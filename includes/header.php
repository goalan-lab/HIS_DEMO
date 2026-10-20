<?php
// layout 上半
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_login();
$user = $_SESSION['user'] ?? null;
$flash = flash_get();
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
function nav_active($f) { global $cur; return $cur === $f ? 'active' : ''; }
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6fb} .card{border-radius:14px}
/* 導覽列按鈕過多時自動換行（含右側登出區） */
#nav{flex-wrap:wrap;row-gap:8px}
#nav .navbar-nav{flex-wrap:wrap;row-gap:6px;column-gap:4px}
#nav .nav-link{position:relative;white-space:nowrap;padding:.35rem .8rem;border-radius:999px;font-weight:500;
  color:rgba(255,255,255,.82)!important;transition:all .22s ease}
/* 懸停：浮起＋發光 */
#nav .nav-link:hover{color:#fff!important;background:rgba(255,255,255,.18);transform:translateY(-2px);
  box-shadow:0 4px 12px rgba(0,0,0,.25)}
/* 使用中：白色膠囊＋藍字＋陰影＋底下小圓點 */
#nav .nav-link.active{color:#0d6efd!important;background:#fff;font-weight:800;
  box-shadow:0 4px 14px rgba(0,0,0,.3);transform:translateY(-1px)}
#nav .nav-link.active::after{content:'';position:absolute;left:50%;bottom:-7px;transform:translateX(-50%);
  width:6px;height:6px;border-radius:50%;background:#ffd54d;box-shadow:0 0 8px #ffd54d}
#nav .nav-user{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;row-gap:6px;margin-left:auto}
#nav .navbar-text{white-space:nowrap}
#nav .btn{white-space:nowrap}</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">🏥 <?= h(APP_NAME) ?></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php foreach (nav_items() as [$f, $label]): ?>
        <li class="nav-item"><a class="nav-link <?= nav_active($f) ?>" href="<?= $f ?>"><?= h($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="nav-user">
      <span class="navbar-text text-white">👤 <?= h($user['username'] ?? '') ?> (<?= h($user['role'] ?? '') ?>)</span>
      <a class="btn btn-light btn-sm" href="logout.php">登出</a>
      </div>
    </div>
  </div>
</nav>
<div class="container pb-5">
<?php if ($flash): ?><div class="alert alert-info"><?= h($flash) ?></div><?php endif; ?>
