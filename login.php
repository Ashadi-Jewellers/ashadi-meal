<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
session_start_safe();

if (is_logged_in()) {
    header('Location: index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, is_blocked FROM meal_users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($row && !$row['is_blocked'] && password_verify($password, $row['password'])) {
            $_SESSION['meal_user_id']   = $row['id'];
            $_SESSION['meal_username']  = $row['username'];
            $_SESSION['meal_full_name'] = $row['full_name'];
            $_SESSION['meal_role']      = $row['role'];
            header('Location: index.php'); exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Ashadi Meal System</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --primary: #1a3a5c; --accent: #2980b9; --gold: #c8a84b;
  --bg: #f0f4f8; --surface: #fff; --border: #d0dae6;
  --text: #1c2b3a; --danger: #c0392b;
}
body {
  font-family: 'Segoe UI', Tahoma, sans-serif;
  background: linear-gradient(135deg, #1a3a5c 0%, #234d7a 50%, #1a3a5c 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.login-box {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,.35);
  padding: 44px 40px;
  width: 100%;
  max-width: 400px;
}
.login-brand {
  text-align: center;
  margin-bottom: 32px;
}
.login-brand .logo { font-size: 28px; font-weight: 800; color: var(--primary); letter-spacing: 3px; }
.login-brand .logo span { color: var(--gold); }
.login-brand .sub { font-size: 13px; color: #7a8a9a; margin-top: 4px; text-transform: uppercase; letter-spacing: 2px; }
.login-brand .divider { width: 50px; height: 3px; background: var(--gold); margin: 12px auto 0; border-radius: 2px; }
h2 { font-size: 16px; color: var(--primary); margin-bottom: 22px; text-align: center; font-weight: 600; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #5a7080; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.form-group input {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid var(--border); border-radius: 6px;
  font-size: 14px; color: var(--text);
  transition: border-color .15s, box-shadow .15s;
}
.form-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(41,128,185,.12); }
.btn-login {
  width: 100%; padding: 11px;
  background: var(--primary); color: #fff;
  border: none; border-radius: 6px;
  font-size: 14px; font-weight: 700;
  cursor: pointer; margin-top: 6px;
  letter-spacing: .5px;
  transition: background .15s;
}
.btn-login:hover { background: #234d7a; }
.alert-danger { background: #f5d5d5; border-left: 4px solid var(--danger); color: #8c1a1a; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
.login-footer { text-align: center; margin-top: 20px; font-size: 11px; color: #9aacba; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-brand">
    <div class="logo">ASH<span>ADI</span></div>
    <div class="sub">Jewellers</div>
    <div class="divider"></div>
  </div>
  <h2>Meal System Login</h2>

  <?php if ($error): ?>
  <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn-login">Sign In</button>
  </form>
  <div class="login-footer">Ashadi Jewellers (Pvt) Ltd &mdash; Meal Management System</div>
</div>
</body>
</html>
