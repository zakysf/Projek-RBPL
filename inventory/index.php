<?php
// ============================================================
// inventory/index.php — Stock Management (Purchasing/Manager)
// Sprint 6: PBI-026, PBI-028, PBI-029, PBI-030
// FR-015, FR-016
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('purchasing','manager');

$db = getDB();

// Handle add stock (Purchasing receives new items)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_stock') {
    verifyCsrf();
    $productId = (int)post('product_id');
    $qty       = (float)post('quantity');
    $notes     = trim(post('notes'));

    if ($productId > 0 && $qty > 0) {
        $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([$qty, $productId]);
        flash('success', "Stok berhasil ditambahkan sebesar $qty.", 'success');
    } else {
        flash('error','Pilih produk dan masukkan jumlah yang valid.','error');
    }
    redirect('inventory/index.php');
}

// Handle add new product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_product') {
    verifyCsrf();
    $name      = trim(post('name'));
    $unit      = trim(post('unit'));
    $stock     = (float)post('stock');
    $minStock  = (float)post('min_stock');

    if ($name && $unit) {
        $db->prepare('INSERT INTO products(name,unit,stock,min_stock) VALUES(?,?,?,?)')
           ->execute([$name,$unit,$stock,$minStock]);
        flash('success','Produk baru berhasil ditambahkan.','success');
    } else {
        flash('error','Nama dan satuan wajib diisi.','error');
    }
    redirect('inventory/index.php');
}

$products = $db->query('SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

$pageTitle = 'Manajemen Stok';
include __DIR__ . '/../views/layouts/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-boxes"></i></div>
            <div>
                <div class="stat-label">Total Produk</div>
                <div class="stat-value"><?= count($products) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <div class="stat-label">Stok Rendah</div>
                <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['stock'] <= $p['min_stock'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-label">Stok Aman</div>
                <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['stock'] > $p['min_stock'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left: Add Stock form + Add Product -->
    <div class="col-lg-4">
        <!-- Add Stock -->
        <div class="card mb-3">
            <div class="card-header"><h5><i class="bi bi-plus-circle me-2"></i>Tambah Stok</h5></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_stock">
                    <div class="mb-3">
                        <label class="form-label">Produk *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?> (<?= $p['stock'] ?> <?= $p['unit'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Masuk *</label>
                        <input type="number" name="quantity" class="form-control" min="1" step="1" required>
                    </div>
                    <button type="submit" class="btn-tea w-100"><i class="bi bi-plus-lg"></i> Tambah Stok</button>
                </form>
            </div>
        </div>

        <!-- Add Product -->
        <div class="card">
            <div class="card-header"><h5><i class="bi bi-box-seam me-2"></i>Produk Baru</h5></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_product">
                    <div class="mb-3">
                        <label class="form-label">Nama Produk *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Satuan *</label>
                        <input type="text" name="unit" class="form-control" placeholder="ml / gram / pcs" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Stok Awal</label>
                            <input type="number" name="stock" class="form-control" value="0" min="0" step="1">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Stok Min.</label>
                            <input type="number" name="min_stock" class="form-control" value="10" min="0" step="1">
                        </div>
                    </div>
                    <button type="submit" class="btn-outline-tea w-100"><i class="bi bi-plus-lg"></i> Tambah Produk</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Product list -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-boxes me-2"></i>Daftar Produk</h5>
                <a href="<?= url('inventory/requests.php') ?>" class="btn-outline-tea" style="font-size:.8rem">
                    <i class="bi bi-clipboard-check"></i> Permintaan Stok
                </a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Stok Minimum</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$products): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="bi bi-boxes"></i><p>Belum ada produk</p></div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($products as $p): ?>
                        <?php $isLow = $p['stock'] <= $p['min_stock']; ?>
                        <tr <?= $isLow ? 'style="background:#fff5f5"' : '' ?>>
                            <td style="font-weight:500"><?= sanitize($p['name']) ?></td>
                            <td>
                                <span style="font-size:1.05rem;font-weight:600;color:<?= $isLow ? '#ef4444' : 'var(--tea-dark)' ?>">
                                    <?= $p['stock'] ?>
                                </span>
                                <span style="color:var(--muted);font-size:.82rem"> <?= $p['unit'] ?></span>
                            </td>
                            <td style="font-size:.85rem;color:var(--muted)"><?= $p['min_stock'] ?> <?= $p['unit'] ?></td>
                            <td>
                                <?php if ($isLow): ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Rendah</span>
                                <?php else: ?>
                                <span class="badge bg-success">Aman</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
