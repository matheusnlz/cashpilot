<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/dashboard_helpers.php';
require_once __DIR__ . '/../includes/onboarding_checklist.php';
exigirLogin();
$tituloPagina = 'Dashboard';
$pdo = conectar();
$usuarioId = usuarioLogadoId();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
$checklistInicio = cp168Checklist($pdo, (int) $usuarioId, usuarioLogadoTipo());
$checklistConcluido = count(array_filter(
    $checklistInicio,
    fn(array $item): bool => $item['concluido']
)) === count($checklistInicio);

if (!$checklistConcluido && !cp168ChecklistDispensado($pdo, (int) $usuarioId)) {
    cp168RenderChecklist($checklistInicio);
}
if (usuarioLogadoTipo() === 'mei') {
    require __DIR__ . '/../includes/dashboard_empreendedor.php';
} else {
    require __DIR__ . '/../includes/dashboard_pf.php';
}
require_once __DIR__ . '/../includes/footer.php';
