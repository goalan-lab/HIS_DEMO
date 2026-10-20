<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
if (isset($_GET['dispense'])) {
    if (!can('pharmacy.dispense')) { flash_set('您的角色無發藥權限'); redirect('pharmacy.php'); }
    $reg_id = (int)$_GET['dispense'];
    // 扣庫存＋寫流水
    $rxs = $pdo->query("SELECT * FROM prescriptions WHERE reg_id=$reg_id AND dispensed=0")->fetchAll();
    foreach ($rxs as $x) {
        $d = $pdo->query("SELECT stock FROM drugs WHERE id={$x['drug_id']}")->fetch();
        $before = (int)$d['stock'];
        $after = max(0, $before - $x['qty']);
        $pdo->prepare('UPDATE drugs SET stock=? WHERE id=?')->execute([$after, $x['drug_id']]);
        $pdo->prepare("INSERT INTO stock_txns(drug_id,txn_type,qty,before_stock,after_stock,ref_type,ref_id,created_by) VALUES(?,'發藥',?,?,?,?,?,?)")
            ->execute([$x['drug_id'], -$x['qty'], $before, $after, 'registrations', $reg_id, $_SESSION['user']['username'] ?? '']);
        $pdo->prepare('UPDATE prescriptions SET dispensed=1 WHERE id=?')->execute([$x['id']]);
    }
    $pdo->prepare("UPDATE registrations SET status='結案' WHERE id=?")->execute([$reg_id]);
    flash_set('已發藥結案，庫存已扣');
    redirect('pharmacy.php');
}
$sel = null; $selrx = [];
if (!empty($_GET['reg_id'])) {
    $st = $pdo->prepare('SELECT r.*, p.name pname, p.chart_no FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.id=?');
    $st->execute([$_GET['reg_id']]); $sel = $st->fetch();
    $st = $pdo->prepare('SELECT pr.*, d.name dname, d.spec FROM prescriptions pr JOIN drugs d ON d.id=pr.drug_id WHERE pr.reg_id=?');
    $st->execute([$sel['id']]); $selrx = $st->fetchAll();
}
$rows = $pdo->query("SELECT r.*, p.name pname, p.chart_no FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.status='待領藥' ORDER BY r.id")->fetchAll();
$stocks = $pdo->query('SELECT * FROM drugs ORDER BY stock ASC LIMIT 20')->fetchAll();
?>
<div class="row g-3">
<div class="col-md-7"><div class="card shadow-sm"><div class="card-header fw-bold">藥局發藥 — 待領藥</div>
<table class="table table-hover mb-0"><tr><th>掛號單</th><th>病人</th><th>狀態</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= h($r['reg_no']) ?></td><td><?= h($r['chart_no'].' '.$r['pname']) ?></td><td><?= h($r['status']) ?></td>
<td><?php if (can('pharmacy.dispense')): ?><a class="btn btn-sm btn-primary" href="pharmacy.php?reg_id=<?= $r['id'] ?>">調劑單</a><?php endif; ?></td></tr><?php endforeach; ?></table>
<?php if (!$rows): ?><div class="card-body text-muted">無待領藥。先完成批價。</div><?php endif; ?></div>
<?php if ($sel): ?><div class="card shadow-sm mt-3"><div class="card-header fw-bold">調劑單 <?= h($sel['reg_no'].' '.$sel['pname']) ?></div><div class="card-body">
<table class="table table-sm"><tr><th>藥品</th><th>規格</th><th>數量</th><th>用法</th><th>已發</th></tr>
<?php foreach ($selrx as $x): ?><tr><td><?= h($x['dname']) ?></td><td><?= h($x['spec']) ?></td><td><?= $x['qty'] ?></td><td><?= h($x['usage_note']) ?></td><td><?= $x['dispensed']?'✅':'—' ?></td></tr><?php endforeach; ?></table>
<?php if (can('pharmacy.dispense')): ?><a class="btn btn-success" href="pharmacy.php?dispense=<?= $sel['id'] ?>" onclick="return confirm('確認發藥並扣庫存？')">確認發藥 → 結案</a><?php else: ?><p class="text-muted small">您的角色（<?= h(current_role()) ?>）不可發藥。</p><?php endif; ?>
</div></div><?php endif; ?></div>
<div class="col-md-5"><div class="card shadow-sm"><div class="card-header fw-bold">藥品庫存 (低庫存優先)</div>
<table class="table table-sm mb-0"><tr><th>代碼</th><th>品名</th><th>單價</th><th>庫存</th></tr>
<?php foreach ($stocks as $d): ?><tr class="<?= $d['stock']<50?'table-warning':'' ?>"><td><?= h($d['code']) ?></td><td><?= h($d['name']) ?></td><td><?= number_format($d['price']) ?></td><td><?= $d['stock'] ?></td></tr><?php endforeach; ?></table></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
