<?php
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../database/conexao.php';require_once __DIR__.'/../includes/dashboard_helpers.php';exigirLogin();
$tituloPagina='Dashboard';$pdo=conectar();$usuarioId=usuarioLogadoId();
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/navbar.php';
if(usuarioLogadoTipo()==='mei') require __DIR__.'/../includes/dashboard_empreendedor.php'; else require __DIR__.'/../includes/dashboard_pf.php';
require_once __DIR__.'/../includes/footer.php';
