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
$f_emp     = trim($_GET['f_emp']       ?? '');

$month_str = str_pad($f_month, 2, '0', STR_PAD_LEFT);
$where = ["DATE_FORMAT(mr.meal_date,'%Y-%m') = '$f_year-$month_str'"];
if ($f_emp) $where[] = "(e.Name LIKE '%" . $conn->real_escape_string($f_emp) . "%' OR e.EmpID LIKE '%" . $conn->real_escape_string($f_emp) . "%')";

$sql = "SELECT mr.meal_date, e.EmpID, e.Name, e.Section, e.Company,
        SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as Amount_BF,
        SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as Amount_LUN,
        SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as Amount_DIN,
        SUM(mr.amount) as Total
        FROM meal_records mr
        LEFT JOIN employees e ON mr.employee_id = e.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY mr.meal_date, mr.employee_id, e.EmpID, e.Name, e.Section, e.Company
        ORDER BY e.Name, mr.meal_date";

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
$conn->close();

$page_title = 'Filter by Employee';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Filter by Employee</div>
      <div class="page-sub">View meal records per employee</div>
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
        <label>Employee Name / Number</label>
        <input type="text" name="f_emp" class="form-control" placeholder="Optional filter..." value="<?= htmlspecialchars($f_emp) ?>">
      </div>
      <div class="form-group">
        <button type="submit" class="btn btn-primary">Search</button>
      </div>
    </form>
  </div>

  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">MEALS FILTER BY EMPLOYEE</h3>
    <p><?= $months_list[$f_month-1] ?> <?= $f_year ?><?= $f_emp ? ' &mdash; ' . htmlspecialchars($f_emp) : '' ?></p>
  </div>

  <div class="card">
    <div class="card-title">Employee Meal Records &mdash; <?= $months_list[$f_month-1] ?> <?= $f_year ?></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Number</th>
            <th>Name</th>
            <th>Section</th>
            <th>Company</th>
            <th class="right">BF</th>
            <th class="right">LUN</th>
            <th class="right">DIN</th>
            <th class="right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">No records found.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= date('d M Y', strtotime($r['meal_date'])) ?></td>
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Company'] ?? '-') ?></td>
            <td class="right"><?= $r['Amount_BF'] > 0 ? number_format($r['Amount_BF'], 0) : '00' ?></td>
            <td class="right"><?= $r['Amount_LUN'] > 0 ? number_format($r['Amount_LUN'], 0) : '00' ?></td>
            <td class="right"><?= $r['Amount_DIN'] > 0 ? number_format($r['Amount_DIN'], 0) : '00' ?></td>
            <td class="right"><?= number_format($r['Total'], 0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="5"><strong><?= count($rows) ?> records</strong></td>
            <td class="right"><strong><?= number_format($t_bf, 0) ?></strong></td>
            <td class="right"><strong><?= number_format($t_lun, 0) ?></strong></td>
            <td class="right"><strong><?= number_format($t_din, 0) ?></strong></td>
            <td class="right"><strong><?= number_format($t_total, 0) ?></strong></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
