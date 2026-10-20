# HIS_DEMO 門診醫療資訊系統 (MVP)

原生 PHP + Bootstrap 5 + MySQL/MariaDB，部署於 XAMPP。

## 功能
- 病歷管理 (`patients.php`)
- 門診掛號 + 看診開處方 (`register.php`)
- 批價收費 (`billing.php`)
- 藥局發藥 + 庫存 (`pharmacy.php`)
- 營運報表 (`reports.php`)

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

