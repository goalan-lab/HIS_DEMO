<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
function stock_apply(PDO $pdo, int $drug_id, string $type, int $qty, string $ref_type = '', ?int $ref_id = null, string $note = ''): void {
    $d = $pdo->query("SELECT stock FROM drugs WHERE id=$drug_id")->fetch();
    $before = (int)$d['stock'];
    $after = max(0, $before + $qty);
    $pdo->prepare('UPDATE drugs SET stock=? WHERE id=?')->execute([$after, $drug_id]);
    $pdo->prepare('INSERT INTO stock_txns(drug_id,txn_type,qty,before_stock,after_stock,ref_type,ref_id,note,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute([$drug_id, $type, $qty, $before, $after, $ref_type, $ref_id, $note, $_SESSION['user']['username'] ?? '']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'new_drug') {
        $pdo->prepare('INSERT INTO drugs(code,name,spec,unit,price,stock,safety_stock,expiry) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([trim($_POST['code']), trim($_POST['name']), trim($_POST['spec'] ?? ''), trim($_POST['unit'] ?? '顆'), (int)$_POST['price'], (int)$_POST['stock'], (int)$_POST['safety_stock'], $_POST['expiry'] ?: null]);
        flash_set('藥品已建檔');
    } elseif ($act === 'purchase') {
        stock_apply($pdo, (int)$_POST['drug_id'], '進貨', max(1,(int)$_POST['qty']), 'purchase', null, trim($_POST['note'] ?? ''));
        flash_set('已進貨');
    } elseif ($act === 'discard') {
        stock_apply($pdo, (int)$_POST['drug_id'], '報廢', -max(1,(int)$_POST['qty']), 'discard', null, trim($_POST['note'] ?? ''));
        flash_set('已報廢');
    } elseif ($act === 'count') {
        $sid = (int)$_POST['st_id'];
        foreach ($_POST['cnt'] as $item_id => $c) {
            $c = ($c === '' || $c === null) ? null : (int)$c;
            $pdo->prepare('UPDATE stocktake_items SET counted_qty=?, diff=' . ($c === null ? 'NULL' : "$c-sys_qty") . ' WHERE id=?')->execute([$c, $item_id]);
        }
        flash_set('盤點數已暫存');
        redirect('inventory.php?st_id=' . $sid);
    }
    redirect('inventory.php');
}
if (isset($_GET['new_st'])) {
    $no = gen_no($pdo, 'S', 'stocktakes', 'st_no');
    $pdo->prepare('INSERT INTO stocktakes(st_no,created_by) VALUES(?,?)')->execute([$no, $_SESSION['user']['username'] ?? '']);
    $sid = (int)$pdo->lastInsertId();
    foreach ($pdo->query('SELECT id, stock FROM drugs')->fetchAll() as $d) {
        $pdo->prepare('INSERT INTO stocktake_items(stocktake_id,drug_id,sys_qty) VALUES(?,?,?)')->execute([$sid, $d['id'], $d['stock']]);
    }
    redirect('inventory.php?st_id=' . $sid);
}
if (isset($_GET['post_st'])) {
    $sid = (int)$_GET['post_st'];
    $pdo->beginTransaction();
    foreach ($pdo->query("SELECT i.*, d.stock FROM stocktake_items i JOIN drugs d ON d.id=i.drug_id WHERE i.stocktake_id=$sid AND i.counted_qty IS NOT NULL")->fetchAll() as $it) {
        $diff = (int)$it['counted_qty'] - (int)$it['sys_qty'];
        if ($diff !== 0) { stock_apply($pdo, (int)$it['drug_id'], '盤點調整', $diff, 'stocktake', $sid, '盤點單'); }
    }
    $pdo->prepare("UPDATE stocktakes SET status='已入帳' WHERE id=?")->execute([$sid]);
    $pdo->commit();
    flash_set('盤點差異已入帳');
    redirect('inventory.php');
}
$drugs = $pdo->query('SELECT * FROM drugs ORDER BY stock ASC')->fetchAll();
$txns = $pdo->query('SELECT t.*, g.code, g.name FROM stock_txns t JOIN drugs g ON g.id=t.drug_id ORDER BY t.id DESC LIMIT 30')->fetchAll();
$st_id = (int)($_GET['st_id'] ?? 0);
$items = $st_id ? $pdo->query("SELECT i.*, g.code, g.name FROM stocktake_items i JOIN drugs g ON g.id=i.drug_id WHERE i.stocktake_id=$st_id ORDER BY i.id")->fetchAll() : [];
$st = $st_id ? $pdo->query("SELECT * FROM stocktakes WHERE id=$st_id")->fetch() : null;
?>
<div class="row g-3">
<div class="col-md-4">
<div class="card shadow-sm"><div class="card-header fw-bold">新增藥品</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="new_drug">
<div class="row g-2"><div class="col"><input name="code" class="form-control" placeholder="代碼 D006" required></div><div class="col"><input name="name" class="form-control" placeholder="品名" required></div></div>
<div class="row g-2 mt-1"><div class="col"><input name="spec" class="form-control" placeholder="規格"></div><div class="col"><input name="unit" class="form-control" value="顆"></div></div>
<div class="row g-2 mt-1"><div class="col"><input name="price" type="number" class="form-control" value="10"></div><div class="col"><input name="stock" type="number" class="form-control" value="100"></div></div>
<div class="row g-2 mt-1"><div class="col"><input name="safety_stock" type="number" class="form-control" value="50"></div><div class="col"><input name="expiry" type="date" class="form-control"></div></div>
<button class="btn btn-primary w-100 mt-2">建檔</button></form></div></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">進貨 / 報廢</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="purchase">
<select name="drug_id" class="form-select mb-2"><?php foreach ($drugs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['code'].' 庫'.$d['stock']) ?></option><?php endforeach; ?></select>
<div class="row g-2"><div class="col"><input name="qty" type="number" value="100" min="1" class="form-control"></div><div class="col"><input name="note" class="form-control" placeholder="備註"></div></div>
<button class="btn btn-success w-100 mt-2">進貨入庫</button></form>
<form method="post" class="mt-2"><input type="hidden" name="act" value="discard">
<select name="drug_id" class="form-select mb-2"><?php foreach ($drugs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['code'].' '.$d['name']) ?></option><?php endforeach; ?></select>
<div class="row g-2"><div class="col"><input name="qty" type="number" value="1" min="1" class="form-control"></div><div class="col"><input name="note" class="form-control" placeholder="報廢原因"></div></div>
<button class="btn btn-outline-danger w-100 mt-2">報廢出庫</button></form></div></div>
</div>
<div class="col-md-8">
<div class="card shadow-sm"><div class="card-header fw-bold d-flex justify-content-between">藥品庫存 <a class="btn btn-sm btn-warning" href="inventory.php?new_st=1">開新盤點單</a></div>
<table class="table table-sm table-hover mb-0"><tr><th>代碼</th><th>品名</th><th>單價</th><th>庫存/安全</th><th>效期</th></tr>
<?php foreach ($drugs as $d): ?><tr class="<?= $d['stock'] <= $d['safety_stock'] ? 'table-warning' : '' ?>">
<td><?= h($d['code']) ?></td><td><?= h($d['name']) ?></td><td><?= number_format($d['price']) ?></td>
<td><?= $d['stock'] ?> / <?= $d['safety_stock'] ?><?= $d['stock'] <= $d['safety_stock'] ? ' ⚠' : '' ?></td><td><?= h($d['expiry'] ?? '-') ?></td></tr><?php endforeach; ?></table></div>
<?php if ($st): ?><div class="card shadow-sm mt-3"><div class="card-header fw-bold">盤點單 <?= h($st['st_no']) ?>（<?= h($st['status']) ?>）
<?php if ($st['status'] === '盤點中'): ?><a class="btn btn-sm btn-success" href="inventory.php?post_st=<?= $st['id'] ?>" onclick="return confirm('差異入帳？')">差異入帳</a><?php endif; ?></div>
<form method="post"><input type="hidden" name="act" value="count"><input type="hidden" name="st_id" value="<?= $st['id'] ?>">
<table class="table table-sm mb-0"><tr><th>藥品</th><th>帳面</th><th>實盤</th><th>差異</th></tr>
<?php foreach ($items as $it): ?><tr><td><?= h($it['code'].' '.$it['name']) ?></td><td><?= $it['sys_qty'] ?></td>
<td><input name="cnt[<?= $it['id'] ?>]" type="number" class="form-control form-control-sm" value="<?= $it['counted_qty'] ?? '' ?>"></td>
<td><?= $it['diff'] ?? '-' ?></td></tr><?php endforeach; ?></table>
<?php if ($st['status'] === '盤點中'): ?><div class="card-body"><button class="btn btn-primary">暫存盤點數</button></div><?php endif; ?></form></div><?php endif; ?>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">進銷存流水（近30筆）</div>
<table class="table table-sm mb-0"><tr><th>時間</th><th>藥品</th><th>類型</th><th>數量</th><th>前→後</th><th>備註</th></tr>
<?php foreach ($txns as $t): ?><tr><td><?= h($t['created_at']) ?></td><td><?= h($t['code']) ?></td><td><?= h($t['txn_type']) ?></td>
<td><?= ($t['qty'] > 0 ? '+' : '') . $t['qty'] ?></td><td><?= $t['before_stock'] ?>→<?= $t['after_stock'] ?></td><td><?= h($t['note'] ?? '') ?></td></tr><?php endforeach; ?></table></div>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
