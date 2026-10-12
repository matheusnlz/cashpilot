<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
$pdo = conectar();
$uid = (int) usuarioLogadoId();
$tipo = usuarioLogadoTipo();
$tituloPagina = 'Notificações';
$itens = [];
$mes = date('Y-m');
try {
    $s = $pdo->prepare(
        'SELECT
                COALESCE(SUM(valor), 0)
            FROM
                despesas
            WHERE
                usuario_id = :u
                AND DATE_FORMAT(data_despesa, "%Y-%m") = :m'
    );
    $s->execute(['u' => $uid, 'm' => $mes]);
    $g = (float) $s->fetchColumn();
    $s = $pdo->prepare('SELECT limite_gastos_mensal FROM usuarios WHERE id=:u');
    $s->execute(['u' => $uid]);
    $lim = (float) $s->fetchColumn();
    if ($lim > 0 && $g / $lim >= 0.8) {
        $itens[] = [
            'tipo' => 'aviso',
            'titulo' => 'Limite mensal em atenção',
            'texto' =>
                'Você já utilizou ' .
                number_format(min(100, ($g / $lim) * 100), 0, ',', '.') .
                '% do limite mensal.'
        ];
    }
    $s = $pdo->prepare(
        'SELECT
                titulo,
                valor_meta,
                valor_atual
            FROM
                metas
            WHERE
                usuario_id = :u
                AND concluida = 0
            ORDER BY
                valor_atual / NULLIF(valor_meta, 0) DESC
            LIMIT
                3'
    );
    $s->execute(['u' => $uid]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if (
            (float) $m['valor_meta'] > 0 &&
            (float) $m['valor_atual'] / (float) $m['valor_meta'] >= 0.8
        ) {
            $itens[] = [
                'tipo' => 'sucesso',
                'titulo' => 'Meta perto da conclusão',
                'texto' =>
                    $m['titulo'] .
                    ' está em ' .
                    number_format(
                        ((float) $m['valor_atual'] / (float) $m['valor_meta']) * 100,
                        0,
                        ',',
                        '.'
                    ) .
                    '%.'
            ];
        }
    }
} catch (Throwable $e) {
    error_log('CashPilot15/Notificacoes: ' . $e->getMessage());
}
if (!$itens) {
    $itens[] = [
        'tipo' => 'neutro',
        'titulo' => 'Tudo em ordem',
        'texto' => 'Nenhum alerta financeiro importante foi identificado agora.'
    ];
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="page-head">
    <div><span class="eyebrow">RADAR PESSOAL</span>
        <h1>Notificações</h1>
        <p>Alertas úteis, sem transformar o CashPilot em uma caixa de mensagens.</p>
    </div>
</div>
<section class="cp15-notification-list">

    <?php foreach ($itens as $item): ?>

    <article class="surface-card cp15-notification <?= limpar($item['tipo']) ?>">
        <span><?= cpIcon('bell') ?></span>
        <div><strong><?= limpar($item['titulo']) ?></strong>
            <p><?= limpar($item['texto']) ?></p>
        </div>
    </article>

    <?php endforeach; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
