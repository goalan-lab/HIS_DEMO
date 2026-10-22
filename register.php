<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$doctors = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();
$sel_patient = null;
if (!empty($_GET['patient_id'])) { $st = $pdo->prepare('SELECT * FROM patients WHERE id=?'); $st->execute([$_GET['patient_id']]); $sel_patient = $st->fetch(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reg') {
    if (!can('register.reg')) { flash_set('您的角色無掛號權限'); redirect('register.php'); }
    $pid = (int)$_POST['patient_id'];
    $vdate = $_POST['visit_date'] ?: today();
    $vslot = $_POST['slot'];
    $vdid = $_POST['doctor_id'] ? (int)$_POST['doctor_id'] : null;
    // 班表檢查：有班＋開診＋未額滿
    if ($vdid) {
        $wd = (int)date('w', strtotime($vdate));
        $st = $pdo->prepare('SELECT * FROM schedules WHERE doctor_id=? AND weekday=? AND slot=? LIMIT 1');
        $st->execute([$vdid, $wd, $vslot]); $sch = $st->fetch();
        if (!$sch) { flash_set('該醫師此時段無班表，不可掛號'); redirect('register.php'); }
        if (!(int)$sch['active']) { flash_set('該時段停診，不可掛號'); redirect('register.php'); }
        $cnt = $pdo->query("SELECT COUNT(*) c FROM registrations WHERE doctor_id=$vdid AND visit_date=" . $pdo->quote($vdate) . " AND slot=" . $pdo->quote($vslot) . " AND status!='退掛'")->fetch()['c'];
        if ($cnt >= (int)$sch['quota']) { flash_set('該時段已額滿（' . $sch['quota'] . '）'); redirect('register.php'); }
    }
    $reg_no = gen_no($pdo, 'R', 'registrations', 'reg_no');
    // 診間號碼：同醫師同日同時段最大號+1
    $qno = 1;
    if ($vdid) {
        $mx = $pdo->query("SELECT MAX(queue_no) m FROM registrations WHERE doctor_id=$vdid AND visit_date=" . $pdo->quote($vdate) . " AND slot=" . $pdo->quote($vslot))->fetch()['m'];
        $qno = ((int)$mx) + 1;
    }
    $st = $pdo->prepare('INSERT INTO registrations(reg_no,patient_id,visit_date,slot,dept,doctor_id,queue_no,status,reg_fee,exam_fee,ins_type,copay,nhi_claim) VALUES(?,?,?,?,?,?,?,?, ?,?,?,?,?)');
    $ins = $_POST['ins_type'] ?? '健保';
    $copay = $ins === '健保' ? 150 : 0;
    if ($ins === '自費') { $copay = 0; }
    $st->execute([$reg_no, $pid, $vdate, $vslot, $_POST['dept'], $vdid, $qno, '掛號', DEFAULT_REG_FEE, DEFAULT_EXAM_FEE, $ins, $copay, $ins === '自費' ? 0 : 1]);
    $reg_id = (int)$pdo->lastInsertId();
    flash_set('掛號成功：' . $reg_no . '（' . $vslot . '第 ' . $qno . ' 號）');
    redirect('register.php?reg_id=' . $reg_id);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'rx') {
    if (!can('register.rx')) { flash_set('您的角色無開處方權限'); redirect('register.php'); }
    $reg_id = (int)$_POST['reg_id'];
    $st = $pdo->prepare('INSERT INTO prescriptions(reg_id,drug_id,qty,usage_note) VALUES(?,?,?,?)');
    $st->execute([$reg_id, (int)$_POST['drug_id'], max(1,(int)$_POST['qty']), trim($_POST['usage_note'] ?? '')]);
    $pdo->prepare("UPDATE registrations SET status='待批價' WHERE id=?")->execute([$reg_id]);
    flash_set('已加入處方');
    redirect('register.php?reg_id=' . $reg_id);
}
if (isset($_GET['done'])) {
    if (!can('register.done')) { flash_set('您的角色無送批價權限'); redirect('register.php'); }
    $pdo->prepare("UPDATE registrations SET status='待批價' WHERE id=?")->execute([(int)$_GET['done']]);
    flash_set('看診完成，已送批價');
    redirect('billing.php');
}
$reg = null; $rxs = [];
if (!empty($_GET['reg_id'])) {
    $st = $pdo->prepare('SELECT r.*, p.name pname, p.chart_no FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE r.id=?');
    $st->execute([$_GET['reg_id']]); $reg = $st->fetch();
    if ($reg) {
        $st = $pdo->prepare('SELECT pr.*, d.name dname, d.price FROM prescriptions pr JOIN drugs d ON d.id=pr.drug_id WHERE pr.reg_id=? ORDER BY pr.id');
        $st->execute([$reg['id']]); $rxs = $st->fetchAll();
    }
}
$allp = $pdo->query('SELECT id,chart_no,name FROM patients ORDER BY id DESC LIMIT 200')->fetchAll();
$drugs = $pdo->query('SELECT * FROM drugs ORDER BY id')->fetchAll();
$today = $pdo->query("SELECT r.*, p.name pname FROM registrations r JOIN patients p ON p.id=r.patient_id WHERE visit_date=CURDATE() ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<div class="row g-3">
<div class="col-md-5"><?php if (can('register.reg')): ?><div class="card shadow-sm"><div class="card-header fw-bold">掛號</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="reg">
<div class="mb-2"><label class="form-label">病人 <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#nhiModal">📇 掃卡掛號</button></label>
<select name="patient_id" id="regPatientSelect" class="form-select" required>
<option value="">-- 選擇 --</option>
<?php foreach ($allp as $p): ?>
<option value="<?= $p['id'] ?>" <?= $sel_patient && $sel_patient['id']==$p['id']?'selected':'' ?>><?= h($p['chart_no'].' '.$p['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="row g-2"><div class="col"><label class="form-label">日期</label><input type="date" name="visit_date" class="form-control" value="<?= today() ?>"></div>
<div class="col"><label class="form-label">診別</label><select name="slot" class="form-select"><?php foreach (slot_options() as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div></div>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">科別</label><input name="dept" class="form-control" value="家醫科"></div>
<div class="col"><label class="form-label">醫師</label><select name="doctor_id" class="form-select"><?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['dept'].' '.$d['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="mt-2"><label class="form-label">身分別</label><select name="ins_type" class="form-select"><option>健保</option><option>自費</option><option>重大傷病</option><option>榮民</option><option>低收</option></select>
<div class="form-text">健保部分負擔預設 150；自費不申報。</div></div>
<button class="btn btn-primary w-100 mt-3">確認掛號</button></form></div></div><?php endif; ?></div>
<div class="col-md-7"><div class="card shadow-sm"><div class="card-header fw-bold">開處方 <?= $reg ? '— '.h($reg['reg_no'].' '.$reg['pname']) : '' ?></div><div class="card-body">
<?php if ($reg && can('register.rx')): ?>
<form method="post" class="row g-2"><input type="hidden" name="act" value="rx"><input type="hidden" name="reg_id" value="<?= $reg['id'] ?>">
<div class="col-md-5"><select name="drug_id" class="form-select"><?php foreach ($drugs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name'].' $'.$d['price']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input name="qty" type="number" value="3" min="1" class="form-control"></div>
<div class="col-md-3"><input name="usage_note" class="form-control" placeholder="用法 TID"></div>
<div class="col-md-2"><button class="btn btn-success w-100">加入</button></div></form>
<table class="table table-sm mt-3"><tr><th>藥品</th><th>數量</th><th>用法</th><th>金額</th></tr>
<?php $sum=0; foreach ($rxs as $x): $sum += $x['price']*$x['qty']; ?>
<tr><td><?= h($x['dname']) ?></td><td><?= $x['qty'] ?></td><td><?= h($x['usage_note']) ?></td><td><?= number_format($x['price']*$x['qty']) ?></td></tr>
<?php endforeach; ?></table>
<p>藥費小計 <b><?= number_format($sum) ?></b> 元</p>
<?php if (can('register.done')): ?><a class="btn btn-warning" href="register.php?done=<?= $reg['id'] ?>" onclick="return confirm('送批價？')">看診完成送批價</a><?php endif; ?>
<?php else: ?><p class="text-muted">先掛號或點右下隊列開方。</p><?php endif; ?>
</div></div>
<div class="card shadow-sm mt-3"><div class="card-header">今日隊列</div>
<table class="table table-sm table-hover mb-0"><tr><th>單號</th><th>病人</th><th>診別</th><th>狀態</th><th></th></tr>
<?php foreach ($today as $t): ?><tr><td><?= h($t['reg_no']) ?></td><td><?= h($t['pname']) ?></td><td><?= h($t['slot']) ?></td><td><?= h($t['status']) ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="register.php?reg_id=<?= $t['id'] ?>">開方</a></td></tr><?php endforeach; ?></table></div>
</div></div>
<?php $nhi_mode = 'register'; require_once __DIR__ . '/includes/nhi_modal.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
