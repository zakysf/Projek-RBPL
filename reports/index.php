<?php
// ============================================================
// reports/index.php — Financial Reports
// Sprint 7: PBI-033, PBI-034
// FR-017, FR-018
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('manager','accounting');

$db = getDB();

// Filter params
$filterType  = get('type','monthly');  // daily | monthly
$filterMonth = get('month', date('Y-m'));
$filterDate  = get('date',  date('Y-m-d'));
$filterFrom  = get('from',  date('Y-m-01'));
$filterTo    = get('to',    date('Y-m-d'));

// Build WHERE clause
if ($filterType === 'daily') {
    $dateWhere = "DATE(p.paid_at) = '$filterDate'";
    $label     = formatDate($filterDate);
} else {
    [$yr, $mo] = explode('-', $filterMonth . '-01');
    $dateWhere = "YEAR(p.paid_at) = $yr AND MONTH(p.paid_at) = $mo";
    $label     = date('F Y', strtotime($filterMonth . '-01'));
}

// Total revenue
$totalStmt = $db->query("SELECT COALESCE(SUM(p.amount),0) as total, COUNT(*) as count
    FROM payments p WHERE p.payment_status = 'Lunas' AND $dateWhere");
$totals = $totalStmt->fetch();

// Revenue by service
$byServiceStmt = $db->query("SELECT s.name as service_name, COUNT(*) as count,
        SUM(p.amount) as revenue
    FROM payments p
    JOIN reservations r ON r.id = p.reservation_id
    JOIN services s ON s.id = r.service_id
    WHERE p.payment_status = 'Lunas' AND $dateWhere
    GROUP BY s.id, s.name ORDER BY revenue DESC");
$byService = $byServiceStmt->fetchAll();

// Daily breakdown (for monthly view)
$dailyStmt = $db->query("SELECT DATE(p.paid_at) as day, COUNT(*) as count,
        SUM(p.amount) as revenue
    FROM payments p WHERE p.payment_status = 'Lunas' AND $dateWhere
    GROUP BY DATE(p.paid_at) ORDER BY day ASC");
$daily = $dailyStmt->fetchAll();

// Transaction list
$transStmt = $db->query("SELECT p.*, r.customer_name, r.reservation_date, r.reservation_time,
        r.room_number, s.name as service_name, u.name as cashier_name
    FROM payments p
    JOIN reservations r ON r.id = p.reservation_id
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = p.confirmed_by
    WHERE p.payment_status = 'Lunas' AND $dateWhere
    ORDER BY p.paid_at DESC");
$transactions = $transStmt->fetchAll();

$pageTitle = 'Laporan Keuangan';
include __DIR__ . '/../views/layouts/header.php';
?>

<!-- Filter bar -->
<div class="card mb-4">
    <div class="card-body" style="padding:1rem 1.5rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div>
                <label class="form-label">Tipe Laporan</label>
                <select name="type" class="form-select" style="width:auto" onchange="toggleFilter(this.value)">
                    <option value="monthly" <?= $filterType==='monthly'?'selected':'' ?>>Bulanan</option>
                    <option value="daily"   <?= $filterType==='daily'?'selected':'' ?>>Harian</option>
                </select>
            </div>
            <div id="filterMonthly" style="<?= $filterType==='daily'?'display:none':'' ?>">
                <label class="form-label">Bulan</label>
                <input type="month" name="month" class="form-control" value="<?= sanitize($filterMonth) ?>">
            </div>
            <div id="filterDaily" style="<?= $filterType==='monthly'?'display:none':'' ?>">
                <label class="form-label">Tanggal</label>
                <input type="date" name="date" class="form-control" value="<?= sanitize($filterDate) ?>">
            </div>
            <div>
                <button type="submit" class="btn-tea" style="margin-top:auto"><i class="bi bi-search"></i> Tampilkan</button>
                <a href="<?= url('reports/export.php?type='.$filterType.'&month='.$filterMonth.'&date='.$filterDate) ?>"
                   class="btn-outline-tea" style="margin-top:auto" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-label">Total Pendapatan</div>
                <div class="stat-value" style="font-size:1.1rem"><?= formatRupiah($totals['total']) ?></div>
                <div class="stat-sub"><?= $label ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="stat-label">Jumlah Transaksi</div>
                <div class="stat-value"><?= $totals['count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="stat-label">Rata-rata/Transaksi</div>
                <div class="stat-value" style="font-size:1rem"><?= $totals['count'] > 0 ? formatRupiah($totals['total']/$totals['count']) : 'Rp 0' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Revenue by Service -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h5><i class="bi bi-pie-chart me-2"></i>Pendapatan per Layanan</h5></div>
            <div class="card-body" style="padding:0">
                <?php if (!$byService): ?>
                <div class="empty-state"><i class="bi bi-bar-chart-line"></i><p>Belum ada data</p></div>
                <?php endif; ?>
                <?php foreach ($byService as $s): ?>
                <?php $pct = $totals['total'] > 0 ? round($s['revenue']/$totals['total']*100) : 0; ?>
                <div style="padding:.85rem 1.25rem;border-bottom:1px solid var(--border)">
                    <div style="display:flex;justify-content:space-between;margin-bottom:.35rem">
                        <span style="font-size:.85rem;font-weight:500"><?= sanitize($s['service_name']) ?></span>
                        <span style="font-size:.85rem;font-weight:600;color:var(--tea-dark)"><?= formatRupiah($s['revenue']) ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div style="flex:1;height:6px;background:#f0f0f0;border-radius:3px">
                            <div style="width:<?= $pct ?>%;height:100%;background:var(--tea);border-radius:3px"></div>
                        </div>
                        <span style="font-size:.72rem;color:var(--muted)"><?= $pct ?>%</span>
                    </div>
                    <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem"><?= $s['count'] ?> transaksi</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Daily chart (monthly view) -->
    <?php if ($filterType === 'monthly' && $daily): ?>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h5><i class="bi bi-bar-chart me-2"></i>Tren Harian</h5></div>
            <div class="card-body">
                <?php $maxRev = max(array_column($daily, 'revenue') ?: [1]); ?>
                <div style="display:flex;align-items:flex-end;gap:4px;height:180px;padding-bottom:1rem;overflow-x:auto">
                    <?php foreach ($daily as $d): ?>
                    <?php $h = max(4, round($d['revenue']/$maxRev*160)); ?>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:2px;min-width:28px;flex:1">
                        <div style="font-size:.6rem;color:var(--muted)"><?= formatRupiah($d['revenue']) ?></div>
                        <div title="<?= formatDate($d['day']) ?>: <?= formatRupiah($d['revenue']) ?>"
                             style="width:100%;height:<?= $h ?>px;background:var(--tea);border-radius:4px 4px 0 0;transition:.2s"
                             onmouseover="this.style.background='var(--tea-dark)'"
                             onmouseout="this.style.background='var(--tea)'"></div>
                        <div style="font-size:.6rem;color:var(--muted)"><?= date('d', strtotime($d['day'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Transaction list -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-list-ul me-2"></i>Daftar Transaksi</h5>
        <span style="font-size:.83rem;color:var(--muted)"><?= count($transactions) ?> transaksi</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Tanggal Layanan</th>
                    <th>Waktu Bayar</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                    <th>Kasir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$transactions): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-receipt"></i><p>Tidak ada transaksi</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($transactions as $i => $t): ?>
                <tr>
                    <td style="color:var(--muted);font-size:.8rem"><?= $i+1 ?></td>
                    <td style="font-weight:500"><?= sanitize($t['customer_name']) ?></td>
                    <td style="font-size:.85rem"><?= sanitize($t['service_name']) ?></td>
                    <td><?= formatDate($t['reservation_date']) ?></td>
                    <td style="font-size:.83rem"><?= $t['paid_at'] ? date('d M Y H:i', strtotime($t['paid_at'])) : '-' ?></td>
                    <td><span class="badge bg-<?= $t['payment_method']==='tunai'?'info':'success' ?>"><?= ucfirst($t['payment_method']) ?></span></td>
                    <td style="font-weight:600;color:var(--tea-dark)"><?= formatRupiah($t['amount']) ?></td>
                    <td style="font-size:.83rem"><?= sanitize($t['cashier_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($transactions): ?>
            <tfoot>
                <tr style="background:var(--tea-light)">
                    <td colspan="6" style="text-align:right;font-weight:600;padding:.75rem 1rem;font-size:.9rem">Total</td>
                    <td style="font-weight:700;color:var(--tea-dark);padding:.75rem 1rem"><?= formatRupiah($totals['total']) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function toggleFilter(type) {
    document.getElementById('filterMonthly').style.display = type === 'monthly' ? '' : 'none';
    document.getElementById('filterDaily').style.display   = type === 'daily'   ? '' : 'none';
}
</script>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
