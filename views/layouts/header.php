<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Tea Spa' ?> — Tea Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-flower1"></i></div>
        <div>
            <div class="brand-name">Tea Spa</div>
            <div class="brand-sub">Management System</div>
        </div>
    </div>

    <?php $role = currentRole(); ?>
    <nav class="sidebar-nav">

        <a href="<?= url('dashboard.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <?php if (in_array($role, ['manager'])): ?>
        <div class="nav-section">Layanan</div>
        <a href="<?= url('services/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'services') ? 'active' : '' ?>">
            <i class="bi bi-stars"></i> Kelola Layanan
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['manager','therapist','cashier'])): ?>
        <div class="nav-section">Reservasi</div>
        <?php endif; ?>
        <?php if (in_array($role, ['manager','cashier'])): ?>
        <a href="<?= url('reservations/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'reservations') ? 'active' : '' ?>">
            <i class="bi bi-calendar3"></i> Reservasi
        </a>
        <?php endif; ?>
        <?php if (in_array($role, ['manager','therapist'])): ?>
        <a href="<?= url('monitoring/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'monitoring') ? 'active' : '' ?>">
            <i class="bi bi-activity"></i> Monitoring
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['cashier'])): ?>
        <div class="nav-section">Keuangan</div>
        <a href="<?= url('payments/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'payments') ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i> Pembayaran
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['manager'])): ?>
        <div class="nav-section">SDM</div>
        <a href="<?= url('therapist/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'therapist') ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Jadwal Terapis
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['therapist','purchasing','manager'])): ?>
        <div class="nav-section">Inventori</div>
        <?php endif; ?>
        <?php if (in_array($role, ['therapist','manager'])): ?>
        <a href="<?= url('inventory/usage.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'usage') ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Pemakaian Stok
        </a>
        <?php endif; ?>
        <?php if (in_array($role, ['purchasing','manager'])): ?>
        <a href="<?= url('inventory/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'/inventory/index') ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i> Manajemen Stok
        </a>
        <a href="<?= url('inventory/requests.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'requests') ? 'active' : '' ?>">
            <i class="bi bi-clipboard-check"></i> Permintaan Stok
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['manager','accounting'])): ?>
        <div class="nav-section">Laporan</div>
        <a href="<?= url('reports/index.php') ?>" class="nav-item <?= str_contains($_SERVER['PHP_SELF'],'reports') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Laporan Keuangan
        </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(auth()['name'],0,1)) ?></div>
            <div>
                <div class="user-name"><?= sanitize(auth()['name']) ?></div>
                <div class="user-role"><?= ucfirst(sanitize(auth()['role'])) ?></div>
            </div>
        </div>
        <a href="<?= url('logout.php') ?>" class="btn-logout" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<!-- Main content -->
<div class="main-content" id="mainContent">
    <!-- Topbar -->
    <div class="topbar">
        <button class="btn-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title"><?= $pageTitle ?? '' ?></div>
        <div class="topbar-right">
            <span class="badge-role"><?= ucfirst(sanitize(auth()['role'])) ?></span>
        </div>
    </div>

    <!-- Flash messages -->
    <?php foreach (['success','error','info','warning'] as $f): ?>
        <?php $msg = getFlash($f); if ($msg): ?>
        <div class="alert-flash alert-flash-<?= $msg['type'] ?>" id="flashMsg">
            <i class="bi bi-<?= $msg['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
            <?= sanitize($msg['message']) ?>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;float:right;cursor:pointer;">×</button>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="page-body">
