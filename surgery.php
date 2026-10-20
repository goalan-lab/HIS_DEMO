<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'add') {
    $room = (int)$_POST['op_room_id'];
    $date = $_POST['op_date'];
    $st = $_POST['start_time'] ?: null;
    $et = $_POST['end_time'] ?: null;
    // 刀房衝突檢查：同房同日時間重疊
    if ($room && $date && $st && $et) {
        $chk = $pdo->prepare("SELECT COUNT(*) c FROM surgeries WHERE op_room_id=? AND op_date=? AND status!='取消' AND start_time IS NOT NULL AND end_time IS NOT NULL AND NOT (end_time<=? OR start_time>=?)");
        $chk->execute([$room, $date, $st, $et]);
        if ((int)$chk->fetch()['c'] > 0) { flash_set('該刀房此時段已被預約'); redirect('surgery.php'); }
    }
    $no = gen_no($pdo, 'O', 'surgeries', 'op_no');
    $pdo->prepare('INSERT INTO surgeries(op_no,admission_id,patient_id,doctor_id,op_room_id,op_name,op_date,start_time,end_time,anesthesia,fee,note) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$no, $_POST['admission_id'] ?: null, (int)$_POST['patient_id'], (int)$_POST['doctor_id'], $room ?: null, trim($_POST['op_name']), $date, $st, $et, $_POST['anesthesia'], (int)$_POST['fee'], trim($_POST['note'] ?? '')]);
    flash_set('手術已排程：' . $no);
    redirect('surgery.php');
}
$flow = ['術前準備' => 'prep', '手術中' => 'start', '恢復中' => 'recover', '完成' => 'done'];
$back_date = $_GET['date'] ?? today();
foreach (['prep' => '術前準備', 'start' => '手術中', 'recover' => '恢復中', 'done' => '完成'] as $k => $v) {
    if (isset($_GET[$k])) { $pdo->prepare('UPDATE surgeries SET status=? WHERE id=?')->execute([$v, (int)$_GET[$k]]); redirect('surgery.php?date=' . $back_date); }
}
if (isset($_GET['cancel'])) { $pdo->prepare("UPDATE surgeries SET status='取消' WHERE id=?")->execute([(int)$_GET['cancel']]); redirect('surgery.php?date=' . $back_date); }
$date = $_GET['date'] ?? today();
$rows = $pdo->query("SELECT s.*, p.name pname, p.chart_no, d.name dname, r.name rname, a.adm_no FROM surgeries s JOIN patients p ON p.id=s.patient_id LEFT JOIN doctors d ON d.id=s.doctor_id LEFT JOIN op_rooms r ON r.id=s.op_room_id LEFT JOIN admissions a ON a.id=s.admission_id WHERE s.op_date=" . $pdo->quote($date) . " ORDER BY s.start_time, s.id")->fetchAll();
$upcoming = $pdo->query("SELECT s.*, p.name pname FROM surgeries s JOIN patients p ON p.id=s.patient_id WHERE s.op_date>=CURDATE() AND s.status IN ('排程','術前準備') ORDER BY s.op_date, s.start_time LIMIT 20")->fetchAll();
$allp = $pdo->query('SELECT id,chart_no,name FROM patients ORDER BY id DESC LIMIT 200')->fetchAll();
$docs = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();
$rooms = $pdo->query('SELECT * FROM op_rooms WHERE active=1 ORDER BY id')->fetchAll();
$adms = $pdo->query("SELECT a.id, a.adm_no, p.name FROM admissions a JOIN patients p ON p.id=a.patient_id WHERE a.status='住院中' ORDER BY a.id DESC LIMIT 50")->fetchAll();
?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-bold">新增手術排程</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="add">
<div class="mb-2"><label class="form-label">病人*</label><select name="patient_id" class="form-select"><?php foreach ($allp as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['chart_no'].' '.$p['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">住院單（可不選）</label><select name="admission_id" class="form-select"><option value="">-- 無 --</option><?php foreach ($adms as $a): ?><option value="<?= $a['id'] ?>"><?= h($a['adm_no'].' '.$a['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">術式*</label><input name="op_name" class="form-control" placeholder="闌尾切除術" required></div>
<div class="row g-2"><div class="col"><label class="form-label">主刀</label><select name="doctor_id" class="form-select"><?php foreach ($docs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><label class="form-label">刀房</label><select name="op_room_id" class="form-select"><?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">日期</label><input type="date" name="op_date" class="form-control" value="<?= today() ?>"></div>
<div class="col"><label class="form-label">麻醉</label><select name="anesthesia" class="form-select"><option>局部</option><option>半身</option><option>全身</option><option>無</option></select></div></div>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">開始</label><input type="time" name="start_time" class="form-control" value="09:00"></div>
<div class="col"><label class="form-label">結束</label><input type="time" name="end_time" class="form-control" value="10:00"></div></div>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">手術費</label><input name="fee" type="number" value="20000" min="0" class="form-control"></div>
<div class="col"><label class="form-label">備註</label><input name="note" class="form-control"></div></div>
<button class="btn btn-primary w-100 mt-2">確認排程</button></form></div></div></div>
<div class="col-md-8">
<div class="card shadow-sm"><div class="card-header fw-bold d-flex justify-content-between">當日刀表
<form class="d-flex gap-2" method="get"><input type="date" name="date" value="<?= h($date) ?>" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-primary">查詢</button></form></div>
<table class="table table-sm table-hover mb-0"><tr><th>時間</th><th>刀房</th><th>術式/病人</th><th>主刀</th><th>費用</th><th>狀態</th><th></th></tr>
<?php foreach ($rows as $s): ?><tr>
<td><?= h(($s['start_time'] ?? '-') . '~' . ($s['end_time'] ?? '-')) ?></td><td><?= h($s['rname'] ?? '-') ?></td>
<td><?= h($s['op_name']) ?><br><small><?= h($s['pname'] . ($s['adm_no'] ? ' '.$s['adm_no'] : '')) ?></small></td>
<td><?= h($s['dname']) ?></td><td><?= number_format($s['fee']) ?></td><td><?= h($s['status']) ?></td>
<td class="text-nowrap"><?php if ($s['status']==='排程'): ?><a class="btn btn-sm btn-outline-primary" href="surgery.php?prep=<?= $s['id'] ?>&date=<?= h($date) ?>">術前</a><?php endif; ?>
<?php if ($s['status']==='術前準備'): ?><a class="btn btn-sm btn-warning" href="surgery.php?start=<?= $s['id'] ?>&date=<?= h($date) ?>">開刀</a><?php endif; ?>
<?php if ($s['status']==='手術中'): ?><a class="btn btn-sm btn-info" href="surgery.php?recover=<?= $s['id'] ?>&date=<?= h($date) ?>">恢復</a><?php endif; ?>
<?php if ($s['status']==='恢復中'): ?><a class="btn btn-sm btn-success" href="surgery.php?done=<?= $s['id'] ?>&date=<?= h($date) ?>">完成</a><?php endif; ?>
<?php if (!in_array($s['status'], ['完成','取消'], true)): ?><a class="btn btn-sm btn-outline-danger" href="surgery.php?cancel=<?= $s['id'] ?>&date=<?= h($date) ?>" onclick="return confirm('取消手術？')">取消</a><?php endif; ?></td></tr><?php endforeach; ?></table>
<?php if (!$rows): ?><div class="card-body text-muted">當日無排程。</div><?php endif; ?></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">未來排程（20筆）</div>
<table class="table table-sm mb-0"><tr><th>日期</th><th>時間</th><th>術式</th><th>病人</th><th>狀態</th></tr>
<?php foreach ($upcoming as $s): ?><tr><td><?= h($s['op_date']) ?></td><td><?= h($s['start_time'] ?? '-') ?></td><td><?= h($s['op_name']) ?></td><td><?= h($s['pname']) ?></td><td><?= h($s['status']) ?></td></tr><?php endforeach; ?></table></div>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
