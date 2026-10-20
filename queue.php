<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$doctors = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();
$did = (int)($_GET['doctor_id'] ?? $doctors[0]['id']);
$slot = $_GET['slot'] ?? '早診';
// 叫號：下一號
if (isset($_GET['call_next'])) {
    if (!can('queue.call')) { flash_set('您的角色無叫號權限'); redirect("queue.php?doctor_id=$did&slot=" . urlencode($slot)); }
    $cur = $pdo->query("SELECT * FROM registrations WHERE doctor_id=$did AND visit_date=CURDATE() AND slot=" . $pdo->quote($slot) . " AND status='看診中' ORDER BY queue_no LIMIT 1")->fetch();
    if ($cur) { $pdo->prepare("UPDATE registrations SET status='待批價' WHERE id=?")->execute([$cur['id']]); }
    $nx = $pdo->query("SELECT * FROM registrations WHERE doctor_id=$did AND visit_date=CURDATE() AND slot=" . $pdo->quote($slot) . " AND status='掛號' ORDER BY queue_no LIMIT 1")->fetch();
    if ($nx) { $pdo->prepare("UPDATE registrations SET status='看診中', called_at=NOW() WHERE id=?")->execute([$nx['id']]); flash_set('叫號 ' . $nx['queue_no'] . ' 號'); }
    else { flash_set('已無待看診'); }
    redirect("queue.php?doctor_id=$did&slot=" . urlencode($slot));
}
if (isset($_GET['recall'])) {
    if (!can('queue.call')) { flash_set('您的角色無重叫權限'); redirect("queue.php?doctor_id=$did&slot=" . urlencode($slot)); }
    $pdo->prepare('UPDATE registrations SET called_at=NOW() WHERE id=?')->execute([(int)$_GET['recall']]);
    redirect("queue.php?doctor_id=$did&slot=" . urlencode($slot));
}
$serving = $pdo->query("SELECT r.*, p.name pname FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.doctor_id=$did AND r.visit_date=CURDATE() AND r.slot=" . $pdo->quote($slot) . " AND r.status='看診中' ORDER BY queue_no LIMIT 1")->fetch();
$waiting = $pdo->query("SELECT r.*, p.name pname FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.doctor_id=$did AND r.visit_date=CURDATE() AND r.slot=" . $pdo->quote($slot) . " AND r.status='掛號' ORDER BY queue_no LIMIT 10")->fetchAll();
$done = $pdo->query("SELECT COUNT(*) c FROM registrations WHERE doctor_id=$did AND visit_date=CURDATE() AND slot=" . $pdo->quote($slot) . " AND status IN ('待批價','待領藥','結案')")->fetch()['c'];
?>
<div class="card shadow-sm mb-3"><div class="card-body d-flex gap-2 align-items-end flex-wrap">
<form class="d-flex gap-2" method="get">
<select name="doctor_id" class="form-select"><?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>" <?= $d['id']==$did?'selected':'' ?>><?= h($d['dept'].' '.$d['name']) ?></option><?php endforeach; ?></select>
<select name="slot" class="form-select"><option <?= $slot==='早診'?'selected':'' ?>>早診</option><option <?= $slot==='午診'?'selected':'' ?>>午診</option><option <?= $slot==='晚診'?'selected':'' ?>>晚診</option></select>
<button class="btn btn-outline-primary">切換診間</button></form>
<?php if (can('queue.call')): ?><a class="btn btn-success btn-lg" href="queue.php?doctor_id=<?= $did ?>&slot=<?= urlencode($slot) ?>&call_next=1">⏭ 叫下一號</a><?php endif; ?>
<a class="btn btn-outline-dark" href="board.php?doctor_id=<?= $did ?>&slot=<?= urlencode($slot) ?>" target="_blank">📺 病患大螢幕</a>
<span class="text-muted">已看 <?= $done ?> 人</span></div></div>
<div class="row g-3">
<div class="col-md-5"><div class="card shadow-sm text-center p-4">
<div class="text-muted">正在看診</div>
<div style="font-size:96px;font-weight:900;color:#0d6efd"><?= $serving ? h($serving['queue_no']) : '—' ?></div>
<div class="fs-5"><?= $serving ? h($serving['pname'] . ' (' . $serving['reg_no'] . ')') : '尚無看診中' ?></div>
<?php if ($serving): ?><div class="mt-2"><?php if (can('queue.call')): ?><a class="btn btn-outline-warning" href="queue.php?doctor_id=<?= $did ?>&slot=<?= urlencode($slot) ?>&recall=<?= $serving['id'] ?>">🔁 重叫</a><?php endif; ?><?php if (can('register.rx')): ?><a class="btn btn-primary" href="register.php?reg_id=<?= $serving['id'] ?>">🩺 開處方</a><?php endif; ?></div><?php endif; ?>
</div></div>
<div class="col-md-7"><div class="card shadow-sm"><div class="card-header fw-bold">待看診（前10號）</div>
<table class="table table-hover mb-0"><tr><th>號</th><th>掛號單</th><th>病人</th><th>狀態</th></tr>
<?php foreach ($waiting as $w): ?><tr><td><b><?= h($w['queue_no']) ?></b></td><td><?= h($w['reg_no']) ?></td><td><?= h($w['pname']) ?></td><td><?= h($w['status']) ?></td></tr><?php endforeach; ?></table>
<?php if (!$waiting): ?><div class="card-body text-muted">無待看診。</div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
