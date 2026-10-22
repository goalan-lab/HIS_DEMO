<?php
// tg_check.php — Telegram 通知四層診斷（暫用，全綠後務必刪掉本檔！會暴露 token 前 8 位與 DB 名稱）
// 用法：
//   tg_check.php         → ①設定 ②DB ③API(getMe+send)
//   tg_check.php?full=1  → ④ 伺服器端模擬完整 tg_login_notify 路徑（含耗時）
//   tg_check.php?force=1 → 即使 TG_TOKEN 為空也走 API 層（預期失敗，確認停用路徑正確）
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/telegram.php';
$full = isset($_GET['full']);
$force = isset($_GET['force']);
$ok_icon = fn($b) => $b ? '✅' : '❌';
?><!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><title>TG 診斷 - <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container" style="max-width:720px;margin-top:3vh">
<div class="alert alert-danger small fw-bold">⚠️ 暫用診斷頁：全綠並實測登入收訊後，請<b>刪除本檔</b>（會暴露 DB 名稱與 token 前 8 位）。</div>

<div class="card mb-3"><div class="card-header fw-bold">① 設定（常數是否注入）</div><div class="card-body small">
<table class="table table-sm mb-0">
<tr><td>APP_NAME</td><td><?= $ok_icon(defined('APP_NAME')) ?> <?= h(defined('APP_NAME') ? APP_NAME : '(未定義)') ?></td></tr>
<tr><td>TG_TOKEN</td><td><?= $ok_icon(TG_TOKEN !== '') ?> <?= TG_TOKEN !== '' ? h(substr(TG_TOKEN, 0, 8)) . '…（' . strlen(TG_TOKEN) . " 字元）" : '（空＝停用通知，API 層全跳過）' ?></td></tr>
<tr><td>TG_CHAT_ID</td><td><?= $ok_icon(TG_CHAT_ID !== '') ?> <?= h(TG_CHAT_ID !== '' ? TG_CHAT_ID : '(未設定)') ?></td></tr>
<tr><td>TG_API_IPS</td><td><?= h(implode(', ', TG_API_IPS)) ?></td></tr>
</table></div></div>

<div class="card mb-3"><div class="card-header fw-bold">② DB（his_demo.users 可連）</div><div class="card-body small">
<?php
$db_ok = false; $db_msg = '';
try { $cnt = (int)db()->query('SELECT COUNT(*) c FROM users')->fetch()['c']; $db_ok = true; $db_msg = "his_demo.users 共 $cnt 筆"; }
catch (Throwable $e) { $db_msg = '連線失敗：' . $e->getMessage(); }
?><?= $ok_icon($db_ok) ?> <?= h($db_msg) ?></div></div>

<div class="card mb-3"><div class="card-header fw-bold">③ API（getMe + 試送一條）<?= $force ? '【force 模式】' : '' ?></div><div class="card-body small">
<?php
$api_ok = false; $api_msg = '（跳過：TG_TOKEN 未設定，等同停用）';
if (TG_TOKEN !== '' || $force) {
    $me = tg_api('getMe');
    $me_ok = is_array($me) && !empty($me['ok']);
    $api_msg = 'getMe ' . ($me_ok ? 'ok=true（' . h($me['result']['username'] ?? '') . '）' : 'ok=false/失敗：' . h($GLOBALS['tg_fail_reason'] ?? json_encode($me)));
    if ($me_ok) {
        $res = tg_send('🧪 [TG Check] 試送成功，可刪掉 tg_check.php 了');
        $api_ok = (bool)$res;
        $api_msg .= '；sendMessage 回傳 ok=' . ($res ? 'true' : 'false（' . h($GLOBALS['tg_fail_reason'] ?? '') . '）');
    } else { $api_ok = false; }
}
?><?= $ok_icon($api_ok) ?> <?= h($api_msg) ?></div></div>

<?php if ($full): ?>
<div class="card mb-3"><div class="card-header fw-bold">④ full 路徑（伺服器端模擬 tg_login_notify 完整呼叫）</div><div class="card-body small">
<?php
$t0 = microtime(true);
try {
    tg_login_notify('admin', ['username' => 'admin', 'role' => '主管'], true);
    $full_ok = ($GLOBALS['tg_fail_reason'] === null) || TG_TOKEN === '';
    $full_msg = $full_ok ? 'full 路徑：tg_login_notify 呼叫完成（' . ($GLOBALS['tg_fail_reason'] === null && TG_TOKEN !== '' ? '送訊成功' : 'token 未設定、如期跳過') . '）'
                         : 'full 路徑失敗：' . h($GLOBALS['tg_fail_reason']);
} catch (Throwable $t) {
    $full_ok = false; $full_msg = 'full 路徑例外：' . h($t->getMessage()); error_log('[TG] check full: ' . $t->getMessage());
}
$ms = round((microtime(true) - $t0) * 1000);
?><?= $ok_icon($full_ok) ?> <?= h($full_msg) ?>（耗時 <?= $ms ?> ms）
<?php if (!$full_ok && TG_TOKEN !== ''): ?><div class="alert alert-warning mt-2 mb-0">💡「API 通但 full 例外」＝應用層 bug，先 grep <code>tg_login_notify</code> 確認簽名與呼叫端參數序（string, ?array, bool）一致（telgram.md §五 坑 1）。</div><?php endif ?>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-header fw-bold">下一步</div><div class="card-body small mb-0">
全綠後：瀏覽器正確密碼登入一次收 ✅、故意錯密碼收 ⚠️，各確認一次後 <b>刪除本檔</b>。
</div></div>
</div></body></html>