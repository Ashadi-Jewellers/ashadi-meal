<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/meal_helpers.php';
require_login();

$conn = db_connect();
$user = current_user();
$msg = $err = '';

// Handle form submission
// The individual form now lets the user tick any combination of Breakfast /
// Lunch / Dinner for ONE employee and submit them all together, instead of
// having to re-search the same employee three separate times.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_meal'])) {
    $meal_date   = $_POST['meal_date'] ?? date('Y-m-d');
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $remarks     = trim($_POST['remarks'] ?? '');

    // Price is never taken from the form - it always comes from the admin-set fixed
    // price for the meal type, multiplied by quantity. Staff cannot override this.
    $prices = get_meal_prices($conn);

    // Collect the meal types the user ticked, each with its own quantity
    $selected_meals = [];
    foreach (['BF', 'LUN', 'DIN'] as $t) {
        if (!empty($_POST['include_' . $t])) {
            $selected_meals[$t] = max(1, intval($_POST['qty_' . $t] ?? 1));
        }
    }

    if (!$employee_id || empty($selected_meals)) {
        $err = 'Please select an employee from the search results and tick at least one meal.';
    } else {
        $added = []; $skipped = []; $blocked = [];

        foreach ($selected_meals as $meal_type => $quantity) {
            // Per-meal cutoff time check
            if (!is_admin() && ($timing_err = check_meal_timing($conn, $meal_date, $meal_type))) {
                $blocked[] = meal_type_label($meal_type) . ' - ' . $timing_err;
                continue;
            }

            // Duplicate check (one record per employee/date/meal type)
            $dup = $conn->prepare("SELECT id FROM meal_records WHERE meal_date=? AND employee_id=? AND meal_type=?");
            $dup->bind_param('sis', $meal_date, $employee_id, $meal_type);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                $dup->close();
                $skipped[] = meal_type_label($meal_type) . ' (already exists for this date)';
                continue;
            }
            $dup->close();

            $amount = $prices[$meal_type] * $quantity;
            $stmt = $conn->prepare("INSERT INTO meal_records (meal_date, employee_id, meal_type, quantity, amount, remarks, created_by) VALUES (?,?,?,?,?,?,?)");
            $actor = $user['full_name'] ?: $user['username'];
            $stmt->bind_param('sisidss', $meal_date, $employee_id, $meal_type, $quantity, $amount, $remarks, $actor);
            if ($stmt->execute()) {
                $added[] = meal_type_label($meal_type) . ' (Rs. ' . number_format($amount, 2) . ')';
            } else {
                $blocked[] = meal_type_label($meal_type) . ' - database error: ' . $conn->error;
            }
            $stmt->close();
        }

        if ($added) {
            $msg = 'Added: ' . implode(', ', $added) . '.';
        }
        if ($skipped) {
            $msg .= ($msg ? ' ' : '') . 'Skipped (duplicate): ' . implode(', ', $skipped) . '.';
        }
        if ($blocked) {
            $err = implode(' | ', $blocked);
        }
        if (!$added && !$skipped && !$blocked) {
            $err = 'Nothing was added.';
        }
    }
}

// Handle guest / non-employee meal submission.
// Non-employees (visitors, contractors, walk-ins etc.) also take meals sometimes,
// but we don't track who they are individually - just how many. There is ONE
// running row per date/meal type; ticking a meal again the same day adds the
// new quantity onto that row instead of creating a duplicate.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_guest_meal'])) {
    $g_meal_date = $_POST['guest_meal_date'] ?? date('Y-m-d');
    $g_remarks   = trim($_POST['guest_remarks'] ?? '');

    $prices = get_meal_prices($conn);

    $g_selected_meals = [];
    foreach (['BF', 'LUN', 'DIN'] as $t) {
        if (!empty($_POST['ginclude_' . $t])) {
            $g_selected_meals[$t] = max(1, intval($_POST['gqty_' . $t] ?? 1));
        }
    }

    if (empty($g_selected_meals)) {
        $err = 'Please tick at least one meal for the non-employees.';
    } else {
        $g_added = []; $g_blocked = [];

        foreach ($g_selected_meals as $meal_type => $quantity) {
            // Per-meal cutoff time check - same rule as employee meals
            if (!is_admin() && ($timing_err = check_meal_timing($conn, $g_meal_date, $meal_type))) {
                $g_blocked[] = meal_type_label($meal_type) . ' - ' . $timing_err;
                continue;
            }

            $unit_price = $prices[$meal_type];

            // Look for today's existing non-employee row for this meal type
            $existing = $conn->prepare("SELECT id, quantity FROM meal_records WHERE meal_date=? AND is_guest=1 AND guest_name IS NULL AND meal_type=?");
            $existing->bind_param('ss', $g_meal_date, $meal_type);
            $existing->execute();
            $row = $existing->get_result()->fetch_assoc();
            $existing->close();

            if ($row) {
                $new_qty    = intval($row['quantity']) + $quantity;
                $new_amount = $unit_price * $new_qty;
                $upd = $conn->prepare("UPDATE meal_records SET quantity=?, amount=?, remarks=? WHERE id=?");
                $upd->bind_param('idsi', $new_qty, $new_amount, $g_remarks, $row['id']);
                if ($upd->execute()) {
                    $g_added[] = meal_type_label($meal_type) . ' - now ' . $new_qty . ' (Rs. ' . number_format($new_amount, 2) . ')';
                } else {
                    $g_blocked[] = meal_type_label($meal_type) . ' - database error: ' . $conn->error;
                }
                $upd->close();
            } else {
                $amount = $unit_price * $quantity;
                $stmt = $conn->prepare("INSERT INTO meal_records (meal_date, employee_id, guest_name, is_guest, meal_type, quantity, amount, remarks, created_by) VALUES (?,NULL,NULL,1,?,?,?,?,?)");
                $actor = $user['full_name'] ?: $user['username'];
                $stmt->bind_param('ssidss', $g_meal_date, $meal_type, $quantity, $amount, $g_remarks, $actor);
                if ($stmt->execute()) {
                    $g_added[] = meal_type_label($meal_type) . ' (Rs. ' . number_format($amount, 2) . ')';
                } else {
                    $g_blocked[] = meal_type_label($meal_type) . ' - database error: ' . $conn->error;
                }
                $stmt->close();
            }
        }

        if ($g_added) {
            $msg = 'Non-Employee meals: ' . implode(', ', $g_added) . '.';
        }
        if ($g_blocked) {
            $err = implode(' | ', $g_blocked);
        }
        if (!$g_added && !$g_blocked) {
            $err = 'Nothing was added.';
        }
    }
}

// Delete an existing meal record (only within the meal's cutoff time, unless admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $existing = $conn->query("SELECT meal_date, meal_type FROM meal_records WHERE id = $del_id")->fetch_assoc();
    if (!$existing) {
        $err = 'Record not found.';
    } elseif (!is_admin() && ($lock_err = check_meal_edit_timing($conn, $existing['meal_date'], $existing['meal_type']))) {
        $err = $lock_err;
    } else {
        $conn->query("DELETE FROM meal_records WHERE id = $del_id");
        $msg = 'Record deleted.';
    }
}

// Update an existing meal record (only within the meal's cutoff time, unless admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $upd_id   = intval($_POST['update_id']);
    $existing = $conn->query("SELECT meal_date, meal_type FROM meal_records WHERE id = $upd_id")->fetch_assoc();
    if (!$existing) {
        $err = 'Record not found.';
    } elseif (!is_admin() && ($lock_err = check_meal_edit_timing($conn, $existing['meal_date'], $existing['meal_type']))) {
        $err = $lock_err;
    } else {
        $quantity  = max(1, intval($_POST['upd_quantity'] ?? 1));
        $remarks   = $conn->real_escape_string(trim($_POST['upd_remarks'] ?? ''));
        $meal_type = in_array($_POST['upd_meal_type'] ?? '', ['BF','LUN','DIN']) ? $_POST['upd_meal_type'] : $existing['meal_type'];

        // Amount is never taken from the form - always fixed price x quantity.
        $prices = get_meal_prices($conn);
        $amount = $prices[$meal_type] * $quantity;

        $conn->query("UPDATE meal_records SET amount=$amount, quantity=$quantity, remarks='$remarks', meal_type='$meal_type' WHERE id=$upd_id");
        $msg = 'Record updated.';
    }
}

// Filter for "Meals Entered" list - defaults to today, Sri Lanka time
$f_date = $_GET['f_date'] ?? sl_now()->format('Y-m-d');
$f_type = $_GET['f_type'] ?? '';

$where = ["mr.meal_date = '" . $conn->real_escape_string($f_date) . "'"];
if ($f_type && in_array($f_type, ['BF','LUN','DIN'])) {
    $where[] = "mr.meal_type = '" . $conn->real_escape_string($f_type) . "'";
}
$list_sql = "SELECT mr.*, COALESCE(e.Name, mr.guest_name) AS Name, e.EmpID, e.Section FROM meal_records mr
             LEFT JOIN employees e ON mr.employee_id = e.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY mr.meal_type, Name";
$entered_records = $conn->query($list_sql);

// Load meal timing cutoffs (Sri Lanka time) to display as hints on the form
$cutoffs = get_meal_cutoffs($conn);

// Load fixed meal prices (admin-set only) to display as read-only info on the form
$prices = get_meal_prices($conn);

$page_title = 'Add Meals';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <div class="page-title">Add Meals</div>
      <div class="page-sub">Add meals for an employee, and manage what's already entered for a date</div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- Individual Add -->
  <div class="card">
    <div class="card-title">Add Individual Meal</div>
    <form method="POST" id="meal-form">
      <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="meal_date" class="form-control" value="<?= $_POST['meal_date'] ?? date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="grid-column: span 2;">
          <label>Search Employee (Name or Number)</label>
          <div style="position:relative;">
            <input type="text" id="emp_search" class="form-control" placeholder="Type employee name or number..." autocomplete="off" value="<?= htmlspecialchars($_POST['emp_search_display'] ?? '') ?>">
            <input type="hidden" name="employee_id" id="employee_id_val" value="<?= intval($_POST['employee_id'] ?? 0) ?>">
            <div id="emp_results"></div>
          </div>
          <div id="emp_selected" style="margin-top:5px;font-size:12px;color:#1a5a8c;font-weight:600;min-height:18px;">
            <?php if (!empty($_POST['employee_id'])): ?>Selected employee ID: <?= intval($_POST['employee_id']) ?><?php endif; ?>
          </div>
        </div>
        <div class="form-group">
          <label>Remarks</label>
          <input type="text" name="remarks" class="form-control" value="<?= htmlspecialchars($_POST['remarks'] ?? '') ?>">
        </div>
      </div>

      <!-- Meal selection table - tick any combination of Breakfast/Lunch/Dinner
           for the selected employee, each with its own quantity and amount,
           so all meals can be submitted together in one go. -->
      <div style="margin-top:18px;overflow-x:auto;">
        <table id="meal_select_table" style="width:100%;border-collapse:collapse;min-width:520px;">
          <thead>
            <tr style="background:var(--page-bg);">
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Include</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Meal</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Cutoff (SL time)</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Quantity</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Amount (Rs.)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['BF' => 'Breakfast', 'LUN' => 'Lunch', 'DIN' => 'Dinner'] as $code => $label): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:10px;">
                <input type="checkbox" class="meal-include" name="include_<?= $code ?>" id="include_<?= $code ?>" value="1" data-type="<?= $code ?>" style="width:18px;height:18px;cursor:pointer;">
              </td>
              <td style="padding:10px;">
                <label for="include_<?= $code ?>" style="cursor:pointer;font-weight:600;color:var(--navy);"><?= $label ?></label>
                <div style="font-size:11px;color:var(--text-muted);">Rs. <?= htmlspecialchars(number_format($prices[$code], 2)) ?> each</div>
              </td>
              <td style="padding:10px;font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(format_cutoff_time($cutoffs[$code])) ?></td>
              <td style="padding:10px;">
                <input type="number" class="form-control meal-qty" name="qty_<?= $code ?>" id="qty_<?= $code ?>" data-price="<?= htmlspecialchars($prices[$code]) ?>" step="1" min="1" value="1" disabled style="max-width:90px;">
              </td>
              <td style="padding:10px;">
                <input type="text" class="form-control meal-amount" id="amount_<?= $code ?>" value="0.00" readonly disabled style="max-width:120px;background:var(--page-bg);font-weight:600;">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="padding:12px 10px;text-align:right;font-weight:700;color:var(--navy);">Total</td>
              <td style="padding:12px 10px;">
                <input type="text" id="total_amount_display" value="0.00" readonly disabled style="max-width:120px;width:100%;font-weight:700;font-size:15px;background:var(--page-bg);border:1.5px solid var(--border);border-radius:6px;padding:8px 10px;color:var(--navy);">
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div style="margin-top:14px;">
        <button type="submit" name="submit_meal" class="btn btn-primary">Add Meal Record(s)</button>
      </div>
    </form>
  </div>

  <!-- Add Meal for Non-Employee (guest / visitor / contractor) -->
  <div class="card">
    <div class="card-title">Add Meal for Non-Employee</div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Just tick how many extra meals were taken by people outside the employee list - no name needed, quantities add up through the day.</p>
    <form method="POST" id="guest-meal-form">
      <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="guest_meal_date" class="form-control" value="<?= $_POST['guest_meal_date'] ?? date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="grid-column: span 2;">
          <label>Remarks</label>
          <input type="text" name="guest_remarks" class="form-control" value="<?= htmlspecialchars($_POST['guest_remarks'] ?? '') ?>">
        </div>
      </div>

      <div style="margin-top:18px;overflow-x:auto;">
        <table id="guest_meal_select_table" style="width:100%;border-collapse:collapse;min-width:520px;">
          <thead>
            <tr style="background:var(--page-bg);">
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Include</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Meal</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Cutoff (SL time)</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Quantity</th>
              <th style="text-align:left;padding:9px 10px;font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Amount (Rs.)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['BF' => 'Breakfast', 'LUN' => 'Lunch', 'DIN' => 'Dinner'] as $code => $label): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:10px;">
                <input type="checkbox" class="guest-meal-include" name="ginclude_<?= $code ?>" id="ginclude_<?= $code ?>" value="1" data-type="<?= $code ?>" style="width:18px;height:18px;cursor:pointer;">
              </td>
              <td style="padding:10px;">
                <label for="ginclude_<?= $code ?>" style="cursor:pointer;font-weight:600;color:var(--navy);"><?= $label ?></label>
                <div style="font-size:11px;color:var(--text-muted);">Rs. <?= htmlspecialchars(number_format($prices[$code], 2)) ?> each</div>
              </td>
              <td style="padding:10px;font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(format_cutoff_time($cutoffs[$code])) ?></td>
              <td style="padding:10px;">
                <input type="number" class="form-control guest-meal-qty" name="gqty_<?= $code ?>" id="gqty_<?= $code ?>" data-price="<?= htmlspecialchars($prices[$code]) ?>" step="1" min="1" value="1" disabled style="max-width:90px;">
              </td>
              <td style="padding:10px;">
                <input type="text" class="form-control guest-meal-amount" id="gamount_<?= $code ?>" value="0.00" readonly disabled style="max-width:120px;background:var(--page-bg);font-weight:600;">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="padding:12px 10px;text-align:right;font-weight:700;color:var(--navy);">Total</td>
              <td style="padding:12px 10px;">
                <input type="text" id="guest_total_amount_display" value="0.00" readonly disabled style="max-width:120px;width:100%;font-weight:700;font-size:15px;background:var(--page-bg);border:1.5px solid var(--border);border-radius:6px;padding:8px 10px;color:var(--navy);">
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div style="margin-top:14px;">
        <button type="submit" name="submit_guest_meal" class="btn btn-primary">Add Non-Employee Meal(s)</button>
      </div>
    </form>
  </div>

  <!-- Meals Entered - filter by date, see counts, edit/delete within cutoff time -->
  <div class="card">
    <div class="card-title">Meals Entered</div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">See how many meals have been entered for a date. Edit and Delete are only available while the meal's cutoff time (set in <a href="meal_timings.php">Meal Timings</a>) hasn't passed yet for today - once it passes the record locks automatically. Admin users are never locked.</p>

    <form method="GET" style="margin-bottom:18px;">
      <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="f_date" class="form-control" value="<?= htmlspecialchars($f_date) ?>">
        </div>
        <div class="form-group">
          <label>Meal Type</label>
          <select name="f_type" class="form-control">
            <option value="">All</option>
            <option value="BF"  <?= $f_type==='BF'  ? 'selected':'' ?>>Breakfast</option>
            <option value="LUN" <?= $f_type==='LUN' ? 'selected':'' ?>>Lunch</option>
            <option value="DIN" <?= $f_type==='DIN' ? 'selected':'' ?>>Dinner</option>
          </select>
        </div>
        <div class="form-group" style="justify-content:flex-end;">
          <button type="submit" class="btn btn-primary">Filter</button>
        </div>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Emp No</th>
            <th>Name</th>
            <th>Section</th>
            <th>Type</th>
            <th class="right">Qty</th>
            <th class="right">Amount</th>
            <th>Remarks</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $entered_rows = [];
          while ($r = $entered_records->fetch_assoc()) { $entered_rows[] = $r; }
          $viewer_is_admin = is_admin();
          $entered_total = 0;
          $entered_qty_total = 0;
          if (!$entered_rows): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:20px;">No meals entered for <?= date('d M Y', strtotime($f_date)) ?> yet.</td></tr>
          <?php endif;
          foreach ($entered_rows as $r):
              $entered_total += $r['amount'];
              $entered_qty_total += intval($r['quantity'] ?? 1);
              $labels = ['BF'=>'Breakfast','LUN'=>'Lunch','DIN'=>'Dinner'];
              $classes = ['BF'=>'badge-bf','LUN'=>'badge-lun','DIN'=>'badge-din'];
          ?>
          <tr id="entered-row-<?= $r['id'] ?>">
            <td><?= !empty($r['is_guest']) ? '<span class="badge" style="background:#eaf1f8;color:#1a5a8c;">GUEST</span>' : htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? ($r['is_guest'] ? 'Non-Employee' : '-')) ?></td>
            <td><?= !empty($r['is_guest']) ? '-' : htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td><span class="badge <?= $classes[$r['meal_type']] ?? '' ?>"><?= $labels[$r['meal_type']] ?? $r['meal_type'] ?></span></td>
            <td class="right"><?= intval($r['quantity'] ?? 1) ?></td>
            <td class="right">Rs. <?= number_format($r['amount'], 2) ?></td>
            <td><?= htmlspecialchars($r['remarks'] ?? '') ?></td>
            <td style="white-space:nowrap;">
              <?php $row_lock = !$viewer_is_admin ? check_meal_edit_timing($conn, $r['meal_date'], $r['meal_type']) : null; ?>
              <?php if ($row_lock): ?>
              <span class="badge" style="background:#f1eaea;color:#8a5a5a;" title="<?= htmlspecialchars($row_lock) ?>">Locked</span>
              <?php else: ?>
              <button type="button" class="btn btn-accent btn-sm" onclick="openEnteredEdit(<?= $r['id'] ?>,'<?= $r['meal_type'] ?>',<?= intval($r['quantity'] ?? 1) ?>,'<?= addslashes($r['remarks'] ?? '') ?>')">Edit</button>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?')">
                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="f_date" value="<?= htmlspecialchars($f_date) ?>">
                <input type="hidden" name="f_type" value="<?= htmlspecialchars($f_type) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($entered_rows): ?>
        <tfoot>
          <tr>
            <td colspan="5"><strong>Total: <?= $entered_qty_total ?> meals</strong> <span style="font-weight:400;color:var(--text-muted);">(<?= count($entered_rows) ?> record<?= count($entered_rows) === 1 ? '' : 's' ?>)</span></td>
            <td class="right"><strong>Rs. <?= number_format($entered_total, 2) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Edit modal for a "Meals Entered" row -->
  <div id="entered-edit-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;padding:28px 32px;width:100%;max-width:420px;box-shadow:0 10px 40px rgba(0,0,0,.3);">
      <h3 style="color:var(--navy);margin-bottom:18px;font-size:16px;">Edit Meal Record</h3>
      <form method="POST">
        <input type="hidden" name="update_id" id="entered_edit_id">
        <input type="hidden" name="f_date" value="<?= htmlspecialchars($f_date) ?>">
        <input type="hidden" name="f_type" value="<?= htmlspecialchars($f_type) ?>">
        <div class="form-group" style="margin-bottom:12px;">
          <label>Meal Type</label>
          <select name="upd_meal_type" id="entered_edit_type" class="form-control">
            <option value="BF"  data-price="<?= htmlspecialchars($prices['BF']) ?>">Breakfast</option>
            <option value="LUN" data-price="<?= htmlspecialchars($prices['LUN']) ?>">Lunch</option>
            <option value="DIN" data-price="<?= htmlspecialchars($prices['DIN']) ?>">Dinner</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Quantity</label>
          <input type="number" name="upd_quantity" id="entered_edit_quantity" class="form-control" step="1" min="1">
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Amount (Rs.) &mdash; fixed price, not editable</label>
          <input type="text" id="entered_edit_amount" class="form-control" readonly disabled style="background:var(--page-bg);font-weight:600;">
        </div>
        <div class="form-group" style="margin-bottom:18px;">
          <label>Remarks</label>
          <input type="text" name="upd_remarks" id="entered_edit_remarks" class="form-control">
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <button type="button" class="btn btn-outline" onclick="closeEnteredEdit()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
  <?php $conn->close(); ?>
</div>

<style>
#emp_results {
  background:#fff;
  border:1.5px solid #d0dae6;
  border-radius:6px;
  max-height:220px;
  overflow-y:auto;
  display:none;
  position:absolute;
  width:100%;
  box-shadow:0 4px 12px rgba(0,0,0,.12);
  z-index:999;
  top:100%;
  left:0;
}
.emp-item { padding:9px 14px; cursor:pointer; font-size:13px; border-bottom:1px solid #f0f4f8; }
.emp-item:hover { background:#f0f4f8; }
.emp-item strong { color:#1a3a5c; }
.emp-item small { color:#7a8a9a; margin-left:8px; }
</style>
<script>
const searchInput = document.getElementById('emp_search');
const resultsBox  = document.getElementById('emp_results');
const hiddenId    = document.getElementById('employee_id_val');
const selectedDiv = document.getElementById('emp_selected');
let debounceTimer;

searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    const q = this.value.trim();
    hiddenId.value = '';
    selectedDiv.textContent = '';
    selectedDiv.style.color = '#1a5a8c';
    if (q.length < 2) { resultsBox.style.display='none'; resultsBox.innerHTML=''; return; }
    debounceTimer = setTimeout(() => {
        fetch('search_employee.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    resultsBox.innerHTML = '<div class="emp-item" style="color:#999;cursor:default;">No employees found</div>';
                    resultsBox.style.display = 'block';
                    return;
                }
                resultsBox.innerHTML = data.map(e =>
                    `<div class="emp-item" data-id="${e.id}" data-name="${e.Name}" data-empid="${e.EmpID || '-'}" data-section="${e.Section || ''}">
                        <strong>${e.EmpID || '-'}</strong> &mdash; ${e.Name}<small>${e.Section || ''}</small>
                    </div>`
                ).join('');
                resultsBox.style.display = 'block';
            })
            .catch(() => { resultsBox.style.display='none'; });
    }, 280);
});

resultsBox.addEventListener('click', function(e) {
    const item = e.target.closest('.emp-item');
    if (!item || !item.dataset.id) return;
    hiddenId.value = item.dataset.id;
    searchInput.value = item.dataset.name;
    selectedDiv.textContent = 'Selected: ' + item.dataset.empid + ' - ' + item.dataset.name + ' (' + item.dataset.section + ')';
    selectedDiv.style.color = '#27ae60';
    resultsBox.style.display = 'none';
    resultsBox.innerHTML = '';
});

document.addEventListener('click', function(e) {
    if (!resultsBox.contains(e.target) && e.target !== searchInput) {
        resultsBox.style.display = 'none';
    }
});

// Meal selection table (Breakfast/Lunch/Dinner) for the individual add form.
// Ticking a meal enables its quantity input; amount = fixed price x quantity;
// the Total field sums every ticked meal's amount live.
const mealCodes = ['BF', 'LUN', 'DIN'];
const totalAmountDisplay = document.getElementById('total_amount_display');

function updateMealRow(code) {
    const checkbox = document.getElementById('include_' + code);
    const qtyInput = document.getElementById('qty_' + code);
    const amountDisplay = document.getElementById('amount_' + code);
    const price = parseFloat(qtyInput.dataset.price || '0');

    qtyInput.disabled = !checkbox.checked;

    if (checkbox.checked) {
        const qty = Math.max(1, parseInt(qtyInput.value || '1', 10));
        amountDisplay.value = (price * qty).toFixed(2);
    } else {
        amountDisplay.value = '0.00';
    }
}

function updateTotal() {
    let total = 0;
    mealCodes.forEach(function (code) {
        total += parseFloat(document.getElementById('amount_' + code).value || '0');
    });
    totalAmountDisplay.value = total.toFixed(2);
}

function refreshAll() {
    mealCodes.forEach(updateMealRow);
    updateTotal();
}

mealCodes.forEach(function (code) {
    const checkbox = document.getElementById('include_' + code);
    const qtyInput = document.getElementById('qty_' + code);
    checkbox.addEventListener('change', refreshAll);
    qtyInput.addEventListener('input', refreshAll);
});
refreshAll();

// Meal selection table for the Non-Employee (guest) add form - same live
// price x quantity behaviour as the employee table above, kept separate.
const guestMealCodes = ['BF', 'LUN', 'DIN'];
const guestTotalAmountDisplay = document.getElementById('guest_total_amount_display');

function updateGuestMealRow(code) {
    const checkbox = document.getElementById('ginclude_' + code);
    const qtyInput = document.getElementById('gqty_' + code);
    const amountDisplay = document.getElementById('gamount_' + code);
    const price = parseFloat(qtyInput.dataset.price || '0');

    qtyInput.disabled = !checkbox.checked;

    if (checkbox.checked) {
        const qty = Math.max(1, parseInt(qtyInput.value || '1', 10));
        amountDisplay.value = (price * qty).toFixed(2);
    } else {
        amountDisplay.value = '0.00';
    }
}

function updateGuestTotal() {
    let total = 0;
    guestMealCodes.forEach(function (code) {
        total += parseFloat(document.getElementById('gamount_' + code).value || '0');
    });
    guestTotalAmountDisplay.value = total.toFixed(2);
}

function refreshGuestAll() {
    guestMealCodes.forEach(updateGuestMealRow);
    updateGuestTotal();
}

guestMealCodes.forEach(function (code) {
    const checkbox = document.getElementById('ginclude_' + code);
    const qtyInput = document.getElementById('gqty_' + code);
    checkbox.addEventListener('change', refreshGuestAll);
    qtyInput.addEventListener('input', refreshGuestAll);
});
refreshGuestAll();

document.getElementById('guest-meal-form').addEventListener('submit', function(e) {
    const anyChecked = guestMealCodes.some(function (code) {
        return document.getElementById('ginclude_' + code).checked;
    });
    if (!anyChecked) {
        e.preventDefault();
        alert('Please tick at least one meal (Breakfast/Lunch/Dinner) to add.');
    }
});

// Edit modal for a "Meals Entered" row
function updateEnteredEditAmount() {
    const sel = document.getElementById('entered_edit_type');
    const opt = sel.options[sel.selectedIndex];
    const price = opt ? parseFloat(opt.dataset.price || '0') : 0;
    const qty = Math.max(1, parseInt(document.getElementById('entered_edit_quantity').value || '1', 10));
    document.getElementById('entered_edit_amount').value = (price * qty).toFixed(2);
}
function openEnteredEdit(id, type, quantity, remarks) {
    document.getElementById('entered_edit_id').value = id;
    document.getElementById('entered_edit_type').value = type;
    document.getElementById('entered_edit_quantity').value = quantity;
    document.getElementById('entered_edit_remarks').value = remarks;
    updateEnteredEditAmount();
    document.getElementById('entered-edit-modal').style.display = 'flex';
}
function closeEnteredEdit() {
    document.getElementById('entered-edit-modal').style.display = 'none';
}
document.getElementById('entered_edit_type').addEventListener('change', updateEnteredEditAmount);
document.getElementById('entered_edit_quantity').addEventListener('input', updateEnteredEditAmount);

// Warn if submitting without selecting employee or without ticking any meal
document.getElementById('meal-form').addEventListener('submit', function(e) {
    if (!hiddenId.value || hiddenId.value === '0') {
        e.preventDefault();
        selectedDiv.textContent = 'Please search and click on an employee from the list first.';
        selectedDiv.style.color = '#c0392b';
        searchInput.focus();
        return;
    }
    const anyChecked = mealCodes.some(function (code) {
        return document.getElementById('include_' + code).checked;
    });
    if (!anyChecked) {
        e.preventDefault();
        selectedDiv.textContent = 'Please tick at least one meal (Breakfast/Lunch/Dinner) to add.';
        selectedDiv.style.color = '#c0392b';
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
