<?php
require_once __DIR__ . '/../includes/desempenho.php';
cpPerfIniciar();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/dashboard_helpers.php';
require_once __DIR__ . '/../includes/onboarding_checklist.php';
exigirLogin();
$tituloPagina = 'Dashboard';
cpPerfEtapa('conexao_banco');
$pdo = conectar();
cpPerfEtapa('cabecalho');
$usuarioId = usuarioLogadoId();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
cpPerfEtapa('checklist');
$checklistInicio = cp168Checklist($pdo, (int) $usuarioId, usuarioLogadoTipo());
$checklistConcluido = count(array_filter(
    $checklistInicio,
    fn(array $item): bool => $item['concluido']
)) === count($checklistInicio);

if (!$checklistConcluido && !cp168ChecklistDispensado($pdo, (int) $usuarioId)) {
    cp168RenderChecklist($checklistInicio);
}
cpPerfEtapa('carregar_dashboard');
if (usuarioLogadoTipo() === 'mei') {
    require __DIR__ . '/../includes/dashboard_empreendedor.php';
} else {
    require __DIR__ . '/../includes/dashboard_pf.php';
}
cpPerfEtapa('rodape');
require_once __DIR__ . '/../includes/footer.php';
