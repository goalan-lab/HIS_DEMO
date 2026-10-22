# Telegram 登入通知系統 — 完整流程與導入指南

> 建立日期：2026-10-22
> 適用：任何 PHP 網站的「登入成功 / 登入失敗」即時通知到 Telegram
> 實測環境：InfinityFree（cPanel 封鎖出站 DNS）＋ 本機 XAMPP 雙模通過

---

## 一、系統架構（4 個檔案角色）

| 檔案 | 角色 | 其他網站導入時 |
|---|---|---|
| `includes/telegram.php` | 通知引擎：API 四階段 fallback、`tg_send()`、`tg_login_notify()` | **整檔複製**，改訊息文案即可 |
| `config/config.php` | 定義 `TG_TOKEN` / `TG_CHAT_ID` / `APP_NAME` 三個常數 | 加入 3 行 define |
| `login.php` | 成功/失敗兩路徑各呼叫一次通知 | 改 2 行呼叫 |
| `tg_check.php` | 暫用診斷頁（逐層定位：設定→DB→API→full 路徑） | 複製用，**用完刪掉** |

另外：`includes/telegram.php` 開頭會自動 `require_once 'includes/helpers.php'` 取
`e()`（HTML escape）——若你的網站沒有 `e()` 函式，把 helpers.php 一併複製，
或把 telegram.php 裡的 `e()` 全改成 `htmlspecialchars($v, ENT_QUOTES)`。

---

## 二、登入後送訊的完整流程

```
使用者瀏覽器
   │  POST login.php (username, password)
   ▼
login.php
   ├─ current_user() 已登入 → 302 dashboard（不觸發通知）
   └─ authenticate($u, $p)
        ├─ ✅ 成功
        │    login_user($user)
        │    try { tg_login_notify($u, $user, true); } catch (Throwable) { error_log }
        │    flash + 302 → dashboard.php
        └─ ❌ 失敗
             $error = '帳號或密碼錯誤'
             try { tg_login_notify($u, null, false); } catch (Throwable) { error_log }
             留在登入頁顯示錯誤
   ▼
tg_login_notify(string $username, ?array $user, bool $ok)
   │  組訊息（HTML）：
   │    成功：✅ [APP_NAME] 登入成功通知
   │    失敗：⚠️ [APP_NAME] 登入嘗試失敗（帳號或密碼錯誤）
   │    內容：網站名稱／登入帳號／姓名角色／登入 IP／登入時間
   ▼
tg_send($text)  →  tg_api('sendMessage', {chat_id, text, parse_mode:HTML})
   ▼  四階段 fallback（每階段 15 秒 timeout）
   ① curl 直連            ← DNS 正常環境（本機）第一槍就過
   ② curl + CURLOPT_RESOLVE ← cPanel 擋出站 DNS 時靠這招（TLS 憑證照驗）
   ③ stream + IP 固定      ← Host 標頭補 api.telegram.org（跳過 TLS 憑證驗證）
   ④ stream 直連          ← 最後一搏
   ▼
api.telegram.org/bot<TOKEN>/sendMessage → 你的 Telegram
```

**關鍵原則：通知永遠不能擋住登入**
- `tg_login_notify` 回傳 `void`，API 失敗只 `error_log`，絕不外拋
- `login.php` 呼叫端仍包 `try/catch (Throwable)` 雙保險（防 `e()`、常數缺失等
  PHP 層錯誤），通知炸掉也照樣登入成功/照樣被拒
- `TG_TOKEN` 留空 → `tg_api` 回 `null` 跳過，等同停用通知、零副作用

---

## 三、其他網站導入步驟（6 步）

### 1. 建立 Bot（多網站可共用同一個 bot）
- Telegram 找 `@BotFather` → `/newbot` → 拿 token
- 拿 chat_id：找 `@userinfobot`，或發一則訊息給自己的 bot 後開
  `https://api.telegram.org/bot<TOKEN>/getUpdates`，回覆裡的 `from.id` 就是

### 2. config.php 加常數
```php
// 留空 TG_TOKEN 即停用通知（API 呼叫直接跳過，不影響登入流程）
define('TG_TOKEN', '<你的 token>');
define('TG_CHAT_ID', '<你的 chat_id>');
// 沒站名常數就 define('APP_NAME', '你的站名');
```

### 3. 複製引擎
把 `includes/telegram.php` 複製過去（路徑不同就只改檔內
`require_once __DIR__ . '/helpers.php'` 的路徑）。

### 4. 改兩處文案
`tg_login_notify()` 內的 `'✅ ERP 登入成功通知'` / `'⚠️ ERP 登入嘗試失敗…'`
改成各網站自己的名稱，收訊時才分得清。

### 5. login.php 接兩行（**參數序一定照 3 參**）
```php
require_once 'includes/telegram.php';   // 在 config.php 之後
// authenticate 成功：
try {
    tg_login_notify($u, $user, true);   // 帳號字串、users 列 array、成功
} catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
// 失敗：
try {
    tg_login_notify($u, null, false);   // 帳號字串、null、失敗
} catch (Throwable $t) { error_log('[TG] notify fatal: ' . $t->getMessage()); }
```
⚠️ **簽名：`tg_login_notify(string $username, ?array $user, bool $ok)` 共 3 參。**
你的 authenticate 回傳格式不同（如回 object）就把第 2 參改成 `?object`，
並同步改函式內的 `$user['name']` 為 `$user->name`——**改了一邊就要改另一邊**（§五 坑 1）。

### 6. 上線驗證
1. 開 `https://你的網域/tg_check.php` → `getMe ok=true`、`sendMessage 回傳=true`
2. 開 `https://你的網域/tg_check.php?full=1` → 「full 路徑：… 呼叫完成」
3. 瀏覽器**正確密碼**登入一次 → 收 ✅ 通知
4. 瀏覽器**故意錯密碼**登入一次 → 收 ⚠️ 通知
5. 全綠後**刪除 `tg_check.php`**（會暴露 DEPLOY/DB 名稱與 token 前 8 位）

---

## 四、DNS 被封鎖（InfinityFree）與 IP 池維護

cPanel 常封鎖 PHP 出站 DNS，`api.telegram.org` 解不出 IP → 直連全失敗。
解法就是 §二的 ②③ 階段：把 IP 寫死進 `TG_API_IPS`（目前 `['149.154.166.110']`，
2026-10-22 實測 getMe 成功）。

**Telegram 會不定期輪換 IP。收到「全部路徑失敗（試過：curl, curl-ip,
stream-ip, stream）」的 error_log 時，更新步驟：**

```powershell
# 1. 查最新 A 記錄（2 台鏡像互相驗證）
(Invoke-RestMethod 'https://dns.google/resolve?name=api.telegram.org&type=A').Answer | Select -Expand Data
(Invoke-RestMethod 'https://dns.dig.it/api/v1/dns?name=api.telegram.org&type=A').Answer
# 2. 把 1~2 個新 IP 補進 telegram.php 的 TG_API_IPS
# 3. tg_check.php（或 ?force）確認 curl-ip 路徑 ok=true 後刪診斷頁
```

備註：stream-ip 階段跳過 TLS 憑證驗證是**刻意接受的小風險**——token 走
HTTPS 443 加密傳輸，最壞情況是被中間人攔截 token（token 可直接操作你的
bot 發訊息，但無法讀你的帳號資料）。

---

## 五、注意事項與踩過的坑

### 坑 1（今天的主故障）：函式簽名與呼叫端不同步 → 被 try/catch 靜默吞掉
- 症狀：`?full=1` 診斷頁能送訊，**真實登入卻完全沒通知**，PHP 無錯、
  登入功能一切正常。
- 原因：`telegram.php` 簽名已改成 3 參 `(string, ?array, bool)`，但
  `login.php` 呼叫端還是舊的 2 參 `tg_login_notify($user, true)`
  → 每次必爆 `TypeError`（array 撞 string 參）→ 被
  `catch (Throwable) { error_log }` 靜默吞掉 → 無聲。
- 教訓：
  1. **改簽名要 grep 全專案所有呼叫端一起改**（搜 `tg_login_notify`）
  2. `try/catch (Throwable) + error_log` 是「不影響主流程」的正確設計，
     但代價是**失敗零提示**——通知消失先去查 `error_log`（cPanel 錯誤
     日誌／PHP 日誌檔）找 `[TG] notify fatal:` 字樣
  3. 確認「呼叫端真的送得出」，用 `tg_check.php?full=1` 這種
     **伺服器端模擬完整路徑**的方式，別只本地測（見坑 4）

### 坑 2：本地 `file_get_contents` POST 與 curl 行為不一致
- PHP `stream_context` 的 `file_get_contents` 發 POST 沒帶
  `Content-Type` 時，`$_POST` 可能完全不被解析 → 本地「測不出」實際行為
- 教訓：**本機冒煙測試一律用 `curl.exe`**：
  ```powershell
  curl.exe -s -o NUL -w "HTTP %{http_code} redirect=%{redirect_url}" -d "username=admin&password=admin123" http://127.0.0.1:8098/login.php
  ```

### 坑 3：InfinityFree 的 JS 反爬蟲擋本地模擬 POST
- 主機端 `aes.js` / `__test` cookie 的 JS 挑戰，**瀏覽器**自動過；
  `curl.exe`、`Invoke-WebRequest` 等工具直接 POST 會被擋 →
  「本地模擬真實登入」走不通
- 解法：改在**伺服器上**放 `tg_check.php?full=1`（無 cookie 的 PHP 直
  接跑同一路徑），繞過 JS 層直接驗證 PHP 端

### 坑 4：診斷頁要「逐層可斷」
`tg_check.php` 四層各自獨立定位，缺任何一層都會誤判：

| 層 | 驗證 | 判斷 |
|---|---|---|
| 1 設定 | `TG_TOKEN` / `TG_CHAT_ID` / `APP_NAME` 是否注入 | 空代表沒載入 config.php |
| 2 DB | `SELECT COUNT(*) FROM users` | 同環境一次確認 |
| 3 API | `getMe` + `sendMessage` 實測一條 | token/出站/DNS/IP 池問題 |
| 4 full | 完整登入路徑 + 耗時 | 型別、常數、耗時有沒有問題 |

「API 通但 full 路徑例外」＝**應用層 bug（如坑 1 型別錯誤）**，不是網絡問題。

### 坑 5：IP 池過期是遲早要處理的維護項目
- 4 階段全失敗時 error_log 會寫「出站 DNS 被封鎖且 IP 池失效」
- 照 §四 兩分鐘換 IP 即可，不用重寫邏輯

### 其他小提醒
- **多網站共用同一 bot**：站名/文案務必不同（`APP_NAME` 已內建在 header），
  否則收訊時分不清哪個站
- **時區**：`tg_login_notify` 用 `date('Y-m-d H:i:s')`，確認 config.php 有
  `date_default_timezone_set('Asia/Taipei')`，不然通知時間是 UTC
- **限頻**：目前沒做登入失敗連發保護，惡意亂打密碼可無限刷通知；
  高流量站建議同 IP 5 分鐘內失敗通知上限 3 條（簡易版：檔案記最後送訊時間）
- **token 安全**：`config.php` 含明文 token，別放 git；`tg_check.php`
  會顯示 token 前 8 位，確認完務必刪
- **本地開發**：`TG_TOKEN` 留空即完全停用，不用管本地 DNS

---

## 六、快速檢查表（每次上線前）

- [ ] `config.php`：`TG_TOKEN` 非空、`TG_CHAT_ID` 正確、`APP_NAME` 對
- [ ] `includes/telegram.php`：簽名 `tg_login_notify(string, ?array, bool)`
- [ ] `login.php`：成功 `tg_login_notify($u, $user, true)`／失敗
      `tg_login_notify($u, null, false)`，兩處都有 try/catch
- [ ] 全專案搜尋 `tg_login_notify`：只有 telegram.php（定義）+ login.php（呼叫）
- [ ] `tg_check.php` 全綠 → `?full=1` 全綠 → 真實登入成功/失敗各測一次
- [ ] 全綠後刪除 `tg_check.php`
---

## 七、HIS_DEMO 落地對照（2026-10-22 已導入）

| 本文位置 | HIS_DEMO 實際落點 |
|---|---|
| `config/config.php` 3 行 define | `config/app.php`：`TG_TOKEN` / `TG_CHAT_ID`（空＝停用）＋ `@require config/telegram.php`（gitignore，放真實 token）；`APP_NAME` 既有 |
| `includes/helpers.php` 的 `e()` | 沒有 helpers，引擎自帶 `tg_e()`（htmlspecialchars） |
| `date_default_timezone_set` | `config/app.php` 已加 `Asia/Taipei`；`tg_login_notify` 另外用 `DateTimeZone('Asia/Taipei')` 保險 |
| `login.php` 兩行呼叫 | 三路徑：admin 保底成功、DB 成功、DB 失敗/例外（各包 `try/catch (Throwable) + error_log`） |
| `tg_check.php` | 已建（4 層：設定/DB/API/`?full=1`，另加 `?force=1`）；**全綠＋實測收訊後刪除** |

HIS_DEMO 成功通知內容：帳號、`username（role）`（users 表無「姓名」欄，以角色代替）、IP、台北時區時間。


