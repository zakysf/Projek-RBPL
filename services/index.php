<?php
// ============================================================
// services/index.php — Service List (Manager only)
// Sprint 2: PB-02 Service Management
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('manager');

$db = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verifyCsrf();
    $id = (int)post('id');
    $db->prepare('UPDATE services SET is_active = 0 WHERE id = ?')->execute([$id]);
    flash('success', 'Layanan berhasil dihapus.', 'success');
    redirect('services/index.php');
}

$services = $db->query('SELECT * FROM services ORDER BY is_active DESC, name ASC')->fetchAll();

$pageTitle = 'Kelola Layanan';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-stars me-2"></i>Daftar Layanan</h5>
        <a href="<?= url('services/create.php') ?>" class="btn-tea">
            <i class="bi bi-plus-lg"></i> Tambah Layanan
        </a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Layanan</th>
                    <th>Deskripsi</th>
                    <th>Durasi</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$services): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-stars"></i><p>Belum ada layanan</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($services as $i => $s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td style="font-weight:500"><?= sanitize($s['name']) ?></td>
                    <td style="max-width:200px;font-size:.83rem;color:var(--muted)"><?= sanitize($s['description'] ?? '-') ?></td>
                    <td><?= $s['duration'] ?> menit</td>
                    <td><?= formatRupiah($s['price']) ?></td>
                    <td><?= $s['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
                    <td>
                        <a href="<?= url('services/edit.php?id='.$s['id']) ?>" class="btn-outline-tea" style="font-size:.78rem;padding:.25rem .65rem">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit"
                                data-confirm="Hapus layanan '<?= sanitize($s['name']) ?>'?"
                                style="background:#fef2f2;color:#ef4444;border:1.5px solid #fecaca;border-radius:6px;padding:.25rem .65rem;cursor:pointer;font-size:.78rem">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
