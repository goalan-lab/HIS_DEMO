# HIS_DEMO 門診醫療資訊系統 (MVP)

原生 PHP + Bootstrap 5 + MySQL/MariaDB，部署於 XAMPP。

## 功能
- 病歷管理 (`patients.php`)
- 門診掛號 + 看診開處方 (`register.php`：接班表檢查＋自動診間號)
- 批價收費 + 收據列印 (`billing.php`、`receipt.php?reg_id=`)
- 退掛退費 (`refund.php`：未收費直接退掛，已收費作廢帳單＋可回補庫存；退掛退費同步作廢該掛號門診手術)
- 藥局發藥 + 庫存 (`pharmacy.php`，發藥寫進銷存流水)
- 藥品進銷存/盤點 (`inventory.php`：建檔/進貨/報廢/盤點單/流水)
- 住院 (`ipd.php`：入院/病床/出院結算；`orders.php` 醫囑；`nursing.php` 護理紀錄)
- 檢驗檢查 (`lab.php`：門診/住院開單＋採檢檢驗流程；`result.php` 發報告；費用併門診批價)
- 健保對帳 (`nhi.php`：月統計＋身分別彙總＋CSV下載，院內對帳用)
- 手術排程 (`surgery.php`：刀房時段衝突檢查＋術前/開刀/恢復/完成狀態流＋當日刀表)
- 營運報表 (`reports.php`：營收已扣退費作廢＋每日退費＋手術營收)

流程：掛號 → 待批價 → 待領藥 → 結案

## 快速啟動
1. 啟動 XAMPP `Apache` + `MySQL`
2. 開 `http://localhost/HIS_DEMO/install.php` 執行一鍵建表
3. 用 `admin / his1234` 登入 `http://localhost/HIS_DEMO/login.php`

## 角色帳號（分角色權限）
| 角色 | 帳號 | 密碼 | 可見導覽 / 能做的事 |
|------|------|------|----------------------|
| 主管 | admin | his1234 | 全部頁面（含報表、健保對帳、帳號管理） |
| 醫師 | doctor | his1234 | 掛號、處方、醫囑、檢驗報告、住院、手術 |
| 護理 | nurse | his1234 | 叫號、發藥、護理紀錄、住院、手術備案、檢驗採檢 |
| 收銀 | cashier | his1234 | 掛號、批價收費、退掛退費、發藥、藥品庫存 |

導覽列與功能按鈕會依登入角色自動顯隱；無權限的頁面直接連會跳轉並提示。
既有安裝可用 `_migrate_roles.php` 一次補上各角色 demo 帳號。

## 資料庫
- DB: `his_demo`
- 表: users / patients / doctors / registrations / bills / drugs / prescriptions
- Schema: `database/schema.sql`

## 預設費用
- 掛號費 150，診察費 350（見 `config/app.php`）

