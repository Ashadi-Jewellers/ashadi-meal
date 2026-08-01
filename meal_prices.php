<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/meal_helpers.php';
require_login();
if (!is_admin()) { header('Location: index.php'); exit; }

$conn = db_connect();
$user = current_user();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prices'])) {
    $types_ok = true;
    $values = [];
    foreach (['BF', 'LUN', 'DIN'] as $type) {
        $price = $_POST['price_' . $type] ?? '';
        if (!is_numeric($price) || floatval($price) < 0) {
            $types_ok = false;
            break;
        }
        $values[$type] = round(floatval($price), 2);
    }

    if (!$types_ok) {
        $err = 'Please provide a valid, non-negative price for every meal type.';
    } else {
        $actor = $user['full_name'] ?: $user['username'];
        $updated_records_total = 0;

        foreach ($values as $type => $price) {
            $stmt = $conn->prepare("INSERT INTO meal_price_settings (meal_type, price, UpdatedBy) VALUES (?, ?, ?)
                                     ON DUPLICATE KEY UPDATE price = VALUES(price), UpdatedBy = VALUES(UpdatedBy)");
            $stmt->bind_param('sds', $type, $price, $actor);
            $stmt->execute();
            $stmt->close();

            // Resync every existing meal record of this type so amount = new price x quantity.
            // This intentionally rewrites historical records too, as requested - a price change
            // here is meant to be the single source of truth for that meal type going forward.
            $upd = $conn->prepare("UPDATE meal_records SET amount = ? * quantity WHERE meal_type = ?");
            $upd->bind_param('ds', $price, $type);
            $upd->execute();
            $updated_records_total += $upd->affected_rows;
            $upd->close();
        }
        $msg = "Prices updated successfully. $updated_records_total existing meal record(s) were recalculated to match the new prices.";
    }
}

$prices = get_meal_prices($conn);
$conn->close();

$page_title = 'Meal Prices';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <div class="page-title">Meal Prices</div>
      <div class="page-sub">Set the one fixed price per meal type - staff cannot enter or change prices themselves</div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card" style="max-width:520px;">
    <div class="card-title">Fixed Prices (Rs.)</div>
    <div class="alert alert-info" style="margin-bottom:16px;">
      Changing a price here immediately recalculates the amount on <strong>every</strong> existing
      meal record of that type - past and future - using price &times; quantity. Reports and totals
      for earlier dates will reflect the new price too.
    </div>
    <form method="POST">
      <div class="form-group" style="margin-bottom:14px;">
        <label>Breakfast &mdash; price per meal</label>
        <input type="number" name="price_BF" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($prices['BF']) ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label>Lunch &mdash; price per meal</label>
        <input type="number" name="price_LUN" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($prices['LUN']) ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:18px;">
        <label>Dinner &mdash; price per meal</label>
        <input type="number" name="price_DIN" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($prices['DIN']) ?>" required>
      </div>
      <button type="submit" name="save_prices" class="btn btn-primary" onclick="return confirm('This will update the amount on every existing meal record of the changed type(s). Continue?')">Save Prices</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
