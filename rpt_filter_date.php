<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$years = []; for ($y = date('Y'); $y >= 2020; $y--) $years[] = $y;
$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$days = range(1, 31);

$f_year    = intval($_GET['f_year']    ?? date('Y'));
$f_month   = intval($_GET['f_month']   ?? 0);
$f_day     = intval($_GET['f_day']     ?? 0);
$f_section = trim($_GET['f_section']   ?? '');

$where = ["1=1"];
if ($f_year)    $where[] = "YEAR(mr.meal_date) = $f_year";
if ($f_month)   $where[] = "MONTH(mr.meal_date) = $f_month";
if ($f_day)     $where[] = "DAY(mr.meal_date) = $f_day";
if ($f_section) $where[] = "e.Section = '" . $conn->real_escape_string($f_section) . "'";

$submitted = isset($_GET['f_year']);

$rows = [];
$t_bf = $t_lun = $t_din = $t_total = 0;
if ($submitted) {
    $sql = "SELECT mr.meal_date, e.EmpID, e.Name, e.Section, e.Company,
            SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as amt_BF,
            SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as amt_LUN,
            SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as amt_DIN,
            SUM(mr.amount) as Total
            FROM meal_records mr
            LEFT JOIN employees e ON mr.employee_id = e.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY mr.meal_date, mr.employee_id, e.EmpID, e.Name, e.Section, e.Company
            ORDER BY mr.meal_date, e.Section, e.Name";
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
        $t_bf    += $r['amt_BF'];
        $t_lun   += $r['amt_LUN'];
        $t_din   += $r['amt_DIN'];
        $t_total += $r['Total'];
    }
}

$sections_res = $conn->query("SELECT name FROM opt_sections ORDER BY name");
$sections = []; while ($r = $sections_res->fetch_assoc()) $sections[] = $r['name'];
$conn->close();

$page_title = 'Meals Filter by Date';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Meals Filter by Date</div>
      <div class="page-sub">Filter meal records by year, month, day and section</div>
    </div>
    <?php if ($rows): ?><button onclick="window.print()" class="btn btn-print">Print</button><?php endif; ?>
  </div>

  <div class="card no-print">
    <form method="GET">
      <div class="form-grid">
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
            <option value="0">All Months</option>
            <?php foreach ($months_list as $i=>$m): ?>
            <option value="<?= $i+1 ?>" <?= $f_month==($i+1) ? 'selected':'' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Day</label>
          <select name="f_day" class="form-control">
            <option value="0">All Days</option>
            <?php foreach ($days as $d): ?>
            <option value="<?= $d ?>" <?= $f_day==$d ? 'selected':'' ?>><?= $d ?></option>
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
        <div class="form-group" style="justify-content:flex-end;">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>
      </div>
    </form>
  </div>

  <?php if ($submitted): ?>
  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">MEALS FILTER BY DATE</h3>
  </div>

  <div class="card">
    <div class="card-title">Results &mdash; <?= count($rows) ?> records</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Emp No</th>
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
          <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">No records match the selected filters.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= date('d M Y', strtotime($r['meal_date'])) ?></td>
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Company'] ?? '-') ?></td>
            <td class="right"><?= $r['amt_BF'] > 0 ? number_format($r['amt_BF'],0) : '-' ?></td>
            <td class="right"><?= $r['amt_LUN'] > 0 ? number_format($r['amt_LUN'],0) : '-' ?></td>
            <td class="right"><?= $r['amt_DIN'] > 0 ? number_format($r['amt_DIN'],0) : '-' ?></td>
            <td class="right"><?= number_format($r['Total'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="5"><strong><?= count($rows) ?> records</strong></td>
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
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
