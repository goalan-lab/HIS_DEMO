<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
// 彙總：依身分別
$sum = $pdo->query("SELECT ins_type, COUNT(*) n, IFNULL(SUM(b.total),0) s, IFNULL(SUM(r.copay),0) c FROM registrations r LEFT JOIN bills b ON b.reg_id=r.id AND b.paid=1 AND b.voided=0 WHERE DATE_FORMAT(r.visit_date,'%Y-%m')=" . $pdo->quote($month) . " AND r.status NOT IN ('退掛') GROUP BY ins_type")->fetchAll();
// 明細：掛號+藥+檢驗+部分負擔
$rows = $pdo->query("SELECT r.visit_date, r.reg_no, r.ins_type, r.copay, p.chart_no, p.name pname, d.name dname, r.reg_fee, r.exam_fee, IFNULL((SELECT SUM(g.price*pr.qty) FROM prescriptions pr JOIN drugs g ON g.id=pr.drug_id WHERE pr.reg_id=r.id),0) med, IFNULL((SELECT SUM(price*qty) FROM exam_orders e WHERE e.reg_id=r.id AND e.status!='取消'),0) lab, b.bill_no, b.total, b.voided FROM registrations r JOIN patients p ON p.id=r.patient_id LEFT JOIN doctors d ON d.id=r.doctor_id LEFT JOIN bills b ON b.reg_id=r.id WHERE DATE_FORMAT(r.visit_date,'%Y-%m')=" . $pdo->quote($month) . " ORDER BY r.visit_date, r.id LIMIT 500")->fetchAll();
// CSV 下載
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nhi_' . $month . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['就診日','掛號單','病歷號','姓名','醫師','身分別','掛號費','診察費','藥費','檢驗檢查','部分負擔','帳單','實收','作廢']);
    foreach ($rows as $x) { fputcsv($out, [$x['visit_date'],$x['reg_no'],$x['chart_no'],$x['pname'],$x['dname'],$x['ins_type'],$x['reg_fee'],$x['exam_fee'],$x['med'],$x['lab'],$x['copay'],$x['bill_no'],$x['total'],$x['voided']]); }
    exit;
}
$tot_n = array_sum(array_column($sum, 'n'));
$tot_s = array_sum(array_column($sum, 's'));
?>
<div class="card shadow-sm"><div class="card-header fw-bold d-flex justify-content-between align-items-center">健保對帳 — 院內月統計（不含申報格式）
<form class="d-flex gap-2" method="get"><input type="month" name="month" value="<?= h($month) ?>" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-primary">查詢</button>
<a class="btn btn-sm btn-success" href="nhi.php?month=<?= h($month) ?>&csv=1">下載CSV</a></form></div>
<div class="card-body">
<table class="table table-sm table-bordered mb-0"><tr><th>身分別</th><th>筆數</th><th>實收合計</th><th>部分負擔合計</th></tr>
<?php foreach ($sum as $s): ?><tr><td><?= h($s['ins_type']) ?></td><td><?= $s['n'] ?></td><td><?= number_format($s['s']) ?></td><td><?= number_format($s['c']) ?></td></tr><?php endforeach; ?>
<tr class="table-primary"><th>合計</th><th><?= $tot_n ?></th><th><?= number_format($tot_s) ?></th><th></th></tr></table>
<p class="small text-muted mt-2">退掛排除；作廢帳單不計實收；自費 nhi_claim=0 不列申報。CSV 為 UTF-8 BOM，可直接用 Excel 開。</p></div></div>
<div class="card shadow-sm mt-3"><div class="card-header fw-bold">明細（近500筆）</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0"><tr><th>就診日</th><th>掛號單</th><th>病人</th><th>身分別</th><th>掛號/診察/藥/檢驗</th><th>部分負擔</th><th>帳單/實收</th></tr>
<?php foreach ($rows as $x): ?><tr class="<?= (int)($x['voided'] ?? 0) ? 'table-secondary' : '' ?>">
<td><?= h($x['visit_date']) ?></td><td><?= h($x['reg_no']) ?></td><td><?= h($x['chart_no'].' '.$x['pname']) ?></td><td><?= h($x['ins_type']) ?></td>
<td><?= number_format($x['reg_fee']).'/'.number_format($x['exam_fee']).'/'.number_format($x['med']).'/'.number_format($x['lab']) ?></td>
<td><?= number_format($x['copay']) ?></td><td><?= h(($x['bill_no'] ?? '-').' / '.($x['total'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
