<?php
// ============================================================
// middleware/auth.php - Authentication & Authorization Guard
// TEA SPA System
// ============================================================

require_once __DIR__ . '/../config/app.php';

/**
 * Guard: must be logged in
 */
function guardAuth(): void {
    requireLogin();
}

/**
 * Guard: role whitelist
 */
function guardRole(string ...$roles): void {
    requireRole($roles);
}

/**
 * Check if current user can access a given module
 */
function canAccess(string $module): bool {
    $role = currentRole();
    $map  = ROLE_ACCESS;
    if (!isset($map[$role])) return false;
    foreach ($map[$role] as $allowed) {
        if (str_starts_with($module, $allowed)) return true;
    }
    return false;
}
