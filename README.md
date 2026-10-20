# HIS_DEMO 門診醫療資訊系統 (MVP)

原生 PHP + Bootstrap 5 + MySQL/MariaDB，部署於 XAMPP。

## 功能
- 病歷管理 (`patients.php`)
- 門診掛號 + 看診開處方 (`register.php`：接班表檢查＋自動診間號)
- 批價收費 + 收據列印 (`billing.php`、`receipt.php?reg_id=`)
- 退掛退費 (`refund.php`：未收費直接退掛，已收費作廢帳單＋可回補庫存)
- 藥局發藥 + 庫存 (`pharmacy.php`，發藥寫進銷存流水)
- 藥品進銷存/盤點 (`inventory.php`：建檔/進貨/報廢/盤點單/流水)
- 住院 (`ipd.php`：入院/病床/出院結算；`orders.php` 醫囑；`nursing.php` 護理紀錄)
- 檢驗檢查 (`lab.php`：門診/住院開單＋採檢檢驗流程；`result.php` 發報告；費用併門診批價)
- 健保對帳 (`nhi.php`：月統計＋身分別彙總＋CSV下載，院內對帳用)
- 營運報表 (`reports.php`：營收已扣退費作廢＋每日退費)

流程：掛號 → 待批價 → 待領藥 → 結案

## 快速啟動
1. 啟動 XAMPP `Apache` + `MySQL`
2. 開 `http://localhost/HIS_DEMO/install.php` 執行一鍵建表
3. 用 `admin / his1234` 登入 `http://localhost/HIS_DEMO/login.php`

## 資料庫
- DB: `his_demo`
- 表: users / patients / doctors / registrations / bills / drugs / prescriptions
- Schema: `database/schema.sql`

## 預設費用
- 掛號費 150，診察費 350（見 `config/app.php`）

