<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/aprender_helper.php';
$pdo = conectar();
exigirAdminCashPilot($pdo);
$uid = (int) usuarioLogadoId();
$tituloPagina = 'Administração';
$checks = [
    'Banco de dados' => true,
    'Python' => is_file(__DIR__ . '/../python/insights.py'),
    'Copiloto / Groq' => (string) getenv('GROQ_API_KEY') !== '',
    'E-mail / Brevo' => (string) getenv('BREVO_API_KEY') !== '',
    'Google OAuth' => (string) getenv('GOOGLE_CLIENT_ID') !== ''
];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="page-head">
    <div><span class="eyebrow">ADMINISTRAÇÃO</span>
        <h1>Central do sistema</h1>
        <p>Ferramentas de teste e diagnóstico sem expor credenciais.</p>
    </div>
</div>
<section class="cp15-admin-grid">
    <article class="surface-card cp15-admin-card"><span class="eyebrow">MODO DE TESTE</span>
        <h2>Visualizar como PF ou MEI</h2>
        <p>O perfil real da conta não é alterado no banco.</p>
        <form action="../actions/admin_modo_perfil.php" method="POST" class="cp15-mode-buttons">
            <?= csrfCampo() ?>
             <button class="btn btn-secundario" name="modo" value="pessoa_fisica">Pessoa
                Física</button>
            <button class="btn btn-secundario" name="modo" value="mei">Empreendedor</button>
            <button class="btn btn-primario" name="modo" value="real">Perfil real</button>
        </form>
        <small>Ativo: <?= limpar(adminModoPerfilAtivo() ?? 'perfil real') ?></small>
    </article>
    <article class="surface-card cp15-admin-card"><span class="eyebrow">SAÚDE DO SISTEMA</span>
        <h2>Integrações</h2>
        <div class="cp15-health-list">

            <?php foreach ($checks as $nome => $ok): ?>

            <div><span><?= limpar($nome) ?></span><strong class="<?= $ok ? 'ok' : 'warn' ?>"><?= $ok
            ? 'Disponível'
            : 'Não configurado' ?></strong></div>

            <?php endforeach; ?>


            <div><span>Sistema</span><strong>CashPilot</strong></div>
        </div>
    </article>
    <article class="surface-card cp15-admin-card"><span class="eyebrow">CONTEÚDO</span>
        <h2>Aprender</h2>
        <p>Gerencie trilhas, vídeos, público, tags e associação de conteúdos.</p><a
            class="btn btn-secundario" href="aprender_gerenciar.php">Gerenciar Aprender</a>
    </article>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
