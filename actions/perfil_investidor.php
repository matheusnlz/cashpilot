<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();
validarCsrf();

$uid = (int) usuarioLogadoId();
$modo = (string) ($_POST['modo'] ?? 'descobrir');
$objetivo = trim((string) ($_POST['objetivo'] ?? 'patrimonio'));
$permitidos = ['reserva', 'viagem', 'casa', 'aposentadoria', 'patrimonio', 'outro'];

if (!in_array($objetivo, $permitidos, true)) {
    http_response_code(422);
    exit('Objetivo inválido.');
}

if ($modo === 'direto') {
    $perfil = (string) ($_POST['perfil_direto'] ?? '');
    if (!in_array($perfil, ['conservador', 'moderado', 'arrojado'], true)) {
        http_response_code(422);
        exit('Perfil inválido.');
    }
    $prazo = 'medio';
    $risco = $perfil === 'conservador' ? 1 : ($perfil === 'moderado' ? 2 : 3);
    $reserva = 0;
} else {
    $prazo = (string) ($_POST['prazo'] ?? 'medio');
    $risco = (int) ($_POST['risco'] ?? 2);
    $reserva = !empty($_POST['reserva_emergencia']) ? 1 : 0;
    if (!in_array($prazo, ['curto', 'medio', 'longo'], true) || $risco < 1 || $risco > 3) {
        http_response_code(422);
        exit('Dados inválidos.');
    }
    $pontos = $risco + ($prazo === 'longo' ? 1 : 0) + ($reserva ? 1 : 0);
    $perfil = $pontos <= 2 ? 'conservador' : ($pontos <= 4 ? 'moderado' : 'arrojado');
}

$pdo = conectar();
$stmt = $pdo->prepare(
    'INSERT INTO
            perfil_investidor (
                usuario_id,
                objetivo,
                prazo,
                risco,
                reserva_emergencia,
                perfil,
                atualizado_em
            )
        VALUES
            (:u, :o, :p, :r, :re, :pf, NOW())
        ON DUPLICATE KEY UPDATE
            objetivo = VALUES(objetivo),
            prazo = VALUES(prazo),
            risco = VALUES(risco),
            reserva_emergencia = VALUES(reserva_emergencia),
            perfil = VALUES(perfil),
            atualizado_em = NOW()'
);
$stmt->execute([
    'u' => $uid,
    'o' => $objetivo,
    'p' => $prazo,
    'r' => $risco,
    're' => $reserva,
    'pf' => $perfil
]);
$_SESSION['mensagem_investimentos'] = 'Perfil de investidor definido: ' . ucfirst($perfil) . '.';
header('Location: ../pages/investimentos.php');
exit();
