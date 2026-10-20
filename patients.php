<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$q = trim($_GET['q'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'add') {
    $chart = 'P' . date('Ymd') . str_pad((string)(random_int(1, 999)), 3, '0', STR_PAD_LEFT);
    $st = $pdo->prepare('INSERT INTO patients(chart_no,name,id_no,birth_date,gender,phone,address) VALUES(?,?,?,?,?,?,?)');
    $st->execute([$chart, trim($_POST['name']), trim($_POST['id_no'] ?? ''), $_POST['birth_date'] ?: null, $_POST['gender'], trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? '')]);
    flash_set('已建檔，病歷號 ' . $chart);
    redirect('patients.php');
}
$where = ''; $args = [];
if ($q !== '') { $where = 'WHERE name LIKE ? OR chart_no LIKE ? OR phone LIKE ? OR id_no LIKE ?'; $args = ["%$q%", "%$q%", "%$q%", "%$q%"]; }
$st = $pdo->prepare("SELECT * FROM patients $where ORDER BY id DESC LIMIT 100");
$st->execute($args);
$rows = $st->fetchAll();
?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-bold">新增病人</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="add">
<div class="mb-2"><label class="form-label">姓名*</label><input name="name" class="form-control" required></div>
<div class="row g-2"><div class="col"><label class="form-label">性別</label><select name="gender" class="form-select"><option>男</option><option>女</option><option>其他</option></select></div>
<div class="col"><label class="form-label">生日</label><input type="date" name="birth_date" class="form-control"></div></div>
<div class="mb-2 mt-2"><label class="form-label">身分證</label><input name="id_no" class="form-control"></div>
<div class="mb-2"><label class="form-label">電話</label><input name="phone" class="form-control"></div>
<div class="mb-2"><label class="form-label">地址</label><input name="address" class="form-control"></div>
<button class="btn btn-primary w-100">存檔建病歷號</button></form></div></div></div>
<div class="col-md-8"><div class="card shadow-sm"><div class="card-header fw-bold d-flex justify-content-between">病歷查詢
<form class="d-flex" method="get"><input name="q" value="<?= h($q) ?>" class="form-control form-control-sm me-2" placeholder="姓名/病歷號/電話/身分證"><button class="btn btn-sm btn-outline-primary">搜尋</button></form></div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0"><tr><th>病歷號</th><th>姓名</th><th>性別</th><th>生日</th><th>身分證</th><th>電話</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= h($r['chart_no']) ?></td><td><?= h($r['name']) ?></td><td><?= h($r['gender']) ?></td><td><?= h($r['birth_date']) ?></td><td><?= h($r['id_no']) ?></td><td><?= h($r['phone']) ?></td>
<td><a class="btn btn-sm btn-primary" href="register.php?patient_id=<?= $r['id'] ?>">掛號</a></td></tr><?php endforeach; ?></table></div></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
