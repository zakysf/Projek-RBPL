<?php
// ============================================================
// dashboard.php — Main Dashboard
// ============================================================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/middleware/auth.php';

guardAuth();

$db   = getDB();
$role = currentRole();
$today = date('Y-m-d');

// ── Today's Reservations ─────────────────────────────────────
$todayRes = $db->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_date = ?');
$todayRes->execute([$today]);
$todayCount = (int)$todayRes->fetchColumn();

// ── Status counts ────────────────────────────────────────────
$statusStmt = $db->query("SELECT status, COUNT(*) as cnt FROM reservations WHERE reservation_date = '$today' GROUP BY status");
$statusCounts = array_column($statusStmt->fetchAll(), 'cnt', 'status');

// ── Revenue today ────────────────────────────────────────────
$revStmt = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p
    JOIN reservations r ON r.id = p.reservation_id
    WHERE p.payment_status = 'Lunas' AND DATE(p.paid_at) = ?");
$revStmt->execute([$today]);
$todayRevenue = (float)$revStmt->fetchColumn();

// ── Monthly revenue ──────────────────────────────────────────
$revMonthStmt = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p
    WHERE p.payment_status = 'Lunas' AND MONTH(p.paid_at) = MONTH(NOW()) AND YEAR(p.paid_at) = YEAR(NOW())");
$revMonthStmt->execute();
$monthRevenue = (float)$revMonthStmt->fetchColumn();

// ── Pending payments ─────────────────────────────────────────
$pendingStmt = $db->query("SELECT COUNT(*) FROM reservations r
    LEFT JOIN payments p ON p.reservation_id = r.id
    WHERE r.status = 'Selesai' AND (p.payment_status IS NULL OR p.payment_status = 'Belum Bayar')");
$pendingPayments = (int)$pendingStmt->fetchColumn();

// ── Low stock ────────────────────────────────────────────────
$lowStockStmt = $db->query('SELECT COUNT(*) FROM products WHERE stock <= min_stock AND is_active = 1');
$lowStockCount = (int)$lowStockStmt->fetchColumn();

// ── Recent reservations ──────────────────────────────────────
$recentStmt = $db->prepare("SELECT r.*, s.name as service_name, u.name as therapist_name
    FROM reservations r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.therapist_id
    WHERE r.reservation_date >= CURDATE()
    ORDER BY r.reservation_date ASC, r.reservation_time ASC
    LIMIT 8");
$recentStmt->execute();
$recentRes = $recentStmt->fetchAll();

// ── Low stock items ──────────────────────────────────────────
$lowStockItems = $db->query('SELECT * FROM products WHERE stock <= min_stock AND is_active = 1 ORDER BY stock ASC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/views/layouts/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="stat-label">Reservasi Hari Ini</div>
                <div class="stat-value"><?= $todayCount ?></div>
                <div class="stat-sub"><?= formatDate($today) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-label">Pendapatan Hari Ini</div>
                <div class="stat-value" style="font-size:1.1rem"><?= formatRupiah($todayRevenue) ?></div>
                <div class="stat-sub">Bulan ini: <?= formatRupiah($monthRevenue) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= $pendingPayments > 0 ? 'red' : 'green' ?>"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-label">Menunggu Pembayaran</div>
                <div class="stat-value"><?= $pendingPayments ?></div>
                <div class="stat-sub">Antrian kasir</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= $lowStockCount > 0 ? 'red' : 'green' ?>"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="stat-label">Stok Rendah</div>
                <div class="stat-value"><?= $lowStockCount ?></div>
                <div class="stat-sub">Item di bawah minimum</div>
            </div>
        </div>
    </div>
</div>

<!-- Status pills -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php foreach (['Menunggu','Proses','Selesai'] as $s): ?>
    <div class="stat-card" style="padding:.75rem 1.25rem; flex:none;">
        <?= statusBadge($s) ?>
        <span class="ms-2 fw-600"><?= $statusCounts[$s] ?? 0 ?></span>
        <span class="text-muted ms-1" style="font-size:.8rem">hari ini</span>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Upcoming Reservations -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-calendar3 me-2"></i>Reservasi Mendatang</h5>
                <?php if (in_array($role, ['manager','cashier'])): ?>
                <a href="<?= url('reservations/index.php') ?>" class="btn-outline-tea" style="font-size:.8rem;padding:.3rem .8rem">Lihat Semua</a>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Layanan</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Ruang</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentRes): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>Belum ada reservasi</p></div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentRes as $r): ?>
                        <tr>
                            <td>
                                <div style="font-weight:500"><?= sanitize($r['customer_name']) ?></div>
                                <div style="font-size:.78rem;color:var(--muted)"><?= sanitize($r['phone_number']) ?></div>
                            </td>
                            <td><?= sanitize($r['service_name']) ?></td>
                            <td><?= formatDate($r['reservation_date']) ?></td>
                            <td><?= formatTime($r['reservation_time']) ?></td>
                            <td><span class="badge bg-secondary">R<?= sanitize($r['room_number']) ?></span></td>
                            <td><?= statusBadge($r['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-exclamation-triangle text-warning me-2"></i>Stok Rendah</h5>
            </div>
            <div class="card-body" style="padding:0">
                <?php if (!$lowStockItems): ?>
                <div class="empty-state"><i class="bi bi-check-circle text-success"></i><p>Semua stok aman</p></div>
                <?php endif; ?>
                <?php foreach ($lowStockItems as $item): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.25rem;border-bottom:1px solid var(--border)">
                    <div>
                        <div style="font-size:.88rem;font-weight:500"><?= sanitize($item['name']) ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">Min: <?= $item['min_stock'] ?> <?= $item['unit'] ?></div>
                    </div>
                    <span class="badge bg-danger"><?= $item['stock'] ?> <?= $item['unit'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card mt-3">
            <div class="card-header"><h5>Aksi Cepat</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                <?php if (in_array($role, ['manager','cashier'])): ?>
                <a href="<?= url('reservations/create.php') ?>" class="btn-tea" style="justify-content:center">
                    <i class="bi bi-plus-circle"></i> Buat Reservasi
                </a>
                <?php endif; ?>
                <?php if ($role === 'cashier'): ?>
                <a href="<?= url('payments/index.php') ?>" class="btn-outline-tea" style="justify-content:center">
                    <i class="bi bi-credit-card"></i> Antrian Pembayaran
                </a>
                <?php endif; ?>
                <?php if (in_array($role,['manager','accounting'])): ?>
                <a href="<?= url('reports/index.php') ?>" class="btn-outline-tea" style="justify-content:center">
                    <i class="bi bi-bar-chart-line"></i> Laporan Keuangan
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
