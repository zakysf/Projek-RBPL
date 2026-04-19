<?php
// logout.php - Sprint 1 PBI-004
require_once __DIR__ . '/config/app.php';
sessionStart();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
