<?php
// ============================================================
// payments/index.php — Payment Queue Dashboard
// Sprint 4: PBI-017, PBI-019
// FR-012, FR-013
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('cashier','manager');

$db = getDB();

// Pending payment queue (Selesai but unpaid)
$queueStmt = $db->query("SELECT r.*, s.name as service_name, s.price as service_price,
        u.name as therapist_name,
        p.id as payment_id, p.payment_status, p.amount
    FROM reservations r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.therapist_id
    LEFT JOIN payments p ON p.reservation_id = r.id
    WHERE r.status = 'Selesai'
      AND (p.payment_status IS NULL OR p.payment_status = 'Belum Bayar')
    ORDER BY r.reservation_date ASC, r.reservation_time ASC");
$queue = $queueStmt->fetchAll();

// Recent paid
$paidStmt = $db->query("SELECT p.*, r.customer_name, r.reservation_date, r.reservation_time,
        s.name as service_name, u.name as cashier_name
    FROM payments p
    JOIN reservations r ON r.id = p.reservation_id
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = p.confirmed_by
    WHERE p.payment_status = 'Lunas'
    ORDER BY p.paid_at DESC
    LIMIT 10");
$paid = $paidStmt->fetchAll();

$pageTitle = 'Antrian Pembayaran';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-label">Menunggu Bayar</div>
                <div class="stat-value"><?= count($queue) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-label">Lunas Hari Ini</div>
                <div class="stat-value"><?= count($paid) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-label">Total Pending</div>
                <div class="stat-value" style="font-size:1rem"><?= formatRupiah(array_sum(array_column($queue,'service_price'))) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Queue -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-hourglass-split me-2"></i>Antrian Pembayaran</h5>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Terapis</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Ruang</th>
                    <th>Jumlah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$queue): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-check-circle text-success"></i><p>Tidak ada antrian pembayaran</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($queue as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight:500"><?= sanitize($r['customer_name']) ?></div>
                        <div style="font-size:.78rem;color:var(--muted)"><?= sanitize($r['phone_number']) ?></div>
                    </td>
                    <td style="font-size:.85rem"><?= sanitize($r['service_name']) ?></td>
                    <td style="font-size:.85rem"><?= sanitize($r['therapist_name'] ?? '-') ?></td>
                    <td><?= formatDate($r['reservation_date']) ?></td>
                    <td><?= formatTime($r['reservation_time']) ?></td>
                    <td><span class="badge bg-secondary">R<?= sanitize($r['room_number']) ?></span></td>
                    <td style="font-weight:600;color:var(--tea-dark)"><?= formatRupiah($r['service_price']) ?></td>
                    <td>
                        <a href="<?= url('payments/confirm.php?reservation_id='.$r['id']) ?>" class="btn-tea" style="font-size:.8rem;padding:.35rem .8rem">
                            <i class="bi bi-cash-coin"></i> Bayar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Paid -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-check-circle me-2"></i>Transaksi Lunas Terbaru</h5>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Jumlah</th>
                    <th>Metode</th>
                    <th>Waktu Bayar</th>
                    <th>Dikonfirmasi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$paid): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-receipt"></i><p>Belum ada transaksi hari ini</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($paid as $p): ?>
                <tr>
                    <td style="font-weight:500"><?= sanitize($p['customer_name']) ?></td>
                    <td style="font-size:.85rem"><?= sanitize($p['service_name']) ?></td>
                    <td style="font-weight:600;color:var(--tea-dark)"><?= formatRupiah($p['amount']) ?></td>
                    <td><?= ucfirst($p['payment_method']) ?></td>
                    <td><?= $p['paid_at'] ? date('d M Y H:i', strtotime($p['paid_at'])) : '-' ?></td>
                    <td style="font-size:.83rem"><?= sanitize($p['cashier_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
