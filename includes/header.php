<?php
// ============================================================
// ASHADI MEAL SYSTEM - Shared Page Header (included on every page)
// ============================================================

// Set the base URL for all links
if (!defined('BASE_URL')) define('BASE_URL', '/');

// Get the currently logged-in user info
$user         = current_user();
$current_page = basename($_SERVER['PHP_SELF']); // e.g. "index.php"

// Pages that belong to the Reports section (used to highlight the nav link)
$report_pages = [
    'rpt_breakfast.php',
    'rpt_by_section.php',
    'rpt_by_month.php',
    'rpt_by_employee.php',
    'rpt_by_company.php',
    'rpt_daily.php',
    'rpt_monthly_finance.php',
    'rpt_filter_date.php',
];

// ============================================================
// MY PROFILE - own password change + read-only profile details
// (own connection here since the calling page may have already
// closed its own $conn before including this header)
// ============================================================
$profile_msg  = '';
$profile_err  = '';
$profile_conn = db_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_own_password'])) {
    $current_pw = $_POST['current_password'] ?? '';
    $new_pw     = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    if (!$current_pw || !$new_pw || !$confirm_pw) {
        $profile_err = 'All password fields are required.';
    } elseif (strlen($new_pw) < 6) {
        $profile_err = 'New password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm_pw) {
        $profile_err = 'New password and confirmation do not match.';
    } else {
        $stmt = $profile_conn->prepare("SELECT password FROM meal_users WHERE id = ?");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($current_pw, $row['password'])) {
            $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $upd = $profile_conn->prepare("UPDATE meal_users SET password = ? WHERE id = ?");
            $upd->bind_param('si', $new_hash, $user['id']);
            $upd->execute();
            $upd->close();
            $profile_msg = 'Password updated successfully.';
        } else {
            $profile_err = 'Current password is incorrect.';
        }
    }
}

// Fresh read-only profile details for the modal
$profile_data = ['username' => $user['username'], 'full_name' => $user['full_name'], 'role' => $user['role'], 'is_blocked' => 0, 'CreatedAt' => null];
$stmt = $profile_conn->prepare("SELECT username, full_name, role, is_blocked, CreatedAt FROM meal_users WHERE id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) $profile_data = $row;
$stmt->close();
$profile_conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Ashadi Meal System</title>
<style>

/* ============================================================
   GLOBAL RESET - remove browser default spacing
   ============================================================ */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* ============================================================
   COLOUR VARIABLES - change these to restyle the whole system
   ============================================================ */
:root {
    --navy:        #1a3a5c;   /* main dark blue */
    --navy-hover:  #234d7a;   /* lighter navy for hover */
    --blue:        #2980b9;   /* accent blue */
    --blue-hover:  #3498db;
    --gold:        #c8a84b;   /* gold accent */
    --gold-hover:  #e8c76b;
    --green:       #27ae60;   /* success / lunch */
    --red:         #c0392b;   /* danger / delete */
    --orange:      #e67e22;   /* warning */
    --page-bg:     #f0f4f8;   /* light grey page background */
    --white:       #ffffff;
    --border:      #d0dae6;   /* light border colour */
    --text-dark:   #1c2b3a;   /* main text */
    --text-muted:  #5a7080;   /* secondary/muted text */
    --shadow:      0 2px 8px rgba(0,0,0,.10);
    --radius:      6px;
    --radius-lg:   10px;
}

/* ============================================================
   BODY
   ============================================================ */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--page-bg);
    color: var(--text-dark);
    font-size: 14px;
    min-height: 100vh;
}

/* ============================================================
   TOP NAVIGATION BAR
   ============================================================ */
.navbar {
    background: #ffffff;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 125px;
    height: 125px;
    box-shadow: 0 2px 6px rgba(0,0,0,.25);
    position: sticky;
    top: 0;
    z-index: 100;
}

/* Brand / Logo on left */
.navbar-brand     { display:flex; align-items:center; gap:10px; text-decoration:none; color:#fff; }
.navbar-logo      { font-size:20px; font-weight:700; color:var(--gold); letter-spacing:1px; }
.navbar-sub       { font-size:11px; color:rgba(255,255,255,.6); text-transform:uppercase; letter-spacing:2px; }

/* Nav links group on right */
.navbar-right     { display:flex; align-items:center; gap:20px; }

/* ============================================================
   RIGHT-SIDE ACTIONS WRAPPER (user icon + hamburger + nav links)
   ============================================================ */
.navbar-actions { display:flex; align-items:center; gap:10px; }

/* User profile icon button - always visible, desktop and mobile */
.navbar-user-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--page-bg);
    border: 1.5px solid var(--border);
    cursor: pointer;
    color: var(--navy);
    transition: background .15s, box-shadow .15s;
    flex-shrink: 0;
}
.navbar-user-btn:hover { background: #e2e6ea; box-shadow: var(--shadow); }
.navbar-user-btn svg { width: 19px; height: 19px; }

/* ============================================================
   PROFILE MODAL
   ============================================================ */
.profile-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 400;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.profile-modal-overlay.active { display: flex; }

.profile-modal {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: 0 8px 30px rgba(0,0,0,.25);
    width: 100%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px 26px;
}

.profile-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
}
.profile-modal-header h3 { font-size: 16px; color: var(--navy); }
.profile-modal-close {
    background: none;
    border: none;
    font-size: 22px;
    line-height: 1;
    color: var(--text-muted);
    cursor: pointer;
    padding: 2px 6px;
}
.profile-modal-close:hover { color: var(--red); }

.profile-section { margin-bottom: 18px; }
.profile-section-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 12px;
}

.profile-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.profile-row:last-child { border-bottom: none; }
.profile-label { color: var(--text-muted); font-weight: 600; }
.profile-value { color: var(--text-dark); font-weight: 600; text-align: right; }

.profile-divider { height: 1px; background: var(--border); margin: 18px 0; }

.btn-update-pw {
    background: var(--navy);
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
}
.btn-update-pw:hover { background: var(--navy-hover); }

/* ============================================================
   MOBILE HAMBURGER TOGGLE (hidden on desktop)
   ============================================================ */
.navbar-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    width: 34px;
    height: 34px;
    padding: 0;
    background: none;
    border: none;
    cursor: pointer;
    z-index: 220;
}
.navbar-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #000000;
    border-radius: 2px;
    transition: transform .25s ease, opacity .2s ease;
}
.navbar-toggle.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.navbar-toggle.active span:nth-child(2) { opacity: 0; }
.navbar-toggle.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
/* Drawer is light-coloured, so the X needs to be dark to stay visible over it */
.navbar-toggle.active span { background: var(--navy); }

/* Dark overlay behind the mobile drawer */
.navbar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 150;
    opacity: 0;
    transition: opacity .2s ease;
}
.navbar-overlay.active { display: block; opacity: 1; }

/* Chevron icon for the collapsible Reports item on mobile */
.nav-caret { display:none; font-size:10px; transition: transform .2s ease; }

/* Individual nav link */
.nav-link {
    color: rgba(12, 12, 12, 0.85);
    text-decoration: none;
    padding: 6px 12px;
    border-radius: var(--radius);
    font-size: 17px;
    transition: background .15s, color .15s, box-shadow .15s;
    white-space: nowrap;
}
.nav-link:hover  { background: #e2e6ea; color:#000; box-shadow: var(--shadow); }
.nav-link.active { background: rgba(255,255,255,.15); color: var(--gold-hover); }

/* Thin vertical divider between nav groups */
.nav-divider { width:1px; height:24px; background:rgba(0, 0, 0, 0.2); margin:0 4px; }

/* Logged-in user name shown in nav */
.nav-user { font-size:12px; color:rgba(0, 0, 0, 0.7); margin-right:4px; }

/* Logout button */
.btn-logout {
    background: rgba(192,57,43,.7);
    color: #fff;
    border: none;
    padding: 5px 12px;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
}
.btn-logout:hover { background: var(--red); }

/* ============================================================
   DROPDOWN MENU (hover to open)
   ============================================================ */
.nav-group { position: relative; }

/* The dropdown panel */
.nav-group-menu {
    display: none;                    /* hidden by default */
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    min-width: 220px;
    z-index: 200;
    padding: 6px 0;
}

/* Show the dropdown when hovering the nav-group */
.nav-group:hover .nav-group-menu { display: block; }

/* Links inside the dropdown */
.nav-group-menu a {
    display: block;
    padding: 8px 16px;
    color: var(--text-dark);
    text-decoration: none;
    font-size: 13px;
}
.nav-group-menu a:hover { background: var(--page-bg); color: var(--navy); }

/* Thin horizontal line divider inside dropdown */
.nav-group-menu .divider { border-top: 1px solid var(--border); margin: 4px 0; }

/* Sub-group label (e.g. "Meal Report") - not a clickable link */
.nav-sub-label {
    padding: 6px 16px 2px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* Sub-links indented under the label */
.nav-sub-group a {
    padding-left: 26px;
    font-size: 12px;
    color: var(--blue);
}
.nav-sub-group a:hover { background: #eef4fa; color: var(--navy); }

/* ============================================================
   PAGE LAYOUT CONTAINER
   ============================================================ */
.container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 24px 20px;
}

/* Page heading row (title on left, action button on right) */
.page-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.page-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--navy);
    border-left: 4px solid var(--gold);
    padding-left: 10px;
}

.page-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
    padding-left: 14px;
}

/* ============================================================
   CARD (white box with shadow)
   ============================================================ */
.card {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 22px 24px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border);
}

/* ============================================================
   STAT CARDS (dashboard numbers)
   ============================================================ */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px 20px;
    box-shadow: var(--shadow);
    border-top: 4px solid var(--blue);   /* coloured top stripe */
}
.stat-card.gold  { border-top-color: var(--gold); }
.stat-card.green { border-top-color: var(--green); }
.stat-card.red   { border-top-color: var(--red); }

.stat-label { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; }
.stat-value { font-size:28px; font-weight:700; color:var(--navy); margin-top:4px; }
.stat-sub   { font-size:11px; color:var(--text-muted); margin-top:2px; }

/* ============================================================
   DATA TABLE
   ============================================================ */
.table-wrap { overflow-x: auto; border-radius: var(--radius); }

table { width:100%; border-collapse:collapse; font-size:13px; }

/* Header row */
thead th {
    background: var(--navy);
    color: #fff;
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .4px;
    white-space: nowrap;
}
thead th.right { text-align: right; }

/* Data rows */
tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
tbody tr:hover { background: #f5f8fc; }
tbody td { padding: 9px 14px; vertical-align: middle; }
tbody td.right { text-align: right; font-weight: 600; }

/* Footer / totals row */
tfoot td {
    background: var(--navy);
    color: #fff;
    padding: 10px 14px;
    font-weight: 700;
}
tfoot td.right { text-align: right; }

/* ============================================================
   COLOUR BADGES (meal type labels)
   ============================================================ */
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
}
.badge-bf  { background:#d5e8f5; color:#1a5a8c; }   /* breakfast - blue */
.badge-lun { background:#d5f0e0; color:#1a6b3a; }   /* lunch     - green */
.badge-din { background:#f5e8d5; color:#8c4a1a; }   /* dinner    - orange */

/* ============================================================
   FORM ELEMENTS
   ============================================================ */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    align-items: end;
}

.form-group { display:flex; flex-direction:column; gap:5px; }

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .4px;
}

.form-control {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 8px 11px;
    font-size: 13px;
    color: var(--text-dark);
    background: var(--white);
    transition: border-color .15s, box-shadow .15s;
    width: 100%;
}
.form-control:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(41,128,185,.12);
}
select.form-control { cursor: pointer; }

/* ============================================================
   BUTTONS
   ============================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: opacity .15s, transform .08s;
    white-space: nowrap;
}
.btn:active { transform: scale(.97); }

.btn-primary { background: var(--navy);  color: #fff; }
.btn-primary:hover { background: var(--navy-hover); }

.btn-accent  { background: var(--blue);  color: #fff; }
.btn-accent:hover { background: var(--blue-hover); }

.btn-gold    { background: var(--gold);  color: #fff; }
.btn-gold:hover { background: var(--gold-hover); color: var(--navy); }

.btn-success { background: var(--green); color: #fff; }
.btn-danger  { background: var(--red);   color: #fff; }

.btn-outline {
    background: transparent;
    color: var(--navy);
    border: 1.5px solid var(--navy);
}
.btn-outline:hover { background: var(--navy); color: #fff; }

.btn-sm   { padding: 5px 11px; font-size: 12px; }
.btn-print { background: #2c3e50; color: #fff; }
.btn-print:hover { background: #1a252f; }

/* ============================================================
   ALERT MESSAGES
   ============================================================ */
.alert {
    padding: 11px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    font-size: 13px;
    border-left: 4px solid;
}
.alert-success { background:#d5f0e0; border-color:var(--green); color:#1a6b3a; }
.alert-danger  { background:#f5d5d5; border-color:var(--red);   color:#8c1a1a; }
.alert-info    { background:#d5e8f5; border-color:var(--blue);  color:#1a5a8c; }

/* ============================================================
   PRINT STYLES
   ============================================================ */
@media print {
    /* Hide navigation and interactive elements when printing */
    .navbar, .no-print, .btn-print, .btn { display:none !important; }

    body { background:#fff; font-size:12px; }
    .card { box-shadow:none; border:none; padding:0; }
    .container { padding:0; }
    .page-header { margin-bottom:10px; }
    table { font-size:11px; }

    /* Force dark table header to print with colour */
    thead th {
        background: #1a3a5c !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Show the print-only header (company name, date etc.) */
    .print-header { display: block !important; }
}

/* Print header - hidden on screen, shown on print */
.print-header { display:none; text-align:center; margin-bottom:16px; }
.print-header h2 { font-size:16px; color:#1a3a5c; }
.print-header p  { font-size:12px; color:#555; }

/* ============================================================
   RESPONSIVE - smaller screens
   ============================================================ */
@media (max-width: 768px) {
    .navbar     { padding: 0 12px; height: 135px; }
    .navbar-sub { display: none; }
    .container  { padding: 14px 10px; }
    .form-grid  { grid-template-columns: 1fr 1fr; }
    .stat-grid  { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .form-grid { grid-template-columns: 1fr; }
    .stat-grid { grid-template-columns: 1fr; }
    .card      { padding: 16px 14px; }
    .stat-value{ font-size: 24px; }
    .page-title{ font-size: 17px; }
}

/* ============================================================
   MOBILE NAV DRAWER - kicks in below 900px
   ============================================================ */
@media (max-width: 900px) {

    .navbar-toggle { display: flex; order: 1; }

    /* Put the user icon after the hamburger on mobile only (desktop keeps it first) */
    .navbar-user-btn { order: 2; }

    /* The whole right-hand nav becomes a slide-in drawer */
    .navbar-right {
        position: fixed;
        top: 0;
        right: -300px;
        width: 280px;
        max-width: 82vw;
        height: 100vh;
        background: var(--white);
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        padding: 110px 0 24px;
        overflow-y: auto;
        box-shadow: -6px 0 24px rgba(0,0,0,.25);
        transition: right .28s ease;
        z-index: 200;
    }
    .navbar-right.open { right: 0; }

    /* Hide the desktop dividers, use built-in borders instead */
    .navbar-right > .nav-divider { display: none; }

    .nav-link {
        width: 100%;
        padding: 13px 22px;
        border-radius: 0;
        font-size: 14px;
        color: var(--text-dark);
        border-bottom: 1px solid var(--border);
    }
    .nav-link:hover, .nav-link.active { background: var(--page-bg); color: var(--navy); }

    .nav-user {
        padding: 14px 22px 4px;
        margin: 0;
        display: block;
        font-size: 12px;
        color: var(--text-muted);
    }
    .btn-logout {
        display: block;
        margin: 10px 22px 0;
        padding: 9px 12px;
        text-align: center;
        font-size: 13px;
    }

    /* Reports becomes a tap-to-expand accordion instead of hover dropdown */
    .nav-group { width: 100%; position: static; }
    .nav-group > .nav-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .nav-caret { display: inline-block; color: var(--text-muted); }
    .nav-group.open .nav-caret { transform: rotate(180deg); }

    .nav-group-menu {
        display: none;
        position: static;
        width: 100%;
        min-width: 0;
        background: var(--page-bg);
        border: none;
        border-radius: 0;
        box-shadow: none;
        padding: 4px 0;
    }
    .nav-group:hover .nav-group-menu { display: none; } /* disable hover-open on touch */
    .nav-group.open .nav-group-menu { display: block; }

    .nav-group-menu a {
        color: var(--text-dark);
        padding: 11px 22px 11px 34px;
        font-size: 13px;
    }
    .nav-group-menu a:hover { background: #e7edf4; color: var(--navy); }
    .nav-sub-group a { padding-left: 44px; color: var(--blue); }
    .nav-sub-group a:hover { background: #e7edf4; color: var(--navy); }
    .nav-sub-label { color: var(--text-muted); padding-left: 34px; }
    .nav-group-menu .divider { border-top-color: var(--border); margin: 6px 0; }

    /* Note: closing is handled by the hamburger icon itself (it turns into
       an X when the drawer is open) plus tapping the overlay or a link. */
}

</style>
</head>
<body>

<!-- ============================================================
     TOP NAVIGATION BAR
     ============================================================ -->
<nav class="navbar">

  <!-- Left side: Logo and system name -->
<a class="navbar-brand" href="<?= BASE_URL ?>index.php">
    <img src="<?= BASE_URL ?>assets/Ashadi_Jewellers.png" alt="ASHADI Meal System" class="navbar-logo-img">
</a>

  <!-- Right-side group: user profile icon + hamburger + nav links -->
  <div class="navbar-actions">

    <!-- User profile icon - always visible, desktop and mobile -->
    <button type="button" class="navbar-user-btn" id="userProfileBtn" title="My Profile" aria-label="My Profile">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
      </svg>
    </button>

    <!-- Hamburger button - visible only on mobile -->
    <button type="button" class="navbar-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <!-- Dark overlay behind the mobile drawer -->
    <div class="navbar-overlay" id="navOverlay"></div>

    <!-- Right side: Navigation links -->
    <div class="navbar-right" id="navbarRight">

    <!-- Main nav links -->
    <a href="<?= BASE_URL ?>index.php"     class="nav-link <?= $current_page === 'index.php'     ? 'active' : '' ?>">Dashboard</a>
    <a href="<?= BASE_URL ?>add_meal.php"  class="nav-link <?= $current_page === 'add_meal.php'  ? 'active' : '' ?>">Add Meals</a>
    <a href="<?= BASE_URL ?>edit_meal.php" class="nav-link <?= $current_page === 'edit_meal.php' ? 'active' : '' ?>">Edit Meals</a>

    <div class="nav-divider"></div>

    <!-- Reports dropdown (hover on desktop, tap-to-expand on mobile) -->
    <div class="nav-group" id="reportsGroup">
      <a href="#" class="nav-link <?= in_array($current_page, $report_pages) ? 'active' : '' ?>" id="reportsToggle">
        Reports <span class="nav-caret">&#9662;</span>
      </a>

      <div class="nav-group-menu">

        <!-- Meal Report with 3 direct links for each meal type -->
        <div class="nav-sub-group">
          <div class="nav-sub-label">Meal Report</div>
          <a href="<?= BASE_URL ?>rpt_breakfast.php?f_type=BF">Breakfast Meal</a>
          <a href="<?= BASE_URL ?>rpt_breakfast.php?f_type=LUN">Lunch Meal</a>
          <a href="<?= BASE_URL ?>rpt_breakfast.php?f_type=DIN">Dinner Meal</a>
        </div>

        <div class="divider"></div>

        <!-- Other reports -->
        <a href="<?= BASE_URL ?>rpt_filter_date.php">Meals Filter by Date</a>
        <a href="<?= BASE_URL ?>rpt_daily.php">Daily Report</a>

        <div class="divider"></div>

        <a href="<?= BASE_URL ?>rpt_by_section.php">Sum by Section</a>
        <a href="<?= BASE_URL ?>rpt_by_month.php">Sum by Month</a>
        <a href="<?= BASE_URL ?>rpt_by_company.php">Sum by Company</a>
        <a href="<?= BASE_URL ?>rpt_by_employee.php">Filter by Employee</a>

        <div class="divider"></div>

        <a href="<?= BASE_URL ?>rpt_monthly_finance.php">Monthly Report for Finance</a>

      </div>
    </div>

    <!-- Users link (admin only) -->
    <?php if (is_admin()): ?>
    <div class="nav-divider"></div>
    <a href="<?= BASE_URL ?>manage_users.php" class="nav-link <?= $current_page === 'manage_users.php' ? 'active' : '' ?>">Users</a>
    <a href="<?= BASE_URL ?>meal_timings.php" class="nav-link <?= $current_page === 'meal_timings.php' ? 'active' : '' ?>">Meal Timings</a>
    <a href="<?= BASE_URL ?>meal_prices.php" class="nav-link <?= $current_page === 'meal_prices.php' ? 'active' : '' ?>">Meal Prices</a>
    <?php endif; ?>

    <div class="nav-divider"></div>

    <!-- Logged-in user name and logout button -->
    <span class="nav-user"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></span>
    <a href="<?= BASE_URL ?>logout.php" class="btn-logout">Logout</a>

    </div>
  </div>
</nav>

<!-- ============================================================
     MY PROFILE MODAL - read-only details + password change
     ============================================================ -->
<div class="profile-modal-overlay" id="profileModalOverlay">
  <div class="profile-modal" id="profileModal" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle" onclick="event.stopPropagation()">

    <div class="profile-modal-header">
      <h3 id="profileModalTitle">My Profile</h3>
      <button type="button" class="profile-modal-close" id="profileModalClose" aria-label="Close">&times;</button>
    </div>

    <?php if ($profile_msg): ?><div class="alert alert-success"><?= htmlspecialchars($profile_msg) ?></div><?php endif; ?>
    <?php if ($profile_err): ?><div class="alert alert-danger"><?= htmlspecialchars($profile_err) ?></div><?php endif; ?>

    <div class="profile-section">
      <div class="profile-section-title">Account Details</div>
      <div class="profile-row"><span class="profile-label">Full Name</span><span class="profile-value"><?= htmlspecialchars($profile_data['full_name'] ?: '—') ?></span></div>
      <div class="profile-row"><span class="profile-label">Username</span><span class="profile-value"><?= htmlspecialchars($profile_data['username']) ?></span></div>
      <div class="profile-row"><span class="profile-label">Role</span><span class="profile-value"><?= $profile_data['role'] === 'admin' ? 'Admin' : 'Staff' ?></span></div>
      <div class="profile-row"><span class="profile-label">Status</span><span class="profile-value"><?= $profile_data['is_blocked'] ? 'Blocked' : 'Active' ?></span></div>
      <div class="profile-row"><span class="profile-label">Member Since</span><span class="profile-value"><?= $profile_data['CreatedAt'] ? date('d M Y', strtotime($profile_data['CreatedAt'])) : '—' ?></span></div>
    </div>

    <div class="profile-divider"></div>

    <div class="profile-section">
      <div class="profile-section-title">Change Password</div>
      <form method="POST">
        <input type="hidden" name="change_own_password" value="1">
        <div class="form-group" style="margin-bottom:10px;">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password">
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" minlength="6" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn-update-pw">Update Password</button>
      </form>
    </div>

  </div>
</div>

<script>
(function () {
  var toggle   = document.getElementById('navToggle');
  var menu     = document.getElementById('navbarRight');
  var overlay  = document.getElementById('navOverlay');
  var reportsToggle = document.getElementById('reportsToggle');
  var reportsGroup  = document.getElementById('reportsGroup');

  // ---- My Profile modal ----
  var userBtn       = document.getElementById('userProfileBtn');
  var profileOverlay= document.getElementById('profileModalOverlay');
  var profileClose  = document.getElementById('profileModalClose');

  function openProfile()  { profileOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeProfile() { profileOverlay.classList.remove('active'); document.body.style.overflow = ''; }

  userBtn.addEventListener('click', openProfile);
  profileClose.addEventListener('click', closeProfile);
  profileOverlay.addEventListener('click', closeProfile);

  <?php if ($profile_msg || $profile_err): ?>
  // Re-open automatically after a password-change submit so the result is visible
  openProfile();
  <?php endif; ?>

  function isMobile() { return window.innerWidth <= 900; }

  function openMenu() {
    menu.classList.add('open');
    overlay.classList.add('active');
    toggle.classList.add('active');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu() {
    menu.classList.remove('open');
    overlay.classList.remove('active');
    toggle.classList.remove('active');
    toggle.setAttribute('aria-expanded', 'false');
    reportsGroup.classList.remove('open');
    document.body.style.overflow = '';
  }

  toggle.addEventListener('click', function () {
    menu.classList.contains('open') ? closeMenu() : openMenu();
  });
  overlay.addEventListener('click', closeMenu);

  // On mobile, tapping "Reports" expands/collapses the list instead of
  // navigating (hover-to-open doesn't work on touch screens).
  reportsToggle.addEventListener('click', function (e) {
    if (isMobile()) {
      e.preventDefault();
      reportsGroup.classList.toggle('open');
    }
  });

  // Close the drawer whenever any actual link inside it is tapped.
  menu.querySelectorAll('a[href]:not(#reportsToggle)').forEach(function (a) {
    a.addEventListener('click', closeMenu);
  });

  // If the window is resized back up to desktop width, reset drawer state.
  window.addEventListener('resize', function () {
    if (!isMobile()) closeMenu();
  });
})();
</script>
