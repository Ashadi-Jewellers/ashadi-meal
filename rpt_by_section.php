<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$years = [];
for ($y = date('Y'); $y >= 2020; $y--) $years[] = $y;
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$f_year    = intval($_GET['f_year']    ?? date('Y'));
$f_month   = intval($_GET['f_month']   ?? date('n'));
$f_section = trim($_GET['f_section']   ?? '');

$month_str = str_pad($f_month, 2, '0', STR_PAD_LEFT);
$where = ["DATE_FORMAT(mr.meal_date,'%Y-%m') = '$f_year-$month_str'"];
if ($f_section) $where[] = "e.Section = '" . $conn->real_escape_string($f_section) . "'";

$sql = "SELECT e.EmpID, e.Name, e.Section,
        SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as Amount_BF,
        SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as Amount_LUN,
        SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as Amount_DIN,
        SUM(mr.amount) as Total,
        DATE_FORMAT(mr.meal_date,'%M') as Month,
        DATE_FORMAT(mr.meal_date,'%Y') as Year
        FROM meal_records mr
        LEFT JOIN employees e ON mr.employee_id = e.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY mr.employee_id, e.EmpID, e.Name, e.Section, Month, Year
        ORDER BY e.Section, e.Name";

$records = $conn->query($sql);
$rows = [];
$t_bf = $t_lun = $t_din = $t_total = 0; $count = 0;
while ($r = $records->fetch_assoc()) {
    $rows[] = $r;
    $t_bf    += $r['Amount_BF'];
    $t_lun   += $r['Amount_LUN'];
    $t_din   += $r['Amount_DIN'];
    $t_total += $r['Total'];
    $count++;
}

$sections_res = $conn->query("SELECT name FROM opt_sections ORDER BY name");
$sections = [];
while ($r = $sections_res->fetch_assoc()) $sections[] = $r['name'];
$conn->close();

$page_title = 'Meal Sum by Section';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Meal Sum by Section</div>
      <div class="page-sub">Monthly meal totals grouped by employee</div>
    </div>
    <button onclick="window.print()" class="btn btn-print">Print</button>
  </div>

  <div class="card no-print">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group">
        <label>Year</label>
        <select name="f_year" class="form-control">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $f_year==$y ? 'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Month</label>
        <select name="f_month" class="form-control">
          <?php foreach ($months as $i=>$m): ?>
          <option value="<?= $i+1 ?>" <?= $f_month==($i+1) ? 'selected':'' ?>><?= $m ?></option>
          <?php endforeach; ?>
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
        <button type="submit" class="btn btn-primary">Preview Report</button>
      </div>
    </form>
  </div>

  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">MEAL SUM BY SECTION</h3>
    <p><?= $months[$f_month-1] ?> <?= $f_year ?><?= $f_section ? ' &mdash; ' . htmlspecialchars($f_section) : '' ?></p>
  </div>

  <div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;">
      <span>Meal Sum &mdash; <?= $months[$f_month-1] ?> <?= $f_year ?></span>
      <?php if ($f_section): ?><span style="font-weight:400;font-size:12px;">Section: <?= htmlspecialchars($f_section) ?></span><?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Num</th>
            <th>Name</th>
            <th>Section</th>
            <th class="right">BF</th>
            <th class="right">LUN</th>
            <th class="right">DIN</th>
            <th class="right">Total</th>
            <th>Month</th>
            <th>Year</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">No records found.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td class="right"><?= number_format($r['Amount_BF'], 0) ?></td>
            <td class="right"><?= number_format($r['Amount_LUN'], 0) ?></td>
            <td class="right"><?= number_format($r['Amount_DIN'], 0) ?></td>
            <td class="right"><?= number_format($r['Total'], 0) ?></td>
            <td><?= htmlspecialchars($r['Month']) ?></td>
            <td><?= htmlspecialchars($r['Year']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="3"><strong><?= $count ?> records</strong></td>
            <td class="right"><strong><?= number_format($t_bf, 0) ?></strong></td>
            <td class="right"><strong><?= number_format($t_lun, 0) ?></strong></td>
            <td class="right"><strong><?= number_format($t_din, 0) ?></strong></td>
            <td class="right"><strong><?= number_format($t_total, 0) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
