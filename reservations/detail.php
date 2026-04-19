<?php
// ============================================================
// reservations/detail.php — Reservation Detail
// Sprint 3: PBI-012
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardAuth();

$db = getDB();
$id = (int)get('id');

$stmt = $db->prepare("SELECT r.*, s.name as service_name, s.price as service_price,
        s.duration as service_duration,
        u.name as therapist_name, c.name as created_by_name
    FROM reservations r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.therapist_id
    LEFT JOIN users c ON c.id = r.created_by
    WHERE r.id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) {
    flash('error','Reservasi tidak ditemukan.','error');
    redirect('reservations/index.php');
}

// Payment info
$payStmt = $db->prepare("SELECT p.*, u.name as confirmed_by_name FROM payments p
    LEFT JOIN users u ON u.id = p.confirmed_by WHERE p.reservation_id = ?");
$payStmt->execute([$id]);
$payment = $payStmt->fetch();

// Product usage
$usageStmt = $db->prepare("SELECT pu.*, p.name as product_name, p.unit FROM product_usage pu
    JOIN products p ON p.id = pu.product_id WHERE pu.reservation_id = ?");
$usageStmt->execute([$id]);
$usages = $usageStmt->fetchAll();

$pageTitle = 'Detail Reservasi #' . $id;
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="mb-3">
    <a href="javascript:history.back()" class="btn-outline-tea" style="font-size:.83rem">← Kembali</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-calendar-check me-2"></i>Informasi Reservasi</h5>
                <div><?= statusBadge($res['status']) ?></div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Nama Pelanggan</div>
                        <div style="font-weight:500"><?= sanitize($res['customer_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">No. Telepon</div>
                        <div><?= sanitize($res['phone_number']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Layanan</div>
                        <div style="font-weight:500"><?= sanitize($res['service_name']) ?></div>
                        <div style="font-size:.8rem;color:var(--muted)"><?= $res['service_duration'] ?> menit &mdash; <?= formatRupiah($res['service_price']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Terapis</div>
                        <div><?= sanitize($res['therapist_name'] ?? 'Belum ditentukan') ?></div>
                    </div>
                    <div class="col-sm-4">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Tanggal</div>
                        <div><?= formatDate($res['reservation_date']) ?></div>
                    </div>
                    <div class="col-sm-4">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Waktu</div>
                        <div><?= formatTime($res['reservation_time']) ?> &ndash; <?= formatTime($res['end_time']) ?></div>
                    </div>
                    <div class="col-sm-4">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Ruangan</div>
                        <div><span class="badge bg-secondary" style="font-size:.9rem">R<?= sanitize($res['room_number']) ?></span></div>
                    </div>
                    <?php if ($res['notes']): ?>
                    <div class="col-12">
                        <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.25rem">Catatan</div>
                        <div style="background:var(--cream);padding:.6rem .9rem;border-radius:8px;font-size:.88rem"><?= sanitize($res['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Usage -->
        <?php if ($usages): ?>
        <div class="card mt-3">
            <div class="card-header"><h5><i class="bi bi-box-seam me-2"></i>Bahan Terpakai</h5></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Produk</th><th>Jumlah</th><th>Satuan</th></tr></thead>
                    <tbody>
                        <?php foreach ($usages as $u): ?>
                        <tr>
                            <td><?= sanitize($u['product_name']) ?></td>
                            <td><?= $u['quantity_used'] ?></td>
                            <td><?= sanitize($u['unit']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Payment Status -->
        <div class="card">
            <div class="card-header"><h5><i class="bi bi-credit-card me-2"></i>Pembayaran</h5></div>
            <div class="card-body">
                <?php if ($payment): ?>
                <div class="mb-2"><?= statusBadge($payment['payment_status']) ?></div>
                <div style="font-size:.85rem;color:var(--muted);margin-bottom:.25rem">Jumlah</div>
                <div style="font-size:1.3rem;font-weight:600;color:var(--tea-dark);margin-bottom:.75rem"><?= formatRupiah($payment['amount']) ?></div>
                <?php if ($payment['payment_status'] === 'Lunas'): ?>
                <div style="font-size:.82rem;color:var(--muted)">
                    Metode: <b><?= ucfirst($payment['payment_method']) ?></b><br>
                    Dikonfirmasi: <b><?= sanitize($payment['confirmed_by_name'] ?? '-') ?></b><br>
                    Waktu: <b><?= $payment['paid_at'] ? date('d M Y H:i', strtotime($payment['paid_at'])) : '-' ?></b>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div style="color:var(--muted);font-size:.88rem">
                    <?= $res['status'] === 'Selesai' ? 'Menunggu konfirmasi kasir.' : 'Pembayaran belum tersedia.' ?>
                </div>
                <div style="font-size:1.2rem;font-weight:600;color:var(--tea-dark);margin-top:.5rem"><?= formatRupiah($res['service_price']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="card mt-3">
            <div class="card-header"><h5>Aksi</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                <?php if (in_array(currentRole(),['manager','therapist']) && $res['status'] === 'Menunggu'): ?>
                <form method="POST" action="<?= url('monitoring/update_status.php') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $res['id'] ?>">
                    <input type="hidden" name="status" value="Proses">
                    <button type="submit" class="btn-tea w-100"><i class="bi bi-play-circle"></i> Mulai (Proses)</button>
                </form>
                <?php endif; ?>
                <?php if (in_array(currentRole(),['manager','therapist']) && $res['status'] === 'Proses'): ?>
                <form method="POST" action="<?= url('monitoring/update_status.php') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $res['id'] ?>">
                    <input type="hidden" name="status" value="Selesai">
                    <button type="submit" class="btn-tea w-100" style="background:var(--gold)"><i class="bi bi-check-circle"></i> Selesai</button>
                </form>
                <a href="<?= url('inventory/usage.php?reservation_id='.$res['id']) ?>" class="btn-outline-tea" style="justify-content:center;text-align:center">
                    <i class="bi bi-box-seam"></i> Input Pemakaian
                </a>
                <?php endif; ?>
                <?php if (currentRole() === 'cashier' && $res['status'] === 'Selesai' && (!$payment || $payment['payment_status'] === 'Belum Bayar')): ?>
                <a href="<?= url('payments/confirm.php?reservation_id='.$res['id']) ?>" class="btn-tea" style="justify-content:center;text-align:center">
                    <i class="bi bi-cash-coin"></i> Konfirmasi Bayar
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body" style="font-size:.8rem;color:var(--muted)">
                Dibuat oleh: <b><?= sanitize($res['created_by_name'] ?? 'Sistem') ?></b><br>
                Pada: <b><?= date('d M Y H:i', strtotime($res['created_at'])) ?></b>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
