<?php
// 帳號管理：主管專屬
require_once __DIR__ . '/includes/header.php';
if (!can('users.manage')) { flash_set('僅主管可管理帳號'); redirect('index.php'); }
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $u = trim($_POST['username'] ?? ''); $p = $_POST['password'] ?? ''; $r = $_POST['role'] ?? ROLE_CASHIER;
        if (!in_array($r, ROLES_ALL, true)) $r = ROLE_CASHIER;
        if ($u === '' || strlen($u) < 3) { flash_set('帳號至少 3 字'); }
        elseif (strlen($p) < 6) { flash_set('密碼至少 6 字'); }
        else {
            $h = password_hash($p, PASSWORD_DEFAULT);
            try {
                $pdo->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)')->execute([$u, $h, $r]);
                flash_set('已建立帳號 ' . $u . '（' . $r . '）');
            } catch (Throwable $e) { flash_set('建立失敗：帳號可能已存在'); }
        }
    } elseif ($act === 'role') {
        $uid = (int)$_POST['user_id']; $r = $_POST['role'] ?? '';
        // 不可把「自己（含 admin）」降成非主管，避免主管功能被鎖死
        if ($uid === (int)($_SESSION['user']['uid'] ?? 0) && $r !== ROLE_ADMIN) {
            flash_set('不可將自己的帳號改為非主管角色');
        } elseif (in_array($r, ROLES_ALL, true)) {
            $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$r, $uid]);
            flash_set('已更新角色');
        }
    } elseif ($act === 'reset') {
        $uid = (int)$_POST['user_id']; $p = $_POST['password'] ?? '';
        if (strlen($p) < 6) { flash_set('密碼至少 6 字'); }
        else {
            $h = password_hash($p, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$h, $uid]);
            flash_set('已重設密碼');
        }
    }
    redirect('users.php');
}
$users = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY id')->fetchAll();
?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-bold">新增帳號</div><div class="card-body">
<form method="post"><input type="hidden" name="act" value="add">
<div class="mb-2"><label class="form-label">帳號*</label><input name="username" class="form-control" required minlength="3"></div>
<div class="mb-2"><label class="form-label">密碼*</label><input name="password" type="password" class="form-control" required minlength="6"></div>
<div class="mb-3"><label class="form-label">角色</label><select name="role" class="form-select"><?php foreach (ROLES_ALL as $r): ?><option value="<?= h($r) ?>"><?= h($r) ?></option><?php endforeach; ?></select></div>
<button class="btn btn-primary w-100">建立</button></form></div></div>
</div>
<div class="col-md-8"><div class="card shadow-sm"><div class="card-header fw-bold">帳號清單</div>
<table class="table table-sm table-hover mb-0"><tr><th>ID</th><th>帳號</th><th>角色</th><th>重設密碼</th><th>建立日</th></tr>
<?php foreach ($users as $u): ?>
<tr><td><?= $u['id'] ?></td><td><?= h($u['username']) ?></td>
<td><form method="post" class="d-flex gap-1"><input type="hidden" name="act" value="role"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
<select name="role" class="form-select form-select-sm"><?php foreach (ROLES_ALL as $r): ?><option value="<?= h($r) ?>" <?= h($u['role'])===$r?'selected':'' ?>><?= h($r) ?></option><?php endforeach; ?></select>
<button class="btn btn-sm btn-outline-primary">改角色</button></form></td>
<td><form method="post" class="d-flex gap-1"><input type="hidden" name="act" value="reset"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
<input type="password" name="password" class="form-control form-control-sm" placeholder="新密碼" required minlength="6">
<button class="btn btn-sm btn-outline-secondary">重設</button></form></td>
<td><?= h($u['created_at']) ?></td></tr>
<?php endforeach; ?></table></div></div>
</div>
<p class="text-muted small mt-3">權限說明：主管＝全頁；醫師＝掛號/處方/醫囑/檢驗報告；護理＝掛號/叫號/發藥/護理紀錄/手術備案；收銀＝掛號/批價/退費/發藥。導覽與按鈕依角色自動顯示。</p>
<?php require_once __DIR__ . '/includes/footer.php'; ?>