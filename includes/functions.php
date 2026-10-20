<?php
// 共用函式
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/permissions.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: ' . $url); exit; }
function today() { return date('Y-m-d'); }

// 單號產生: prefix + yyyymmdd + 3碼流水
function gen_no(PDO $pdo, string $prefix, string $table, string $col): string {
    $d = date('Ymd');
    $like = $prefix . $d . '%';
    $st = $pdo->prepare("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1");
    $st->execute([$like]);
    $row = $st->fetch();
    $seq = 1;
    if ($row) { $seq = (int)substr($row[$col], -3) + 1; }
    return $prefix . $d . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

function flash_set($msg) { $_SESSION['flash'] = $msg; }
function flash_get() { $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $m; }

// 退掛/退費時同步作廢該掛號之門診手術（掛 reg 且無住院掛單、非取消者；住院手術併出院結算不在此列）
function void_outpatient_surgeries(PDO $pdo, int $reg_id): int {
    $st = $pdo->prepare("UPDATE surgeries SET status='取消' WHERE reg_id=? AND admission_id IS NULL AND status!='取消'");
    $st->execute([$reg_id]);
    return (int)$st->rowCount();
}

function require_login() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $public = ['login.php', 'install.php']; // 完全免登入
    $semi = ['receipt.php', 'board.php', 'print.php', 'result.php']; // 公共展示頁：登入者皆可看，跳過角色檢查
    $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($cur, $public, true)) return;
    if (empty($_SESSION['user'])) { redirect('login.php'); }
    if (in_array($cur, $semi, true)) return;
    // 角色頁面權限（無權限一律擋下，GET/POST 皆然；按鈕級細項另於頁面內以 can_roles() 控制）
    if (!can_page($cur)) {
        flash_set('您的角色（' . current_role() . '）無權限開啟「' . $cur . '」');
        redirect('index.php');
    }
}
