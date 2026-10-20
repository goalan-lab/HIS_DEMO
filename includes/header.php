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
<style>body{background:#f4f6fb} .card{border-radius:14px} .nav-link.active{font-weight:700}</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">🏥 <?= h(APP_NAME) ?></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= nav_active('index.php') ?>" href="index.php">總覽</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('patients.php') ?>" href="patients.php">病歷管理</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('register.php') ?>" href="register.php">門診掛號</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('schedules.php') ?>" href="schedules.php">醫師班表</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('queue.php') ?>" href="queue.php">診間叫號</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('billing.php') ?>" href="billing.php">批價收費</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('receipt.php') ?>" href="billing.php">收據</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('refund.php') ?>" href="refund.php">退掛退費</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('pharmacy.php') ?>" href="pharmacy.php">藥局發藥</a></li>
        <li class="nav-item"><a class="nav-link <?= nav_active('reports.php') ?>" href="reports.php">報表</a></li>
      </ul>
      <span class="navbar-text text-white me-3">👤 <?= h($user['username'] ?? '') ?> (<?= h($user['role'] ?? '') ?>)</span>
      <a class="btn btn-light btn-sm" href="logout.php">登出</a>
    </div>
  </div>
</nav>
<div class="container pb-5">
<?php if ($flash): ?><div class="alert alert-info"><?= h($flash) ?></div><?php endif; ?>
