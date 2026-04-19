<?php
// services/edit.php — redirects to create.php with id param
require_once __DIR__ . '/../config/app.php';
$id = (int)($_GET['id'] ?? 0);
header('Location: ' . BASE_URL . '/services/create.php?id=' . $id);
exit;
