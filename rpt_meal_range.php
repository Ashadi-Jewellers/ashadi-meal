hello world 

<?php
// ============================================================
// MEAL SUMMARY BY DATE RANGE
// Pick a meal type (Breakfast/Lunch/Dinner) and a From/To date,
// see each date's totals listed individually, plus a grand total.
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conn = db_connect();

$meal_types = [
    'BF'  => 'Breakfast',
    'LUN' => 'Lunch',
    'DIN' => 'Dinner',
];

$f_type = $_GET['f_type'] ?? 'BF';
if (!array_key_exists($f_type, $meal_types)) $f_type = 'BF';

$f_from = $_GET['f_from'] ?? date('Y-m-01'); // default: 1st of this month
$f_to   = $_GET['f_to']   ?? date('Y-m-d');  // default: today

// Guard against a reversed range
if ($f_from > $f_to) { $tmp = $f_from; $f_from = $f_to; $f_to = $tmp; }

$safe_from = $conn->real_escape_string($f_from);
$safe_to   = $conn->real_escape_string($f_to);
$safe_type = $conn->real_escape_string($f_type);

$sql = "
    SELECT
        mr.meal_date,
        COUNT(*) as emp_count,
        SUM(mr.quantity) as meal_count,
        SUM(mr.amount) as total_amount
    FROM meal_records mr
    WHERE mr.meal_type = '$safe_type'
      AND mr.meal_date BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY mr.meal_date
    ORDER BY mr.meal_date ASC
";
$result = $conn->query($sql);

$rows = [];
$grand_count = 0;
$grand_meal_count = 0;
$grand_amount = 0;
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $grand_count      += (int)$r['emp_count'];
    $grand_meal_count += (int)$r['meal_count'];
    $grand_amount     += (float)$r['total_amount'];
}

$conn->close();

$meal_label = $meal_types[$f_type];
$page_title = $meal_label . ' Summary by Date';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.rmr-type-tabs{display:flex;gap:8px;margin-bottom:4px;}
.rmr-type-tabs a{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;border:1.5px solid var(--border,#d0dae6);color:var(--text-muted,#5a7080);background:#fff;transition:all .12s;}
.rmr-type-tabs a:hover{border-color:var(--navy,#1a3a5c);}
.rmr-type-tabs a.active{background:var(--navy,#1a3a5c);border-color:var(--navy,#1a3a5c);color:#fff;}
.rmr-summary-strip{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px;}
.rmr-summary-box{flex:1;min-width:170px;background:#f7fafc;border:1px solid var(--border,#d0dae6);border-radius:10px;padding:14px 16px;}
.rmr-summary-box .lbl{font-size:11px;font-weight:700;color:var(--text-muted,#5a7080);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.rmr-summary-box .val{font-size:20px;font-weight:700;color:var(--navy,#1a3a5c);}
.rmr-grand-total td{background:var(--navy,#1a3a5c);color:#fff;font-weight:700;font-size:13px;padding:12px 14px;}
</style>

<div class="container">
  <div class="page-header no-print">
    <div>
      <div class="page-title">Meal Summary by Date</div>
      <div class="page-sub">Daily totals for a chosen meal type across a date range</div>
    </div>
    <button onclick="window.print()" class="btn btn-print">Print</button>
  </div>

  <div class="card no-print">
    <div class="rmr-type-tabs">
      <a href="?f_type=BF&f_from=<?= htmlspecialchars($f_from) ?>&f_to=<?= htmlspecialchars($f_to) ?>"  class="<?= $f_type==='BF'  ? 'active' : '' ?>">Breakfast</a>
      <a href="?f_type=LUN&f_from=<?= htmlspecialchars($f_from) ?>&f_to=<?= htmlspecialchars($f_to) ?>" class="<?= $f_type==='LUN' ? 'active' : '' ?>">Lunch</a>
      <a href="?f_type=DIN&f_from=<?= htmlspecialchars($f_from) ?>&f_to=<?= htmlspecialchars($f_to) ?>" class="<?= $f_type==='DIN' ? 'active' : '' ?>">Dinner</a>
    </div>
    <div style="height:14px;"></div>
    <form method="GET" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
      <input type="hidden" name="f_type" value="<?= htmlspecialchars($f_type) ?>">
      <div class="form-group">
        <label>From Date</label>
        <input type="date" name="f_from" class="form-control" value="<?= htmlspecialchars($f_from) ?>">
      </div>
      <div class="form-group">
        <label>To Date</label>
        <input type="date" name="f_to" class="form-control" value="<?= htmlspecialchars($f_to) ?>">
      </div>
      <div class="form-group">
        <button type="submit" class="btn btn-primary">View Report</button>
      </div>
    </form>
  </div>

  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px;margin-top:4px;"><?= strtoupper($meal_label) ?> SUMMARY BY DATE</h3>
    <p>From <?= date('d M Y', strtotime($f_from)) ?> to <?= date('d M Y', strtotime($f_to)) ?></p>
  </div>

  <div class="rmr-summary-strip">
    <div class="rmr-summary-box">
      <div class="lbl"><?= htmlspecialchars($meal_label) ?> Days</div>
      <div class="val"><?= count($rows) ?></div>
    </div>
    <div class="rmr-summary-box">
      <div class="lbl">Total Meals</div>
      <div class="val"><?= $grand_meal_count ?></div>
    </div>
    <div class="rmr-summary-box">
      <div class="lbl">Total Amount</div>
      <div class="val">Rs. <?= number_format($grand_amount, 2) ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><?= htmlspecialchars($meal_label) ?> &mdash; Day by Day</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Day</th>
            <th class="right">No. of Employees</th>
            <th class="right"><?= htmlspecialchars($meal_label) ?> Count</th>
            <th class="right">Total Amount (Rs.)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No <?= htmlspecialchars(strtolower($meal_label)) ?> records found for this date range.</td></tr>
          <?php endif;
          foreach ($rows as $r): ?>
          <tr>
            <td><?= date('d M Y', strtotime($r['meal_date'])) ?></td>
            <td><?= date('l', strtotime($r['meal_date'])) ?></td>
            <td class="right"><?= (int)$r['emp_count'] ?></td>
            <td class="right"><?= (int)$r['meal_count'] ?></td>
            <td class="right"><?= number_format($r['total_amount'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr class="rmr-grand-total">
            <td colspan="2">Grand Total</td>
            <td class="right"><?= $grand_count ?></td>
            <td class="right"><?= $grand_meal_count ?></td>
            <td class="right"><?= number_format($grand_amount, 2) ?></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
