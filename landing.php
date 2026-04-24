<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
sessionStart();

$db       = getDB();
$success  = false;
$errors   = [];
$formData = [];

// ── AJAX conflict check ────────────────────────────────────
if (isset($_GET['check_conflict'])) {
    header('Content-Type: application/json');
    $date      = $_GET['date'] ?? '';
    $time      = $_GET['time'] ?? '';
    $serviceId = (int)($_GET['service_id'] ?? 0);

    $svc = $db->prepare('SELECT duration FROM services WHERE id=?');
    $svc->execute([$serviceId]);
    $dur = (int)($svc->fetchColumn() ?: 60);
    [$h,$m] = explode(':', $time ?: '00:00');
    $endMin  = (int)$h*60 + (int)$m + $dur;
    $endTime = sprintf('%02d:%02d', floor($endMin/60)%24, $endMin%60);

    // Cek apakah masih ada ruangan kosong di waktu tersebut
    $anyAvailable = false;
    foreach (range(1, 8) as $n) {
        $r = str_pad($n, 2, '0', STR_PAD_LEFT);
        $rs = $db->prepare("SELECT id FROM reservations WHERE reservation_date=? AND room_number=? AND status!='Selesai' AND NOT(end_time<=? OR reservation_time>=?)");
        $rs->execute([$date, $r, $resTime ?? $time, $endTime]);
        if (!$rs->fetch()) { $anyAvailable = true; break; }
    }

    $conflicts = [];
    if (!$anyAvailable) {
        $conflicts[] = 'Semua ruangan telah penuh pada waktu tersebut. Silakan pilih waktu lain.';
    }

    echo json_encode(['conflicts' => $conflicts, 'end_time' => $endTime]);
    exit;
}

// ── Form submission ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('_action') === 'reserve') {
    $customerName = trim(post('customer_name'));
    $phoneNumber  = trim(post('phone_number'));
    $serviceId    = (int)post('service_id');
    $resDate      = post('reservation_date');
    $resTime      = post('reservation_time');
    $notes        = trim(post('notes'));

    if (!$customerName) $errors['customer_name']   = 'Nama wajib diisi.';
    if (!$phoneNumber)  $errors['phone_number']     = 'No. telepon wajib diisi.';
    if (!$serviceId)    $errors['service_id']       = 'Pilih layanan.';
    if (!$resDate)      $errors['reservation_date'] = 'Tanggal wajib diisi.';
    if (!$resTime)      $errors['reservation_time'] = 'Waktu wajib diisi.';
    if ($resDate && $resDate < date('Y-m-d')) $errors['reservation_date'] = 'Tidak bisa memilih tanggal lampau.';

    if (!$errors) {
        $svcStmt = $db->prepare('SELECT duration, name, price FROM services WHERE id=?');
        $svcStmt->execute([$serviceId]);
        $svcRow   = $svcStmt->fetch();
        $duration = (int)$svcRow['duration'];
        [$h,$m]   = explode(':', $resTime);
        $endMin   = (int)$h*60 + (int)$m + $duration;
        $endTime  = sprintf('%02d:%02d', floor($endMin/60)%24, $endMin%60);

        // Auto-assign ruangan kosong
        $availableRoom = null;
        foreach (range(1, 8) as $n) {
            $r = str_pad($n, 2, '0', STR_PAD_LEFT);
            $rc = $db->prepare("SELECT id FROM reservations WHERE reservation_date=? AND room_number=? AND status!='Selesai' AND NOT(end_time<=? OR reservation_time>=?)");
            $rc->execute([$resDate, $r, $resTime, $endTime]);
            if (!$rc->fetch()) { $availableRoom = $r; break; }
        }

        if (!$availableRoom) {
            $errors['reservation_time'] = 'Mohon maaf, semua ruangan telah penuh pada waktu tersebut. Silakan pilih waktu atau tanggal lain.';
        }
    }

    if (!$errors) {
        $db->prepare("INSERT INTO reservations(customer_name,phone_number,service_id,therapist_id,reservation_date,reservation_time,end_time,room_number,notes) VALUES(?,?,?,?,?,?,?,?,?)")
           ->execute([$customerName, $phoneNumber, $serviceId, null, $resDate, $resTime, $endTime, $availableRoom, $notes]);

        // PRG: simpan data sukses ke session lalu redirect GET — mencegah double-submit saat reload
        $_SESSION['reserve_success'] = [
            'customerName' => $customerName,
            'phoneNumber'  => $phoneNumber,
            'resDate'      => $resDate,
            'resTime'      => $resTime,
            'endTime'      => $endTime,
            'room'         => $availableRoom,
            'service_name' => $svcRow['name'],
            'price'        => $svcRow['price'],
        ];
        redirect('landing.php#reservation');
    } else {
        $formData = $_POST;
    }
}

// Ambil data sukses dari session (hasil redirect GET)
if (isset($_SESSION['reserve_success'])) {
    $success  = true;
    $formData = $_SESSION['reserve_success'];
    unset($_SESSION['reserve_success']);
}

// ── Load data for form ─────────────────────────────────────
$services = $db->query('SELECT * FROM services WHERE is_active=1 ORDER BY name ASC')->fetchAll();
$rooms    = array_map(fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT), range(1, 8));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tea Spa — Greenhost Boutique Hotel, Yogyakarta</title>

    <!-- Fonts: Cormorant Garamond (luxury serif) + DM Sans (modern body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
    /* ══════════════════════════════════════════════════
       DESIGN TOKENS — consistent with dashboard palette
    ══════════════════════════════════════════════════ */
    :root {
        --tea:        #5a7c5a;
        --tea-dark:   #3d5c3d;
        --tea-mid:    #4a6b4a;
        --tea-light:  #e8f0e8;
        --tea-pale:   #f2f7f2;
        --cream:      #faf8f3;
        --cream-dark: #f0ece2;
        --gold:       #c9a96e;
        --gold-light: #e8d5a8;
        --gold-dark:  #a8833a;
        --ink:        #1e2a1e;
        --text:       #2c3a2c;
        --muted:      #7a8a7a;
        --border:     rgba(90,124,90,.15);
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    html { scroll-behavior: smooth; }

    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--cream);
        color: var(--text);
        overflow-x: hidden;
    }

    /* ══ UTILITY ══════════════════════════════════════ */
    .serif { font-family: 'Cormorant Garamond', serif; }
    .container { max-width: 1160px; margin: 0 auto; padding: 0 2rem; }
    .section { padding: 7rem 0; }

    /* ══ SCROLL REVEAL ════════════════════════════════ */
    .reveal {
        opacity: 0;
        transform: translateY(32px);
        transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1);
    }
    .reveal.visible { opacity: 1; transform: none; }
    .reveal-left  { opacity:0; transform: translateX(-40px); transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1); }
    .reveal-right { opacity:0; transform: translateX(40px);  transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1); }
    .reveal-left.visible, .reveal-right.visible { opacity:1; transform:none; }

    /* ══ NAV ══════════════════════════════════════════ */
    .nav {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 1000;
        padding: 1.25rem 0;
        transition: background .4s, box-shadow .4s, padding .4s;
    }
    .nav.scrolled {
        background: rgba(250,248,243,.95);
        backdrop-filter: blur(12px);
        box-shadow: 0 1px 0 var(--border);
        padding: .85rem 0;
    }
    .nav-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .nav-brand {
        display: flex;
        align-items: center;
        gap: .75rem;
        text-decoration: none;
    }
    .nav-logo {
        width: 38px; height: 38px;
        background: var(--tea);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .nav-brand-text { line-height: 1.1; }
    .nav-brand-name {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--tea-dark);
        letter-spacing: .04em;
    }
    .nav-brand-sub {
        font-size: .62rem;
        color: var(--muted);
        letter-spacing: .14em;
        text-transform: uppercase;
    }
    .nav-links {
        display: flex;
        align-items: center;
        gap: 2.25rem;
        list-style: none;
    }
    .nav-links a {
        font-size: .82rem;
        font-weight: 500;
        color: var(--text);
        text-decoration: none;
        letter-spacing: .06em;
        transition: color .2s;
    }
    .nav-links a:hover { color: var(--tea); }
    .nav-cta {
        display: flex;
        gap: .75rem;
        align-items: center;
    }
    .btn-ghost {
        font-family: 'DM Sans', sans-serif;
        font-size: .82rem;
        font-weight: 500;
        color: var(--tea-dark);
        border: 1.5px solid var(--border);
        background: transparent;
        padding: .5rem 1.1rem;
        border-radius: 50px;
        text-decoration: none;
        letter-spacing: .04em;
        transition: all .2s;
        cursor: pointer;
    }
    .btn-ghost:hover { border-color: var(--tea); color: var(--tea); background: var(--tea-pale); }
    .btn-primary {
        font-family: 'DM Sans', sans-serif;
        font-size: .82rem;
        font-weight: 500;
        color: #fff;
        background: var(--tea);
        padding: .5rem 1.25rem;
        border-radius: 50px;
        text-decoration: none;
        letter-spacing: .04em;
        transition: all .2s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }
    .btn-primary:hover { background: var(--tea-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(61,92,61,.25); }
    .btn-primary:active { transform: none; }
    .btn-large {
        font-size: .95rem;
        padding: .85rem 2.25rem;
        border-radius: 50px;
    }
    .btn-gold {
        background: var(--gold);
        color: #fff;
    }
    .btn-gold:hover { background: var(--gold-dark); box-shadow: 0 6px 20px rgba(201,169,110,.35); }
    .nav-burger { display: none; background: none; border: none; cursor: pointer; color: var(--text); font-size: 1.4rem; }

    /* ══ HERO ══════════════════════════════════════════ */
    .hero {
        min-height: 100vh;
        position: relative;
        display: flex;
        align-items: center;
        overflow: hidden;
        padding: 7rem 0 5rem;
    }

    /* Organic background blobs */
    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 60% at 70% 40%, rgba(90,124,90,.09) 0%, transparent 60%),
            radial-gradient(ellipse 50% 40% at 20% 80%, rgba(201,169,110,.07) 0%, transparent 55%),
            radial-gradient(ellipse 60% 50% at 90% 10%, rgba(90,124,90,.05) 0%, transparent 50%);
    }

    /* Decorative botanical ring */
    .hero-ring {
        position: absolute;
        right: -120px; top: 50%;
        transform: translateY(-50%);
        width: 680px; height: 680px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(90,124,90,.06) 0%, rgba(201,169,110,.04) 50%, transparent 70%);
        border: 1px solid rgba(90,124,90,.08);
        pointer-events: none;
    }
    .hero-ring::after {
        content: '';
        position: absolute;
        inset: 40px;
        border-radius: 50%;
        border: 1px solid rgba(201,169,110,.1);
    }

    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 620px;
    }
    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        background: rgba(90,124,90,.1);
        color: var(--tea-dark);
        font-size: .7rem;
        font-weight: 500;
        letter-spacing: .18em;
        text-transform: uppercase;
        padding: .4rem 1rem;
        border-radius: 50px;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(90,124,90,.15);
    }
    .hero-eyebrow::before { content: ''; width: 6px; height: 6px; background: var(--gold); border-radius: 50%; flex-shrink: 0; }
    .hero-title {
        font-family: 'Cormorant Garamond', serif;
        font-size: clamp(3rem, 7vw, 5.5rem);
        font-weight: 400;
        line-height: 1.06;
        color: var(--ink);
        margin-bottom: 1.5rem;
        letter-spacing: -.01em;
    }
    .hero-title em { font-style: italic; color: var(--tea); }
    .hero-title .gold-word { color: var(--gold-dark); }

    .hero-desc {
        font-size: 1.05rem;
        line-height: 1.7;
        color: var(--muted);
        font-weight: 300;
        max-width: 480px;
        margin-bottom: 2.5rem;
    }
    .hero-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .hero-note {
        font-size: .78rem;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-top: 1.25rem;
    }
    .hero-note i { color: var(--gold); }

    /* Trust badges */
    .hero-trust {
        display: flex;
        gap: 2rem;
        margin-top: 3.5rem;
        padding-top: 2rem;
        border-top: 1px solid var(--border);
        flex-wrap: wrap;
    }
    .trust-item { text-align: center; }
    .trust-num {
        font-family: 'Cormorant Garamond', serif;
        font-size: 2rem;
        font-weight: 600;
        color: var(--tea-dark);
        line-height: 1;
    }
    .trust-label { font-size: .72rem; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; margin-top: .2rem; }

    /* ══ MARQUEE STRIP ════════════════════════════════ */
    .marquee-strip {
        background: var(--tea);
        padding: .9rem 0;
        overflow: hidden;
        white-space: nowrap;
    }
    .marquee-track {
        display: inline-flex;
        animation: marquee 22s linear infinite;
    }
    .marquee-item {
        display: inline-flex;
        align-items: center;
        gap: .9rem;
        padding: 0 2rem;
        font-family: 'Cormorant Garamond', serif;
        font-size: 1rem;
        font-weight: 400;
        color: rgba(255,255,255,.85);
        letter-spacing: .06em;
        font-style: italic;
    }
    .marquee-dot { width: 4px; height: 4px; background: var(--gold-light); border-radius: 50%; flex-shrink: 0; }
    @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    /* ══ SERVICES SECTION ═════════════════════════════ */
    .section-label {
        font-size: .68rem;
        font-weight: 500;
        letter-spacing: .2em;
        text-transform: uppercase;
        color: var(--gold-dark);
        margin-bottom: .75rem;
    }
    .section-title {
        font-family: 'Cormorant Garamond', serif;
        font-size: clamp(2rem, 4vw, 3.2rem);
        font-weight: 400;
        color: var(--ink);
        line-height: 1.15;
        margin-bottom: 1rem;
    }
    .section-title em { font-style: italic; color: var(--tea); }
    .section-sub {
        font-size: .95rem;
        color: var(--muted);
        font-weight: 300;
        line-height: 1.7;
        max-width: 520px;
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 3.5rem;
    }
    .service-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 2rem;
        transition: transform .3s, box-shadow .3s, border-color .3s;
        cursor: default;
        position: relative;
        overflow: hidden;
    }
    .service-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--tea), var(--gold));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform .4s cubic-bezier(.16,1,.3,1);
    }
    .service-card:hover { transform: translateY(-4px); box-shadow: 0 16px 50px rgba(61,92,61,.1); border-color: rgba(90,124,90,.25); }
    .service-card:hover::before { transform: scaleX(1); }
    .service-icon {
        width: 48px; height: 48px;
        background: var(--tea-light);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: var(--tea);
        margin-bottom: 1.25rem;
    }
    .service-name {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.3rem;
        font-weight: 500;
        color: var(--ink);
        margin-bottom: .4rem;
    }
    .service-desc {
        font-size: .83rem;
        color: var(--muted);
        line-height: 1.65;
        margin-bottom: 1.25rem;
    }
    .service-meta {
        display: flex;
        gap: .75rem;
        align-items: center;
    }
    .service-badge {
        background: var(--tea-pale);
        color: var(--tea-dark);
        font-size: .72rem;
        font-weight: 500;
        padding: .25rem .65rem;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }
    .service-price {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--gold-dark);
        margin-left: auto;
    }

    /* ══ RESERVATION FORM SECTION ═════════════════════ */
    .reservation-section {
        background: var(--tea-dark);
        position: relative;
        overflow: hidden;
    }
    .reservation-section::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 60% 80% at 0% 50%, rgba(90,124,90,.3) 0%, transparent 55%),
            radial-gradient(ellipse 40% 60% at 100% 20%, rgba(201,169,110,.12) 0%, transparent 50%);
    }
    .res-grid {
        display: grid;
        grid-template-columns: 1fr 1.1fr;
        gap: 5rem;
        align-items: start;
        position: relative;
        z-index: 1;
    }
    .res-intro .section-label { color: var(--gold-light); }
    .res-intro .section-title { color: #fff; }
    .res-intro .section-title em { color: var(--gold-light); }
    .res-intro .section-sub { color: rgba(255,255,255,.65); }
    .res-features {
        margin-top: 2rem;
        display: flex;
        flex-direction: column;
        gap: .85rem;
    }
    .res-feature {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        color: rgba(255,255,255,.75);
        font-size: .88rem;
    }
    .res-feature-icon {
        width: 30px; height: 30px;
        background: rgba(255,255,255,.1);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        color: var(--gold-light);
        flex-shrink: 0;
        margin-top: .05rem;
    }

    /* Reservation form card */
    .res-form-card {
        background: var(--cream);
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 30px 80px rgba(0,0,0,.3);
    }
    .res-form-card h3 {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.6rem;
        font-weight: 500;
        color: var(--ink);
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-label {
        display: block;
        font-size: .7rem;
        font-weight: 500;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: .4rem;
    }
    .form-control, .form-select {
        width: 100%;
        padding: .7rem .95rem;
        border: 1.5px solid #e0ddd5;
        border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: .88rem;
        color: var(--text);
        background: #fff;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--tea);
        box-shadow: 0 0 0 3px rgba(90,124,90,.1);
    }
    .form-control.is-invalid { border-color: #ef4444; }
    .invalid-msg { font-size: .73rem; color: #ef4444; margin-top: .25rem; }

    /* Room picker */
    .room-grid { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .3rem; }
    .room-opt { cursor: pointer; }
    .room-opt input[type=radio] { display: none; }
    .room-label {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 46px; height: 38px;
        border: 1.5px solid #e0ddd5;
        border-radius: 8px;
        font-size: .82rem;
        font-weight: 500;
        color: var(--muted);
        transition: all .15s;
        background: #fff;
        user-select: none;
    }
    .room-opt input:checked + .room-label { background: var(--tea); color: #fff; border-color: var(--tea); }
    .room-label:hover { border-color: var(--tea); color: var(--tea); }

    /* Conflict notice */
    .conflict-box {
        background: #fef2f2;
        border: 1px solid #fca5a5;
        color: #b91c1c;
        border-radius: 10px;
        padding: .75rem 1rem;
        font-size: .82rem;
        margin-bottom: 1rem;
        display: none;
        align-items: flex-start;
        gap: .5rem;
    }
    .conflict-box.show { display: flex; }
    .success-state { text-align: center; padding: 2rem 1rem; }
    .success-state .btn-reset { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem 1.4rem; font-size: .82rem; font-weight: 500; background: var(--tea); color: #fff; border: none; border-radius: 50px; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: background .2s; letter-spacing: .03em; }
    .success-state .btn-reset:hover { background: var(--tea-dark); }
    .success-state i { font-size: 3.5rem; color: var(--tea); display: block; margin-bottom: 1rem; }
    .success-state h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; color: var(--tea-dark); margin-bottom: .5rem; }
    .success-state p { font-size: .88rem; color: var(--muted); margin-bottom: 1.5rem; }
    .success-state .ticket {
        background: var(--tea-pale);
        border: 1px dashed rgba(90,124,90,.4);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        text-align: left;
    }
    .ticket-row { display: flex; justify-content: space-between; font-size: .83rem; padding: .3rem 0; border-bottom: 1px solid var(--border); }
    .ticket-row:last-child { border: none; }
    .ticket-row span:first-child { color: var(--muted); }
    .ticket-row span:last-child  { font-weight: 500; color: var(--text); }

    /* ══ WHY SECTION ══════════════════════════════════ */
    .why-section { background: var(--cream-dark); }
    .why-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-top: 3rem;
    }
    .why-card {
        background: var(--cream);
        border-radius: 18px;
        padding: 1.75rem;
        border: 1px solid rgba(201,169,110,.2);
        transition: transform .3s, box-shadow .3s;
    }
    .why-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,.06); }
    .why-icon {
        width: 46px; height: 46px;
        background: linear-gradient(135deg, var(--tea-light), rgba(201,169,110,.15));
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: var(--tea);
        margin-bottom: 1.1rem;
    }
    .why-title { font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; color: var(--ink); margin-bottom: .4rem; font-weight: 500; }
    .why-desc { font-size: .83rem; color: var(--muted); line-height: 1.65; }

    /* ══ ROOMS SECTION ════════════════════════════════ */
    .rooms-section { background: #fff; }
    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-top: 3rem;
    }
    .room-card {
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem 1.25rem;
        text-align: center;
        background: var(--cream);
        transition: all .25s;
        position: relative;
        overflow: hidden;
    }
    .room-card::after {
        content: '';
        position: absolute;
        bottom: 0; left: 50%;
        transform: translateX(-50%);
        width: 0; height: 3px;
        background: linear-gradient(90deg, var(--tea), var(--gold));
        transition: width .3s;
    }
    .room-card:hover::after { width: 100%; }
    .room-card:hover { border-color: rgba(90,124,90,.3); transform: translateY(-2px); }
    .room-num { font-family: 'Cormorant Garamond', serif; font-size: 2rem; font-weight: 600; color: var(--tea-dark); line-height: 1; }
    .room-tag { font-size: .7rem; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-top: .25rem; }
    .room-dot { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; margin: .75rem auto 0; }

    /* ══ STAFF LOGIN SECTION ══════════════════════════ */
    .staff-section { background: var(--cream); }
    .staff-card {
        max-width: 520px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 8px 40px rgba(61,92,61,.08);
        text-align: center;
    }
    .staff-icon {
        width: 72px; height: 72px;
        background: var(--tea);
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: #fff;
        margin: 0 auto 1.5rem;
        box-shadow: 0 10px 30px rgba(61,92,61,.25);
    }
    .staff-title { font-family: 'Cormorant Garamond', serif; font-size: 1.9rem; color: var(--ink); margin-bottom: .5rem; }
    .staff-sub { font-size: .9rem; color: var(--muted); margin-bottom: 2rem; }
    .role-pills {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: .5rem;
        margin-bottom: 2rem;
    }
    .role-pill {
        background: var(--tea-pale);
        color: var(--tea-dark);
        font-size: .72rem;
        font-weight: 500;
        padding: .3rem .85rem;
        border-radius: 50px;
        border: 1px solid var(--border);
        letter-spacing: .04em;
    }

    /* ══ FOOTER ═══════════════════════════════════════ */
    .footer {
        background: var(--ink);
        color: rgba(255,255,255,.6);
        padding: 3.5rem 0 2rem;
    }
    .footer-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 3rem;
        margin-bottom: 2.5rem;
    }
    .footer-brand { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: 1rem; }
    .footer-logo { width: 36px; height: 36px; background: var(--tea); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; flex-shrink: 0; }
    .footer-brand-name { font-family: 'Cormorant Garamond', serif; font-size: 1.1rem; color: #fff; font-weight: 600; line-height: 1.1; }
    .footer-brand-sub { font-size: .6rem; color: rgba(255,255,255,.4); letter-spacing: .12em; text-transform: uppercase; }
    .footer-desc { font-size: .82rem; line-height: 1.7; margin-bottom: 1rem; }
    .footer-contact div { font-size: .8rem; display: flex; align-items: center; gap: .5rem; margin-bottom: .4rem; }
    .footer-contact i { color: var(--gold); width: 14px; }
    .footer-heading { font-size: .68rem; letter-spacing: .15em; text-transform: uppercase; color: rgba(255,255,255,.4); margin-bottom: 1rem; }
    .footer-links { list-style: none; }
    .footer-links li { margin-bottom: .5rem; }
    .footer-links a { font-size: .82rem; color: rgba(255,255,255,.55); text-decoration: none; transition: color .2s; }
    .footer-links a:hover { color: var(--gold-light); }
    .footer-bottom {
        border-top: 1px solid rgba(255,255,255,.08);
        padding-top: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: .75rem;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .footer-bottom a { color: var(--gold-light); text-decoration: none; }

    /* ══ FLOATING RESERVE BUTTON ══════════════════════ */
    .float-btn {
        position: fixed;
        bottom: 1.75rem;
        right: 1.75rem;
        z-index: 500;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: .5rem;
    }
    .float-badge {
        background: var(--tea);
        color: #fff;
        font-size: .72rem;
        font-weight: 500;
        padding: .3rem .85rem;
        border-radius: 50px;
        box-shadow: 0 4px 20px rgba(61,92,61,.3);
        animation: float-badge 3s ease-in-out infinite;
        white-space: nowrap;
    }
    @keyframes float-badge {
        0%,100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
    .float-main {
        width: 58px; height: 58px;
        background: var(--tea);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.4rem;
        box-shadow: 0 8px 30px rgba(61,92,61,.35);
        text-decoration: none;
        transition: transform .2s, background .2s;
    }
    .float-main:hover { background: var(--tea-dark); transform: scale(1.08); color: #fff; }

    /* ══ MOBILE NAV ═══════════════════════════════════ */
    .mobile-menu {
        display: none;
        flex-direction: column;
        background: rgba(250,248,243,.98);
        backdrop-filter: blur(12px);
        position: fixed;
        top: 0; left: 0; right: 0;
        padding: 5rem 2rem 2rem;
        z-index: 999;
        border-bottom: 1px solid var(--border);
        gap: .25rem;
    }
    .mobile-menu.open { display: flex; }
    .mobile-menu a {
        padding: .75rem 1rem;
        font-size: .9rem;
        font-weight: 500;
        color: var(--text);
        text-decoration: none;
        border-radius: 10px;
        transition: background .15s;
    }
    .mobile-menu a:hover { background: var(--tea-pale); color: var(--tea); }
    .mobile-menu-close {
        position: absolute;
        top: 1.25rem; right: 1.5rem;
        background: none;
        border: none;
        font-size: 1.4rem;
        cursor: pointer;
        color: var(--text);
    }

    /* ══ RESPONSIVE ═══════════════════════════════════ */
    @media (max-width: 900px) {
        .res-grid { grid-template-columns: 1fr; gap: 3rem; }
        .rooms-grid { grid-template-columns: repeat(4, 1fr); }
        .footer-grid { grid-template-columns: 1fr 1fr; }
        .nav-links, .nav-cta .btn-ghost { display: none; }
        .nav-burger { display: block; }
    }
    @media (max-width: 640px) {
        .section { padding: 5rem 0; }
        .form-row { grid-template-columns: 1fr; }
        .rooms-grid { grid-template-columns: repeat(4, 1fr); }
        .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
        .hero { padding: 6rem 0 4rem; }
        .hero-trust { gap: 1.25rem; }
        .float-btn { bottom: 1.25rem; right: 1.25rem; }
    }
    </style>
</head>
<body>

<!-- ══════════════ NAVIGATION ══════════════ -->
<nav class="nav" id="mainNav">
    <div class="container">
        <div class="nav-inner">
            <a href="#" class="nav-brand">
                <div class="nav-logo"><i class="bi bi-flower1"></i></div>
                <div class="nav-brand-text">
                    <div class="nav-brand-name">Tea Spa</div>
                    <div class="nav-brand-sub">Greenhost Hotel</div>
                </div>
            </a>
            <ul class="nav-links">
                <li><a href="#services">Layanan</a></li>
                <li><a href="#reservation">Reservasi</a></li>
                <li><a href="#fasilitas">Fasilitas</a></li>
                <li><a href="#kontak">Kontak</a></li>
            </ul>
            <div class="nav-cta">
                <a href="<?= url('login.php') ?>" class="btn-ghost">
                    <i class="bi bi-grid-1x2" style="font-size:.8rem"></i> Staff Login
                </a>
                <a href="#reservation" class="btn-primary">
                    <i class="bi bi-calendar-plus" style="font-size:.8rem"></i> Reservasi
                </a>
            </div>
            <button class="nav-burger" id="burgerBtn" onclick="toggleMenu()"><i class="bi bi-list"></i></button>
        </div>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <button class="mobile-menu-close" onclick="toggleMenu()"><i class="bi bi-x"></i></button>
    <a href="#services" onclick="toggleMenu()">Layanan</a>
    <a href="#reservation" onclick="toggleMenu()">Reservasi</a>
    <a href="#fasilitas" onclick="toggleMenu()">Fasilitas</a>
    <a href="#kontak" onclick="toggleMenu()">Kontak</a>
    <div style="height:1px;background:var(--border);margin:.5rem 0"></div>
    <a href="<?= url('login.php') ?>" style="color:var(--tea);font-weight:600">
        <i class="bi bi-grid-1x2"></i> Staff Login — Dashboard
    </a>
</div>

<!-- ══════════════ HERO ══════════════ -->
<section class="hero" id="hero">
    <div class="hero-ring"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-eyebrow reveal">Greenhost Boutique Hotel · Yogyakarta</div>
            <h1 class="hero-title reveal" style="transition-delay:.1s">
                Temukan <em>Kedamaian</em><br>
                di Setiap <span class="gold-word">Sentuhan</span>
            </h1>
            <p class="hero-desc reveal" style="transition-delay:.2s">
                Rasakan pengalaman relaksasi holistik di jantung Yogyakarta. Terapis profesional, bahan alami pilihan, dan delapan ruangan eksklusif siap menyambut Anda.
            </p>
            <div class="hero-actions reveal" style="transition-delay:.3s">
                <a href="#reservation" class="btn-primary btn-large">
                    <i class="bi bi-calendar-heart"></i> Buat Reservasi
                </a>
                <a href="#services" class="btn-ghost btn-large">Lihat Layanan</a>
            </div>
            <p class="hero-note reveal" style="transition-delay:.35s">
                <i class="bi bi-check-circle-fill"></i>
                Reservasi mudah · Tanpa akun · Konfirmasi instan
            </p>
            <div class="hero-trust reveal" style="transition-delay:.45s">
                <div class="trust-item">
                    <div class="trust-num">8</div>
                    <div class="trust-label">Ruangan Privat</div>
                </div>
                <div class="trust-item">
                    <div class="trust-num">8+</div>
                    <div class="trust-label">Jenis Layanan</div>
                </div>
                <div class="trust-item">
                    <div class="trust-num">60-90</div>
                    <div class="trust-label">Menit per Sesi</div>
                </div>
                <div class="trust-item">
                    <div class="trust-num">100%</div>
                    <div class="trust-label">Bahan Alami</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════ MARQUEE ══════════════ -->
<div class="marquee-strip">
    <div class="marquee-track">
        <?php
        $items = ['Body Treatment','Aromatherapy Massage','Hot Stone Massage','Body Therapy','Deep Tissue','Facial Treatment','Scrub & Wrap','Relaksasi Total'];
        $all   = array_merge($items,$items,$items,$items);
        foreach ($all as $item): ?>
        <span class="marquee-item"><span class="marquee-dot"></span><?= $item ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══════════════ SERVICES ══════════════ -->
<section class="section" id="services">
    <div class="container">
        <div class="reveal">
            <div class="section-label">Pilihan Layanan</div>
            <h2 class="section-title">Terapi untuk <em>Jiwa & Raga</em></h2>
            <p class="section-sub">Setiap layanan dirancang oleh terapis bersertifikasi dengan menggunakan bahan organik pilihan untuk pengalaman relaksasi terbaik.</p>
        </div>
        <div class="services-grid">
            <?php
            $icons = ['bi-flower1','bi-wind','bi-water','bi-gem','bi-sun','bi-leaf','bi-heart','bi-stars'];
            foreach ($services as $i => $s):
            $icon = $icons[$i % count($icons)];
            ?>
            <div class="service-card reveal" style="transition-delay:<?= $i * .07 ?>s">
                <div class="service-icon"><i class="bi <?= $icon ?>"></i></div>
                <div class="service-name"><?= sanitize($s['name']) ?></div>
                <div class="service-desc"><?= sanitize($s['description'] ?: 'Layanan spa premium dengan bahan alami pilihan untuk relaksasi optimal.') ?></div>
                <div class="service-meta">
                    <span class="service-badge"><i class="bi bi-clock"></i> <?= $s['duration'] ?> mnt</span>
                    <span class="service-price"><?= formatRupiah($s['price']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$services): ?>
            <div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:3rem">
                <i class="bi bi-stars" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                Layanan akan segera tersedia.
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══════════════ RESERVATION ══════════════ -->
<section class="section reservation-section" id="reservation">
    <div class="container">
        <div class="res-grid">
            <!-- Left: intro -->
            <div class="res-intro reveal-left">
                <div class="section-label">Reservasi Mudah</div>
                <h2 class="section-title">Pesan <em>Sekarang</em>,<br>Relaksasi Segera</h2>
                <p class="section-sub">Isi form di samping untuk memesan jadwal Anda. Tidak perlu akun atau login — cukup nama, nomor telepon, dan pilih waktu favorit Anda.</p>
                <div class="res-features">
                    <div class="res-feature">
                        <div class="res-feature-icon"><i class="bi bi-shield-check"></i></div>
                        <div><strong style="color:rgba(255,255,255,.9)">Validasi Otomatis</strong><br>Sistem langsung mengecek ketersediaan ruangan & terapis secara real-time.</div>
                    </div>
                    <div class="res-feature">
                        <div class="res-feature-icon"><i class="bi bi-person-lines-fill"></i></div>
                        <div><strong style="color:rgba(255,255,255,.9)">Ruangan Otomatis</strong><br>Sistem akan memilihkan ruangan yang tersedia secara otomatis untuk Anda.</div>
                    </div>
                    <div class="res-feature">
                        <div class="res-feature-icon"><i class="bi bi-person-badge"></i></div>
                        <div><strong style="color:rgba(255,255,255,.9)">Terapis Pilihan Manajer</strong><br>Terapis terbaik yang tersedia akan ditugaskan oleh manajer kami untuk Anda.</div>
                    </div>
                    <div class="res-feature">
                        <div class="res-feature-icon"><i class="bi bi-clock-history"></i></div>
                        <div><strong style="color:rgba(255,255,255,.9)">Fleksibel Waktu</strong><br>Tersedia setiap hari. Pilih waktu yang paling nyaman untuk Anda.</div>
                    </div>
                    <div class="res-feature">
                        <div class="res-feature-icon"><i class="bi bi-cash-coin"></i></div>
                        <div><strong style="color:rgba(255,255,255,.9)">Bayar di Tempat</strong><br>Tidak ada pembayaran di muka. Bayar tunai atau transfer setelah layanan.</div>
                    </div>
                </div>
            </div>

            <!-- Right: form -->
            <div class="reveal-right">
                <div class="res-form-card">

                    <!-- SUCCESS STATE — tampil setelah reservasi berhasil -->
                    <div class="success-state" id="successState" style="<?= $success ? '' : 'display:none' ?>">
                        <i class="bi bi-check-circle-fill"></i>
                        <h3>Reservasi Berhasil!</h3>
                        <p>Terima kasih, <strong id="sName"><?= sanitize($formData['customerName'] ?? '') ?></strong>. Kami akan mempersiapkan layanan terbaik untuk Anda.</p>
                        <div class="ticket">
                            <div class="ticket-row"><span>Layanan</span><span id="sService"><?= sanitize($formData['service_name'] ?? '') ?></span></div>
                            <div class="ticket-row"><span>Tanggal</span><span id="sDate"><?= $success ? formatDate($formData['resDate']) : '' ?></span></div>
                            <div class="ticket-row"><span>Waktu</span><span id="sTime"><?= $success ? formatTime($formData['resTime']).' – '.formatTime($formData['endTime']) : '' ?></span></div>
                            <div class="ticket-row"><span>Ruangan</span><span id="sRoom"><?= $success ? 'R'.sanitize($formData['room']).' (otomatis)' : '' ?></span></div>
                            <div class="ticket-row"><span>Terapis</span><span>Akan ditentukan oleh manajer</span></div>
                            <div class="ticket-row"><span>Tarif</span><span id="sPrice"><?= $success ? formatRupiah($formData['price']) : '' ?></span></div>
                        </div>
                        <button onclick="resetForm()" class="btn-reset">
                            <i class="bi bi-plus-circle"></i> Buat Reservasi Lagi
                        </button>
                    </div>

                    <!-- FORM STATE — selalu ada di DOM, disembunyikan saat sukses -->
                    <div id="formState" style="<?= $success ? 'display:none' : '' ?>">
                        <h3><i class="bi bi-calendar-heart" style="color:var(--tea);font-size:1.2rem;margin-right:.5rem"></i>Form Reservasi</h3>

                        <div class="conflict-box" id="conflictBox">
                            <i class="bi bi-exclamation-triangle" style="flex-shrink:0;margin-top:.1rem"></i>
                            <div id="conflictMsg"></div>
                        </div>

                        <form method="POST" id="reserveForm">
                            <input type="hidden" name="_action" value="reserve">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nama Lengkap *</label>
                                    <input type="text" name="customer_name" id="inputName" class="form-control <?= isset($errors['customer_name'])?'is-invalid':'' ?>"
                                           value="<?= sanitize($formData['customer_name'] ?? '') ?>" placeholder="Nama Anda" required>
                                    <?php if (isset($errors['customer_name'])): ?><div class="invalid-msg"><?= $errors['customer_name'] ?></div><?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">No. Telepon / WA *</label>
                                    <input type="tel" name="phone_number" id="inputPhone" class="form-control <?= isset($errors['phone_number'])?'is-invalid':'' ?>"
                                           value="<?= sanitize($formData['phone_number'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                                    <?php if (isset($errors['phone_number'])): ?><div class="invalid-msg"><?= $errors['phone_number'] ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pilih Layanan *</label>
                                <select name="service_id" id="serviceId" class="form-select <?= isset($errors['service_id'])?'is-invalid':'' ?>" onchange="checkConflict()" required>
                                    <option value="">— Pilih Layanan —</option>
                                    <?php foreach ($services as $s): ?>
                                    <option value="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>" data-price="<?= $s['price'] ?>"
                                        <?= ($formData['service_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($s['name']) ?> · <?= $s['duration'] ?> mnt · <?= formatRupiah($s['price']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['service_id'])): ?><div class="invalid-msg"><?= $errors['service_id'] ?></div><?php endif; ?>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Tanggal *</label>
                                    <input type="date" name="reservation_date" id="resDate" class="form-control <?= isset($errors['reservation_date'])?'is-invalid':'' ?>"
                                           value="<?= sanitize($formData['reservation_date'] ?? date('Y-m-d')) ?>"
                                           min="<?= date('Y-m-d') ?>" onchange="checkConflict()" required>
                                    <?php if (isset($errors['reservation_date'])): ?><div class="invalid-msg"><?= $errors['reservation_date'] ?></div><?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Waktu Mulai *</label>
                                    <input type="time" name="reservation_time" id="resTime" class="form-control <?= isset($errors['reservation_time'])?'is-invalid':'' ?>"
                                           value="<?= sanitize($formData['reservation_time'] ?? '09:00') ?>"
                                           onchange="checkConflict()" required>
                                    <?php if (isset($errors['reservation_time'])): ?><div class="invalid-msg"><?= $errors['reservation_time'] ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div id="endTimeBadge" style="display:none;margin-bottom:.85rem;padding:.5rem .85rem;background:var(--tea-pale);border-radius:8px;font-size:.8rem;color:var(--tea-dark);align-items:center;gap:.4rem">
                                <i class="bi bi-clock-history"></i>
                                Estimasi selesai: <strong id="endTimeVal"></strong>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Catatan Khusus</label>
                                <textarea name="notes" id="inputNotes" class="form-control" rows="2" placeholder="Alergi, preferensi teknik, dll..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary btn-large" style="width:100%;justify-content:center;margin-top:.5rem">
                                <i class="bi bi-calendar-check"></i> Konfirmasi Reservasi
                            </button>
                            <p style="text-align:center;font-size:.73rem;color:var(--muted);margin-top:.75rem">
                                <i class="bi bi-lock" style="color:var(--gold)"></i>
                                Data Anda aman. Pembayaran dilakukan di tempat.
                            </p>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════ WHY US ══════════════ -->
<section class="section why-section" id="fasilitas">
    <div class="container">
        <div class="reveal" style="text-align:center;max-width:520px;margin:0 auto 0">
            <div class="section-label">Mengapa Tea Spa</div>
            <h2 class="section-title">Standar <em>Premium</em>,<br>Harga Terjangkau</h2>
        </div>
        <div class="why-grid">
            <?php
            $whys = [
                ['bi-award','Terapis Bersertifikasi','Semua terapis kami memiliki sertifikasi resmi dan pengalaman bertahun-tahun dalam berbagai teknik pijat & terapi.'],
                ['bi-leaf','100% Bahan Alami','Kami hanya menggunakan minyak esensial organik, scrub kopi, dan bahan alami pilihan yang aman untuk kulit.'],
                ['bi-house-heart','8 Ruangan Privat','Setiap sesi berlangsung di ruangan privat ber-AC yang bersih, nyaman, dan dirancang untuk ketenangan total.'],
                ['bi-calendar-check','Fleksibel & Mudah','Reservasi online tanpa akun. Pilih layanan dan waktu sesuai keinginan Anda. Ruangan dan terapis disiapkan oleh tim kami.'],
                ['bi-cash-coin','Bayar Setelah Layanan','Tidak ada DP atau pembayaran di muka. Bayar di tempat setelah puas dengan layanan kami.'],
                ['bi-geo-alt','Lokasi Strategis','Berlokasi di Greenhost Boutique Hotel, mudah diakses di pusat kota Yogyakarta.'],
                ['bi-shield-check','Higienitas Terjaga','Seluruh peralatan disterilisasi setiap sesi. Handuk dan linen selalu bersih dan segar.'],
                ['bi-star','Pengalaman Premium','Didesain untuk memberikan pengalaman wellness terbaik dalam suasana yang tenang dan mewah.'],
            ];
            foreach ($whys as $i => [$icon,$title,$desc]): ?>
            <div class="why-card reveal" style="transition-delay:<?= $i*.06 ?>s">
                <div class="why-icon"><i class="bi <?= $icon ?>"></i></div>
                <div class="why-title"><?= $title ?></div>
                <div class="why-desc"><?= $desc ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════ STAFF LOGIN ══════════════ -->
<section class="section staff-section" id="kontak">
    <div class="container">
        <div class="staff-card reveal">
            <div class="staff-icon"><i class="bi bi-grid-1x2"></i></div>
            <h2 class="staff-title">Akses Internal Staf</h2>
            <p class="staff-sub">Login ke sistem manajemen Tea Spa untuk mengelola reservasi, pembayaran, stok, dan laporan keuangan.</p>
            <div class="role-pills">
                <?php foreach (['Manager','Terapis','Kasir','Purchasing','Accounting'] as $r): ?>
                <span class="role-pill"><?= $r ?></span>
                <?php endforeach; ?>
            </div>
            <a href="<?= url('login.php') ?>" class="btn-primary btn-large" style="width:100%;justify-content:center">
                <i class="bi bi-box-arrow-in-right"></i> Masuk ke Dashboard
            </a>
            <p style="margin-top:1rem;font-size:.75rem;color:var(--muted)">
                Hanya untuk staf Tea Spa yang terdaftar.
                Hubungi manager untuk akun baru.
            </p>
        </div>
    </div>
</section>

<!-- ══════════════ FOOTER ══════════════ -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <div class="footer-logo"><i class="bi bi-flower1"></i></div>
                    <div>
                        <div class="footer-brand-name">Tea Spa</div>
                        <div class="footer-brand-sub">Greenhost Boutique Hotel</div>
                    </div>
                </div>
                <p class="footer-desc">Spa wellness premium di jantung Yogyakarta. Menghadirkan ketenangan, keindahan, dan pemulihan melalui sentuhan terapis profesional dan bahan alami terbaik.</p>
                <div class="footer-contact">
                    <div><i class="bi bi-geo-alt-fill"></i> Greenhost Boutique Hotel, Yogyakarta</div>
                    <div><i class="bi bi-clock-fill"></i> Buka setiap hari, 09.00 – 21.00 WIB</div>
                    <div><i class="bi bi-whatsapp"></i> Hubungi via WhatsApp</div>
                </div>
            </div>
            <div>
                <div class="footer-heading">Layanan</div>
                <ul class="footer-links">
                    <li><a href="#services">Body Treatment</a></li>
                    <li><a href="#services">Body Therapy</a></li>
                    <li><a href="#services">Aromatherapy Massage</a></li>
                    <li><a href="#services">Hot Stone Massage</a></li>
                    <li><a href="#services">Facial Treatment</a></li>
                    <li><a href="#services">Scrub & Wrap</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-heading">Sistem</div>
                <ul class="footer-links">
                    <li><a href="#reservation">Buat Reservasi</a></li>
                    <li><a href="<?= url('login.php') ?>">Staff Login</a></li>
                </ul>
                <div class="footer-heading" style="margin-top:1.5rem">Informasi</div>
                <ul class="footer-links">
                    <li><a href="#fasilitas">Fasilitas</a></li>
                    <li><a href="#kontak">Kontak</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> Tea Spa · Greenhost Boutique Hotel, Yogyakarta</span>
            <span>Dibuat dengan <i class="bi bi-heart-fill" style="color:var(--gold)"></i> untuk kenyamanan tamu</span>
        </div>
    </div>
</footer>

<!-- Floating Reservation Button -->
<div class="float-btn" id="floatBtn">
    <span class="float-badge"><i class="bi bi-calendar-plus"></i> Reservasi Sekarang</span>
    <a href="#reservation" class="float-main"><i class="bi bi-calendar-heart"></i></a>
</div>

<script>
// ── Scroll nav styling ────────────────────────────────────
const nav   = document.getElementById('mainNav');
const float = document.getElementById('floatBtn');

window.addEventListener('scroll', () => {
    const scrolled = window.scrollY > 60;
    nav.classList.toggle('scrolled', scrolled);

    // Hide float btn when near reservation section
    const resSection = document.getElementById('reservation');
    if (resSection) {
        const rect = resSection.getBoundingClientRect();
        const nearRes = rect.top < window.innerHeight && rect.bottom > 0;
        float.style.opacity = nearRes ? '0' : '1';
        float.style.pointerEvents = nearRes ? 'none' : 'auto';
    }
});

// ── Scroll reveal ─────────────────────────────────────────
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => observer.observe(el));

// ── Mobile menu ───────────────────────────────────────────
function toggleMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
}

// ── Conflict check ────────────────────────────────────────
function checkConflict() {
    const date      = document.getElementById('resDate')?.value;
    const time      = document.getElementById('resTime')?.value;
    const serviceId = document.getElementById('serviceId')?.value;
    const box       = document.getElementById('conflictBox');
    const msg       = document.getElementById('conflictMsg');
    const endBadge  = document.getElementById('endTimeBadge');
    const endVal    = document.getElementById('endTimeVal');

    if (!date || !time || !serviceId) return;

    const params = new URLSearchParams({
        check_conflict: 1, date, time, service_id: serviceId
    });

    fetch('?' + params)
        .then(r => r.json())
        .then(data => {
            if (data.end_time && endBadge && endVal) {
                endVal.textContent = data.end_time;
                endBadge.style.display = 'flex';
            }
            if (data.conflicts && data.conflicts.length > 0) {
                msg.innerHTML = '<strong>Jadwal Bentrok:</strong><br>' + data.conflicts.map(c => '• ' + c).join('<br>');
                box.classList.add('show');
            } else {
                box.classList.remove('show');
            }
        }).catch(() => {});
}

function resetForm() {
    // Sembunyikan success, tampilkan form
    document.getElementById('successState').style.display = 'none';
    document.getElementById('formState').style.display    = '';

    // Reset semua field ke kosong / default
    document.getElementById('reserveForm').reset();
    document.getElementById('inputName').value  = '';
    document.getElementById('inputPhone').value = '';
    document.getElementById('serviceId').value  = '';
    document.getElementById('resDate').value    = '<?= date('Y-m-d') ?>';
    document.getElementById('resTime').value    = '09:00';
    document.getElementById('inputNotes').value = '';

    // Sembunyikan estimasi & conflict
    document.getElementById('endTimeBadge').style.display = 'none';
    document.getElementById('conflictBox').classList.remove('show');

    // Scroll ke form
    document.getElementById('formState').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Init check on load if values exist
document.addEventListener('DOMContentLoaded', () => {
    const serviceId = document.getElementById('serviceId');
    if (serviceId && serviceId.value) checkConflict();
});
</script>

</body>
</html>