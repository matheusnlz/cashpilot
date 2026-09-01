<?php
require_once __DIR__ . '/includes/auth.php';

exigirLogin();
header('Location: pages/perfil.php');
exit;
