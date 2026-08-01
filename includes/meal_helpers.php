<?php
// ============================================================
// ASHADI MEAL SYSTEM - Meal Timing Helpers
// Handles Sri Lanka time + per-meal cutoff time logic
// ============================================================

const MEAL_TYPE_LABELS = ['BF' => 'Breakfast', 'LUN' => 'Lunch', 'DIN' => 'Dinner'];

// Default cutoffs used only if the settings table has no row for a meal type yet
const MEAL_TYPE_DEFAULT_CUTOFFS = ['BF' => '07:30:00', 'LUN' => '12:00:00', 'DIN' => '19:00:00'];

// Default prices used only if the settings table has no row for a meal type yet
const MEAL_TYPE_DEFAULT_PRICES = ['BF' => 0.00, 'LUN' => 0.00, 'DIN' => 0.00];

function meal_type_label($type) {
    return MEAL_TYPE_LABELS[$type] ?? $type;
}

// Current date/time in Sri Lanka (Asia/Colombo), regardless of server timezone
function sl_now() {
    return new DateTime('now', new DateTimeZone('Asia/Colombo'));
}

// Returns ['BF' => '07:30:00', 'LUN' => '12:00:00', 'DIN' => '19:00:00'] from DB, falling back to defaults
function get_meal_cutoffs($conn) {
    $cutoffs = MEAL_TYPE_DEFAULT_CUTOFFS;
    $res = $conn->query("SELECT meal_type, cutoff_time FROM meal_timing_settings");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cutoffs[$r['meal_type']] = $r['cutoff_time'];
        }
    }
    return $cutoffs;
}

// Returns ['BF' => 150.00, 'LUN' => 250.00, 'DIN' => 200.00] from DB, falling back to defaults.
// This is the single source of truth for meal prices - staff never type a price themselves,
// it is always looked up here and multiplied by quantity. Only the admin-only Meal Prices
// page (meal_prices.php) can change these values.
function get_meal_prices($conn) {
    $prices = MEAL_TYPE_DEFAULT_PRICES;
    $res = $conn->query("SELECT meal_type, price FROM meal_price_settings");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $prices[$r['meal_type']] = (float)$r['price'];
        }
    }
    return $prices;
}

// Formats a "HH:MM:SS" time string as "7:30 AM" style for display
function format_cutoff_time($time_str) {
    $t = DateTime::createFromFormat('H:i:s', $time_str);
    return $t ? $t->format('g:i A') : $time_str;
}

/**
 * Checks whether a meal of $meal_type can be added for $meal_date right now (Sri Lanka time).
 * The cutoff time is a DEADLINE - the meal can only be added before it passes.
 * Only restricts TODAY's date - past dates (backfilling) and future dates are not time-restricted.
 * Returns null if allowed, or an error message string if blocked.
 */
function check_meal_timing($conn, $meal_date, $meal_type) {
    $cutoffs = get_meal_cutoffs($conn);
    if (!isset($cutoffs[$meal_type])) return null;

    $now   = sl_now();
    $today = $now->format('Y-m-d');

    if ($meal_date !== $today) return null; // only enforce cutoff for today's date

    $cutoff_time = $cutoffs[$meal_type];
    $cutoff_dt = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $cutoff_time, new DateTimeZone('Asia/Colombo'));

    if ($now > $cutoff_dt) {
        return meal_type_label($meal_type) . ' meals can only be added before ' . format_cutoff_time($cutoff_time) . ' Sri Lanka time.';
    }
    return null;
}

/**
 * Same cutoff rule as check_meal_timing(), but for EDITING/DELETING an existing record.
 * Only applies to TODAY's date - past dates are never locked for editing.
 * Returns null if editing is allowed, or an error message string if the cutoff has passed.
 */
function check_meal_edit_timing($conn, $meal_date, $meal_type) {
    $cutoffs = get_meal_cutoffs($conn);
    if (!isset($cutoffs[$meal_type])) return null;

    $now   = sl_now();
    $today = $now->format('Y-m-d');

    if ($meal_date !== $today) return null; // only enforce cutoff for today's date

    $cutoff_time = $cutoffs[$meal_type];
    $cutoff_dt = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $cutoff_time, new DateTimeZone('Asia/Colombo'));

    if ($now > $cutoff_dt) {
        return meal_type_label($meal_type) . ' meals can no longer be edited or deleted after ' . format_cutoff_time($cutoff_time) . ' Sri Lanka time.';
    }
    return null;
}
