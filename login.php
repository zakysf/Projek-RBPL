<?php
// ============================================================
// login.php - Staff Login
// Sprint 1: PBI-002
// ============================================================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

sessionStart();

// Already logged in? Redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(post('username'));
    $password = post('password');

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'       => $user['id'],
                'name'     => $user['name'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ];
            session_regenerate_id(true);
            redirect('dashboard.php');
        } else {
            $error = 'Username atau password salah.';
        }
    } else {
        $error = 'Username dan password wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Tea Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --tea:       #5a7c5a;
            --tea-dark:  #3d5c3d;
            --tea-light: #e8f0e8;
            --cream:     #faf8f3;
            --gold:      #c9a96e;
            --text:      #2c2c2c;
            --muted:     #8a8a8a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: var(--cream);
            font-family: 'Jost', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image:
                radial-gradient(ellipse at 20% 50%, rgba(90,124,90,.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(201,169,110,.06) 0%, transparent 50%);
        }
        .login-wrap {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
        }
        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .brand-logo {
            width: 72px; height: 72px;
            background: var(--tea);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(90,124,90,.25);
        }
        .brand-logo i { font-size: 2rem; color: #fff; }
        .brand h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--tea-dark);
            letter-spacing: 0.04em;
        }
        .brand p {
            font-size: .8rem;
            color: var(--muted);
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-top: .25rem;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 40px rgba(0,0,0,.06), 0 1px 4px rgba(0,0,0,.04);
            border: 1px solid rgba(90,124,90,.12);
        }
        .card h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            color: var(--text);
            margin-bottom: 1.75rem;
            font-weight: 400;
        }
        .form-label {
            font-size: .78rem;
            font-weight: 500;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: .4rem;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: .75rem 1rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
            font-size: .95rem;
            color: var(--text);
            background: var(--cream);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--tea);
            box-shadow: 0 0 0 3px rgba(90,124,90,.12);
            background: #fff;
        }
        .input-group { position: relative; }
        .input-icon {
            position: absolute; left: .9rem; top: 50%;
            transform: translateY(-50%);
            color: var(--muted); font-size: 1rem;
        }
        .input-group .form-control { padding-left: 2.5rem; }
        .mb-4 { margin-bottom: 1.25rem; }
        .btn-primary {
            width: 100%;
            padding: .85rem;
            background: var(--tea);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
            font-size: .95rem;
            font-weight: 500;
            letter-spacing: .05em;
            cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: .5rem;
        }
        .btn-primary:hover { background: var(--tea-dark); transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .88rem;
            margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .demo-box {
            margin-top: 1.5rem;
            background: var(--tea-light);
            border-radius: 10px;
            padding: 1rem 1.2rem;
        }
        .demo-box p { font-size: .78rem; color: var(--tea-dark); font-weight: 500; margin-bottom: .5rem; }
        .demo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem; }
        .demo-grid span { font-size: .75rem; color: var(--muted); }
        .demo-grid b { color: var(--tea-dark); }
        footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .75rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="brand">
        <div class="brand-logo"><i class="bi bi-flower1"></i></div>
        <h1>Tea Spa</h1>
        <p>Reservation & Operational Management</p>
    </div>

    <div class="card">
        <h2>Selamat datang kembali</h2>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i> <?= sanitize($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="username" class="form-control"
                           value="<?= sanitize(post('username')) ?>"
                           placeholder="Masukkan username" autocomplete="username" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control"
                           placeholder="Masukkan password" autocomplete="current-password" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Masuk
            </button>
        </form>

        <div class="demo-box">
            <p>🔑 Demo Credentials (password: <b>password</b>)</p>
            <div class="demo-grid">
                <span>Manager:</span>     <span><b>manager</b></span>
                <span>Terapis:</span>     <span><b>therapist</b></span>
                <span>Kasir:</span>       <span><b>cashier</b></span>
                <span>Purchasing:</span>  <span><b>purchasing</b></span>
                <span>Accounting:</span>  <span><b>accounting</b></span>
            </div>
        </div>
    </div>
    <footer>© <?= date('Y') ?> Tea Spa — Greenhost Boutique Hotel, Yogyakarta</footer>
</div>
</body>
</html>
