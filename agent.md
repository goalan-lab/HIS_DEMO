# agent.md — HIS_DEMO 專案上下文（給 AI Agent / 協作者看）

> 最後更新：2026-10-20；維護者：goalan-lab；遠端：https://github.com/goalan-lab/HIS_DEMO.git（分支 main）

## 1. 專案一句話
原生 PHP + Bootstrap 5 + MySQL/MariaDB 的門診 HIS 最小 MVP，跑在 Windows XAMPP（D:\xampp\htdocs\HIS_DEMO），
涵蓋：病歷管理 → 門診掛號＋開處方 → 批價收費 → 藥局發藥＋扣庫存 → 營運報表。

## 2. 技術棧與環境
- 後端：原生 PHP 8.2（D:\xampp\php\php.exe），無框架、無 composer，無路由，直接對應 .php 檔。
- 前端：Bootstrap 5.3.3 CDN + 內聯 PHP 模板（includes/header.php / footer.php）。
- 資料庫：MariaDB 10.4（D:\xampp\mysql\bin\mysql.exe），DB 名 his_demo，帳號 root / 空密碼，host 127.0.0.1:3306，utf8mb4。
- 部署：XAMPP，Apache + MySQL 需手動在 xampp-control.exe 啟動；網址 http://localhost/HIS_DEMO/。
- 工具：Git 2.56（Windows），PowerShell 執行指令。
- 注意：git -C <path> 在此機會掉工作目錄，一律先 Set-Location d:/xampp/htdocs/HIS_DEMO 再下 git 指令。
- 認證：本機 git 身份 goalan-lab；遠端 owner 同為 goalan-lab，HTTPS push 已驗證可用。
  另有 goalanwork-rgb/HIS_DEMO（無寫入權限 403，已棄用，勿推）。

## 3. 目錄結構（共 18 檔）
- agent.md：本檔 AI 上下文；README.md：使用者啟動說明；.gitignore：PHP/XAMPP 忽略規則。
- config/app.php：APP_NAME/DB 連線常數/DEFAULT_REG_FEE=150/DEFAULT_EXAM_FEE=350/slot_options()。
- config/db.php：db():PDO 單例；Unknown database 時導向 install.php。
- includes/functions.php：h()/redirect()/today()/gen_no()/flash_set()/flash_get()/require_login()。
- includes/header.php：版頭＋登入檢查＋Bootstrap＋導覽列（總覽/病歷/掛號/批價/藥局/報表）。
- includes/footer.php：版尾 Bootstrap JS。
- login.php：登入（DB users 表＋內建 admin/his1234 保底）；logout.php：登出。
- install.php：一鍵建表（建庫＋逐句執行 database/schema.sql＋重置 admin 密碼）。
- index.php：總覽儀表板（今日掛號/待批價/待領藥/實收＋今日名單20筆）。
- patients.php：病歷新增（自動病歷號 P+ymd+流水）＋關鍵字查詢＋直跳掛號。
- register.php：掛號＋開處方（掛號單 R+ymd+流水，看診加藥後狀態→待批價）。
- billing.php：批價（合計掛號+診察+藥費→收費產帳單 B+ymd+流水→狀態待領藥）。
- pharmacy.php：藥局（待領藥→調劑單→發藥扣庫存→結案＋低庫存警示）。
- reports.php：報表（每日營收/科別掛號/醫師掛號）。
- database/schema.sql：6 表＋demo 資料（醫師3＋藥品5＋admin）。


## 4. 資料模型（見 database/schema.sql）
- users(id, username UNIQUE, password_hash, role 掛號/批價/藥局/管理)
- patients(id, chart_no UNIQUE, name, id_no, birth_date, gender, phone, address)
- doctors(id, name, dept, room)：王大明/家醫科/101，李小華/內科/102，陳美玲/小兒科/103
- registrations(id, reg_no UNIQUE R+ymd+3碼, patient_id FK, visit_date, slot 早/午/晚診, dept, doctor_id FK, status 掛號/看診中/待批價/待領藥/結案/退掛, reg_fee 150, exam_fee 350)
- bills(id, bill_no UNIQUE B+ymd+3碼, reg_id UNIQUE FK, med_fee, total, paid, paid_at, cashier, voided 作廢, void_at)
- refunds(id, reg_id FK, bill_id FK, amount, reason, restock 是否回補, created_by)
- drugs(id, code UNIQUE, name, spec, unit, price, stock, safety_stock, expiry)：demo D001~D005
- prescriptions(id, reg_id FK, drug_id FK, qty, usage_note, dispensed)
- schedules(id, doctor_id FK, weekday 0-6, slot, room, quota 限額, active)：週一~六早午晚預設54筆
- registrations 加 queue_no 診間號、called_at 叫號時間；看診中狀態正式啟用（叫號用）
- 住院：wards(病房/病床費)/beds(空床佔床清潔中)/admissions(住院號A+ymd)/ipd_orders(藥品檢查處置護理)/nursing_notes(生命徵象)
- 進銷存：stock_txns(進貨發藥退藥盤點報廢流水)/stocktakes+items(盤點單)；發藥/退藥/盤點統一走 stock_apply 寫流水
- 檢驗檢查：exam_items(9項預設)/exam_orders(L+ymd，reg或住院二選一)/exam_results(異常紅字)；門診批價＋收據已含檢驗費
- 健保對帳：registrations 加 ins_type/copay/nhi_claim；nhi.php 月統計＋CSV（院內對帳，不含健保XML格式）
- 手術排程：op_rooms(刀房A/B/C)＋surgeries(O+ymd 手術號，admission_id 可空掛住院、刀房時間重疊擋預約)；狀態流 排程→術前準備→手術中→恢復中→完成/取消（surgery.php 按鍵推進，跳轉保留日期）
- 出院結算：ipd.php 住院單顯示 病床費＋醫囑費＋手術費（該住院掛之非取消 surgeries.fee 合計）＝出院總計；併入明細表
- 單號規則：共用 gen_no($pdo, prefix, table, col)＝前綴＋當日 ymd＋3 碼流水。
- 狀態流（MVP 主線）：掛號 → 待批價 → 待領藥 → 結案；看診中/退掛欄位已定義但流程尚未使用。

## 5. 關鍵流程與金額邏輯
1. 病歷建檔：patients.php act=add，chart_no 自動編。
2. 掛號：register.php act=reg，寫 registrations（費用取常數），flash 後跳 ?reg_id=。
3. 開處方：register.php act=rx，逐筆 insert prescriptions，狀態推成待批價；?done= 看診完成亦推待批價並跳 billing。
4. 批價：billing.php?pay={reg_id} 計算 med＝SUM(price*qty)，total＝reg+exam+med，upsert bills（paid=1），狀態→待領藥，跳 pharmacy。
5. 發藥：pharmacy.php?reg_id= 看調劑單，?dispense= 逐筆扣 stock（不低於0）、dispensed=1、狀態→結案。
6. 報表：reports 直接 GROUP BY 統計 bills/registrations。

## 6. 啟動／驗證 SOP
1. 啟動服務（GUI）：D:\xampp\xampp-control.exe → Start Apache + MySQL。
2. 語法檢查：d:/xampp/php/php.exe -l d:/xampp/htdocs/HIS_DEMO/index.php（其餘頁比照）。
3. 瀏覽器：http://localhost/HIS_DEMO/install.php 執行安裝；http://localhost/HIS_DEMO/login.php 用 admin/his1234 登入。
4. 手動走一遍：總覽→病歷建檔→掛號→開處方→送批價→收費→發藥→結案→報表。
- 已知坑：MySQL 未啟動時 PDO 報 SQLSTATE HY000 2002 拒絕連線；PowerShell 下 php -r 引號易壞，改用暫存 .php 檔測試；git -C 會掉目錄，改 Set-Location；editor 大檔（>6000字元）要拆段寫。

## 7. Git 協作規範
- 分支：main 單線（已設 upstream origin/main）。
- 歷史（截至 2026-10-20）：318d9f8 MVP 初始版 → c211b7f GitHub Initial commit → a5b5d15 合併（解 README/.gitignore 衝突）。
- 常用（先 Set-Location d:/xampp/htdocs/HIS_DEMO）：
  git status --short；git add -A; git commit -m "feat/fix: …"; git push；git log --oneline --graph -10
- 訊息用中文＋ conventional 前綴（feat/fix/merge/docs）。

## 8. 程式慣例（新增程式碼請遵守）
- 每頁開頭 require includes/header.php（已含 session＋登入檢查＋db＋functions），結尾 require includes/footer.php。
- 輸出全部 h() escape；跳轉用 redirect()；跨頁訊息用 flash_set/get。
- SQL 用 PDO prepared statement；金額為 INT（元）；日期 today()＝Y-m-d。
- POST 用 act 隱藏欄位區分（add/reg/rx/pay/dispense/done）。
- 別引入 composer/Laravel/Vue/React；保持單檔一頁＋Bootstrap CDN 輕量風格，除非使用者明確升級。

## 9. 目前狀態與下一步
- 已完成：MVP 全流程可跑、php -l 全過、git+GitHub 同步。
- 待做（二選一未定）：A. 收據列印＋退掛/退費；B. 醫師班表＋診間叫號。
- 更遠：藥品進銷存、檢驗檢查、住院、健保申報、Laravel+Vue 重構。
- 下次 Agent 接手：先讀本檔＋README＋schema.sql，再 git pull，改完跑 php -l，commit＋push。

