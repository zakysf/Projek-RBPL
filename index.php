<?php
// index.php — Entry point → Public Landing Page
require_once __DIR__ . '/config/app.php';
sessionStart();
// Staff already logged in? Go straight to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}
// Everyone else sees the public landing page
redirect('landing.php');