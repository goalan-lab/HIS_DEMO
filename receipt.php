<?php
// 收據列印（獨立頁，不套 header 避免導覽列被印出）
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user'])) { redirect('login.php'); }
$pdo = db();
$reg_id = (int)($_GET['reg_id'] ?? 0);
$st = $pdo->prepare('SELECT r.*, p.name pname, p.chart_no, p.id_no, d.name dname FROM registrations r JOIN patients p ON p.id=r.patient_id LEFT JOIN doctors d ON d.id=r.doctor_id WHERE r.id=?');
$st->execute([$reg_id]); $r = $st->fetch();
if (!$r) die('找不到掛號單');
$st = $pdo->prepare('SELECT b.* FROM bills b WHERE b.reg_id=? ORDER BY b.id DESC LIMIT 1');
$st->execute([$reg_id]); $b = $st->fetch();
$st = $pdo->prepare('SELECT pr.qty, pr.usage_note, g.name gname, g.price FROM prescriptions pr JOIN drugs g ON g.id=pr.drug_id WHERE pr.reg_id=? ORDER BY pr.id');
$st->execute([$reg_id]); $rxs = $st->fetchAll();
$st = $pdo->prepare('SELECT e.qty, e.price, i.name FROM exam_orders e JOIN exam_items i ON i.id=e.item_id WHERE e.reg_id=? AND e.status!=? ORDER BY e.id');
$st->execute([$reg_id, '取消']); $labs = $st->fetchAll();
$voided = $b && (int)$b['voided'] === 1;
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><title>收據 <?= h($r['reg_no']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>@media print{ .no-print{display:none} body{background:#fff} } .receipt{max-width:560px;margin:auto;border:1px dashed #999;padding:24px} </style>
</head><body class="p-3">
<div class="receipt">
<h4 class="text-center fw-bold">🏥 <?= h(APP_NAME) ?> 收據</h4>
<p class="text-center text-muted"><?= $voided ? '【已作廢/退費】' : '正式收據' ?>　列印時間 <?= date('Y-m-d H:i') ?></p>
<hr>
<p>掛號單：<b><?= h($r['reg_no']) ?></b>　帳單：<b><?= h($b['bill_no'] ?? '-') ?></b><br>
病人：<?= h($r['chart_no'] . ' ' . $r['pname']) ?>　科別/醫師：<?= h($r['dept'] . '/' . $r['dname']) ?><br>
就診：<?= h($r['visit_date'] . ' ' . $r['slot']) ?>　狀態：<?= h($r['status']) ?></p>
<table class="table table-sm table-bordered"><tr><th>項目</th><th class="text-end">金額</th></tr>
<tr><td>掛號費</td><td class="text-end"><?= number_format($r['reg_fee']) ?></td></tr>
<tr><td>診察費</td><td class="text-end"><?= number_format($r['exam_fee']) ?></td></tr>
<?php foreach ($rxs as $x): ?><tr><td><?= h($x['gname'] . ' x' . $x['qty']) ?></td><td class="text-end"><?= number_format($x['price']*$x['qty']) ?></td></tr><?php endforeach; ?>
<?php foreach ($labs as $x): ?><tr><td><?= h('[檢驗] ' . $x['name'] . ' x' . $x['qty']) ?></td><td class="text-end"><?= number_format($x['price']*$x['qty']) ?></td></tr><?php endforeach; ?>
<tr><th>合計</th><th class="text-end"><?= number_format((int)($b['total'] ?? ($r['reg_fee']+$r['exam_fee']))) ?></th></tr></table>
<p>收銀：<?= h($b['cashier'] ?? '-') ?>　收費時間：<?= h($b['paid_at'] ?? '-') ?></p>
<div class="text-center text-muted small">謝謝蒞臨，祝早日康復</div>
<div class="text-center mt-3 no-print"><button class="btn btn-primary" onclick="window.print()">🖨 列印</button>
<a class="btn btn-secondary" href="billing.php">回批價</a></div>
</div></body></html>
