<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/privacidade17.php';
exigirLogin();
exigirPost();
validarCsrf();

try {
    $dados = cp17ExportarDados(conectar(), (int) usuarioLogadoId());
    $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="CashPilot_meus_dados.json"');
    header('Cache-Control: private, no-store');
    echo $json;
} catch (Throwable $erro) {
    cp17Log('ExportacaoDados', $erro);
    cp17Interromper(
        503,
        'Não foi possível exportar os dados. Tente novamente ou procure o responsável.'
    );
}
