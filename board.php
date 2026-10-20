<?php
// 病患叫號大螢幕（獨立頁，每10秒自動更新）
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
$pdo = db();
$did = (int)($_GET['doctor_id'] ?? 1);
$slot = $_GET['slot'] ?? '早診';
$d = $pdo->query("SELECT * FROM doctors WHERE id=$did")->fetch();
$serving = $pdo->query("SELECT r.*, p.name pname FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.doctor_id=$did AND r.visit_date=CURDATE() AND r.slot=" . $pdo->quote($slot) . " AND r.status='看診中' ORDER BY queue_no LIMIT 1")->fetch();
$waiting = $pdo->query("SELECT queue_no FROM registrations WHERE doctor_id=$did AND visit_date=CURDATE() AND slot=" . $pdo->quote($slot) . " AND status='掛號' ORDER BY queue_no LIMIT 5")->fetchAll();
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta http-equiv="refresh" content="10">
<title>叫號 <?= htmlspecialchars($d['name'] ?? '') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#0b1530;color:#fff} .big{font-size:180px;font-weight:900;color:#ffd54d}</style></head>
<body class="text-center p-4">
<h2><?= htmlspecialchars(($d['dept'] ?? '') . ' ' . ($d['name'] ?? '') . ' 醫師 ' . $slot) ?></h2>
<p class="text-white-50"><?= date('Y-m-d H:i:s') ?> 每10秒自動更新</p>
<div class="mt-3">正在看診</div>
<div class="big"><?= $serving ? htmlspecialchars($serving['queue_no']) : '—' ?></div>
<div class="fs-3"><?= $serving ? htmlspecialchars(mb_substr($serving['pname'], 0, 1) . '○○ 請進診間') : '請稍候' ?></div>
<hr class="bg-light">
<div class="fs-5">接下來：<?= $waiting ? implode('、', array_column($waiting, 'queue_no')) . ' 號請準備' : '無等候' ?></div>
</body></html>
