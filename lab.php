<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
// 開單（門診掛號 / 住院二選一）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'order') {
    $reg_id = $_POST['reg_id'] ? (int)$_POST['reg_id'] : null;
    $adm_id = $_POST['admission_id'] ? (int)$_POST['admission_id'] : null;
    if (!$reg_id && !$adm_id) { flash_set('請選擇掛號單或住院單'); redirect('lab.php'); }
    $it = $pdo->query('SELECT * FROM exam_items WHERE id=' . (int)$_POST['item_id'])->fetch();
    $no = gen_no($pdo, 'L', 'exam_orders', 'order_no');
    $pdo->prepare('INSERT INTO exam_orders(order_no,reg_id,admission_id,item_id,qty,price,ordered_by) VALUES(?,?,?,?,?,?,?)')
        ->execute([$no, $reg_id, $adm_id, $it['id'], max(1,(int)$_POST['qty']), (int)$it['price'], $_SESSION['user']['username'] ?? '']);
    flash_set('已開單：' . $no . ' ' . $it['name']);
    redirect('lab.php');
}
// 狀態推進
foreach (['collect' => '檢體採集', 'testing' => '檢驗中'] as $k => $v) {
    if (isset($_GET[$k])) { $pdo->prepare('UPDATE exam_orders SET status=? WHERE id=?')->execute([$v, (int)$_GET[$k]]); redirect('lab.php'); }
}
if (isset($_GET['cancel'])) { $pdo->prepare("UPDATE exam_orders SET status='取消' WHERE id=?")->execute([(int)$_GET['cancel']]); redirect('lab.php'); }
// 發報告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'report') {
    $oid = (int)$_POST['order_id'];
    $pdo->prepare('INSERT INTO exam_results(order_id,result_text,abnormal,reported_by,reported_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE result_text=VALUES(result_text),abnormal=VALUES(abnormal),reported_by=VALUES(reported_by),reported_at=NOW()')
        ->execute([$oid, trim($_POST['result_text'] ?? ''), isset($_POST['abnormal']) ? 1 : 0, $_SESSION['user']['username'] ?? '']);
    $pdo->prepare("UPDATE exam_orders SET status='已發報告' WHERE id=?")->execute([$oid]);
    flash_set('報告已發出');
    redirect('lab.php');
}
$items = $pdo->query("SELECT * FROM exam_items WHERE active=1 ORDER BY category, code")->fetchAll();
$regs = $pdo->query("SELECT r.id, r.reg_no, p.name FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.status NOT IN ('退掛') ORDER BY r.id DESC LIMIT 50")->fetchAll();
$adms = $pdo->query("SELECT a.id, a.adm_no, p.name FROM admissions a JOIN patients p ON p.id=a.patient_id WHERE a.status='住院中' ORDER BY a.id DESC LIMIT 50")->fetchAll();
$orders = $pdo->query("SELECT o.*, i.code, i.category, i.name, p1.name rname, p2.name aname, r.reg_no, a.adm_no, e.result_text, e.abnormal FROM exam_orders o JOIN exam_items i ON i.id=o.item_id LEFT JOIN registrations r ON r.id=o.reg_id LEFT JOIN patients p1 ON p1.id=r.patient_id LEFT JOIN admissions a ON a.id=o.admission_id LEFT JOIN patients p2 ON p2.id=a.patient_id LEFT JOIN exam_results e ON e.order_id=o.id ORDER BY o.id DESC LIMIT 50")->fetchAll();
$osum = $pdo->query("SELECT IFNULL(SUM(price*qty),0) s FROM exam_orders WHERE status!='取消'")->fetch()['s'];
?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-bold">開檢驗檢查單</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="order">
<div class="mb-2"><label class="form-label">項目</label><select name="item_id" class="form-select"><?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= h('['.$it['category'].'] '.$it['code'].' '.$it['name'].' $'.$it['price']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">門診掛號（可不選）</label><select name="reg_id" class="form-select"><option value="">-- 無 --</option><?php foreach ($regs as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['reg_no'].' '.$r['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">住院單（可不選）</label><select name="admission_id" class="form-select"><option value="">-- 無 --</option><?php foreach ($adms as $a): ?><option value="<?= $a['id'] ?>"><?= h($a['adm_no'].' '.$a['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">數量</label><input name="qty" type="number" value="1" min="1" class="form-control"></div>
<button class="btn btn-primary w-100">開單</button></form>
<p class="small text-muted mt-2">開單金額合計（未取消）：<b><?= number_format($osum) ?></b> 元，可併門診帳單或住院結算收費。</p></div></div></div>
<div class="col-md-8"><div class="card shadow-sm"><div class="card-header fw-bold">檢驗檢查單（近50筆）</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0"><tr><th>單號</th><th>項目</th><th>來源</th><th>金額</th><th>狀態</th><th></th></tr>
<?php foreach ($orders as $o): ?><tr class="<?= (int)($o['abnormal'] ?? 0) ? 'table-danger' : '' ?>">
<td><?= h($o['order_no']) ?></td><td><?= h('['.$o['category'].'] '.$o['name']) ?></td>
<td><?= h($o['reg_no'] ? $o['reg_no'].' '.$o['rname'] : ($o['adm_no'] ? $o['adm_no'].' '.$o['aname'] : '-')) ?></td>
<td><?= number_format($o['price']*$o['qty']) ?></td><td><?= h($o['status']) ?><?= $o['result_text'] ? '<br><small>'.h(mb_substr($o['result_text'],0,20)).'</small>' : '' ?></td>
<td class="text-nowrap">
<?php if ($o['status']==='開立'): ?><a class="btn btn-sm btn-outline-primary" href="lab.php?collect=<?= $o['id'] ?>">採檢</a><?php endif; ?>
<?php if ($o['status']==='檢體採集'): ?><a class="btn btn-sm btn-outline-warning" href="lab.php?testing=<?= $o['id'] ?>">檢驗中</a><?php endif; ?>
<?php if (in_array($o['status'], ['檢體採集','檢驗中'], true)): ?><a class="btn btn-sm btn-success" href="result.php?order_id=<?= $o['id'] ?>">發報告</a><?php endif; ?>
<?php if ($o['status'] !== '取消' && $o['status'] !== '已發報告'): ?><a class="btn btn-sm btn-outline-danger" href="lab.php?cancel=<?= $o['id'] ?>" onclick="return confirm('取消此單？')">取消</a><?php endif; ?>
</td></tr><?php endforeach; ?></table></div></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
