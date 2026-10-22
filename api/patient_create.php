<?php
// 健保卡建檔：掃卡/手動輸入後建立新病歷（掛號頁自動建檔用）
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => '未登入']); exit; }
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim($in['name'] ?? '');
if ($name === '') { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少姓名']); exit; }
$pdo = db();
$nhi = strtoupper(trim($in['nhi_no'] ?? ''));
// 卡號已建檔 → 不重複建，回既有病歷
if ($nhi !== '') {
    $st = $pdo->prepare('SELECT id, chart_no, name FROM patients WHERE nhi_no=? LIMIT 1');
    $st->execute([$nhi]);
    if ($ex = $st->fetch()) {
        echo json_encode(['ok' => true, 'existing' => $ex, 'message' => '此卡號已有病歷 ' . $ex['chart_no']], true);
        exit;
    }
}
$chart = 'P' . date('Ymd') . str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
$st = $pdo->prepare('INSERT INTO patients(chart_no,name,id_no,nhi_no,birth_date,gender,phone) VALUES(?,?,?,?,?,?,?)');
$st->execute([
    $chart,
    $name,
    trim($in['id_no'] ?? ''),
    $nhi !== '' ? $nhi : null,
    $in['bdate'] ?? null,
    in_array($in['sex'] ?? '', ['男', '女', '其他']) ? $in['sex'] : '男',
    trim($in['phone'] ?? ''),
]);
$pid = (int)$pdo->lastInsertId();
echo json_encode(['ok' => true, 'id' => $pid, 'chart_no' => $chart, 'name' => $name, 'message' => '已建檔，病歷號 ' . $chart], true);
