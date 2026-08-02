<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$f_date = $_GET['f_date'] ?? date('Y-m-d');
$f_section = trim($_GET['f_section'] ?? '');

$where = ["mr.meal_date = '" . $conn->real_escape_string($f_date) . "'"];
if ($f_section) $where[] = "e.Section = '" . $conn->real_escape_string($f_section) . "'";

$sql = "SELECT e.EmpID, e.Name, e.Section, e.Company,
        SUM(CASE WHEN mr.meal_type='BF'  THEN 1 ELSE 0 END) as cnt_BF,
        SUM(CASE WHEN mr.meal_type='LUN' THEN 1 ELSE 0 END) as cnt_LUN,
        SUM(CASE WHEN mr.meal_type='DIN' THEN 1 ELSE 0 END) as cnt_DIN,
        SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as amt_BF,
        SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as amt_LUN,
        SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as amt_DIN,
        SUM(mr.amount) as Total
        FROM meal_records mr
        LEFT JOIN employees e ON mr.employee_id = e.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY mr.employee_id, e.EmpID, e.Name, e.Section, e.Company
        ORDER BY e.Section, e.Name";

$records = $conn->query($sql);
$rows = [];
$t_bf = $t_lun = $t_din = $t_total = 0;
$n_bf = $n_lun = $n_din = 0;
while ($r = $records->fetch_assoc()) {
    $rows[] = $r;
    $t_bf    += $r['amt_BF'];
    $t_lun   += $r['amt_LUN'];
    $t_din   += $r['amt_DIN'];
    $t_total += $r['Total'];
    if ($r['cnt_BF'])  $n_bf++;
    if ($r['cnt_LUN']) $n_lun++;
    if ($r['cnt_DIN']) $n_din++;
}

$sections_res = $conn->query("SELECT name FROM opt_sections ORDER BY name");
$sections = [];
while ($r = $sections_res->fetch_assoc()) $sections[] = $r['name'];
$conn->close();

$page_title = 'Daily Report';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Daily Report</div>
      <div class="page-sub">All meals for a selected date</div>
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="window.print()" class="btn btn-print">Print</button>
      <button onclick="printCountReport()" class="btn btn-print">Print Count Sheet</button>
    </div>
  </div>

  <div class="card no-print">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group">
        <label>Date</label>
        <input type="date" name="f_date" class="form-control" value="<?= htmlspecialchars($f_date) ?>">
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
        <button type="submit" class="btn btn-primary">View</button>
      </div>
    </form>
  </div>

  <!-- Summary Stats -->
  <?php if ($rows): ?>
  <div class="stat-grid no-print">
    <div class="stat-card">
      <div class="stat-label">Breakfast</div>
      <div class="stat-value"><?= $n_bf ?></div>
      <div class="stat-sub">Rs. <?= number_format($t_bf, 2) ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Lunch</div>
      <div class="stat-value"><?= $n_lun ?></div>
      <div class="stat-sub">Rs. <?= number_format($t_lun, 2) ?></div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Dinner</div>
      <div class="stat-value"><?= $n_din ?></div>
      <div class="stat-sub">Rs. <?= number_format($t_din, 2) ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Total</div>
      <div class="stat-value"><?= count($rows) ?></div>
      <div class="stat-sub">Rs. <?= number_format($t_total, 2) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="print-header amount-print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">DAILY REPORT</h3>
    <p><?= date('l, F j, Y', strtotime($f_date)) ?> &nbsp;&nbsp; <?= date('g:i:s A') ?></p>
  </div>

  <div class="card amount-report-card">
    <div class="card-title">Daily Meal Summary &mdash; <?= date('d M Y', strtotime($f_date)) ?></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Emp No</th>
            <th>Name</th>
            <th>Section</th>
            <th>Company</th>
            <th class="right">BF</th>
            <th class="right">LUN</th>
            <th class="right">DIN</th>
            <th class="right">Total (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:20px;">No records found for this date.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Company'] ?? '-') ?></td>
            <td class="right"><?= $r['cnt_BF'] ? number_format($r['amt_BF'],0) : '-' ?></td>
            <td class="right"><?= $r['cnt_LUN'] ? number_format($r['amt_LUN'],0) : '-' ?></td>
            <td class="right"><?= $r['cnt_DIN'] ? number_format($r['amt_DIN'],0) : '-' ?></td>
            <td class="right"><?= number_format($r['Total'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="4"><strong><?= count($rows) ?> employees</strong></td>
            <td class="right"><strong><?= number_format($t_bf, 2) ?></strong></td>
            <td class="right"><strong><?= number_format($t_lun, 2) ?></strong></td>
            <td class="right"><strong><?= number_format($t_din, 2) ?></strong></td>
            <td class="right"><strong>Rs. <?= number_format($t_total, 2) ?></strong></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Count-only print header (only shown when "Print Count Sheet" is used) -->
  <div class="print-header count-print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">DAILY MEAL COUNT REPORT</h3>
    <p><?= date('l, F j, Y', strtotime($f_date)) ?> &nbsp;&nbsp; <?= date('g:i:s A') ?></p>
  </div>

  <!-- Count-only table (Emp No, Name, Section, BF, LUN, DIN counts) -->
  <div class="card count-report-card">
    <div class="card-title">Daily Meal Count &mdash; <?= date('d M Y', strtotime($f_date)) ?></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Emp No</th>
            <th>Name</th>
            <th>Section</th>
            <th class="right">BF</th>
            <th class="right">LUN</th>
            <th class="right">DIN</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:20px;">No records found for this date.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td class="right"><?= $r['cnt_BF'] ?: '-' ?></td>
            <td class="right"><?= $r['cnt_LUN'] ?: '-' ?></td>
            <td class="right"><?= $r['cnt_DIN'] ?: '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="3"><strong><?= count($rows) ?> employees</strong></td>
            <td class="right"><strong><?= $n_bf ?></strong></td>
            <td class="right"><strong><?= $n_lun ?></strong></td>
            <td class="right"><strong><?= $n_din ?></strong></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<style>
  /* Count sheet hidden on screen by default, and hidden by default when printing too */
  .count-print-header,
  .count-report-card { display: none; }

  @media print {
    /* When "Print Count Sheet" is used, hide the amount report and show the count one */
    body.print-mode-count .amount-print-header,
    body.print-mode-count .amount-report-card { display: none !important; }

    body.print-mode-count .count-print-header,
    body.print-mode-count .count-report-card { display: block !important; }
  }
</style>
<script>
function printCountReport() {
  document.body.classList.add('print-mode-count');
  window.print();
}
window.addEventListener('afterprint', function () {
  document.body.classList.remove('print-mode-count');
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
