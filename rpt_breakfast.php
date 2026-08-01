<?php
// ============================================================
// MEAL REPORT PAGE
// Shows all employees who had a specific meal on a chosen date.
// Meal type can be: BF (Breakfast), LUN (Lunch), DIN (Dinner)
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login(); // Redirect to login if not logged in

$conn = db_connect();

// ---- Meal type labels and badge colours ----
$meal_types = [
    'BF'  => 'Breakfast',
    'LUN' => 'Lunch',
    'DIN' => 'Dinner',
];

// ---- Read filter values from URL (GET parameters) ----
$selected_date    = $_GET['f_date']    ?? date('Y-m-d');  // default = today
$selected_type    = $_GET['f_type']    ?? 'BF';           // default = Breakfast
$selected_section = trim($_GET['f_section'] ?? '');       // default = all sections

// Make sure the meal type is valid
if (!array_key_exists($selected_type, $meal_types)) {
    $selected_type = 'BF';
}

$meal_label = $meal_types[$selected_type]; // e.g. "Breakfast"

// ---- Build the SQL query ----
// We join meal_records with employees to get the employee name, number and section
$safe_date    = $conn->real_escape_string($selected_date);
$safe_type    = $conn->real_escape_string($selected_type);
$safe_section = $conn->real_escape_string($selected_section);

$section_filter = $selected_section ? "AND e.Section = '$safe_section'" : '';

$sql = "
    SELECT
        mr.meal_date,
        mr.amount,
        mr.remarks,
        e.EmpID   AS employee_number,
        e.Name    AS employee_name,
        e.Section AS section
    FROM meal_records mr
    LEFT JOIN employees e ON mr.employee_id = e.id
    WHERE mr.meal_date  = '$safe_date'
      AND mr.meal_type  = '$safe_type'
      $section_filter
    ORDER BY e.Section, e.Name
";

$result = $conn->query($sql);

// ---- Collect rows into an array ----
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// ---- Calculate totals ----
$total_count  = count($rows);
$total_amount = array_sum(array_column($rows, 'amount'));

// ---- Load sections for the section dropdown ----
$sections     = [];
$sections_res = $conn->query("SELECT name FROM opt_sections ORDER BY name");
while ($s = $sections_res->fetch_assoc()) {
    $sections[] = $s['name'];
}

$conn->close();

// ---- Set page title and load the shared header ----
$page_title = $meal_label . ' Report';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

  <!-- Page heading -->
  <div class="page-header no-print">
    <div>
      <div class="page-title">Meal Report</div>
      <div class="page-sub">Daily meal entries filtered by type and date</div>
    </div>
    <button onclick="window.print()" class="btn btn-print">Print</button>
  </div>

  <!-- ---- FILTER FORM ---- -->
  <div class="card no-print">
    <form method="GET">
      <div class="form-grid" style="grid-template-columns: 200px 180px 220px auto;">

        <!-- Meal type selector -->
        <div class="form-group">
          <label>Meal Type</label>
          <select name="f_type" class="form-control">
            <?php foreach ($meal_types as $value => $label): ?>
            <option value="<?= $value ?>" <?= $selected_type === $value ? 'selected' : '' ?>>
              <?= $label ?> Meal
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Date selector -->
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="f_date" class="form-control" value="<?= htmlspecialchars($selected_date) ?>">
        </div>

        <!-- Section selector -->
        <div class="form-group">
          <label>Section</label>
          <select name="f_section" class="form-control">
            <option value="">All Sections</option>
            <?php foreach ($sections as $section): ?>
            <option value="<?= htmlspecialchars($section) ?>" <?= $selected_section === $section ? 'selected' : '' ?>>
              <?= htmlspecialchars($section) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Search button -->
        <div class="form-group">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>

      </div>
    </form>
  </div>

  <!-- ---- QUICK SWITCH TABS (Breakfast / Lunch / Dinner) ---- -->
  <div class="card no-print" style="padding:14px 20px;">
    <div style="display:flex; gap:8px; align-items:center;">
      <span style="font-size:12px; color:var(--text-muted); margin-right:4px;">Quick switch:</span>

      <?php
      // Colours for each meal type tab
      $tab_colours = ['BF' => '#1a5a8c', 'LUN' => '#1a6b3a', 'DIN' => '#8c4a1a'];

      foreach ($meal_types as $value => $label):
          $is_active   = ($selected_type === $value);
          $bg_colour   = $is_active ? $tab_colours[$value] : '#f0f4f8';
          $text_colour = $is_active ? '#fff' : '#5a7080';
          $border      = $is_active ? $tab_colours[$value] : '#d0dae6';

          // Build the URL keeping the current date and section filters
          $tab_url = '?f_type=' . $value
                   . '&f_date='    . urlencode($selected_date)
                   . '&f_section=' . urlencode($selected_section);
      ?>
      <a href="<?= $tab_url ?>"
         style="padding:6px 18px; border-radius:30px; font-size:12px; font-weight:600;
                text-decoration:none; background:<?= $bg_colour ?>; color:<?= $text_colour ?>;
                border:1.5px solid <?= $border ?>; transition:all .15s;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ---- PRINT HEADER (only shows when printing) ---- -->
  <div class="print-header">
    <h2>ASHADI JEWELLERS</h2>
    <h3 style="font-size:14px; margin-top:4px;"><?= strtoupper($meal_label) ?> MEAL REPORT</h3>
    <p><?= date('l, F j, Y', strtotime($selected_date)) ?> &nbsp;&nbsp; Printed: <?= date('d/m/Y g:i A') ?></p>
  </div>

  <!-- ---- RESULTS TABLE ---- -->
  <div class="card">

    <!-- Table heading showing which meal and date -->
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
      <span><?= htmlspecialchars($meal_label) ?> &mdash; <?= date('d M Y', strtotime($selected_date)) ?></span>
      <?php if ($selected_section): ?>
      <span style="font-weight:400; font-size:12px; color:var(--text-muted);">
        Section: <?= htmlspecialchars($selected_section) ?>
      </span>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Number</th>
            <th>Name</th>
            <th>Section</th>
            <th class="right"><?= htmlspecialchars($meal_label) ?></th>
            <th class="right">Amount (Rs.)</th>
            <th>Remarks</th>
          </tr>
        </thead>

        <tbody>
          <?php if (empty($rows)): ?>
          <!-- No records found -->
          <tr>
            <td colspan="7" style="text-align:center; color:var(--text-muted); padding:24px;">
              No <?= htmlspecialchars($meal_label) ?> records found for <?= date('d M Y', strtotime($selected_date)) ?>.
            </td>
          </tr>

          <?php else: ?>
          <!-- One row per employee -->
          <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= date('d M Y', strtotime($row['meal_date'])) ?></td>
            <td><?= htmlspecialchars($row['employee_number'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['employee_name']   ?? '-') ?></td>
            <td><?= htmlspecialchars($row['section']         ?? '-') ?></td>
            <td class="right">1</td>
            <td class="right"><?= number_format((float)($row['amount'] ?? 0), 2) ?></td>
            <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>

        <!-- Totals footer row (only shown when there are records) -->
        <?php if (!empty($rows)): ?>
        <tfoot>
          <tr>
            <td colspan="3"><strong><?= $total_count ?> employees</strong></td>
            <td></td>
            <td class="right"><strong><?= $total_count ?></strong></td>
            <td class="right"><strong><?= number_format($total_amount, 2) ?></strong></td>
            <td></td>
          </tr>
        </tfoot>
        <?php endif; ?>

      </table>
    </div>

  </div><!-- end card -->

</div><!-- end container -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
