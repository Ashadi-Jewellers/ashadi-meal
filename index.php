<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$today = date('Y-m-d');
$this_month = date('Y-m');

// Active filter (BF, LUN, DIN or empty = all)
$filter = $_GET['filter'] ?? '';
if (!in_array($filter, ['BF','LUN','DIN'])) $filter = '';

// Today stats
$today_stats = ['BF'=>['cnt'=>0,'total'=>0],'LUN'=>['cnt'=>0,'total'=>0],'DIN'=>['cnt'=>0,'total'=>0]];
$stmt = $conn->query("SELECT meal_type, SUM(quantity) as cnt, SUM(amount) as total FROM meal_records WHERE meal_date='$today' GROUP BY meal_type");
if ($stmt) while ($r = $stmt->fetch_assoc()) {
    $today_stats[$r['meal_type']] = ['cnt'=>(int)$r['cnt'],'total'=>(float)($r['total']??0)];
}

// Month stats
$month_total = ['cnt'=>0,'total'=>0.0];
$ms = $conn->query("SELECT SUM(quantity) as cnt, SUM(amount) as total FROM meal_records WHERE DATE_FORMAT(meal_date,'%Y-%m')='$this_month'");
if ($ms) { $row=$ms->fetch_assoc(); $month_total=['cnt'=>(int)($row['cnt']??0),'total'=>(float)($row['total']??0)]; }

// Recent entries - ONE row per employee per date, merged BF/LUN/DIN columns
$filter_where = $filter ? "AND mr.meal_type='$filter'" : '';
$recent_sql = "
    SELECT
        mr.meal_date,
        e.EmpID,
        e.Name,
        e.Section,
        MAX(CASE WHEN mr.meal_type='BF'  THEN mr.amount END) as bf_amount,
        MAX(CASE WHEN mr.meal_type='LUN' THEN mr.amount END) as lun_amount,
        MAX(CASE WHEN mr.meal_type='DIN' THEN mr.amount END) as din_amount,
        SUM(mr.amount) as total,
        GROUP_CONCAT(DISTINCT mr.remarks SEPARATOR ', ') as remarks,
        MAX(mr.is_guest) as is_guest
    FROM meal_records mr
    LEFT JOIN employees e ON mr.employee_id = e.id
    WHERE 1=1 $filter_where
    GROUP BY mr.meal_date, mr.employee_id, e.EmpID, e.Name, e.Section
    ORDER BY mr.meal_date DESC, e.Name
    LIMIT 30
";
$recent_rows = [];
$res = $conn->query($recent_sql);
if ($res) while ($r = $res->fetch_assoc()) $recent_rows[] = $r;

$conn->close();

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <div class="page-title">Dashboard</div>
      <div class="page-sub"><?= date('l, F j, Y') ?></div>
    </div>
    <a href="add_meal.php" class="btn btn-primary">Add Meals</a>
  </div>

  <!-- Stats -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Breakfast Today</div>
      <div class="stat-value"><?= $today_stats['BF']['cnt'] ?></div>
      <div class="stat-sub">Rs. <?= number_format($today_stats['BF']['total'],2) ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Lunch Today</div>
      <div class="stat-value"><?= $today_stats['LUN']['cnt'] ?></div>
      <div class="stat-sub">Rs. <?= number_format($today_stats['LUN']['total'],2) ?></div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Dinner Today</div>
      <div class="stat-value"><?= $today_stats['DIN']['cnt'] ?></div>
      <div class="stat-sub">Rs. <?= number_format($today_stats['DIN']['total'],2) ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">This Month Total</div>
      <div class="stat-value"><?= $month_total['cnt'] ?></div>
      <div class="stat-sub">Rs. <?= number_format($month_total['total'],2) ?></div>
    </div>
  </div>

  <!-- Quick Meal Summaries (highlighted) -->
  <div class="card no-print" style="background:linear-gradient(135deg,#fff9ef,#fef6e8);border:1.5px solid #e8d5a8;">
    <div class="card-title" style="display:flex;align-items:center;gap:8px;">
      <span>Quick Meal Summaries</span>
      <span style="font-size:11px;font-weight:500;color:var(--text-muted,#5a7080);text-transform:none;letter-spacing:0;">— pick a date range and print</span>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;">
      <a href="rpt_meal_range.php?f_type=BF"  class="btn btn-primary" style="background:#1a5a8c;border-color:#1a5a8c;">Breakfast Summary (Date Range)</a>
      <a href="rpt_meal_range.php?f_type=LUN" class="btn btn-primary" style="background:#1a6b3a;border-color:#1a6b3a;">Lunch Summary (Date Range)</a>
      <a href="rpt_meal_range.php?f_type=DIN" class="btn btn-primary" style="background:#8c4a1a;border-color:#8c4a1a;">Dinner Summary (Date Range)</a>
    </div>
  </div>

  <!-- Reports -->
  <div class="card no-print">
    <div class="card-title">Reports</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
      <a href="rpt_breakfast.php"       class="btn btn-outline btn-sm">Meal Report</a>
      <a href="rpt_filter_date.php"     class="btn btn-outline btn-sm">Meals Filter by Date</a>
      <a href="rpt_daily.php"           class="btn btn-outline btn-sm">Daily Report</a>
      <a href="rpt_by_section.php"      class="btn btn-outline btn-sm">Sum by Section</a>
      <a href="rpt_by_month.php"        class="btn btn-outline btn-sm">Sum by Month</a>
      <a href="rpt_by_company.php"      class="btn btn-outline btn-sm">Sum by Company</a>
      <a href="rpt_by_employee.php"     class="btn btn-outline btn-sm">Filter by Employee</a>
      <a href="rpt_monthly_finance.php" class="btn btn-outline btn-sm">Monthly Report for Finance</a>
    </div>
  </div>

  <!-- Recent Entries with Filter -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--border);">
      <div class="card-title" style="margin:0;padding:0;border:none;">Recent Meal Entries</div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a href="index.php"             class="filter-btn <?= $filter===''    ? 'active' : '' ?>">All</a>
        <a href="index.php?filter=BF"   class="filter-btn bf  <?= $filter==='BF'  ? 'active' : '' ?>">Breakfast</a>
        <a href="index.php?filter=LUN"  class="filter-btn lun <?= $filter==='LUN' ? 'active' : '' ?>">Lunch</a>
        <a href="index.php?filter=DIN"  class="filter-btn din <?= $filter==='DIN' ? 'active' : '' ?>">Dinner</a>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Emp No</th>
            <th>Name</th>
            <th>Section</th>
            <th class="right">Breakfast</th>
            <th class="right">Lunch</th>
            <th class="right">Dinner</th>
            <th class="right">Total</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_rows)): ?>
          <tr>
            <td colspan="9" style="text-align:center;color:var(--text-muted);padding:24px;">
              No meal records found. <a href="add_meal.php" style="color:var(--accent);">Add first record</a>
            </td>
          </tr>
          <?php else: foreach ($recent_rows as $r): ?>
          <tr>
            <td><?= date('d M Y', strtotime($r['meal_date'])) ?></td>
            <td><?= !empty($r['is_guest']) ? '<span class="badge" style="background:#eaf1f8;color:#1a5a8c;">GUEST</span>' : htmlspecialchars($r['EmpID'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['Name'] ?? ($r['is_guest'] ? 'Non-Employee' : '-')) ?></td>
            <td><?= !empty($r['is_guest']) ? '-' : htmlspecialchars($r['Section'] ?? '-') ?></td>
            <td class="right">
              <?php if ($r['bf_amount'] !== null): ?>
                <span class="badge badge-bf">Rs.<?= number_format((float)$r['bf_amount'],2) ?></span>
              <?php else: ?>
                <span style="color:#ccc;">-</span>
              <?php endif; ?>
            </td>
            <td class="right">
              <?php if ($r['lun_amount'] !== null): ?>
                <span class="badge badge-lun">Rs.<?= number_format((float)$r['lun_amount'],2) ?></span>
              <?php else: ?>
                <span style="color:#ccc;">-</span>
              <?php endif; ?>
            </td>
            <td class="right">
              <?php if ($r['din_amount'] !== null): ?>
                <span class="badge badge-din">Rs.<?= number_format((float)$r['din_amount'],2) ?></span>
              <?php else: ?>
                <span style="color:#ccc;">-</span>
              <?php endif; ?>
            </td>
            <td class="right"><strong>Rs.<?= number_format((float)($r['total']??0),2) ?></strong></td>
            <td style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($r['remarks'] ?? '') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($recent_rows)): ?>
    <div style="margin-top:10px;font-size:12px;color:var(--text-muted);">Showing last 30 entries<?= $filter ? ' filtered by <strong>' . ($filter==='BF'?'Breakfast':($filter==='LUN'?'Lunch':'Dinner')) . '</strong>' : '' ?></div>
    <?php endif; ?>
  </div>
</div>

<style>
.filter-btn {
  display:inline-block;
  padding:5px 14px;
  border-radius:30px;
  font-size:12px;
  font-weight:600;
  text-decoration:none;
  border:1.5px solid var(--border);
  color:var(--text-muted);
  background:#fff;
  transition:all .15s;
}
.filter-btn:hover        { border-color:var(--primary); color:var(--primary); }
.filter-btn.active       { background:var(--primary); color:#fff; border-color:var(--primary); }
.filter-btn.bf.active    { background:#1a5a8c; border-color:#1a5a8c; }
.filter-btn.lun.active   { background:#1a6b3a; border-color:#1a6b3a; }
.filter-btn.din.active   { background:#8c4a1a; border-color:#8c4a1a; }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
