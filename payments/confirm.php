<?php
// ============================================================
// payments/confirm.php — Confirm Payment (Cashier)
// Sprint 4: PBI-017, PBI-018
// FR-013
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('cashier','manager');

$db  = getDB();
$rid = (int)get('reservation_id');

// Load reservation + service
$stmt = $db->prepare("SELECT r.*, s.name as service_name, s.price as service_price,
        s.duration as service_duration, u.name as therapist_name
    FROM reservations r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.therapist_id
    WHERE r.id = ? AND r.status = 'Selesai'");
$stmt->execute([$rid]);
$res = $stmt->fetch();

if (!$res) {
    flash('error','Reservasi tidak ditemukan atau belum selesai.','error');
    redirect('payments/index.php');
}

// Check if already paid
$payStmt = $db->prepare("SELECT * FROM payments WHERE reservation_id = ?");
$payStmt->execute([$rid]);
$payment = $payStmt->fetch();

if ($payment && $payment['payment_status'] === 'Lunas') {
    flash('info','Reservasi ini sudah lunas.','info');
    redirect('payments/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $amount = (float)post('amount', $res['service_price']);
    $method = post('payment_method','tunai');
    $notes  = trim(post('notes'));

    if (!in_array($method, ['tunai','transfer'], true)) {
        $errors['payment_method'] = 'Metode tidak valid.';
    }
    if ($amount <= 0) {
        $errors['amount'] = 'Jumlah harus lebih dari 0.';
    }

    if (!$errors) {
        if ($payment) {
            // Update existing
            $db->prepare("UPDATE payments SET amount=?,payment_method=?,payment_status='Lunas',
                paid_at=NOW(),confirmed_by=?,notes=? WHERE reservation_id=?")
               ->execute([$amount,$method,auth()['id'],$notes,$rid]);
        } else {
            // Insert new
            $db->prepare("INSERT INTO payments(reservation_id,amount,payment_method,payment_status,paid_at,confirmed_by,notes)
                VALUES(?,?,?,'Lunas',NOW(),?,?)")
               ->execute([$rid,$amount,$method,auth()['id'],$notes]);
        }

        flash('success','Pembayaran berhasil dikonfirmasi. Status: Lunas.','success');
        redirect('payments/index.php');
    }
}

$pageTitle = 'Konfirmasi Pembayaran';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7">

<div class="card mb-3">
    <div class="card-header">
        <h5><i class="bi bi-receipt me-2"></i>Detail Transaksi</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6">
                <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.2rem">Pelanggan</div>
                <div style="font-weight:600"><?= sanitize($res['customer_name']) ?></div>
                <div style="font-size:.82rem;color:var(--muted)"><?= sanitize($res['phone_number']) ?></div>
            </div>
            <div class="col-sm-6">
                <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.2rem">Layanan</div>
                <div style="font-weight:600"><?= sanitize($res['service_name']) ?></div>
                <div style="font-size:.82rem;color:var(--muted)"><?= $res['service_duration'] ?> menit</div>
            </div>
            <div class="col-sm-4">
                <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.2rem">Tanggal</div>
                <div><?= formatDate($res['reservation_date']) ?></div>
            </div>
            <div class="col-sm-4">
                <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.2rem">Waktu</div>
                <div><?= formatTime($res['reservation_time']) ?></div>
            </div>
            <div class="col-sm-4">
                <div style="font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.2rem">Ruangan</div>
                <div>R<?= sanitize($res['room_number']) ?></div>
            </div>
        </div>

        <div style="background:var(--tea-light);border-radius:10px;padding:1rem 1.25rem;margin-top:1rem;display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.85rem;color:var(--tea-dark)">Total Tagihan</span>
            <span style="font-size:1.6rem;font-weight:700;color:var(--tea-dark)"><?= formatRupiah($res['service_price']) ?></span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-cash-coin me-2"></i>Konfirmasi Pembayaran</h5>
        <a href="<?= url('payments/index.php') ?>" class="btn-outline-tea" style="font-size:.8rem">← Kembali</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Jumlah Diterima (Rp) *</label>
                <input type="number" name="amount" class="form-control <?= isset($errors['amount'])?'is-invalid':'' ?>"
                       value="<?= post('amount', $res['service_price']) ?>" min="0" step="1000" required>
                <?php if (isset($errors['amount'])): ?><div class="invalid-feedback"><?= $errors['amount'] ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Metode Pembayaran *</label>
                <div style="display:flex;gap:1rem;margin-top:.25rem">
                    <?php foreach (['tunai'=>'Tunai (Cash)','transfer'=>'Transfer Bank'] as $val=>$label): ?>
                    <label style="cursor:pointer;display:flex;align-items:center;gap:.5rem;font-size:.9rem">
                        <input type="radio" name="payment_method" value="<?= $val ?>"
                               <?= post('payment_method','tunai') === $val ? 'checked' : '' ?>>
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Opsional..."><?= sanitize(post('notes')) ?></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= url('payments/index.php') ?>" class="btn-outline-tea">Batal</a>
                <button type="submit" class="btn-tea" style="background:#16a34a">
                    <i class="bi bi-check-circle"></i> Konfirmasi Lunas
                </button>
            </div>
        </form>
    </div>
</div>

</div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
