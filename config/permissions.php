<?php
// ===== 角色權限 (RBAC) =====
// 角色：主管(全權) / 醫師 / 護理 / 收銀；舊「掛號」「管理」自動視為主管
define('ROLE_ADMIN', '主管');
define('ROLE_DOCTOR', '醫師');
define('ROLE_NURSE', '護理');
define('ROLE_CASHIER', '收銀');
define('ROLES_ALL', [ROLE_ADMIN, ROLE_DOCTOR, ROLE_NURSE, ROLE_CASHIER]);

// 各角色可開啟的頁面（$page = 腳本檔名；不列者 = 所有登入者可開。
// 註：receipt/print/board/result 為公共列印/展示頁，任何角色皆可直開，但導覽依此表過濾）
define('ROLE_PAGE_ACCESS', [
    ROLE_ADMIN   => null, // 主管全頁
    ROLE_DOCTOR  => ['index.php','patients.php','register.php','schedules.php','queue.php',
                     'receipt.php','lab.php','ipd.php','orders.php','nursing.php','surgery.php'],
    ROLE_NURSE   => ['index.php','patients.php','queue.php','pharmacy.php',
                     'lab.php','ipd.php','orders.php','nursing.php','surgery.php'],
    ROLE_CASHIER => ['index.php','patients.php','register.php','billing.php','receipt.php',
                     'refund.php','pharmacy.php'],
]);

// 舊角色值對映（DB 存量資料可能為 掛號/管理）
function normalize_role($role): string {
    $role = (string)($role ?? '');
    return in_array($role, ROLES_ALL, true) ? $role : ROLE_ADMIN; // 未知/舊角色 → 主管
}

// 目前使用者角色（session）
function current_role(): string {
    return normalize_role($_SESSION['user']['role'] ?? '');
}

// 目前使用者是否為指定角色之一：can_roles([ROLE_DOCTOR, ROLE_ADMIN])
function can_roles(array $roles): bool {
    return in_array(current_role(), $roles, true);
}

// 目前使用者可否開啟指定頁面：can_page('billing.php')
function can_page(string $page): bool {
    $r = current_role();
    if ($r === ROLE_ADMIN) return true;
    $allowed = ROLE_PAGE_ACCESS[$r] ?? null;
    return $allowed === null || in_array($page, $allowed, true);
}

// 快捷：可以否對指定 action 做某操作：can('nursing.add')
function can(string $action): bool {
    static $map = [
        'register.reg' => [ROLE_ADMIN, ROLE_NURSE],
        'register.rx' => [ROLE_ADMIN, ROLE_DOCTOR],
        'register.done' => [ROLE_ADMIN, ROLE_DOCTOR],
        'queue.call' => [ROLE_ADMIN, ROLE_NURSE, ROLE_DOCTOR],
        'lab.collect' => [ROLE_ADMIN, ROLE_NURSE],
        'lab.testing' => [ROLE_ADMIN, ROLE_NURSE],
        'lab.result' => [ROLE_ADMIN, ROLE_DOCTOR],
        'lab.cancel' => [ROLE_ADMIN, ROLE_DOCTOR],
        'lab.order' => [ROLE_ADMIN, ROLE_DOCTOR, ROLE_NURSE],
        'nursing.add' => [ROLE_ADMIN, ROLE_NURSE],
        'ipd.discharge' => [ROLE_ADMIN, ROLE_CASHIER],
        'ipd.admit' => [ROLE_ADMIN, ROLE_NURSE],
        'ipd.clean' => [ROLE_ADMIN, ROLE_NURSE],
        'ipd.orders' => [ROLE_ADMIN, ROLE_DOCTOR],
        'orders.done' => [ROLE_ADMIN, ROLE_NURSE, ROLE_DOCTOR],
        'orders.add' => [ROLE_ADMIN, ROLE_DOCTOR],
        'orders.cancel' => [ROLE_ADMIN, ROLE_DOCTOR],
        'pharmacy.dispense' => [ROLE_ADMIN, ROLE_NURSE, ROLE_CASHIER],
        'surgery.prep' => [ROLE_ADMIN, ROLE_NURSE],
        'surgery.flow' => [ROLE_ADMIN, ROLE_DOCTOR, ROLE_NURSE],
        'surgery.cancel' => [ROLE_ADMIN, ROLE_DOCTOR, ROLE_NURSE],
        'surgery.add' => [ROLE_ADMIN, ROLE_DOCTOR],
        'refund.act' => [ROLE_ADMIN, ROLE_CASHIER],
        'billing.pay' => [ROLE_ADMIN, ROLE_CASHIER],
        'billing.refund' => [ROLE_ADMIN, ROLE_CASHIER],
        'users.manage' => [ROLE_ADMIN],
    ];
    $allow = $map[$action] ?? null;
    if ($allow === null) return true; // 未定義 = 不限制
    return can_roles($allow);
}
// 依目前角色回可顯示的導覽項目（header 用）
function nav_items() {
    static $all = [
        ['index.php','總覽'], ['patients.php','病歷管理'], ['register.php','門診掛號'],
        ['schedules.php','醫師班表'], ['queue.php','診間叫號'], ['billing.php','批價收費'],
        ['receipt.php','收據'], ['refund.php','退掛退費'], ['pharmacy.php','藥局發藥'],
        ['inventory.php','藥品庫存'], ['ipd.php','住院'], ['lab.php','檢驗檢查'],
        ['nhi.php','健保對帳'], ['surgery.php','手術排程'], ['reports.php','報表'],
        ['users.php','帳號管理'],
    ];
    $r = current_role();
    if ($r === ROLE_ADMIN) return $all;
    return array_values(array_filter($all, fn($it) => can_page($it[0])));
}
