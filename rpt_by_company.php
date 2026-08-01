<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$years = [];
for ($y = date('Y'); $y >= 2020; $y--) $years[] = $y;
$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$f_year    = intval($_GET['f_year']    ?? date('Y'));
$f_month   = intval($_GET['f_month']   ?? date('n'));
$f_company = trim($_GET['f_company']   ?? '');

$month_str = str_pad($f_month, 2, '0', STR_PAD_LEFT);
$where = ["DATE_FORMAT(mr.meal_date,'%Y-%m') = '$f_year-$month_str'"];
if ($f_company) $where[] = "e.Company = '" . $conn->real_escape_string($f_company) . "'";

$sql = "SELECT e.Company,
        SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as Amount_BF,
        SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as Amount_LUN,
        SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as Amount_DIN,
        SUM(mr.amount) as Total,
        COUNT(DISTINCT mr.employee_id) as emp_count
        FROM meal_records mr
        LEFT JOIN employees e ON mr.employee_id = e.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY e.Company
        ORDER BY e.Company";

$records = $conn->query($sql);
$rows = [];
$t_bf = $t_lun = $t_din = $t_total = 0;
while ($r = $records->fetch_assoc()) {
    $rows[] = $r;
    $t_bf    += $r['Amount_BF'];
    $t_lun   += $r['Amount_LUN'];
    $t_din   += $r['Amount_DIN'];
    $t_total += $r['Total'];
}

// Companies for filter
$comp_res = $conn->query("SELECT DISTINCT Company FROM employees WHERE Company IS NOT NULL AND Company != '' ORDER BY Company");
$companies = [];
while ($c = $comp_res->fetch_assoc()) $companies[] = $c['Company'];
$conn->close();

$page_title = 'Sum by Company';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Sum by Company</div>
      <div class="page-sub">Monthly meal totals per company</div>
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
          <?php foreach ($months_list as $i=>$m): ?>
          <option value="<?= $i+1 ?>" <?= $f_month==($i+1) ? 'selected':'' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Company</label>
        <select name="f_company" class="form-control">
          <option value="">All Companies</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $f_company===$c ? 'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <button type="submit" class="btn btn-primary">View Report</button>
      </div>
    </form>
  </div>

  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">SUM BY COMPANY</h3>
    <p><?= $months_list[$f_month-1] ?> <?= $f_year ?></p>
  </div>

  <div class="card">
    <div class="card-title">Company Summary &mdash; <?= $months_list[$f_month-1] ?> <?= $f_year ?></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Company</th>
            <th class="right">Employees</th>
            <th class="right">BF (Rs.)</th>
            <th class="right">LUN (Rs.)</th>
            <th class="right">DIN (Rs.)</th>
            <th class="right">Total (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:20px;">No records found.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['Company'] ?? 'Unknown') ?></td>
            <td class="right"><?= $r['emp_count'] ?></td>
            <td class="right"><?= number_format($r['Amount_BF'], 2) ?></td>
            <td class="right"><?= number_format($r['Amount_LUN'], 2) ?></td>
            <td class="right"><?= number_format($r['Amount_DIN'], 2) ?></td>
            <td class="right"><?= number_format($r['Total'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="2"><strong>Grand Total</strong></td>
            <td class="right"><strong><?= number_format($t_bf, 2) ?></strong></td>
            <td class="right"><strong><?= number_format($t_lun, 2) ?></strong></td>
            <td class="right"><strong><?= number_format($t_din, 2) ?></strong></td>
            <td class="right"><strong><?= number_format($t_total, 2) ?></strong></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
