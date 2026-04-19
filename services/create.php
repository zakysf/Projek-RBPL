<?php
// services/create.php — Add / Edit Service
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('manager');

$db = getDB();
$id = (int)get('id');
$isEdit = $id > 0;
$service = [];
$errors = [];

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    $service = $stmt->fetch();
    if (!$service) { flash('error','Layanan tidak ditemukan.','error'); redirect('services/index.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name        = trim(post('name'));
    $description = trim(post('description'));
    $duration    = (int)post('duration');
    $price       = (float)post('price');
    $is_active   = post('is_active') === '1' ? 1 : 0;

    if (!$name)     $errors['name']     = 'Nama wajib diisi.';
    if ($duration <= 0) $errors['duration'] = 'Durasi harus > 0.';
    if ($price <= 0)    $errors['price']    = 'Harga harus > 0.';

    if (!$errors) {
        if ($isEdit) {
            $db->prepare('UPDATE services SET name=?,description=?,duration=?,price=?,is_active=? WHERE id=?')
               ->execute([$name,$description,$duration,$price,$is_active,$id]);
            flash('success','Layanan berhasil diperbarui.','success');
        } else {
            $db->prepare('INSERT INTO services(name,description,duration,price) VALUES(?,?,?,?)')
               ->execute([$name,$description,$duration,$price]);
            flash('success','Layanan berhasil ditambahkan.','success');
        }
        redirect('services/index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Layanan' : 'Tambah Layanan';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-stars me-2"></i><?= $pageTitle ?></h5>
        <a href="<?= url('services/index.php') ?>" class="btn-outline-tea" style="font-size:.8rem">← Kembali</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Nama Layanan *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                       value="<?= sanitize($service['name'] ?? post('name')) ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= $errors['name'] ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3"><?= sanitize($service['description'] ?? post('description')) ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Durasi (menit) *</label>
                    <select name="duration" class="form-select <?= isset($errors['duration'])?'is-invalid':'' ?>">
                        <?php foreach ([30,45,60,75,90,120] as $d): ?>
                        <option value="<?= $d ?>" <?= ($service['duration'] ?? post('duration')) == $d ? 'selected' : '' ?>><?= $d ?> menit</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Harga (Rp) *</label>
                    <input type="number" name="price" class="form-control <?= isset($errors['price'])?'is-invalid':'' ?>"
                           value="<?= $service['price'] ?? post('price') ?>" min="0" step="1000">
                    <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= $errors['price'] ?></div><?php endif; ?>
                </div>
            </div>
            <?php if ($isEdit): ?>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-select">
                    <option value="1" <?= ($service['is_active'] ?? 1) ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= !($service['is_active'] ?? 1) ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= url('services/index.php') ?>" class="btn-outline-tea">Batal</a>
                <button type="submit" class="btn-tea"><i class="bi bi-check-lg"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
