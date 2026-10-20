<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$aid = (int)($_GET['adm_id'] ?? 0);
$a = $pdo->query("SELECT a.*, p.name pname FROM admissions a JOIN patients p ON p.id=a.patient_id WHERE a.id=$aid")->fetch();
if (!$a) { flash_set('找不到住院單'); redirect('ipd.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'add') {
    if (!can('nursing.add')) { flash_set('您的角色無寫護理紀錄權限'); redirect('ipd.php'); }
    $pdo->prepare('INSERT INTO nursing_notes(admission_id,vitals,content,nurse) VALUES(?,?,?,?)')
        ->execute([$aid, trim($_POST['vitals'] ?? ''), trim($_POST['content'] ?? ''), $_SESSION['user']['username'] ?? '']);
    flash_set('護理紀錄已儲存');
    redirect('nursing.php?adm_id=' . $aid);
}
$notes = $pdo->query("SELECT * FROM nursing_notes WHERE admission_id=$aid ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<div class="card shadow-sm"><div class="card-header fw-bold">護理紀錄 — <?= h($a['adm_no'].' '.$a['pname']) ?> <a class="btn btn-sm btn-secondary" href="ipd.php?adm_id=<?= $aid ?>">回住院單</a></div><div class="card-body">
<?php if (can('nursing.add')): ?>
<form method="post"><input type="hidden" name="act" value="add">
<div class="mb-2"><label class="form-label">生命徵象（T/P/R/BP）</label><input name="vitals" class="form-control" placeholder="36.8°C / 72 / 18 / 120/80"></div>
<div class="mb-2"><label class="form-label">紀錄內容</label><textarea name="content" class="form-control" rows="3" placeholder="病況、處置、交班事項"></textarea></div>
<button class="btn btn-info">儲存紀錄</button></form>
<?php endif; ?>
<table class="table table-sm mb-0"><tr><th>時間</th><th>生命徵象</th><th>內容</th><th>護理師</th></tr>
<?php foreach ($notes as $n): ?><tr><td><?= h($n['note_date']) ?></td><td><?= h($n['vitals']) ?></td><td><?= nl2br(h($n['content'])) ?></td><td><?= h($n['nurse']) ?></td></tr><?php endforeach; ?></table></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
