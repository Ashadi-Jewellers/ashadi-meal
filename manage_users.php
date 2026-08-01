<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header('Location: index.php'); exit; }

$conn = db_connect();
$msg = $err = '';

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';
    $password  = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $err = 'Username and password are required.';
    } elseif (strlen($password) < 6) {
        $err = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO meal_users (username, password, full_name, role) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $username, $hash, $full_name, $role);
        if ($stmt->execute()) {
            $msg = 'User created successfully.';
        } else {
            $err = 'Username already exists or error: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Toggle block
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_block'])) {
    $uid = intval($_POST['toggle_block']);
    $new = intval($_POST['new_block']);
    $conn->query("UPDATE meal_users SET is_blocked=$new WHERE id=$uid");
    $msg = 'User status updated.';
}

// Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pw'])) {
    $uid = intval($_POST['reset_pw']);
    $np  = $_POST['new_password'] ?? '';
    if (strlen($np) < 6) {
        $err = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($np, PASSWORD_BCRYPT);
        $conn->query("UPDATE meal_users SET password='$hash' WHERE id=$uid");
        $msg = 'Password reset successfully.';
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = intval($_POST['delete_user']);
    $conn->query("DELETE FROM meal_users WHERE id=$uid AND id != " . current_user()['id']);
    $msg = 'User deleted.';
}

$users = $conn->query("SELECT * FROM meal_users ORDER BY role, full_name");
$conn->close();

$page_title = 'Manage Users';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <div class="page-title">Manage Users</div>
      <div class="page-sub">Meal system login accounts</div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

    <!-- Add User Form -->
    <div class="card">
      <div class="card-title">Add New User</div>
      <form method="POST">
        <div class="form-group" style="margin-bottom:12px;">
          <label>Username</label>
          <input type="text" name="username" class="form-control" required autocomplete="off">
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Full Name</label>
          <input type="text" name="full_name" class="form-control" autocomplete="off">
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Password (min 6 chars)</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <div class="form-group" style="margin-bottom:16px;">
          <label>Role</label>
          <select name="role" class="form-control">
            <option value="staff">Staff (Read + Add Meals)</option>
            <option value="admin">Admin (Full Access)</option>
          </select>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
      </form>
    </div>

    <!-- Users List -->
    <div class="card">
      <div class="card-title">Existing Users</div>
      <?php while ($u = $users->fetch_assoc()): $me = ($u['id'] == current_user()['id']); ?>
      <div style="border:1px solid var(--border);border-radius:6px;padding:12px 14px;margin-bottom:10px;background:<?= $u['is_blocked'] ? '#fff8f8' : '#fff' ?>;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
          <div>
            <strong><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></strong>
            <span style="font-size:11px;color:var(--text-muted);margin-left:6px;">@<?= htmlspecialchars($u['username']) ?></span>
            <?php if ($me): ?><span style="font-size:11px;color:var(--accent);margin-left:4px;">(You)</span><?php endif; ?>
            <br>
            <span style="font-size:11px;color:var(--text-muted);">
              <?= $u['role'] === 'admin' ? 'Admin' : 'Staff' ?>
              &nbsp;&bull;&nbsp;
              <?= $u['is_blocked'] ? '<span style="color:var(--danger);">Blocked</span>' : '<span style="color:var(--success);">Active</span>' ?>
            </span>
          </div>
          <?php if (!$me): ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="toggle_block" value="<?= $u['id'] ?>">
              <input type="hidden" name="new_block" value="<?= $u['is_blocked'] ? 0 : 1 ?>">
              <button class="btn btn-sm <?= $u['is_blocked'] ? 'btn-success' : 'btn-outline' ?>"><?= $u['is_blocked'] ? 'Unblock' : 'Block' ?></button>
            </form>
            <button class="btn btn-accent btn-sm" onclick="showReset(<?= $u['id'] ?>,'<?= addslashes($u['username']) ?>')">Reset PW</button>
            <form method="POST" onsubmit="return confirm('Delete user <?= addslashes($u['username']) ?>?')" style="display:inline;">
              <input type="hidden" name="delete_user" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:10px;padding:28px 32px;width:100%;max-width:360px;">
    <h3 style="color:var(--primary);margin-bottom:16px;font-size:15px;">Reset Password &mdash; <span id="reset-username"></span></h3>
    <form method="POST">
      <input type="hidden" name="reset_pw" id="reset_id">
      <div class="form-group" style="margin-bottom:16px;">
        <label>New Password</label>
        <input type="password" name="new_password" class="form-control" minlength="6" required>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Set Password</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('reset-modal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function showReset(id, username) {
    document.getElementById('reset_id').value = id;
    document.getElementById('reset-username').textContent = username;
    document.getElementById('reset-modal').style.display = 'flex';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
