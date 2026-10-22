<?php
// ===== Telegram 登入通知引擎（依 telgram.md §二/§四 實作）=====
// 用法：login.php 等頁 require_once 本檔後，於成功/失敗路徑各呼叫一次
//   try { tg_login_notify($username, $userRowOrNull, $ok); }
//   catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
// 關鍵原則：通知永遠不能擋住主流程 —— tg_login_notify 只 error_log 不外拋；
// TG_TOKEN 留空即停用（API 呼叫直接跳過，零副作用）。
// 不依賴 helpers.php：escape 一律用本檔 tg_e()。

// 常數缺省（正常都由 config/app.php 定義；直接獨立 require 本檔時保險用）
if (!defined('TG_TOKEN'))   define('TG_TOKEN', '');
if (!defined('TG_CHAT_ID')) define('TG_CHAT_ID', '');

// Telegram 會不定期輪換 IP：cPanel 封鎖出站 DNS 時靠寫死 IP 維持通達。
// 全路徑失敗（error_log 出現「出站 DNS 被封鎖且 IP 池失效」）時照 telgram.md §四 更新：
//   (Invoke-RestMethod 'https://dns.google/resolve?name=api.telegram.org&type=A').Answer | Select -Expand Data
if (!defined('TG_API_HOST')) define('TG_API_HOST', 'api.telegram.org');
if (!defined('TG_API_IPS'))  define('TG_API_IPS', ['149.154.166.110']); // 2026-10-22 實測 getMe 成功
if (!defined('TG_TIMEOUT'))  define('TG_TIMEOUT', 15); // 每階段 timeout（秒）

function tg_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// 逐層 fallback 呼叫 Telegram Bot API；回傳 json_decode 後的 array，全失敗回 null（只 error_log）
function tg_api(string $method, array $params = []) {
    $GLOBALS['tg_fail_reason'] = null; // tg_check.php 用：失敗原因
    if (TG_TOKEN === '') return ['skipped' => true, 'reason' => 'TG_TOKEN 未設定'];
    $base = 'https://' . TG_API_HOST . '/bot' . TG_TOKEN . '/' . $method;
    $body = http_build_query($params);

    // ① curl 直連（DNS 正常環境第一槍就過）
    if (function_exists('curl_init')) {
        $ch = curl_init($base);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => TG_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        if ($res !== false) {
            $j = json_decode((string)$res, true);
            curl_close($ch);
            if (is_array($j)) { $GLOBALS['tg_fail_reason'] = null; return $j; }
        } else { $GLOBALS['tg_fail_reason'] = 'curl: ' . curl_error($ch); }
        curl_close($ch);
    }

    // ② curl + CURLOPT_RESOLVE（cPanel 擋出站 DNS；IP 寫死但 TLS 憑證照驗）
    if (function_exists('curl_init')) {
        foreach (TG_API_IPS as $ip) {
            $ch = curl_init($base);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => TG_TIMEOUT,
                CURLOPT_RESOLVE => [TG_API_HOST . ':443:' . $ip],
            ]);
            $res = curl_exec($ch);
            if ($res !== false) {
                $j = json_decode((string)$res, true);
                curl_close($ch);
                if (is_array($j)) { $GLOBALS['tg_fail_reason'] = null; return $j; }
            } else { $GLOBALS['tg_fail_reason'] = "curl-ip($ip): " . curl_error($ch); }
            curl_close($ch);
        }
    }

    // ③ stream + IP 固定（Host 標頭補 api.telegram.org，跳過 TLS 憑證驗證——刻意接受的小風險，
    //    token 仍走 HTTPS 443 加密，最壞是中間人攔到 token 但讀不到帳號資料）
    foreach (TG_API_IPS as $ip) {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Host: " . TG_API_HOST . "\r\n",
            'content' => $body, 'ignore_errors' => true, 'timeout' => TG_TIMEOUT,
        ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $target = 'https://' . $ip . '/bot' . TG_TOKEN . '/' . $method;
        $res = @file_get_contents($target, false, $ctx);
        if ($res !== false) {
            $j = json_decode((string)$res, true);
            if (is_array($j)) { $GLOBALS['tg_fail_reason'] = null; return $j; }
        } else { $GLOBALS['tg_fail_reason'] = "stream-ip($ip): " . error_get_last()['message'] ?? 'unknown'; }
    }

    // ④ stream 直連（最後一搏）
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'content' => $body, 'ignore_errors' => true, 'timeout' => TG_TIMEOUT,
    ]]);
    $res = @file_get_contents($base, false, $ctx);
    if ($res !== false) {
        $j = json_decode((string)$res, true);
        if (is_array($j)) { $GLOBALS['tg_fail_reason'] = null; return $j; }
    }
    $GLOBALS['tg_fail_reason'] = '全部路徑失敗（試過：curl' . (function_exists('curl_init') ? ', curl-ip, stream-ip' : '') . ', stream）'
        . '——出站 DNS 被封鎖且 IP 池失效？照 telgram.md §四 更新 TG_API_IPS。';
    error_log('[TG] ' . $GLOBALS['tg_fail_reason']);
    return null;
}

// 發一則 HTML 訊息；失敗只 log 不回傳錯誤
function tg_send(string $text): bool {
    if (TG_TOKEN === '' || TG_CHAT_ID === '') return false;
    $r = tg_api('sendMessage', ['chat_id' => TG_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML']);
    if (!$r || empty($r['ok'])) {
        error_log('[TG] sendMessage 失敗：' . (is_array($r) ? (json_encode($r) ?: $GLOBALS['tg_fail_reason'] ?? '') : ($GLOBALS['tg_fail_reason'] ?? 'null')));
        return false;
    }
    return true;
}

// 登入通知：$user 為 users 表列（array），失敗時傳 null
// ⚠ 簽名固定 3 參 (string, ?array, bool)：改簽名要 grep 全專案呼叫端一起改（telgram.md §五 坑 1）
function tg_login_notify(string $username, ?array $user, bool $ok): void {
    $site = defined('APP_NAME') ? APP_NAME : 'PHP 網站';
    $time = (new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei')))->format('Y-m-d H:i:s'); // 固定台北時區，不依賴 server tz
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $head = ($ok ? '✅ ' : '⚠️ ') . tg_e($site) . ($ok ? ' 登入成功通知' : ' 登入嘗試失敗（帳號或密碼錯誤）');
    $lines = [
        '<b>' . $head . '</b>',
        '帳號：' . tg_e($username),
    ];
    if ($ok && $user) {
        $lines[] = '姓名/角色：' . tg_e($user['username'] ?? $username) . '（' . tg_e($user['role'] ?? '') . '）';
    }
    $lines[] = '登入 IP：' . tg_e($ip);
    $lines[] = '登入時間：' . $time;
    tg_send(implode("\n", $lines));
}