<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
// 未收費直接退掛
if (isset($_GET['cancel'])) {
    if (!can('refund.act')) { flash_set('您的角色無退掛權限'); redirect('refund.php'); }
    $reg_id = (int)$_GET['cancel'];
    $r = $pdo->query("SELECT * FROM registrations WHERE id=$reg_id")->fetch();
    if ($r && in_array($r['status'], ['掛號','待批價'], true)) {
        $pdo->prepare("UPDATE registrations SET status='退掛' WHERE id=?")->execute([$reg_id]);
        $n_op = void_outpatient_surgeries($pdo, $reg_id);
        flash_set('已退掛：' . $r['reg_no'] . ($n_op ? "（同步作廢門診手術 $n_op 台）" : ''));
    } else { flash_set('僅 掛號/待批價 可直接退掛，已收費請走退費'); }
    redirect('refund.php');
}
// 已收費退掛退費（含回補庫存選項）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'refund') {
    if (!can('refund.act')) { flash_set('您的角色無退費權限'); redirect('refund.php'); }
    $reg_id = (int)$_POST['reg_id'];
    $reason = trim($_POST['reason'] ?? '退掛退費');
    $restock = isset($_POST['restock']) ? 1 : 0;
    $r = $pdo->query("SELECT * FROM registrations WHERE id=$reg_id")->fetch();
    $b = $pdo->query("SELECT * FROM bills WHERE reg_id=$reg_id ORDER BY id DESC LIMIT 1")->fetch();
    if (!$r) { flash_set('找不到掛號單'); redirect('refund.php'); }
    $pdo->beginTransaction();
    $amount = 0; $bill_id = null;
    if ($b && (int)$b['paid'] && !(int)$b['voided']) {
        $amount = (int)$b['total']; $bill_id = (int)$b['id'];
        $pdo->prepare('UPDATE bills SET voided=1, void_at=NOW() WHERE id=?')->execute([$bill_id]);
    }
    if ($restock) {
        // 回補已發藥庫存
        $rxs = $pdo->query("SELECT * FROM prescriptions WHERE reg_id=$reg_id AND dispensed=1")->fetchAll();
        foreach ($rxs as $x) { $pdo->prepare('UPDATE drugs SET stock=stock+? WHERE id=?')->execute([$x['qty'], $x['drug_id']]); }
    }
    $pdo->prepare('INSERT INTO refunds(reg_id,bill_id,amount,reason,restock,created_by) VALUES(?,?,?,?,?,?)')
        ->execute([$reg_id, $bill_id, $amount, $reason, $restock, $_SESSION['user']['username'] ?? '']);
    $pdo->prepare("UPDATE registrations SET status='退掛' WHERE id=?")->execute([$reg_id]);
    $n_op = void_outpatient_surgeries($pdo, $reg_id);
    $pdo->commit();
    flash_set('已退掛' . ($amount ? '退費 ' . number_format($amount) . ' 元' : '') . ($restock ? '（已回補庫存）' : '') . ($n_op ? "、同步作廢門診手術 $n_op 台" : ''));
    redirect('refund.php');
}
$cands = $pdo->query("SELECT r.*, p.name pname, p.chart_no, (SELECT bill_no FROM bills b WHERE b.reg_id=r.id ORDER BY b.id DESC LIMIT 1) bill_no FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.status IN ('掛號','待批價','待領藥','結案') ORDER BY r.id DESC LIMIT 50")->fetchAll();
$logs = $pdo->query("SELECT f.*, r.reg_no, p.name pname FROM refunds f JOIN registrations r ON r.id=f.reg_id JOIN patients p ON p.id=r.patient_id ORDER BY f.id DESC LIMIT 30")->fetchAll();
?>
<div class="row g-3">
<div class="col-md-5"><div class="card shadow-sm"><div class="card-header fw-bold">退掛 / 退費</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="refund">
<div class="mb-2"><label class="form-label">掛號單</label>
<select name="reg_id" class="form-select"><?php foreach ($cands as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['reg_no'].' '.$c['chart_no'].' '.$c['pname'].' ['.$c['status'].']') ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">原因</label><input name="reason" class="form-control" value="病人要求退掛"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="restock" id="rs" checked><label class="form-check-label" for="rs">已發藥則回補庫存</label></div>
<button class="btn btn-danger w-100" onclick="return confirm('確認退掛退費？')">確認退掛退費</button></form>
<p class="small text-muted mt-2">未收費（掛號/待批價）可在下方直接退掛；已收費用此單退費並作廢帳單。</p>
</div></div></div>
<div class="col-md-7"><div class="card shadow-sm"><div class="card-header fw-bold">可退掛名單（近50筆）</div>
<table class="table table-sm table-hover mb-0"><tr><th>掛號單</th><th>病人</th><th>帳單</th><th>狀態</th><th></th></tr>
<?php foreach ($cands as $c): ?><tr><td><?= h($c['reg_no']) ?></td><td><?= h($c['pname']) ?></td><td><?= h($c['bill_no'] ?? '-') ?></td><td><?= h($c['status']) ?></td>
<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="receipt.php?reg_id=<?= $c['id'] ?>">收據</a>
<?php if (in_array($c['status'], ['掛號','待批價'], true)): ?><a class="btn btn-sm btn-outline-danger" href="refund.php?cancel=<?= $c['id'] ?>" onclick="return confirm('直接退掛？')">退掛</a><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">退掛退費紀錄（近30筆）</div>
<table class="table table-sm mb-0"><tr><th>時間</th><th>掛號單</th><th>金額</th><th>回補</th><th>原因/人員</th></tr>
<?php foreach ($logs as $l): ?><tr><td><?= h($l['created_at']) ?></td><td><?= h($l['reg_no'].' '.$l['pname']) ?></td><td><?= number_format($l['amount']) ?></td><td><?= (int)$l['restock'] ? '是' : '否' ?></td><td><?= h($l['reason'].' / '.$l['created_by']) ?></td></tr><?php endforeach; ?></table></div>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
