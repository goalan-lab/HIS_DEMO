<?php
// 健保卡號查詢：先查已建檔病歷，查不到則回 demo 樣本（無實體卡展示用）
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error' => '未登入']); exit; }
$in = json_decode(file_get_contents('php://input'), true) ?: (is_array($_POST) ? $_POST : []);
$card = strtoupper(trim($in['card_no'] ?? ''));
if ($card === '') { http_response_code(400); echo json_encode(['error' => '缺少卡號']); exit; }
$pdo = db();
// 1) 已建檔（nhi_no 或 id_no 相符）
$st = $pdo->prepare('SELECT id, chart_no, name, id_no, nhi_no, gender, birth_date FROM patients WHERE nhi_no=? OR id_no=? LIMIT 1');
$st->execute([$card, $card]);
$p = $st->fetch();
if ($p) {
    echo json_encode(['found' => true, 'patient' => $p, 'source' => 'db'], true);
    exit;
}
// 2) demo 樣本卡號（與 schema.sql / install.php 的 seed 一致）
$demo = [
    'A12345678' => ['name' => '陳志強', 'sex' => '男', 'bdate' => '1985-03-12', 'id_no' => 'A123456789'],
    'N01234567' => ['name' => '林麗華', 'sex' => '女', 'bdate' => '1992-11-05', 'id_no' => 'N012345678'],
];
if (isset($demo[$card])) {
    echo json_encode(['found' => false, 'demo' => $demo[$card], 'source' => 'demo'], true);
    exit;
}
// 3) 卡上讀到的欄位也要回傳（若前端有提供，建檔時直接可用）
$extra = [];
foreach (['name', 'sex', 'bdate', 'id_no'] as $k) {
    if (!empty($in[$k])) $extra[$k] = $in[$k];
}
echo json_encode(['found' => false, 'extra' => $extra, 'source' => 'none'], true);
