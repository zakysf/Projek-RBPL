<?php
require_once __DIR__ . '/../config/app.php';
$id = (int)($_GET['id'] ?? 0);
header('Location: ' . BASE_URL . '/reservations/create.php?id=' . $id);
exit;
