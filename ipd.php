<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'admit') {
    $bed_id = (int)$_POST['bed_id'];
    $bed = $pdo->query("SELECT * FROM beds WHERE id=$bed_id")->fetch();
    if (!$bed || $bed['status'] !== '空床') { flash_set('該床非空床'); redirect('ipd.php'); }
    $adm_no = gen_no($pdo, 'A', 'admissions', 'adm_no');
    $pdo->prepare('INSERT INTO admissions(adm_no,patient_id,bed_id,doctor_id,admit_date) VALUES(?,?,?,?,?)')
        ->execute([$adm_no, (int)$_POST['patient_id'], $bed_id, (int)$_POST['doctor_id'], $_POST['admit_date'] ?: today()]);
    $pdo->prepare("UPDATE beds SET status='佔床' WHERE id=?")->execute([$bed_id]);
    flash_set('入院成功：' . $adm_no);
    redirect('ipd.php');
}
if (isset($_GET['discharge'])) {
    $aid = (int)$_GET['discharge'];
    $a = $pdo->query("SELECT * FROM admissions WHERE id=$aid")->fetch();
    if ($a && $a['status'] === '住院中') {
        $pdo->prepare('UPDATE admissions SET discharge_date=?, status=? WHERE id=?')->execute([today(), '已出院', $aid]);
        $pdo->prepare("UPDATE beds SET status='清潔中' WHERE id=?")->execute([$a['bed_id']]);
        flash_set('已出院，病床轉清潔中');
    }
    redirect('ipd.php?adm_id=' . $aid);
}
if (isset($_GET['clean_done'])) {
    $pdo->prepare("UPDATE beds SET status='空床' WHERE id=?")->execute([(int)$_GET['clean_done']]);
    redirect('ipd.php');
}
$sel = null;
if (!empty($_GET['adm_id'])) {
    $st = $pdo->prepare('SELECT a.*, p.name pname, p.chart_no, w.name wname, w.bed_fee, b.bed_no, d.name dname FROM admissions a JOIN patients p ON p.id=a.patient_id LEFT JOIN beds b ON b.id=a.bed_id LEFT JOIN wards w ON w.id=b.ward_id LEFT JOIN doctors d ON d.id=a.doctor_id WHERE a.id=?');
    $st->execute([$_GET['adm_id']]); $sel = $st->fetch();
}
$inpatients = $pdo->query("SELECT a.*, p.name pname, p.chart_no, w.name wname, b.bed_no FROM admissions a JOIN patients p ON p.id=a.patient_id LEFT JOIN beds b ON b.id=a.bed_id LEFT JOIN wards w ON w.id=b.ward_id WHERE a.status='住院中' ORDER BY a.id DESC")->fetchAll();
$beds = $pdo->query('SELECT b.*, w.name wname, w.bed_fee FROM beds b JOIN wards w ON w.id=b.ward_id ORDER BY w.id, b.bed_no')->fetchAll();
$allp = $pdo->query('SELECT id,chart_no,name FROM patients ORDER BY id DESC LIMIT 200')->fetchAll();
$docs = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();
?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-bold">辦理入院</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="admit">
<div class="mb-2"><label class="form-label">病人</label><select name="patient_id" class="form-select"><?php foreach ($allp as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['chart_no'].' '.$p['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">空床</label><select name="bed_id" class="form-select"><?php foreach ($beds as $b): if ($b['status'] !== '空床') continue; ?><option value="<?= $b['id'] ?>"><?= h($b['wname'].'-'.$b['bed_no'].' $'.$b['bed_fee'].'/日') ?></option><?php endforeach; ?></select></div>
<div class="row g-2"><div class="col"><label class="form-label">主治</label><select name="doctor_id" class="form-select"><?php foreach ($docs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><label class="form-label">入院日</label><input type="date" name="admit_date" class="form-control" value="<?= today() ?>"></div></div>
<button class="btn btn-primary w-100 mt-2">確認入院</button></form></div></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">病床一覽（18床）</div>
<table class="table table-sm mb-0"><tr><th>病房-床</th><th>費用/日</th><th>狀態</th><th></th></tr>
<?php foreach ($beds as $b): ?><tr class="<?= $b['status']==='空床' ? '' : ($b['status']==='佔床' ? 'table-danger' : 'table-warning') ?>">
<td><?= h($b['wname'].'-'.$b['bed_no']) ?></td><td><?= number_format($b['bed_fee']) ?></td><td><?= h($b['status']) ?></td>
<td><?php if ($b['status']==='清潔中'): ?><a class="btn btn-sm btn-outline-success" href="ipd.php?clean_done=<?= $b['id'] ?>">清潔完</a><?php endif; ?></td></tr><?php endforeach; ?></table></div></div>
<div class="col-md-8"><div class="card shadow-sm"><div class="card-header fw-bold">住院中</div>
<table class="table table-hover table-sm mb-0"><tr><th>住院號</th><th>病人</th><th>病床</th><th>入院日</th><th></th></tr>
<?php foreach ($inpatients as $a): ?><tr><td><?= h($a['adm_no']) ?></td><td><?= h($a['chart_no'].' '.$a['pname']) ?></td>
<td><?= h($a['wname'].'-'.$a['bed_no']) ?></td><td><?= h($a['admit_date']) ?></td>
<td class="text-nowrap"><a class="btn btn-sm btn-primary" href="ipd.php?adm_id=<?= $a['id'] ?>">醫囑護理</a>
<a class="btn btn-sm btn-success" href="ipd.php?discharge=<?= $a['id'] ?>" onclick="return confirm('確認出院結算？')">出院</a></td></tr><?php endforeach; ?></table>
<?php if (!$inpatients): ?><div class="card-body text-muted">目前無住院。</div><?php endif; ?></div>
<?php if ($sel): $days = max(1, (int)((strtotime($sel['discharge_date'] ?? today()) - strtotime($sel['admit_date'])) / 86400) + 1);
$ords = $pdo->query("SELECT * FROM ipd_orders WHERE admission_id={$sel['id']} ORDER BY id")->fetchAll();
$osum = array_sum(array_map(fn($o) => $o['price']*$o['qty'], $ords));
$ops = $pdo->query("SELECT s.*, r.name room_name FROM surgeries s LEFT JOIN op_rooms r ON r.id=s.op_room_id WHERE s.admission_id={$sel['id']} AND s.status!='取消' ORDER BY s.op_date, s.start_time")->fetchAll();
$opsum = array_sum(array_map(fn($o) => $o['fee'], $ops));
$bedsum = $days * (int)$sel['bed_fee'];
$grand = $bedsum + $osum + $opsum; ?>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">住院單 <?= h($sel['adm_no'].' '.$sel['pname']) ?>（<?= h($sel['status']) ?>）</div><div class="card-body">
<p>病床：<?= h($sel['wname'].'-'.$sel['bed_no']) ?>　主治：<?= h($sel['dname']) ?>　入院：<?= h($sel['admit_date']) ?>　出院：<?= h($sel['discharge_date'] ?? '-') ?><br>
天數 <b><?= $days ?></b>　病床費 <b><?= number_format($bedsum) ?></b>　醫囑費 <b><?= number_format($osum) ?></b>　手術費 <b><?= number_format($opsum) ?></b>　合計 <b class="text-danger"><?= number_format($grand) ?></b></p>
<?php if ($sel['status'] === '住院中'): ?><a class="btn btn-sm btn-outline-primary" href="orders.php?adm_id=<?= $sel['id'] ?>">開醫囑</a>
<a class="btn btn-sm btn-outline-info" href="nursing.php?adm_id=<?= $sel['id'] ?>">護理紀錄</a><?php endif; ?>
<table class="table table-sm mt-2"><tr><th>醫囑</th><th>數量</th><th>金額</th><th>狀態</th></tr>
<?php foreach ($ords as $o): ?><tr><td><?= h('['.$o['order_type'].'] '.$o['item_name']) ?></td><td><?= $o['qty'] ?></td><td><?= number_format($o['price']*$o['qty']) ?></td><td><?= h($o['status']) ?></td></tr><?php endforeach; ?></table>
<?php if ($ops): ?><h6 class="mt-3">手術</h6><table class="table table-sm"><tr><th>手術號</th><th>日期</th><th>術式</th><th>刀房</th><th>手術費</th><th>狀態</th></tr>
<?php foreach ($ops as $op): ?><tr><td><?= h($op['op_no']) ?></td><td><?= h($op['op_date']) ?></td><td><?= h($op['op_name']) ?></td>
<td><?= h($op['room_name'] ?? '-') ?></td>
<td><?= number_format($op['fee']) ?></td><td><?= h($op['status']) ?></td></tr><?php endforeach; ?></table>
<p class="small text-muted">手術費已併入出院合計；取消之手術不計費。</p><?php endif; ?>
</div></div><?php endif; ?>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
