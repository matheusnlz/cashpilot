<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/ia.php';

exigirLogin();

$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();

$stmt = $pdo->prepare(
    'SELECT is_admin
     FROM usuarios
     WHERE id = :uid
     LIMIT 1'
);

$stmt->execute([
    'uid' => $usuarioId,
]);

if (!(bool) $stmt->fetchColumn()) {
    http_response_code(403);
    exit('Acesso restrito ao administrador.');
}

$tituloPagina = 'Status do Copiloto';
$config = configuracaoIA();
$teste = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    $mensagensTeste = [
        [
            'role' => 'system',
            'content' => 'Você está executando um teste técnico do CashPilot. Responda somente com: OK',
        ],
        [
            'role' => 'user',
            'content' => 'Teste de conexão do Copiloto.',
        ],
    ];

    $teste = enviarParaIADetalhado($mensagensTeste);
}

function cpStatusHttpDescricao(?int $status): string
{
    return match ($status) {
        400 => 'Requisição inválida ou modelo incompatível.',
        401 => 'Chave inválida ou não autorizada.',
        403 => 'Acesso ao recurso/modelo não permitido.',
        404 => 'Modelo ou endpoint não encontrado.',
        408 => 'Tempo de requisição excedido.',
        429 => 'Limite temporário de uso atingido.',
        500, 502, 503, 504 => 'Instabilidade temporária no serviço da Groq.',
        null, 0 => 'Sem resposta HTTP.',
        default => 'Resposta HTTP ' . $status . '.',
    };
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <a href="perfil.php" class="back-link">
            ← Voltar para Configurações
        </a>

        <span class="eyebrow">DIAGNÓSTICO ADMINISTRATIVO</span>
        <h1>Status do Copiloto</h1>
        <p>
            Verifique a conexão com a Groq e os modelos configurados sem
            expor a chave de API.
        </p>
    </div>
</div>

<div class="status-ai-grid">
    <section class="surface-card">
        <span class="eyebrow">CONFIGURAÇÃO</span>
        <h2>
            <?= chaveIAConfigurada()
                ? 'Configuração encontrada'
                : 'Chave não encontrada' ?>
        </h2>

        <div class="status-list">
            <div>
                <span>Provider</span>
                <strong><?= limpar((string) ($config['provider'] ?? 'groq')) ?></strong>
            </div>

            <div>
                <span>Modelo principal</span>
                <strong><?= limpar((string) ($config['model'] ?? '')) ?></strong>
            </div>

            <div>
                <span>Modelo de reserva</span>
                <strong><?= limpar((string) ($config['fallback_model'] ?? '')) ?></strong>
            </div>

            <div>
                <span>cURL PHP</span>
                <strong><?= function_exists('curl_init') ? 'Disponível' : 'Indisponível' ?></strong>
            </div>

            <div>
                <span>Chave no processo PHP</span>
                <strong><?= chaveIAConfigurada() ? 'Sim' : 'Não' ?></strong>
            </div>
        </div>
    </section>

    <section class="surface-card">
        <span class="eyebrow">TESTE REAL</span>
        <h2>Consultar o Copiloto</h2>

        <p class="secao-ajuda">
            O teste envia uma mensagem mínima. Se o modelo principal falhar,
            o CashPilot tenta automaticamente o modelo de reserva.
        </p>

        <form method="POST">
            <?= csrfCampo() ?>

            <button class="btn btn-primario">
                Testar Copiloto
            </button>
        </form>

        <?php if ($teste): ?>
            <div class="ai-test-result <?= $teste['ok'] ? 'ok' : 'fail' ?>">
                <strong>
                    <?= $teste['ok']
                        ? 'Conexão funcionando'
                        : 'Falha na conexão' ?>
                </strong>

                <?php if ($teste['ok']): ?>
                    <span>
                        Modelo utilizado:
                        <?= limpar((string) $teste['model']) ?>
                        <?= !empty($teste['used_fallback']) ? ' · fallback utilizado' : '' ?>
                    </span>

                    <?php
                    $detalheSucesso = !empty($teste['used_fallback'])
                        ? ($teste['fallback'] ?? [])
                        : ($teste['primary'] ?? []);
                    ?>

                    <span>
                        HTTP <?= (int) ($detalheSucesso['http_status'] ?? 0) ?>
                        · <?= number_format((float) ($detalheSucesso['elapsed'] ?? 0), 2, ',', '.') ?>s
                    </span>
                <?php else: ?>
                    <?php $principal = $teste['primary'] ?? []; ?>

                    <span>
                        Principal:
                        <?= limpar((string) ($principal['model'] ?? $config['model'] ?? '')) ?>
                    </span>

                    <span>
                        <?= limpar(
                            cpStatusHttpDescricao(
                                isset($principal['http_status'])
                                    ? (int) $principal['http_status']
                                    : null
                            )
                        ) ?>
                    </span>

                    <?php if (!cashpilotEhProducao() && !empty($principal['api_error'])): ?>
                        <span>
                            API: <?= limpar(mb_substr((string) $principal['api_error'], 0, 240)) ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!cashpilotEhProducao() && !empty($principal['curl_error'])): ?>
                        <span>
                            cURL: <?= limpar(mb_substr((string) $principal['curl_error'], 0, 240)) ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($teste['fallback'])): ?>
                        <?php $fallback = $teste['fallback']; ?>

                        <span>
                            Fallback:
                            <?= limpar((string) ($fallback['model'] ?? $config['fallback_model'] ?? '')) ?>
                            · <?= limpar(
                                cpStatusHttpDescricao(
                                    isset($fallback['http_status'])
                                        ? (int) $fallback['http_status']
                                        : null
                                )
                            ) ?>
                        </span>

                        <?php if (!cashpilotEhProducao() && !empty($fallback['api_error'])): ?>
                            <span>
                                API fallback:
                                <?= limpar(mb_substr((string) $fallback['api_error'], 0, 240)) ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="surface-card section-block">
    <h2>Como o CashPilot escolhe o modelo</h2>

    <p class="secao-ajuda">
        O modelo principal desta versão é <code>openai/gpt-oss-120b</code>.
        Se ele falhar por indisponibilidade, timeout, limite ou outro erro que
        permita nova tentativa, o sistema usa <code>openai/gpt-oss-20b</code>.
        Erros de autenticação (401/403) não repetem a requisição, pois o mesmo
        problema afetaria os dois modelos.
    </p>
</section>

<section class="surface-card section-block">
    <h2>Se a chave não aparecer</h2>

    <p class="secao-ajuda">
        No XAMPP, uma variável criada depois que o Apache foi iniciado pode não
        estar disponível ao PHP. Reinicie o Apache depois de configurar
        <code>GROQ_API_KEY</code>. Como alternativa apenas local, copie
        <code>includes/groq_config.example.php</code> para
        <code>includes/groq_config.php</code>. Esse arquivo continua ignorado
        pelo Git.
    </p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
