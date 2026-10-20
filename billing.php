<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
if (isset($_GET['pay'])) {
    $reg_id = (int)$_GET['pay'];
    $r = $pdo->query("SELECT * FROM registrations WHERE id=$reg_id")->fetch();
    $med = (int)$pdo->query("SELECT IFNULL(SUM(d.price*pr.qty),0) s FROM prescriptions pr JOIN drugs d ON d.id=pr.drug_id WHERE pr.reg_id=$reg_id")->fetch()['s'];
    $total = $r['reg_fee'] + $r['exam_fee'] + $med;
    $bill_no = gen_no($pdo, 'B', 'bills', 'bill_no');
    $pdo->prepare('INSERT INTO bills(bill_no,reg_id,med_fee,total,paid,paid_at,cashier) VALUES(?,?,?,?,1,NOW(),?) ON DUPLICATE KEY UPDATE med_fee=VALUES(med_fee),total=VALUES(total),paid=1,paid_at=NOW()')
        ->execute([$bill_no, $reg_id, $med, $total, $_SESSION['user']['username'] ?? 'cashier']);
    $pdo->prepare("UPDATE registrations SET status='待領藥' WHERE id=?")->execute([$reg_id]);
    flash_set("已收費 $bill_no，共 " . number_format($total) . ' 元，已送藥局');
    redirect('pharmacy.php?reg_id=' . $reg_id);
}
$rows = $pdo->query("SELECT r.*, p.name pname, p.chart_no, (SELECT IFNULL(SUM(d.price*pr.qty),0) FROM prescriptions pr JOIN drugs d ON d.id=pr.drug_id WHERE pr.reg_id=r.id) med FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.status='待批價' ORDER BY r.id")->fetchAll();
$done = $pdo->query("SELECT b.*, r.reg_no, p.name pname FROM bills b JOIN registrations r ON r.id=b.reg_id JOIN patients p ON p.id=r.patient_id WHERE b.paid=1 ORDER BY b.id DESC LIMIT 20")->fetchAll();
?>
<div class="card shadow-sm"><div class="card-header fw-bold">批價收費 — 待批價清單</div>
<div class="table-responsive"><table class="table table-hover mb-0">
<tr><th>掛號單</th><th>病人</th><th>掛號費</th><th>診察費</th><th>藥費</th><th>合計</th><th></th></tr>
<?php foreach ($rows as $r): $t = $r['reg_fee']+$r['exam_fee']+$r['med']; ?>
<tr><td><?= h($r['reg_no']) ?></td><td><?= h($r['chart_no'].' '.$r['pname']) ?></td>
<td><?= number_format($r['reg_fee']) ?></td><td><?= number_format($r['exam_fee']) ?></td><td><?= number_format($r['med']) ?></td>
<td><b><?= number_format($t) ?></b></td>
<td><a class="btn btn-sm btn-success" href="billing.php?pay=<?= $r['id'] ?>" onclick="return confirm('確認收取 <?= number_format($t) ?> 元？')">收費列印</a></td></tr>
<?php endforeach; ?></table></div>
<?php if (!$rows): ?><div class="card-body text-muted">目前沒有待批價。先到掛號頁看診開方。</div><?php endif; ?></div>
<div class="card shadow-sm mt-3"><div class="card-header">今日已收費 (近20筆)</div>
<table class="table table-sm mb-0"><tr><th>帳單</th><th>掛號單</th><th>病人</th><th>金額</th><th>時間/收銀</th></tr>
<?php foreach ($done as $b): ?><tr><td><?= h($b['bill_no']) ?></td><td><?= h($b['reg_no']) ?></td><td><?= h($b['pname']) ?></td><td><?= number_format($b['total']) ?></td><td><?= h($b['paid_at'].' '.$b['cashier']) ?></td></tr><?php endforeach; ?></table></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
