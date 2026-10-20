<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$aid = (int)($_GET['adm_id'] ?? 0);
$a = $pdo->query("SELECT a.*, p.name pname FROM admissions a JOIN patients p ON p.id=a.patient_id WHERE a.id=$aid")->fetch();
if (!$a) { flash_set('找不到住院單'); redirect('ipd.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'add') {
    $type = $_POST['order_type'];
    $did = $_POST['drug_id'] ? (int)$_POST['drug_id'] : null;
    $name = trim($_POST['item_name'] ?? '');
    $price = (int)$_POST['price'];
    if ($did) { $g = $pdo->query("SELECT name, price FROM drugs WHERE id=$did")->fetch(); $name = $g['name']; $price = (int)$g['price']; }
    $pdo->prepare('INSERT INTO ipd_orders(admission_id,order_type,drug_id,item_name,qty,price,note) VALUES(?,?,?,?,?,?,?)')
        ->execute([$aid, $type, $did, $name, max(1,(int)$_POST['qty']), $price, trim($_POST['note'] ?? '')]);
    flash_set('醫囑已開立');
    redirect('orders.php?adm_id=' . $aid);
}
if (isset($_GET['done'])) { $pdo->prepare("UPDATE ipd_orders SET status='完成' WHERE id=?")->execute([(int)$_GET['done']]); redirect('orders.php?adm_id=' . $aid); }
if (isset($_GET['cancel'])) { $pdo->prepare("UPDATE ipd_orders SET status='取消' WHERE id=?")->execute([(int)$_GET['cancel']]); redirect('orders.php?adm_id=' . $aid); }
$ords = $pdo->query("SELECT * FROM ipd_orders WHERE admission_id=$aid ORDER BY id DESC")->fetchAll();
$drugs = $pdo->query('SELECT * FROM drugs ORDER BY id')->fetchAll();
?>
<div class="card shadow-sm"><div class="card-header fw-bold">醫囑 — <?= h($a['adm_no'].' '.$a['pname']) ?> <a class="btn btn-sm btn-secondary" href="ipd.php?adm_id=<?= $aid ?>">回住院單</a></div><div class="card-body">
<form method="post" class="row g-2"><input type="hidden" name="act" value="add">
<div class="col-md-2"><select name="order_type" class="form-select"><option>藥品</option><option>檢查</option><option>處置</option><option>護理</option></select></div>
<div class="col-md-3"><select name="drug_id" class="form-select"><option value="">-- 藥品選單（選了自動帶名/價） --</option><?php foreach ($drugs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name'].' $'.$d['price']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input name="item_name" class="form-control" placeholder="項目名（非藥品填）"></div>
<div class="col-md-1"><input name="qty" type="number" value="1" min="1" class="form-control"></div>
<div class="col-md-1"><input name="price" type="number" value="0" min="0" class="form-control" placeholder="單價"></div>
<div class="col-md-2"><input name="note" class="form-control" placeholder="備註"></div>
<div class="col-md-1"><button class="btn btn-primary w-100">開立</button></div></form></div>
<table class="table table-sm table-hover mb-0"><tr><th>類型/項目</th><th>數量</th><th>金額</th><th>狀態</th><th></th></tr>
<?php foreach ($ords as $o): ?><tr><td><?= h('['.$o['order_type'].'] '.$o['item_name']) ?></td><td><?= $o['qty'] ?></td><td><?= number_format($o['price']*$o['qty']) ?></td><td><?= h($o['status']) ?></td>
<td><?php if ($o['status']==='開立'): ?><a class="btn btn-sm btn-success" href="orders.php?adm_id=<?= $aid ?>&done=<?= $o['id'] ?>">完成</a>
<a class="btn btn-sm btn-outline-danger" href="orders.php?adm_id=<?= $aid ?>&cancel=<?= $o['id'] ?>">取消</a><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
