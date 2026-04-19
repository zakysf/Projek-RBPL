<?php
// ============================================================
// config/app.php - Application Configuration & Global Helpers
// TEA SPA System
// ============================================================

define('APP_NAME',    'Tea Spa');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    '/teaspa'); // Change this to match your XAMPP subfolder

// Role access map: role => allowed module prefixes
define('ROLE_ACCESS', [
    'manager'    => ['dashboard','services','reservations','monitoring','therapist','reports','users'],
    'therapist'  => ['dashboard','monitoring','inventory_usage'],
    'cashier'    => ['dashboard','payments'],
    'purchasing' => ['dashboard','inventory'],
    'accounting' => ['dashboard','reports'],
]);

// ─── Session ────────────────────────────────────────────────

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('TEASPA_SESS');
        session_start();
    }
}

function auth(): ?array {
    sessionStart();
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return auth() !== null;
}

function currentRole(): string {
    return auth()['role'] ?? '';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../views/errors/403.php';
        exit;
    }
}

// ─── URL & Redirect ─────────────────────────────────────────

function redirect(string $path): never {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

// ─── Flash Messages ─────────────────────────────────────────

function flash(string $key, string $message, string $type = 'success'): void {
    sessionStart();
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash(string $key): ?array {
    sessionStart();
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ─── Input & Security ───────────────────────────────────────

function sanitize(mixed $value): string {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

function csrfToken(): string {
    sessionStart();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void {
    sessionStart();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ─── Formatting ─────────────────────────────────────────────

function formatRupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate(string $date): string {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function formatTime(string $time): string {
    if (!$time) return '-';
    return substr($time, 0, 5); // HH:MM
}

function statusBadge(string $status): string {
    $map = [
        'Menunggu'    => 'warning',
        'Proses'      => 'info',
        'Selesai'     => 'success',
        'Belum Bayar' => 'danger',
        'Lunas'       => 'success',
        'Pending'     => 'warning',
        'Disetujui'   => 'success',
        'Ditolak'     => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    return "<span class=\"badge bg-{$color}\">{$status}</span>";
}

// ─── Pagination ─────────────────────────────────────────────

function paginate(int $total, int $perPage, int $page): array {
    $totalPages = (int)ceil($total / $perPage);
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;
    return compact('total','perPage','page','totalPages','offset');
}
