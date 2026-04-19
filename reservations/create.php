<?php
// ============================================================
// reservations/create.php — Create / Edit Reservation
// Sprint 2: PBI-008, PBI-009, PBI-010
// FR-006, FR-007, FR-008
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('manager','cashier');

$db     = getDB();
$id     = (int)get('id');
$isEdit = $id > 0;
$res    = [];
$errors = [];

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    if (!$res || $res['status'] !== 'Menunggu') {
        flash('error','Reservasi tidak dapat diedit.','error');
        redirect('reservations/index.php');
    }
}

// ── Load data for selects ────────────────────────────────────
$services   = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY name')->fetchAll();
$therapists = $db->query("SELECT * FROM users WHERE role = 'therapist' AND is_active = 1 ORDER BY name")->fetchAll();
$rooms      = array_map(fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT), range(1,8));

// ── Handle AJAX conflict check ───────────────────────────────
if (isset($_GET['check_conflict'])) {
    header('Content-Type: application/json');
    $date       = $_GET['date']        ?? '';
    $time       = $_GET['time']        ?? '';
    $serviceId  = (int)($_GET['service_id'] ?? 0);
    $therapistId= (int)($_GET['therapist_id'] ?? 0);
    $room       = $_GET['room']        ?? '';
    $excludeId  = (int)($_GET['exclude'] ?? 0);

    // Get duration
    $svc = $db->prepare('SELECT duration FROM services WHERE id = ?');
    $svc->execute([$serviceId]);
    $dur = (int)($svc->fetchColumn() ?: 60);

    // Calculate end time
    [$h,$m] = explode(':', $time);
    $endMin = (int)$h * 60 + (int)$m + $dur;
    $endTime = sprintf('%02d:%02d', floor($endMin/60)%24, $endMin%60);

    $conflicts = [];

    // Room conflict
    $roomStmt = $db->prepare("SELECT customer_name FROM reservations
        WHERE reservation_date = ? AND room_number = ? AND id != ?
        AND status != 'Selesai'
        AND NOT (end_time <= ? OR reservation_time >= ?)");
    $roomStmt->execute([$date, $room, $excludeId, $time, $endTime]);
    if ($rc = $roomStmt->fetch()) {
        $conflicts[] = "Ruangan $room sudah digunakan oleh {$rc['customer_name']} pada waktu tersebut.";
    }

    // Therapist conflict
    if ($therapistId) {
        $thStmt = $db->prepare("SELECT customer_name FROM reservations
            WHERE reservation_date = ? AND therapist_id = ? AND id != ?
            AND status != 'Selesai'
            AND NOT (end_time <= ? OR reservation_time >= ?)");
        $thStmt->execute([$date, $therapistId, $excludeId, $time, $endTime]);
        if ($tc = $thStmt->fetch()) {
            $conflicts[] = "Terapis sudah ada jadwal dengan {$tc['customer_name']} pada waktu tersebut.";
        }
    }

    echo json_encode(['conflicts' => $conflicts, 'end_time' => $endTime]);
    exit;
}

// ── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $customerName  = trim(post('customer_name'));
    $phoneNumber   = trim(post('phone_number'));
    $serviceId     = (int)post('service_id');
    $therapistId   = (int)post('therapist_id') ?: null;
    $resDate       = post('reservation_date');
    $resTime       = post('reservation_time');
    $room          = post('room_number');
    $notes         = trim(post('notes'));

    // Validation
    if (!$customerName) $errors['customer_name'] = 'Nama wajib diisi.';
    if (!$phoneNumber)  $errors['phone_number']  = 'No. telepon wajib diisi.';
    if (!$serviceId)    $errors['service_id']    = 'Pilih layanan.';
    if (!$resDate)      $errors['reservation_date'] = 'Tanggal wajib diisi.';
    if (!$resTime)      $errors['reservation_time'] = 'Waktu wajib diisi.';
    if (!$room)         $errors['room_number']   = 'Pilih nomor ruangan.';

    if (!$errors) {
        // Get service duration
        $svcStmt = $db->prepare('SELECT duration FROM services WHERE id = ?');
        $svcStmt->execute([$serviceId]);
        $duration = (int)$svcStmt->fetchColumn();

        [$h,$m] = explode(':', $resTime);
        $endMin  = (int)$h * 60 + (int)$m + $duration;
        $endTime = sprintf('%02d:%02d', floor($endMin/60)%24, $endMin%60);

        $excludeId = $isEdit ? $id : 0;

        // Check room conflict
        $roomChk = $db->prepare("SELECT id FROM reservations
            WHERE reservation_date = ? AND room_number = ? AND id != ?
            AND status != 'Selesai'
            AND NOT (end_time <= ? OR reservation_time >= ?)");
        $roomChk->execute([$resDate, $room, $excludeId, $resTime, $endTime]);
        if ($roomChk->fetch()) $errors['room_number'] = 'Ruangan sudah terpakai pada waktu tersebut.';

        // Check therapist conflict
        if ($therapistId) {
            $thChk = $db->prepare("SELECT id FROM reservations
                WHERE reservation_date = ? AND therapist_id = ? AND id != ?
                AND status != 'Selesai'
                AND NOT (end_time <= ? OR reservation_time >= ?)");
            $thChk->execute([$resDate, $therapistId, $excludeId, $resTime, $endTime]);
            if ($thChk->fetch()) $errors['therapist_id'] = 'Terapis sudah ada jadwal pada waktu tersebut.';
        }
    }

    if (!$errors) {
        if ($isEdit) {
            $db->prepare("UPDATE reservations SET customer_name=?,phone_number=?,service_id=?,
                therapist_id=?,reservation_date=?,reservation_time=?,end_time=?,room_number=?,notes=? WHERE id=?")
               ->execute([$customerName,$phoneNumber,$serviceId,$therapistId,$resDate,$resTime,$endTime,$room,$notes,$id]);
            flash('success','Reservasi berhasil diperbarui.','success');
        } else {
            $db->prepare("INSERT INTO reservations(customer_name,phone_number,service_id,therapist_id,
                reservation_date,reservation_time,end_time,room_number,notes,created_by)
                VALUES(?,?,?,?,?,?,?,?,?,?)")
               ->execute([$customerName,$phoneNumber,$serviceId,$therapistId,$resDate,$resTime,$endTime,$room,$notes,auth()['id']]);
            flash('success','Reservasi berhasil dibuat.','success');
        }
        redirect('reservations/index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Reservasi' : 'Buat Reservasi Baru';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-calendar-plus me-2"></i><?= $pageTitle ?></h5>
        <a href="<?= url('reservations/index.php') ?>" class="btn-outline-tea" style="font-size:.8rem">← Kembali</a>
    </div>
    <div class="card-body">
        <!-- Conflict alert -->
        <div id="conflictAlert" style="display:none;margin-bottom:1rem;background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;font-size:.88rem"></div>

        <form method="POST" id="resForm">
            <?= csrfField() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Pelanggan *</label>
                    <input type="text" name="customer_name" class="form-control <?= isset($errors['customer_name'])?'is-invalid':'' ?>"
                           value="<?= sanitize($res['customer_name'] ?? post('customer_name')) ?>" required>
                    <?php if (isset($errors['customer_name'])): ?><div class="invalid-feedback"><?= $errors['customer_name'] ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">No. Telepon *</label>
                    <input type="tel" name="phone_number" class="form-control <?= isset($errors['phone_number'])?'is-invalid':'' ?>"
                           value="<?= sanitize($res['phone_number'] ?? post('phone_number')) ?>" required>
                    <?php if (isset($errors['phone_number'])): ?><div class="invalid-feedback"><?= $errors['phone_number'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Layanan *</label>
                    <select name="service_id" id="serviceId" class="form-select <?= isset($errors['service_id'])?'is-invalid':'' ?>" onchange="checkConflict()" required>
                        <option value="">-- Pilih Layanan --</option>
                        <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>" data-price="<?= $s['price'] ?>"
                            <?= ($res['service_id'] ?? post('service_id')) == $s['id'] ? 'selected' : '' ?>>
                            <?= sanitize($s['name']) ?> (<?= $s['duration'] ?> mnt — <?= formatRupiah($s['price']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['service_id'])): ?><div class="invalid-feedback"><?= $errors['service_id'] ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Terapis</label>
                    <select name="therapist_id" id="therapistId" class="form-select <?= isset($errors['therapist_id'])?'is-invalid':'' ?>" onchange="checkConflict()">
                        <option value="">-- Belum ditentukan --</option>
                        <?php foreach ($therapists as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($res['therapist_id'] ?? post('therapist_id')) == $t['id'] ? 'selected' : '' ?>>
                            <?= sanitize($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['therapist_id'])): ?><div class="invalid-feedback"><?= $errors['therapist_id'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal *</label>
                    <input type="date" name="reservation_date" id="resDate" class="form-control <?= isset($errors['reservation_date'])?'is-invalid':'' ?>"
                           value="<?= sanitize($res['reservation_date'] ?? post('reservation_date', date('Y-m-d'))) ?>"
                           min="<?= date('Y-m-d') ?>" onchange="checkConflict()" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Waktu Mulai *</label>
                    <input type="time" name="reservation_time" id="resTime" class="form-control <?= isset($errors['reservation_time'])?'is-invalid':'' ?>"
                           value="<?= sanitize($res['reservation_time'] ?? post('reservation_time','09:00')) ?>"
                           onchange="checkConflict()" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Estimasi Selesai</label>
                    <input type="time" id="endTimeDisplay" class="form-control" readonly style="background:#f5f5f5"
                           value="<?= sanitize($res['end_time'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Nomor Ruangan (01-08) *</label>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    <?php foreach ($rooms as $room): ?>
                    <label style="cursor:pointer">
                        <input type="radio" name="room_number" value="<?= $room ?>" style="display:none" class="room-radio"
                               <?= ($res['room_number'] ?? post('room_number')) === $room ? 'checked' : '' ?>
                               onchange="checkConflict()">
                        <span class="room-btn" data-room="<?= $room ?>">R<?= $room ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['room_number'])): ?><div class="invalid-feedback d-block"><?= $errors['room_number'] ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="2"><?= sanitize($res['notes'] ?? post('notes')) ?></textarea>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= url('reservations/index.php') ?>" class="btn-outline-tea">Batal</a>
                <button type="submit" class="btn-tea"><i class="bi bi-check-lg"></i> Simpan Reservasi</button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<style>
.room-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 52px; height: 42px;
    border: 1.5px solid #e0e0e0; border-radius: 8px;
    font-size: .85rem; font-weight: 500; color: #666;
    transition: all .15s;
}
.room-radio:checked + .room-btn {
    background: var(--tea); color: #fff; border-color: var(--tea);
}
.room-btn:hover { border-color: var(--tea); color: var(--tea); }
</style>

<script>
const EXCLUDE_ID = <?= $isEdit ? $id : 0 ?>;

function checkConflict() {
    const date       = document.getElementById('resDate')?.value;
    const time       = document.getElementById('resTime')?.value;
    const serviceId  = document.getElementById('serviceId')?.value;
    const therapistId= document.getElementById('therapistId')?.value;
    const room       = document.querySelector('input[name="room_number"]:checked')?.value;
    const endDisplay = document.getElementById('endTimeDisplay');

    if (!date || !time || !serviceId) return;

    const params = new URLSearchParams({
        check_conflict: 1,
        date, time, service_id: serviceId,
        therapist_id: therapistId || 0,
        room: room || '',
        exclude: EXCLUDE_ID
    });

    fetch('<?= url('reservations/create.php') ?>?' + params)
        .then(r => r.json())
        .then(data => {
            if (endDisplay) endDisplay.value = data.end_time || '';

            const alertEl = document.getElementById('conflictAlert');
            if (data.conflicts && data.conflicts.length > 0) {
                alertEl.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><strong>Konflik Jadwal:</strong><br>' +
                    data.conflicts.map(c => '• ' + c).join('<br>');
                alertEl.style.display = 'block';
            } else {
                alertEl.style.display = 'none';
            }
        }).catch(() => {});
}

document.addEventListener('DOMContentLoaded', checkConflict);
</script>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
