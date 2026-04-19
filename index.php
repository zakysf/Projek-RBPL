<?php
// index.php — Entry point
require_once __DIR__ . '/config/app.php';
sessionStart();
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
