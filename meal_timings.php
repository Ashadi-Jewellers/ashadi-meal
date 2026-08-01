<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/meal_helpers.php';
require_login();
if (!is_admin()) { header('Location: index.php'); exit; }

$conn = db_connect();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timings'])) {
    $types_ok = true;
    $values = [];
    foreach (['BF', 'LUN', 'DIN'] as $type) {
        $time = trim($_POST['cutoff_' . $type] ?? '');
        // Expect HH:MM from <input type="time">
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $types_ok = false;
            break;
        }
        $values[$type] = $time . ':00';
    }

    if (!$types_ok) {
        $err = 'Please provide a valid time for every meal type.';
    } else {
        foreach ($values as $type => $time) {
            $stmt = $conn->prepare("INSERT INTO meal_timing_settings (meal_type, cutoff_time) VALUES (?, ?)
                                     ON DUPLICATE KEY UPDATE cutoff_time = VALUES(cutoff_time)");
            $stmt->bind_param('ss', $type, $time);
            $stmt->execute();
            $stmt->close();
        }
        $msg = 'Meal timings updated successfully.';
    }
}

$cutoffs = get_meal_cutoffs($conn);
$conn->close();

$page_title = 'Meal Timings';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <div class="page-title">Meal Timings</div>
      <div class="page-sub">Set the deadline (Sri Lanka time) by which each meal must be added for today</div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card" style="max-width:520px;">
    <div class="card-title">Cutoff Times</div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">
      Staff will not be able to add a meal record dated <strong>today</strong> once the time below has passed
      (Sri Lanka time). This does not apply to past or future dates, and does not apply to Admin users.
    </p>
    <form method="POST">
      <div class="form-group" style="margin-bottom:14px;">
        <label>Breakfast &mdash; deadline</label>
        <input type="time" name="cutoff_BF" class="form-control" value="<?= substr($cutoffs['BF'], 0, 5) ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label>Lunch &mdash; deadline</label>
        <input type="time" name="cutoff_LUN" class="form-control" value="<?= substr($cutoffs['LUN'], 0, 5) ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:18px;">
        <label>Dinner &mdash; deadline</label>
        <input type="time" name="cutoff_DIN" class="form-control" value="<?= substr($cutoffs['DIN'], 0, 5) ?>" required>
      </div>
      <button type="submit" name="save_timings" class="btn btn-primary">Save Timings</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
