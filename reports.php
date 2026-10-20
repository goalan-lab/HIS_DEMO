<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$rev = $pdo->query("SELECT DATE(paid_at) d, COUNT(*) n, SUM(total) s FROM bills WHERE paid=1 AND voided=0 GROUP BY DATE(paid_at) ORDER BY d DESC LIMIT 14")->fetchAll();
$refund = $pdo->query("SELECT DATE(created_at) d, COUNT(*) n, SUM(amount) s FROM refunds GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 14")->fetchAll();
$dept = $pdo->query("SELECT dept, COUNT(*) n FROM registrations GROUP BY dept ORDER BY n DESC")->fetchAll();
$doc = $pdo->query("SELECT d.name, COUNT(*) n FROM registrations r LEFT JOIN doctors d ON d.id=r.doctor_id GROUP BY d.name ORDER BY n DESC")->fetchAll();
$surg = $pdo->query("SELECT op_date d, COUNT(*) n, SUM(fee) s FROM surgeries WHERE status!='取消' GROUP BY op_date ORDER BY op_date DESC LIMIT 14")->fetchAll();
$surg_tot = $pdo->query("SELECT COUNT(*) n, SUM(fee) s FROM surgeries WHERE status='完成'")->fetch();
?>
<div class="row g-3">
<div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-bold">每日營收（已扣退費作廢）</div>
<table class="table table-sm mb-0"><tr><th>日期</th><th>筆數</th><th>金額</th></tr>
<?php foreach ($rev as $r): ?><tr><td><?= h($r['d']) ?></td><td><?= $r['n'] ?></td><td><?= number_format($r['s']) ?></td></tr><?php endforeach; ?></table></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">每日退費</div>
<table class="table table-sm mb-0"><tr><th>日期</th><th>筆數</th><th>金額</th></tr>
<?php foreach ($refund as $r): ?><tr><td><?= h($r['d']) ?></td><td><?= $r['n'] ?></td><td><?= number_format($r['s']) ?></td></tr><?php endforeach; ?></table></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">手術營收（未取消，近14日）</div>
<table class="table table-sm mb-0"><tr><th>手術日</th><th>台數</th><th>手術費</th></tr>
<?php foreach ($surg as $s): ?><tr><td><?= h($s['d']) ?></td><td><?= $s['n'] ?></td><td><?= number_format($s['s']) ?></td></tr><?php endforeach; ?>
<tr class="table-primary"><th>累計（完成）</th><th><?= (int)$surg_tot['n'] ?></th><th><?= number_format((int)$surg_tot['s']) ?></th></tr></table>
<p class="small text-muted mt-2">住院手術併出院結算、門診手術併批價收據；報表另列供營收統計。</p></div>
</div>
<div class="col-md-3"><div class="card shadow-sm"><div class="card-header fw-bold">科別掛號</div>
<table class="table table-sm mb-0"><?php foreach ($dept as $r): ?><tr><td><?= h($r['dept']) ?></td><td><?= $r['n'] ?></td></tr><?php endforeach; ?></table></div></div>
<div class="col-md-3"><div class="card shadow-sm"><div class="card-header fw-bold">醫師掛號</div>
<table class="table table-sm mb-0"><?php foreach ($doc as $r): ?><tr><td><?= h($r['name']) ?></td><td><?= $r['n'] ?></td></tr><?php endforeach; ?></table></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
