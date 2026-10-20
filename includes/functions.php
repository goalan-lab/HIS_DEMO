<?php
// 共用函式
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

function require_login() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $public = ['login.php', 'install.php'];
    $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($cur, $public, true)) return;
    if (empty($_SESSION['user'])) { redirect('login.php'); }
}
