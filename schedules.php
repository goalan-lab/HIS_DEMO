<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$wd_names = ['日','一','二','三','四','五','六'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
    $st = $pdo->prepare('INSERT INTO schedules(doctor_id,weekday,slot,room,quota,active) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE room=VALUES(room),quota=VALUES(quota),active=VALUES(active)');
    $st->execute([(int)$_POST['doctor_id'], (int)$_POST['weekday'], $_POST['slot'], trim($_POST['room'] ?? ''), max(1,(int)$_POST['quota']), isset($_POST['active']) ? 1 : 0]);
    flash_set('班表已儲存');
    redirect('schedules.php');
}
if (isset($_GET['toggle'])) {
    $pdo->prepare('UPDATE schedules SET active=1-active WHERE id=?')->execute([(int)$_GET['toggle']]);
    redirect('schedules.php');
}
$doctors = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();
$scheds = $pdo->query('SELECT s.*, d.name dname, d.dept FROM schedules s JOIN doctors d ON d.id=s.doctor_id ORDER BY s.weekday, s.slot, d.id')->fetchAll();
?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-bold">新增 / 修改班表</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="save">
<div class="mb-2"><label class="form-label">醫師</label><select name="doctor_id" class="form-select"><?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['dept'].' '.$d['name']) ?></option><?php endforeach; ?></select></div>
<div class="row g-2"><div class="col"><label class="form-label">星期</label><select name="weekday" class="form-select"><?php foreach ($wd_names as $i=>$w): ?><option value="<?= $i ?>">週<?= $w ?></option><?php endforeach; ?></select></div>
<div class="col"><label class="form-label">診別</label><select name="slot" class="form-select"><option>早診</option><option>午診</option><option>晚診</option></select></div></div>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">診間</label><input name="room" class="form-control" value="101"></div>
<div class="col"><label class="form-label">限額</label><input name="quota" type="number" class="form-control" value="30" min="1"></div></div>
<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="active" checked id="ac"><label class="form-check-label" for="ac">開診</label></div>
<button class="btn btn-primary w-100 mt-2">儲存</button></form></div></div></div>
<div class="col-md-8"><div class="card shadow-sm"><div class="card-header fw-bold">週班表（54 筆預設，週日休診）</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0"><tr><th>星期</th><th>診別</th><th>醫師</th><th>診間</th><th>限額</th><th>狀態</th><th></th></tr>
<?php foreach ($scheds as $s): ?><tr class="<?= (int)$s['active'] ? '' : 'table-secondary' ?>">
<td>週<?= $wd_names[$s['weekday']] ?></td><td><?= h($s['slot']) ?></td><td><?= h($s['dept'].' '.$s['dname']) ?></td>
<td><?= h($s['room'] ?? '-') ?></td><td><?= $s['quota'] ?></td><td><?= (int)$s['active'] ? '開診' : '停診' ?></td>
<td><a class="btn btn-sm btn-outline-secondary" href="schedules.php?toggle=<?= $s['id'] ?>">開/停</a></td></tr><?php endforeach; ?></table></div></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
