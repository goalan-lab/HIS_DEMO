<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
// 統計
$stats = [];
foreach ([
  'today_regs' => "SELECT COUNT(*) c FROM registrations WHERE visit_date=CURDATE()",
  'unbilled' => "SELECT COUNT(*) c FROM registrations WHERE status='待批價'",
  'undispensed' => "SELECT COUNT(*) c FROM registrations WHERE status='待領藥'",
  'revenue' => "SELECT IFNULL(SUM(total),0) c FROM bills WHERE paid=1 AND DATE(paid_at)=CURDATE()",
] as $k => $q) { $stats[$k] = (int)$pdo->query($q)->fetch()['c']; }
$rows = $pdo->query("SELECT r.*, p.name pname, p.chart_no, d.name dname FROM registrations r JOIN patients p ON p.id=r.patient_id LEFT JOIN doctors d ON d.id=r.doctor_id WHERE r.visit_date=CURDATE() ORDER BY r.id DESC LIMIT 20")->fetchAll();
?>
<div class="row g-3 mb-4">
<div class="col-md-3"><div class="card p-3 shadow-sm"><div class="text-muted">今日掛號</div><h3><?= $stats['today_regs'] ?></h3><a href="register.php" class="btn btn-sm btn-outline-primary">去掛號</a></div></div>
<div class="col-md-3"><div class="card p-3 shadow-sm"><div class="text-muted">待批價</div><h3><?= $stats['unbilled'] ?></h3><a href="billing.php" class="btn btn-sm btn-outline-warning">去批價</a></div></div>
<div class="col-md-3"><div class="card p-3 shadow-sm"><div class="text-muted">待領藥</div><h3><?= $stats['undispensed'] ?></h3><a href="pharmacy.php" class="btn btn-sm btn-outline-success">去發藥</a></div></div>
<div class="col-md-3"><div class="card p-3 shadow-sm"><div class="text-muted">今日實收(元)</div><h3><?= number_format($stats['revenue']) ?></h3><a href="reports.php" class="btn btn-sm btn-outline-secondary">看報表</a></div></div>
</div>
<div class="card shadow-sm"><div class="card-header fw-bold">今日掛號名單 (最新20筆)</div>
<div class="table-responsive"><table class="table table-hover mb-0">
<tr><th>掛號單</th><th>病歷號</th><th>姓名</th><th>診別</th><th>科別/醫師</th><th>狀態</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['reg_no']) ?></td><td><?= h($r['chart_no']) ?></td><td><?= h($r['pname']) ?></td>
<td><?= h($r['visit_date'] . ' ' . $r['slot']) ?></td><td><?= h($r['dept']) ?>/<?= h($r['dname']) ?></td>
<td><span class="badge bg-info"><?= h($r['status']) ?></span></td>
<td><a class="btn btn-sm btn-outline-primary" href="register.php?reg_id=<?= $r['id'] ?>">開處方</a></td></tr>
<?php endforeach; ?></table></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
