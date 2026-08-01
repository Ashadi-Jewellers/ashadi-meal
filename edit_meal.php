<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/meal_helpers.php';
require_login();

$conn = db_connect();
$msg = $err = '';

// Handle delete
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

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $upd_id   = intval($_POST['update_id']);
    $existing = $conn->query("SELECT meal_date, meal_type FROM meal_records WHERE id = $upd_id")->fetch_assoc();
    if (!$existing) {
        $err = 'Record not found.';
    } elseif (!is_admin() && ($lock_err = check_meal_edit_timing($conn, $existing['meal_date'], $existing['meal_type']))) {
        $err = $lock_err;
    } else {
        $quantity = max(1, intval($_POST['upd_quantity'] ?? 1));
        $remarks  = $conn->real_escape_string(trim($_POST['upd_remarks'] ?? ''));
        $meal_type = in_array($_POST['upd_meal_type'] ?? '', ['BF','LUN','DIN']) ? $_POST['upd_meal_type'] : 'BF';

        // Amount is never taken from the form - always fixed price x quantity.
        $prices = get_meal_prices($conn);
        $amount = $prices[$meal_type] * $quantity;

        $conn->query("UPDATE meal_records SET amount=$amount, quantity=$quantity, remarks='$remarks', meal_type='$meal_type' WHERE id=$upd_id");
        $msg = 'Record updated.';
    }
}

// Filter
$f_date     = $_GET['f_date']    ?? date('Y-m-d');
$f_type     = $_GET['f_type']    ?? '';
$f_section  = $_GET['f_section'] ?? '';
$f_emp      = trim($_GET['f_emp'] ?? '');

$where = ["mr.meal_date = '" . $conn->real_escape_string($f_date) . "'"];
if ($f_type)    $where[] = "mr.meal_type = '" . $conn->real_escape_string($f_type) . "'";
if ($f_section) $where[] = "e.Section = '" . $conn->real_escape_string($f_section) . "'";
if ($f_emp)     $where[] = "(e.Name LIKE '%" . $conn->real_escape_string($f_emp) . "%' OR e.EmpID LIKE '%" . $conn->real_escape_string($f_emp) . "%')";

$sql = "SELECT mr.*, e.Name, e.EmpID, e.Section, e.Company FROM meal_records mr LEFT JOIN employees e ON mr.employee_id = e.id WHERE " . implode(' AND ', $where) . " ORDER BY e.Section, e.Name";
$records = $conn->query($sql);

$sections_res = $conn->query("SELECT name FROM opt_sections ORDER BY name");
$sections = [];
while ($r = $sections_res->fetch_assoc()) $sections[] = $r['name'];

// Fixed meal prices (admin-set only) - used to show a live read-only amount in the edit modal
$prices = get_meal_prices($conn);

$page_title = 'Edit Meals';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <div class="page-title">Edit Meals</div>
      <div class="page-sub">Search, edit or delete meal records</div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- Filter -->
  <div class="card no-print">
    <div class="card-title">Filter Records</div>
    <form method="GET">
      <div class="form-grid">
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
        <div class="form-group">
          <label>Section</label>
          <select name="f_section" class="form-control">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $f_section===$s ? 'selected':'' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Employee</label>
          <input type="text" name="f_emp" class="form-control" placeholder="Name or number..." value="<?= htmlspecialchars($f_emp) ?>">
        </div>
        <div class="form-group" style="justify-content:flex-end;">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Results -->
  <div class="card">
    <div class="card-title">Records for <?= date('d M Y', strtotime($f_date)) ?></div>
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
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $total = 0; $count = 0;
          $rows_data = [];
          while ($r = $records->fetch_assoc()) { $rows_data[] = $r; }
          $viewer_is_admin = is_admin();
          if (!$rows_data): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:20px;">No records found.</td></tr>
          <?php endif;
          foreach ($rows_data as $r):
              $total += $r['amount']; $count++;
          ?>
          <tr id="row-<?= $r['id'] ?>">
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td>
              <?php $labels = ['BF'=>'Breakfast','LUN'=>'Lunch','DIN'=>'Dinner']; $classes=['BF'=>'badge-bf','LUN'=>'badge-lun','DIN'=>'badge-din']; ?>
              <span class="badge <?= $classes[$r['meal_type']] ?>"><?= $labels[$r['meal_type']] ?></span>
            </td>
            <td class="right"><?= intval($r['quantity'] ?? 1) ?></td>
            <td class="right">Rs. <?= number_format($r['amount'], 2) ?></td>
            <td><?= htmlspecialchars($r['remarks'] ?? '') ?></td>
            <td class="no-print" style="white-space:nowrap;">
              <?php $row_lock = !$viewer_is_admin ? check_meal_edit_timing($conn, $r['meal_date'], $r['meal_type']) : null; ?>
              <?php if ($row_lock): ?>
              <span class="badge" style="background:#f1eaea;color:#8a5a5a;" title="<?= htmlspecialchars($row_lock) ?>">Locked</span>
              <?php else: ?>
              <button class="btn btn-accent btn-sm" onclick="openEdit(<?= $r['id'] ?>,'<?= $r['meal_type'] ?>',<?= intval($r['quantity'] ?? 1) ?>,<?= $r['amount'] ?>,'<?= addslashes($r['remarks']) ?>')">Edit</button>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?')">
                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                <?php foreach ($_GET as $k=>$v): ?><input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>"><?php endforeach; ?>
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows_data): ?>
        <tfoot>
          <tr>
            <td colspan="5"><strong>Total: <?= $count ?> records</strong></td>
            <td class="right"><strong>Rs. <?= number_format($total, 2) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:10px;padding:28px 32px;width:100%;max-width:420px;box-shadow:0 10px 40px rgba(0,0,0,.3);">
    <h3 style="color:var(--primary);margin-bottom:18px;font-size:16px;">Edit Meal Record</h3>
    <form method="POST">
      <input type="hidden" name="update_id" id="edit_id">
      <?php foreach ($_GET as $k=>$v): ?><input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>"><?php endforeach; ?>
      <div class="form-group" style="margin-bottom:12px;">
        <label>Meal Type</label>
        <select name="upd_meal_type" id="edit_type" class="form-control">
          <option value="BF"  data-price="<?= htmlspecialchars($prices['BF']) ?>">Breakfast</option>
          <option value="LUN" data-price="<?= htmlspecialchars($prices['LUN']) ?>">Lunch</option>
          <option value="DIN" data-price="<?= htmlspecialchars($prices['DIN']) ?>">Dinner</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label>Quantity</label>
        <input type="number" name="upd_quantity" id="edit_quantity" class="form-control" step="1" min="1">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label>Amount (Rs.) &mdash; fixed price, not editable</label>
        <input type="text" id="edit_amount" class="form-control" readonly disabled style="background:var(--page-bg);font-weight:600;">
      </div>
      <div class="form-group" style="margin-bottom:18px;">
        <label>Remarks</label>
        <input type="text" name="upd_remarks" id="edit_remarks" class="form-control">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-outline" onclick="closeEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function updateEditAmount() {
    const opt = document.getElementById('edit_type').options[document.getElementById('edit_type').selectedIndex];
    const price = opt ? parseFloat(opt.dataset.price || '0') : 0;
    const qty = Math.max(1, parseInt(document.getElementById('edit_quantity').value || '1', 10));
    document.getElementById('edit_amount').value = (price * qty).toFixed(2);
}
function openEdit(id, type, quantity, amount, remarks) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_type').value = type;
    document.getElementById('edit_quantity').value = quantity;
    document.getElementById('edit_remarks').value = remarks;
    updateEditAmount();
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit_type').addEventListener('change', updateEditAmount);
document.getElementById('edit_quantity').addEventListener('input', updateEditAmount);
</script>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
