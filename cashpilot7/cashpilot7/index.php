<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit;
