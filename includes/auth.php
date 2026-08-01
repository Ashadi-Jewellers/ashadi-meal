<?php
// ============================================================
// ASHADI MEAL SYSTEM - Authentication Helpers
// ============================================================
require_once __DIR__ . '/config.php';

function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in() {
    session_start_safe();
    return isset($_SESSION['meal_user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function is_admin() {
    session_start_safe();
    return isset($_SESSION['meal_role']) && $_SESSION['meal_role'] === 'admin';
}

function current_user() {
    session_start_safe();
    return [
        'id'        => $_SESSION['meal_user_id'] ?? null,
        'username'  => $_SESSION['meal_username'] ?? '',
        'full_name' => $_SESSION['meal_full_name'] ?? '',
        'role'      => $_SESSION['meal_role'] ?? '',
    ];
}

if (!defined('BASE_URL')) define('BASE_URL', '/');
