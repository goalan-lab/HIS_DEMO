<?php
// _test_roles.php：驗證 can()/can_page()/nav_items() 邏輯（純 session 模擬，不碰 DB）
session_start();
require_once __DIR__ . '/config/permissions.php';
$pass = 0; $fail = 0;
function check($label, $got, $expect) {
    global $pass, $fail;
    if ($got === $expect) { $pass++; echo "  ✅ $label\n"; }
    else { $fail++; echo "  ❌ $label（期望 " . var_export($expect, true) . "，實際 " . var_export($got, true) . "）\n"; }
}
$cases = [
    // [角色, 頁面, 可否開啟]
    ['主管', 'nhi.php', true], ['主管', 'users.php', true],
    ['醫師', 'surgery.php', true], ['醫師', 'billing.php', false], ['醫師', 'users.php', false],
    ['護理', 'lab.php', true], ['護理', 'billing.php', false], ['護理', 'refund.php', false],
    ['收銀', 'refund.php', true], ['收銀', 'nhi.php', false], ['收銀', 'surgery.php', false],
];
echo "== can_page ==\n";
foreach ($cases as [$role, $page, $expect]) {
    $_SESSION['user'] = ['username' => 't', 'role' => $role, 'uid' => 1];
    check("$role / $page => " . ($expect ? '可' : '否'), can_page($page), $expect);
}
// 舊角色自動升為主管
$_SESSION['user'] = ['username' => 'a', 'role' => '管理', 'uid' => 1];
check('舊角色「管理」視同主管（users.php）', can_page('users.php'), true);
echo "== can() action ==\n";
$act = [
    ['收銀', 'lab.collect', false], ['收銀', 'billing.pay', true],
    ['醫師', 'lab.result', true], ['醫師', 'lab.collect', false],
    ['護理', 'surgery.prep', true], ['護理', 'surgery.flow', true],
    ['醫師', 'surgery.flow', true], ['醫師', 'refund.act', false],
    ['主管', 'users.manage', true], ['收銀', 'users.manage', false],
];
foreach ($act as [$role, $a, $expect]) {
    $_SESSION['user'] = ['username' => 't', 'role' => $role, 'uid' => 1];
    check("$role / can('$a') => " . ($expect ? '可' : '否'), can($a), $expect);
}
echo "== nav_items 數量 ==\n";
$_SESSION['user'] = ['username' => 't', 'role' => '主管', 'uid' => 1];
check('主管導覽 16 項', count(nav_items()), 16);
$_SESSION['user'] = ['username' => 't', 'role' => '收銀', 'uid' => 1];
$nav = array_map(fn($i) => $i[0], nav_items());
check('收銀導覽含 billing.php/refund.php', in_array('billing.php', $nav) && in_array('refund.php', $nav), true);
check('收銀導覽無 nhi.php/users.php', in_array('nhi.php', $nav) && in_array('users.php', $nav), false);
$_SESSION['user'] = ['username' => 't', 'role' => '醫師', 'uid' => 1];
$nav = array_map(fn($i) => $i[0], nav_items());
check('醫師導覽含 surgery.php 無 refund.php', in_array('surgery.php', $nav) && !in_array('refund.php', $nav), true);
echo "\n結果：$pass 通過，$fail 失敗\n";
exit($fail ? 1 : 0);