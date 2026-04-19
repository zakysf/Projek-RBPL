<?php
// ============================================================
// reservations/index.php — Reservation List
// Sprint 2: PBI-007 to PBI-010
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('manager','cashier');

$db = getDB();

// Filters
$filterDate   = get('date');
$filterStatus = get('status');

$where  = ['1=1'];
$params = [];

if ($filterDate) {
    $where[]  = 'r.reservation_date = ?';
    $params[] = $filterDate;
}
if ($filterStatus) {
    $where[]  = 'r.status = ?';
    $params[] = $filterStatus;
}

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("SELECT r.*, s.name as service_name, s.price as service_price,
        u.name as therapist_name
    FROM reservations r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.therapist_id
    WHERE $whereStr
    ORDER BY r.reservation_date DESC, r.reservation_time ASC");
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$pageTitle = 'Manajemen Reservasi';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-calendar3 me-2"></i>Daftar Reservasi</h5>
        <a href="<?= url('reservations/create.php') ?>" class="btn-tea">
            <i class="bi bi-plus-lg"></i> Reservasi Baru
        </a>
    </div>

    <!-- Filter bar -->
    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
        <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;flex:1">
            <input type="date" name="date" class="form-control" style="width:auto" value="<?= sanitize($filterDate) ?>" placeholder="Filter tanggal">
            <select name="status" class="form-select" style="width:auto">
                <option value="">Semua Status</option>
                <?php foreach (['Menunggu','Proses','Selesai'] as $st): ?>
                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-tea" style="padding:.5rem .9rem"><i class="bi bi-search"></i> Filter</button>
            <a href="<?= url('reservations/index.php') ?>" class="btn-outline-tea" style="padding:.5rem .9rem">Reset</a>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Terapis</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Ruang</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$reservations): ?>
                <tr><td colspan="10"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>Tidak ada reservasi</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($reservations as $i => $r): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:500"><?= sanitize($r['customer_name']) ?></div>
                        <div style="font-size:.78rem;color:var(--muted)"><?= sanitize($r['phone_number']) ?></div>
                    </td>
                    <td style="font-size:.85rem"><?= sanitize($r['service_name']) ?></td>
                    <td style="font-size:.85rem"><?= sanitize($r['therapist_name'] ?? '-') ?></td>
                    <td><?= formatDate($r['reservation_date']) ?></td>
                    <td><?= formatTime($r['reservation_time']) ?> - <?= formatTime($r['end_time']) ?></td>
                    <td><span class="badge bg-secondary">R<?= sanitize($r['room_number']) ?></span></td>
                    <td style="font-size:.85rem"><?= formatRupiah($r['service_price']) ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td>
                        <a href="<?= url('reservations/detail.php?id='.$r['id']) ?>" class="btn-outline-tea" style="font-size:.78rem;padding:.25rem .6rem">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($r['status'] === 'Menunggu'): ?>
                        <a href="<?= url('reservations/edit.php?id='.$r['id']) ?>" class="btn-outline-tea" style="font-size:.78rem;padding:.25rem .6rem">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
