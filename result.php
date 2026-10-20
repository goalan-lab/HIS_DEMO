<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$oid = (int)($_GET['order_id'] ?? 0);
$o = $pdo->query("SELECT o.*, i.name, p1.name rname, p2.name aname, r.reg_no, a.adm_no FROM exam_orders o JOIN exam_items i ON i.id=o.item_id LEFT JOIN registrations r ON r.id=o.reg_id LEFT JOIN patients p1 ON p1.id=r.patient_id LEFT JOIN admissions a ON a.id=o.admission_id LEFT JOIN patients p2 ON p2.id=a.patient_id WHERE o.id=$oid")->fetch();
if (!$o) { flash_set('找不到檢驗單'); redirect('lab.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO exam_results(order_id,result_text,abnormal,reported_by,reported_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE result_text=VALUES(result_text),abnormal=VALUES(abnormal),reported_by=VALUES(reported_by),reported_at=NOW()')
        ->execute([$oid, trim($_POST['result_text'] ?? ''), isset($_POST['abnormal']) ? 1 : 0, $_SESSION['user']['username'] ?? '']);
    $pdo->prepare("UPDATE exam_orders SET status='已發報告' WHERE id=?")->execute([$oid]);
    flash_set('報告已發出');
    redirect('lab.php');
}
$old = $pdo->query("SELECT * FROM exam_results WHERE order_id=$oid")->fetch();
?>
<div class="card shadow-sm" style="max-width:640px"><div class="card-header fw-bold">發報告 — <?= h($o['order_no'].' '.$o['name']) ?></div><div class="card-body">
<p>來源：<?= h($o['reg_no'] ? $o['reg_no'].' '.$o['rname'] : ($o['adm_no'] ? $o['adm_no'].' '.$o['aname'] : '-')) ?>　狀態：<?= h($o['status']) ?></p>
<form method="post">
<div class="mb-2"><label class="form-label">結果</label><textarea name="result_text" class="form-control" rows="5" placeholder="檢驗數值 / 影像所見 / 判讀"><?= h($old['result_text'] ?? '') ?></textarea></div>
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="abnormal" id="ab" <?= ($old['abnormal'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label text-danger" for="ab">異常（會紅字警示）</label></div>
<button class="btn btn-success">發出報告</button> <a class="btn btn-secondary" href="lab.php">回檢驗單</a></form></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
