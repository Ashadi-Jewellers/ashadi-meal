<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();
$years = [];
for ($y = date('Y'); $y >= 2020; $y--) $years[] = $y;
$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$f_year     = intval($_GET['f_year']  ?? date('Y'));
$f_month    = intval($_GET['f_month'] ?? 0);
$f_view     = ($_GET['f_view'] ?? 'employee') === 'section' ? 'section' : 'employee';
$f_sections = $_GET['f_section'] ?? []; // array of selected section names (can be empty = all)
if (!is_array($f_sections)) $f_sections = [];
$f_sections = array_values(array_filter(array_map('trim', $f_sections)));

// Load sections list for the filter checkboxes
$sections_res = $conn->query("SELECT name FROM opt_sections ORDER BY name");
$all_sections = [];
while ($r = $sections_res->fetch_assoc()) $all_sections[] = $r['name'];

$where = ["YEAR(mr.meal_date) = $f_year"];
if ($f_month) {
    $where[] = "MONTH(mr.meal_date) = $f_month";
}
if ($f_sections) {
    $escaped = array_map(fn($s) => "'" . $conn->real_escape_string($s) . "'", $f_sections);
    $where[] = "e.Section IN (" . implode(',', $escaped) . ")";
}
$where_sql = implode(' AND ', $where);

$rows = [];
$t_bf = $t_lun = $t_din = $t_total = 0;

if ($f_view === 'employee') {
    $sql = "SELECT e.EmpID, e.Name, e.Section,
            DATE_FORMAT(mr.meal_date,'%M') as Month,
            YEAR(mr.meal_date) as Year,
            MONTH(mr.meal_date) as MonthNum,
            SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as Amount_BF,
            SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as Amount_LUN,
            SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as Amount_DIN,
            SUM(mr.amount) as Total
            FROM meal_records mr
            LEFT JOIN employees e ON mr.employee_id = e.id
            WHERE $where_sql
            GROUP BY mr.employee_id, e.EmpID, e.Name, e.Section, Year, MonthNum, Month
            ORDER BY e.Section, e.Name, Year DESC, MonthNum DESC";
} else {
    $sql = "SELECT e.Section,
            DATE_FORMAT(mr.meal_date,'%M') as Month,
            YEAR(mr.meal_date) as Year,
            MONTH(mr.meal_date) as MonthNum,
            SUM(CASE WHEN mr.meal_type='BF'  THEN mr.amount ELSE 0 END) as Amount_BF,
            SUM(CASE WHEN mr.meal_type='LUN' THEN mr.amount ELSE 0 END) as Amount_LUN,
            SUM(CASE WHEN mr.meal_type='DIN' THEN mr.amount ELSE 0 END) as Amount_DIN,
            SUM(mr.amount) as Total
            FROM meal_records mr
            LEFT JOIN employees e ON mr.employee_id = e.id
            WHERE $where_sql
            GROUP BY e.Section, Year, MonthNum, Month
            ORDER BY Year DESC, MonthNum DESC, e.Section";
}

$records = $conn->query($sql);
while ($r = $records->fetch_assoc()) {
    $rows[] = $r;
    $t_bf    += $r['Amount_BF'];
    $t_lun   += $r['Amount_LUN'];
    $t_din   += $r['Amount_DIN'];
    $t_total += $r['Total'];
}
$conn->close();

// For employee-wise view, group rows by Section so we can render section sub-headers + sub-totals
$grouped = [];
if ($f_view === 'employee') {
    foreach ($rows as $r) {
        $sec = $r['Section'] ?: 'Unassigned';
        if (!isset($grouped[$sec])) {
            $grouped[$sec] = ['rows' => [], 'bf' => 0, 'lun' => 0, 'din' => 0, 'total' => 0];
        }
        $grouped[$sec]['rows'][]  = $r;
        $grouped[$sec]['bf']    += $r['Amount_BF'];
        $grouped[$sec]['lun']   += $r['Amount_LUN'];
        $grouped[$sec]['din']   += $r['Amount_DIN'];
        $grouped[$sec]['total']+= $r['Total'];
    }
}

$page_title = 'Sum by Month';
require_once __DIR__ . '/includes/header.php';

$section_filter_label = $f_sections ? implode(', ', $f_sections) : 'All Sections';
$all_checked = count($f_sections) === 0 || count($f_sections) === count($all_sections);
?>
<style>
.smy-toolbar{background:#fff;border:1px solid var(--border,#d0dae6);border-radius:10px;padding:18px 20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(20,40,70,.05);}
.smy-toolbar-row1{display:flex;gap:22px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;}
.smy-toolbar-row2{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;}
.smy-field label{display:block;font-size:11px;font-weight:700;color:var(--text-muted,#5a7080);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;}
.smy-field select, .smy-field input{border-radius:7px;}
.smy-section-box{border:1.5px solid var(--border,#d0dae6);border-radius:8px;padding:10px 14px;width:100%;background:#f9fbfd;}
.smy-chip-list{display:flex;flex-wrap:wrap;gap:6px;max-height:78px;overflow-y:auto;padding-right:2px;}
.smy-section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.smy-section-head span.title{font-size:11px;font-weight:700;color:var(--text-muted,#5a7080);text-transform:uppercase;letter-spacing:.6px;}
.smy-link-btns a{font-size:11px;color:var(--blue,#2980b9);text-decoration:none;font-weight:600;margin-left:10px;cursor:pointer;}
.smy-link-btns a:hover{text-decoration:underline;}
.smy-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:5px 10px;border-radius:20px;border:1.3px solid var(--border,#d0dae6);background:#fff;cursor:pointer;user-select:none;transition:all .12s;color:var(--text-dark,#1c2b3a);}
.smy-chip:hover{border-color:var(--blue,#2980b9);}
.smy-chip input{display:none;}
.smy-chip.checked{background:var(--navy,#1a3a5c);border-color:var(--navy,#1a3a5c);color:#fff;}
.smy-summary-bar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;}
.smy-summary-bar .badge-pill{background:#eef3f8;color:var(--navy,#1a3a5c);font-size:11px;font-weight:600;padding:5px 12px;border-radius:20px;}
.smy-group-head td{background:linear-gradient(90deg, var(--navy,#1a3a5c), var(--navy-hover,#234d7a));color:#fff;font-weight:700;font-size:12.5px;padding:9px 14px;letter-spacing:.3px;}
.smy-group-sub td{background:#f2f6fa;font-weight:700;color:var(--navy,#1a3a5c);border-top:1.5px solid var(--border,#d0dae6);}
.smy-table tbody tr:not(.smy-group-head):not(.smy-group-sub):hover{background:#f7fafc;}
.smy-emp-name{font-weight:600;color:var(--text-dark,#1c2b3a);}
.smy-emp-id{color:var(--text-muted,#5a7080);font-size:11.5px;}
.smy-grand-total td{background:var(--navy,#1a3a5c);color:#fff;font-weight:700;font-size:13px;padding:12px 14px;}
</style>

<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Sum by Month</div>
      <div class="page-sub">Monthly meal totals &mdash; view by section or by individual employee</div>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="rpt_by_month.php" class="btn btn-print" style="text-decoration:none;display:inline-flex;align-items:center;">Reset Filters</a>
      <button onclick="window.print()" class="btn btn-print">Print</button>
    </div>
  </div>

  <form method="GET" class="smy-toolbar no-print" id="smy-form">
    <div class="smy-toolbar-row1">
      <div class="smy-field">
        <label>Year</label>
        <select name="f_year" class="form-control">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $f_year==$y ? 'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="smy-field">
        <label>Month</label>
        <select name="f_month" class="form-control">
          <option value="0">All Months</option>
          <?php foreach ($months_list as $i=>$m): ?>
          <option value="<?= $i+1 ?>" <?= $f_month==($i+1) ? 'selected':'' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="smy-field">
        <label>View</label>
        <select name="f_view" class="form-control">
          <option value="employee" <?= $f_view==='employee' ? 'selected':'' ?>>Employee-wise (individual names)</option>
          <option value="section"  <?= $f_view==='section'  ? 'selected':'' ?>>Section-wise (totals)</option>
        </select>
      </div>
      <div class="smy-field">
        <button type="submit" class="btn btn-primary">View Report</button>
      </div>
    </div>
    <div class="smy-toolbar-row2">
      <div class="smy-section-box">
        <div class="smy-section-head">
          <span class="title">Section(s) &mdash; leave blank for all</span>
          <span class="smy-link-btns">
            <a onclick="smySetAll(true)">All</a>
            <a onclick="smySetAll(false)">Clear</a>
          </span>
        </div>
        <div class="smy-chip-list" id="smy-chip-list">
          <?php foreach ($all_sections as $s): $checked = in_array($s, $f_sections); ?>
          <label class="smy-chip <?= $checked ? 'checked' : '' ?>">
            <input type="checkbox" name="f_section[]" value="<?= htmlspecialchars($s) ?>" <?= $checked ? 'checked' : '' ?> onchange="this.closest('label').classList.toggle('checked', this.checked)">
            <?= htmlspecialchars($s) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </form>

  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;">SUM BY MONTH &mdash; <?= $f_view === 'employee' ? 'EMPLOYEE-WISE' : 'SECTION-WISE' ?></h3>
    <p>Year: <?= $f_year ?> <?= $f_month ? '| Month: ' . $months_list[$f_month-1] : '' ?> | Section: <?= htmlspecialchars($section_filter_label) ?></p>
  </div>

  <div class="card">
    <div class="smy-summary-bar">
      <div class="card-title" style="border:none;margin:0;padding:0;">
        Monthly Summary &mdash; <?= $f_year ?><?= $f_month ? ' / ' . $months_list[$f_month-1] : '' ?>
      </div>
      <span class="badge-pill"><?= $all_checked ? 'All Sections' : htmlspecialchars($section_filter_label) ?></span>
    </div>

    <div class="table-wrap">
      <table class="smy-table">
        <thead>
          <?php if ($f_view === 'employee'): ?>
          <tr>
            <th>Employee</th>
            <th>Month</th>
            <th>Year</th>
            <th class="right">BF (Rs.)</th>
            <th class="right">LUN (Rs.)</th>
            <th class="right">DIN (Rs.)</th>
            <th class="right">Total (Rs.)</th>
          </tr>
          <?php else: ?>
          <tr>
            <th>Section</th>
            <th>Month</th>
            <th>Year</th>
            <th class="right">BF (Rs.)</th>
            <th class="right">LUN (Rs.)</th>
            <th class="right">DIN (Rs.)</th>
            <th class="right">Total (Rs.)</th>
          </tr>
          <?php endif; ?>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:26px;">No records found for these filters.</td></tr>
          <?php elseif ($f_view === 'employee'): ?>
            <?php foreach ($grouped as $secName => $g): ?>
            <tr class="smy-group-head">
              <td colspan="7"><?= htmlspecialchars($secName) ?> &nbsp;<span style="opacity:.75;font-weight:400;">(<?= count($g['rows']) ?> record<?= count($g['rows'])===1?'':'s' ?>)</span></td>
            </tr>
            <?php foreach ($g['rows'] as $r): ?>
            <tr>
              <td>
                <div class="smy-emp-name"><?= htmlspecialchars($r['Name'] ?? '-') ?></div>
                <div class="smy-emp-id">ID: <?= htmlspecialchars($r['EmpID'] ?? '-') ?></div>
              </td>
              <td><?= htmlspecialchars($r['Month']) ?></td>
              <td><?= $r['Year'] ?></td>
              <td class="right"><?= number_format($r['Amount_BF'], 2) ?></td>
              <td class="right"><?= number_format($r['Amount_LUN'], 2) ?></td>
              <td class="right"><?= number_format($r['Amount_DIN'], 2) ?></td>
              <td class="right"><?= number_format($r['Total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="smy-group-sub">
              <td colspan="3">Subtotal &mdash; <?= htmlspecialchars($secName) ?></td>
              <td class="right"><?= number_format($g['bf'], 2) ?></td>
              <td class="right"><?= number_format($g['lun'], 2) ?></td>
              <td class="right"><?= number_format($g['din'], 2) ?></td>
              <td class="right"><?= number_format($g['total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['Section'] ?? '-') ?></td>
              <td><?= htmlspecialchars($r['Month']) ?></td>
              <td><?= $r['Year'] ?></td>
              <td class="right"><?= number_format($r['Amount_BF'], 2) ?></td>
              <td class="right"><?= number_format($r['Amount_LUN'], 2) ?></td>
              <td class="right"><?= number_format($r['Amount_DIN'], 2) ?></td>
              <td class="right"><?= number_format($r['Total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr class="smy-grand-total">
            <td colspan="3">Grand Total</td>
            <td class="right"><?= number_format($t_bf, 2) ?></td>
            <td class="right"><?= number_format($t_lun, 2) ?></td>
            <td class="right"><?= number_format($t_din, 2) ?></td>
            <td class="right"><?= number_format($t_total, 2) ?></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<script>
function smySetAll(state) {
    document.querySelectorAll('#smy-chip-list input[type=checkbox]').forEach(cb => {
        cb.checked = state;
        cb.closest('label').classList.toggle('checked', state);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
