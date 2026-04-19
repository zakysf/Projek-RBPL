<?php
// ============================================================
// reports/export.php — PDF Export (FR-018)
// Uses browser print CSS trick for XAMPP without Composer
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

guardRole('manager','accounting');

$db = getDB();

$filterType  = get('type','monthly');
$filterMonth = get('month', date('Y-m'));
$filterDate  = get('date',  date('Y-m-d'));

if ($filterType === 'daily') {
    $dateWhere = "DATE(p.paid_at) = '$filterDate'";
    $label     = 'Laporan Harian: ' . date('d F Y', strtotime($filterDate));
} else {
    [$yr, $mo] = explode('-', $filterMonth . '-01');
    $dateWhere = "YEAR(p.paid_at) = $yr AND MONTH(p.paid_at) = $mo";
    $label     = 'Laporan Bulanan: ' . date('F Y', strtotime($filterMonth . '-01'));
}

$totalStmt = $db->query("SELECT COALESCE(SUM(p.amount),0) as total, COUNT(*) as count
    FROM payments p WHERE p.payment_status = 'Lunas' AND $dateWhere");
$totals = $totalStmt->fetch();

$byService = $db->query("SELECT s.name as service_name, COUNT(*) as count, SUM(p.amount) as revenue
    FROM payments p JOIN reservations r ON r.id = p.reservation_id JOIN services s ON s.id = r.service_id
    WHERE p.payment_status = 'Lunas' AND $dateWhere
    GROUP BY s.id, s.name ORDER BY revenue DESC")->fetchAll();

$transactions = $db->query("SELECT p.*, r.customer_name, r.reservation_date, s.name as service_name, u.name as cashier_name
    FROM payments p JOIN reservations r ON r.id = p.reservation_id JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = p.confirmed_by
    WHERE p.payment_status = 'Lunas' AND $dateWhere ORDER BY p.paid_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $label ?> — Tea Spa</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        @page { margin: 1.5cm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; font-size: 11pt; color: #2c2c2c; background: #fff; }
        .header { text-align: center; border-bottom: 2px solid #5a7c5a; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .header h1 { font-family: 'Cormorant Garamond', serif; font-size: 22pt; color: #3d5c3d; }
        .header p  { font-size: 10pt; color: #888; margin-top: .25rem; }
        .header .period { font-size: 13pt; font-weight: 600; margin-top: .5rem; color: #5a7c5a; }
        .summary { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .sum-box { border: 1px solid #e0e0e0; border-radius: 8px; padding: .75rem 1rem; }
        .sum-label { font-size: 9pt; color: #888; text-transform: uppercase; letter-spacing: .05em; }
        .sum-value { font-size: 16pt; font-weight: 700; color: #3d5c3d; margin-top: .2rem; }
        h2 { font-family: 'Cormorant Garamond', serif; font-size: 13pt; color: #3d5c3d; margin: 1rem 0 .6rem; border-bottom: 1px solid #e8e8e8; padding-bottom: .3rem; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 1rem; }
        th { background: #e8f0e8; padding: .5rem .75rem; text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .05em; color: #3d5c3d; border-bottom: 1px solid #ccc; }
        td { padding: .45rem .75rem; border-bottom: 1px solid #f0f0f0; }
        tfoot td { background: #e8f0e8; font-weight: 700; color: #3d5c3d; }
        .footer { margin-top: 2rem; text-align: right; font-size: 9pt; color: #aaa; }
        .no-print { margin-bottom: 1rem; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="no-print" style="padding:1rem;background:#f9f9f9;border-bottom:1px solid #eee;display:flex;gap:1rem;align-items:center">
    <button onclick="window.print()" style="background:#5a7c5a;color:#fff;border:none;padding:.5rem 1.25rem;border-radius:8px;cursor:pointer;font-size:.88rem">
        🖨️ Cetak / Simpan PDF
    </button>
    <a href="javascript:history.back()" style="font-size:.85rem;color:#888;text-decoration:none">← Kembali</a>
</div>

<div class="header">
    <h1>Tea Spa</h1>
    <p>Greenhost Boutique Hotel, Yogyakarta</p>
    <div class="period"><?= $label ?></div>
    <p style="margin-top:.25rem;font-size:9pt">Dicetak: <?= date('d F Y H:i') ?></p>
</div>

<div class="summary">
    <div class="sum-box">
        <div class="sum-label">Total Pendapatan</div>
        <div class="sum-value"><?= formatRupiah($totals['total']) ?></div>
    </div>
    <div class="sum-box">
        <div class="sum-label">Jumlah Transaksi</div>
        <div class="sum-value"><?= $totals['count'] ?></div>
    </div>
    <div class="sum-box">
        <div class="sum-label">Rata-rata / Transaksi</div>
        <div class="sum-value"><?= $totals['count'] > 0 ? formatRupiah($totals['total']/$totals['count']) : 'Rp 0' ?></div>
    </div>
</div>

<h2>Pendapatan per Layanan</h2>
<table>
    <thead><tr><th>Layanan</th><th>Jumlah Transaksi</th><th>Total Pendapatan</th><th>Persentase</th></tr></thead>
    <tbody>
        <?php foreach ($byService as $s): ?>
        <?php $pct = $totals['total'] > 0 ? round($s['revenue']/$totals['total']*100,1) : 0; ?>
        <tr>
            <td><?= sanitize($s['service_name']) ?></td>
            <td><?= $s['count'] ?></td>
            <td><?= formatRupiah($s['revenue']) ?></td>
            <td><?= $pct ?>%</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2>Daftar Transaksi</h2>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Pelanggan</th>
            <th>Layanan</th>
            <th>Tgl Layanan</th>
            <th>Waktu Bayar</th>
            <th>Metode</th>
            <th>Jumlah</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $i => $t): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= sanitize($t['customer_name']) ?></td>
            <td><?= sanitize($t['service_name']) ?></td>
            <td><?= formatDate($t['reservation_date']) ?></td>
            <td><?= $t['paid_at'] ? date('d M Y H:i', strtotime($t['paid_at'])) : '-' ?></td>
            <td><?= ucfirst($t['payment_method']) ?></td>
            <td><?= formatRupiah($t['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" style="text-align:right">Total</td>
            <td><?= formatRupiah($totals['total']) ?></td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    Tea Spa Management System &bull; Generated <?= date('d M Y H:i') ?> &bull; <?= sanitize(auth()['name']) ?>
</div>
</body>
</html>
