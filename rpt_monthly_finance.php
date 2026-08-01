<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$years = [];
for ($y = date('Y'); $y >= 2020; $y--) $years[] = $y;
$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$f_year  = intval($_GET['f_year']  ?? date('Y'));
$f_month = intval($_GET['f_month'] ?? date('n'));

$month_str = str_pad($f_month, 2, '0', STR_PAD_LEFT);

$sql = "SELECT e.EmpID, e.Name, e.Section, e.Company,
        SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as Amount_BF,
        SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as Amount_LUN,
        SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as Amount_DIN,
        SUM(mr.amount) as Total
        FROM meal_records mr
        LEFT JOIN employees e ON mr.employee_id = e.id
        WHERE DATE_FORMAT(mr.meal_date,'%Y-%m') = '$f_year-$month_str'
        GROUP BY mr.employee_id, e.EmpID, e.Name, e.Section, e.Company
        ORDER BY e.Company, e.Section, e.Name";

$records = $conn->query($sql);
$rows = [];
$t_bf = $t_lun = $t_din = $t_total = 0;
$company_groups = [];
while ($r = $records->fetch_assoc()) {
    $rows[] = $r;
    $company = $r['Company'] ?: 'Unknown';
    if (!isset($company_groups[$company])) $company_groups[$company] = ['rows'=>[],'bf'=>0,'lun'=>0,'din'=>0,'total'=>0];
    $company_groups[$company]['rows'][]   = $r;
    $company_groups[$company]['bf']      += $r['Amount_BF'];
    $company_groups[$company]['lun']     += $r['Amount_LUN'];
    $company_groups[$company]['din']     += $r['Amount_DIN'];
    $company_groups[$company]['total']   += $r['Total'];
    $t_bf    += $r['Amount_BF'];
    $t_lun   += $r['Amount_LUN'];
    $t_din   += $r['Amount_DIN'];
    $t_total += $r['Total'];
}
$conn->close();

$page_title = 'Monthly Report for Finance';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Finance Report – page-level overrides ─────────────────── */

/* Scrollable table on small screens */
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.table-wrap table {
    min-width: 560px;   /* prevent columns from collapsing too far */
    width: 100%;
}

/* ── Grand-Total box ───────────────────────────────────────── */
.grand-total-box {
    background: var(--navy);          /* always uses the defined navy */
    color: #fff;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 20px 24px;
    margin-bottom: 20px;
    border: none;
}

.grand-total-label {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .5px;
    color: #fff;
    margin-bottom: 16px;
}

/* 4-column grid for the totals – collapses to 2 on mobile */
.grand-total-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

@media (max-width: 600px) {
    .grand-total-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 360px) {
    .grand-total-grid {
        grid-template-columns: 1fr;
    }
}

.gt-item {
    background: rgba(255,255,255,.10);
    border-radius: var(--radius);
    padding: 12px 14px;
    text-align: center;
}

.gt-item-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: rgba(255,255,255,.65);
    margin-bottom: 6px;
}

.gt-item-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--gold-hover, #e8c76b);
}

/* make the TOTAL item stand out a bit more */
.gt-item.is-total .gt-item-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--gold, #c8a84b);
}

/* ── Mobile – nav overflows: allow horizontal scroll ──────── */
@media (max-width: 600px) {
    .container { padding: 12px 8px; }

    /* Shrink page-header to stack vertically */
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    /* Finance filter form stacks */
    .finance-form {
        flex-direction: column !important;
        gap: 10px !important;
    }
    .finance-form .form-group { width: 100%; }
    .finance-form select.form-control { width: 100%; }
}
</style>

<div class="container">

  <!-- Page header -->
  <div class="page-header no-print">
    <div>
      <div class="page-title">Monthly Report for Finance</div>
      <div class="page-sub">Full breakdown grouped by company</div>
    </div>
    <button onclick="window.print()" class="btn btn-print">Print Report</button>
  </div>

  <!-- Filter form -->
  <div class="card no-print">
    <form method="GET" class="finance-form" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
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
        <button type="submit" class="btn btn-primary">Generate Report</button>
      </div>
    </form>
  </div>

  <!-- Print-only header -->
  <div class="print-header">
    <h2>ASHADI JEWELLERS (PVT) LTD</h2>
    <h3 style="font-size:14px;margin-top:4px;">MONTHLY MEAL REPORT FOR FINANCE</h3>
    <p><?= strtoupper($months_list[$f_month-1]) ?> <?= $f_year ?> &nbsp;&nbsp; Generated: <?= date('d/m/Y H:i') ?></p>
  </div>

  <!-- Per Company Sections -->
  <?php foreach ($company_groups as $company => $group): ?>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-title"><?= htmlspecialchars($company) ?></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Emp No</th>
            <th>Name</th>
            <th>Section</th>
            <th class="right">BF (Rs.)</th>
            <th class="right">LUN (Rs.)</th>
            <th class="right">DIN (Rs.)</th>
            <th class="right">Total (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($group['rows'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td class="right"><?= number_format($r['Amount_BF'], 2) ?></td>
            <td class="right"><?= number_format($r['Amount_LUN'], 2) ?></td>
            <td class="right"><?= number_format($r['Amount_DIN'], 2) ?></td>
            <td class="right"><?= number_format($r['Total'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3"><strong>Sub Total</strong></td>
            <td class="right"><strong><?= number_format($group['bf'], 2) ?></strong></td>
            <td class="right"><strong><?= number_format($group['lun'], 2) ?></strong></td>
            <td class="right"><strong><?= number_format($group['din'], 2) ?></strong></td>
            <td class="right"><strong><?= number_format($group['total'], 2) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Grand Total -->
  <?php if ($rows): ?>
  <div class="grand-total-box">
    <div class="grand-total-label">
      GRAND TOTAL &mdash; <?= strtoupper($months_list[$f_month-1]) ?> <?= $f_year ?>
    </div>
    <div class="grand-total-grid">
      <div class="gt-item">
        <div class="gt-item-label">Breakfast</div>
        <div class="gt-item-value">Rs. <?= number_format($t_bf, 2) ?></div>
      </div>
      <div class="gt-item">
        <div class="gt-item-label">Lunch</div>
        <div class="gt-item-value">Rs. <?= number_format($t_lun, 2) ?></div>
      </div>
      <div class="gt-item">
        <div class="gt-item-label">Dinner</div>
        <div class="gt-item-value">Rs. <?= number_format($t_din, 2) ?></div>
      </div>
      <div class="gt-item is-total">
        <div class="gt-item-label">Total</div>
        <div class="gt-item-value">Rs. <?= number_format($t_total, 2) ?></div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div style="text-align:center;color:var(--text-muted);padding:30px;">
      No records found for <?= $months_list[$f_month-1] ?> <?= $f_year ?>.
    </div>
  </div>
  <?php endif; ?>

</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
