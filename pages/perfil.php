<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$tituloPagina = 'Meu perfil';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();
$secao = $_GET['secao'] ?? 'geral';

$stmt = $pdo->prepare(
    'SELECT
        nome,
        username,
        username_alterado_em,
        email,
        email_verificado,
        email_verificado_em,
        email_pendente,
        telefone,
        tipo_perfil,
        nicho,
        data_cadastro,
        avatar_path,
        limite_gastos_mensal,
        is_admin,
        tema_preferido
     FROM usuarios
     WHERE id = :uid'
);
$stmt->execute(['uid' => $usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$_SESSION['usuario_avatar'] = $usuario['avatar_path'] ?? '';
$_SESSION['tema_preferido'] = $usuario['tema_preferido'] ?? 'light';
$_SESSION['email_verificado'] = (int) ($usuario['email_verificado'] ?? 1);

$mensagem = $_SESSION['mensagem_perfil'] ?? null;
unset($_SESSION['mensagem_perfil']);

$avatar = $usuario['avatar_path'] ?? '';
$iniciais = '';

foreach (preg_split('/\s+/', trim($usuario['nome'] ?? '')) as $parte) {
    if ($parte !== '') {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }

    if (mb_strlen($iniciais) >= 2) {
        break;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">CONFIGURAÇÕES DA CONTA</span>
        <h1>Meu perfil</h1>
        <p>
            Gerencie identidade, segurança, e-mail, aparência e dados da sua conta
            em um único lugar.
        </p>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="alerta-mensagem sucesso">
        <?= limpar($mensagem) ?>
    </div>
<?php endif; ?>

<section class="profile-identity-card surface-card">
    <div class="perfil-identidade">
        <?php if ($avatar): ?>
            <img
                class="avatar avatar-grande"
                src="<?= $avatar === '__db_avatar__'
                    ? '../actions/avatar.php'
                    : '../' . limpar($avatar) ?>"
                alt="Foto de perfil"
            >
        <?php else: ?>
            <span class="avatar avatar-grande avatar-iniciais">
                <?= limpar($iniciais ?: 'CP') ?>
            </span>
        <?php endif; ?>

        <div>
            <h2><?= limpar($usuario['nome'] ?? '') ?></h2>
            <p>
                @<?= limpar($usuario['username'] ?? 'usuario') ?>
                · <?= ($usuario['tipo_perfil'] ?? '') === 'mei'
                    ? 'Empreendedor'
                    : 'Pessoa física' ?>
                <?php if (!empty($usuario['nicho'])): ?>
                    · <?= limpar($usuario['nicho']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="profile-avatar-actions">
        <form
            action="../actions/perfil.php"
            method="POST"
            enctype="multipart/form-data"
        >
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="upload_avatar">
            <input
                type="file"
                name="avatar"
                id="avatar"
                accept="image/jpeg,image/png,image/webp"
                hidden
                onchange="this.form.submit()"
            >
            <label for="avatar" class="btn btn-secundario">
                <?= $avatar ? 'Trocar foto' : 'Adicionar foto' ?>
            </label>
        </form>

        <?php if ($avatar): ?>
            <form
                action="../actions/perfil.php"
                method="POST"
                data-confirm="Remover foto de perfil?"
                data-confirm-message="Sua conta voltará a mostrar suas iniciais."
            >
                <?= csrfCampo() ?>
                <input type="hidden" name="acao" value="remover_avatar">
                <button class="btn btn-perigo-suave">Remover foto</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<nav class="profile-tabs" aria-label="Configurações do perfil">
    <a class="<?= $secao === 'geral' ? 'ativo' : '' ?>" href="perfil.php">
        Visão geral
    </a>
    <a class="<?= $secao === 'dados' ? 'ativo' : '' ?>" href="perfil.php?secao=dados">
        Dados pessoais
    </a>
    <a class="<?= $secao === 'seguranca' ? 'ativo' : '' ?>" href="perfil.php?secao=seguranca">
        Segurança
    </a>
    <a class="<?= $secao === 'email' ? 'ativo' : '' ?>" href="perfil.php?secao=email">
        E-mail
    </a>
</nav>

<?php if ($secao === 'dados'): ?>
    <section class="surface-card profile-account-panel">
        <div class="profile-section-head">
            <div>
                <span class="eyebrow">DADOS PESSOAIS</span>
                <h2>Editar informações</h2>
                <p class="secao-ajuda">
                    Seu tipo de conta e nicho principal permanecem protegidos.
                </p>
            </div>
        </div>

        <form action="../actions/perfil.php" method="POST" autocomplete="off">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="atualizar_dados">

            <div class="form-linha">
                <div class="form-grupo">
                    <label>Nome</label>
                    <input
                        name="nome"
                        required
                        value="<?= limpar($usuario['nome'] ?? '') ?>"
                    >
                </div>

                <div class="form-grupo">
                    <label>Nome de usuário</label>
                    <div class="username-input-wrap">
                        <span>@</span>
                        <input
                            id="username"
                            name="username"
                            minlength="3"
                            maxlength="20"
                            value="<?= limpar($usuario['username'] ?? '') ?>"
                        >
                    </div>
                    <small class="secao-ajuda">
                        Alteração disponível a cada 7 dias.
                        <span id="usernamePerfilStatus"></span>
                    </small>
                </div>
            </div>

            <div class="form-grupo">
                <label>Telefone</label>
                <input
                    type="tel"
                    id="telefone"
                    name="telefone"
                    value="<?= limpar($usuario['telefone'] ?? '') ?>"
                >
            </div>

            <?php if (($usuario['tipo_perfil'] ?? '') !== 'mei'): ?>
                <div class="form-grupo">
                    <label>Limite mensal de gastos</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        name="limite_gastos_mensal"
                        value="<?= limpar((string) ($usuario['limite_gastos_mensal'] ?? '')) ?>"
                    >
                </div>
            <?php endif; ?>

            <div class="profile-readonly-row">
                <span>Tipo de conta</span>
                <strong>
                    <?= ($usuario['tipo_perfil'] ?? '') === 'mei'
                        ? 'Empreendedor'
                        : 'Pessoa física' ?>
                </strong>
            </div>

            <?php if (($usuario['tipo_perfil'] ?? '') === 'mei'): ?>
                <div class="profile-readonly-row">
                    <span>Nicho</span>
                    <strong><?= limpar($usuario['nicho'] ?? '') ?></strong>
                </div>
            <?php endif; ?>

            <button class="btn btn-primario">Salvar dados</button>
        </form>
    </section>

<?php elseif ($secao === 'seguranca'): ?>
    <section class="surface-card profile-account-panel">
        <span class="eyebrow">SEGURANÇA</span>
        <h2>Alterar senha</h2>
        <p class="secao-ajuda">
            Confirme a senha atual antes de definir uma nova.
        </p>

        <form
            action="../actions/perfil.php"
            method="POST"
            autocomplete="off"
            class="profile-security-form"
        >
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="alterar_senha">

            <div class="form-grupo">
                <label>Senha atual</label>
                <input type="password" name="senha_atual" required>
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label>Nova senha</label>
                    <input type="password" name="senha_nova" minlength="6" required>
                </div>

                <div class="form-grupo">
                    <label>Confirmar nova senha</label>
                    <input type="password" name="senha_confirmacao" minlength="6" required>
                </div>
            </div>

            <button class="btn btn-primario">Atualizar senha</button>
        </form>
    </section>

<?php elseif ($secao === 'email'): ?>
    <section class="surface-card profile-account-panel">
        <span class="eyebrow">E-MAIL DA CONTA</span>
        <h2>Confirmação e alteração</h2>

        <div class="profile-email-current">
            <div>
                <span>E-mail atual</span>
                <strong><?= limpar($usuario['email'] ?? '') ?></strong>
            </div>
            <span class="soft-badge <?= !empty($usuario['email_verificado']) ? 'positivo' : 'alerta' ?>">
                <?= !empty($usuario['email_verificado']) ? 'Verificado' : 'Não verificado' ?>
            </span>
        </div>

        <?php if (empty($usuario['email_verificado'])): ?>
            <div class="profile-email-action">
                <p>Confirme seu e-mail para aumentar a segurança da conta.</p>
                <a class="btn btn-primario" href="verificar_email.php">Confirmar e-mail</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($usuario['email_pendente'])): ?>
            <div class="import-note">
                <strong>Novo e-mail aguardando confirmação</strong>
                <p><?= limpar($usuario['email_pendente']) ?></p>

                <form
                    action="../actions/email_verificacao.php"
                    method="POST"
                    class="profile-pending-code"
                >
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="confirmar_troca">
                    <input
                        name="codigo"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        placeholder="Código de 6 dígitos"
                        required
                    >
                    <button class="btn btn-primario">Confirmar novo e-mail</button>
                </form>

                <form action="../actions/email_verificacao.php" method="POST" style="margin-top:8px">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="reenviar_troca">
                    <button class="btn btn-secundario">Reenviar código</button>
                </form>
            </div>
        <?php endif; ?>

        <hr class="profile-separator">

        <h3>Alterar e-mail</h3>
        <p class="secao-ajuda">
            O endereço atual continuará ativo até o novo e-mail ser confirmado.
        </p>

        <form action="../actions/perfil.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="alterar_email">

            <div class="form-grupo">
                <label>Novo e-mail</label>
                <input type="email" name="novo_email" required>
            </div>

            <button class="btn btn-secundario">Enviar código de confirmação</button>
        </form>
    </section>

<?php else: ?>
    <div class="profile-settings-grid">
        <section class="surface-card profile-settings-section">
            <span class="eyebrow">CONTA</span>
            <h2>Informações principais</h2>

            <div class="profile-list-facts">
                <div><span>Nome</span><strong><?= limpar($usuario['nome'] ?? '') ?></strong></div>
                <div><span>Usuário</span><strong>@<?= limpar($usuario['username'] ?? '') ?></strong></div>
                <div><span>E-mail</span><strong><?= limpar($usuario['email'] ?? '') ?></strong></div>
                <div>
                    <span>Status do e-mail</span>
                    <strong><?= !empty($usuario['email_verificado']) ? 'Verificado' : 'Pendente' ?></strong>
                </div>
            </div>

            <a class="btn btn-secundario" href="perfil.php?secao=dados">
                Editar dados pessoais
            </a>
        </section>

        <div class="profile-settings-stack">
            <section class="surface-card profile-settings-section">
                <span class="eyebrow">PREFERÊNCIAS</span>
                <h2>Aparência</h2>

                <div class="profile-preference-row">
                    <div>
                        <strong>Tema do painel</strong>
                        <p>Essa preferência vale somente dentro do painel do CashPilot.</p>
                    </div>

                    <button
                        type="button"
                        class="airplane-theme-control"
                        id="profileThemeToggle"
                    >
                        <span class="airplane-window">
                            <i class="airplane-window-sky"><b></b><em></em></i>
                            <i class="airplane-window-shade"></i>
                        </span>
                        <span id="profileThemeLabel">
                            <?= ($usuario['tema_preferido'] ?? 'light') === 'dark'
                                ? 'Modo escuro'
                                : 'Modo claro' ?>
                        </span>
                    </button>
                </div>
            </section>

            <section class="surface-card profile-settings-section">
                <span class="eyebrow">ORGANIZAÇÃO E DADOS</span>
                <h2>Recursos da conta</h2>

                <div class="profile-link-list">
                    <a href="contas.php"><strong>Contas</strong><span>Contas e carteiras →</span></a>
                    <a href="importar.php"><strong>Importar extrato</strong><span>CSV ou PDF →</span></a>
                    <a href="importacoes.php"><strong>Importações</strong><span>Histórico de arquivos →</span></a>
                    <a href="categorias.php"><strong>Categorias</strong><span>Organizar classificações →</span></a>

                    <?php if (($usuario['tipo_perfil'] ?? '') === 'mei'): ?>
                        <a href="negocio.php"><strong>Estrutura do negócio</strong><span>Perfil empresarial →</span></a>
                    <?php endif; ?>

                    <?php if (!empty($usuario['is_admin'])): ?>
                        <a href="aprender_gerenciar.php"><strong>Conteúdo do Aprender</strong><span>Gerenciar vídeos →</span></a>
                        <a href="copiloto_status.php"><strong>Status do Copiloto</strong><span>Diagnóstico da IA →</span></a>
                    <?php endif; ?>

                    <a href="../actions/exportar.php?token=<?= urlencode(csrfToken()) ?>">
                        <strong>Exportar dados</strong><span>Baixar CSV →</span>
                    </a>
                </div>
            </section>

            <section class="surface-card profile-settings-section">
                <span class="eyebrow">AJUDA</span>
                <h2>Conheça o sistema novamente</h2>
                <p class="secao-ajuda">Reabra a apresentação inicial do CashPilot.</p>

                <form action="../actions/preferencias.php" method="POST">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="reiniciar_onboarding">
                    <button class="btn btn-secundario">Refazer apresentação</button>
                </form>
            </section>
        </div>
    </div>

    <div class="perfil-zona-saida">
        <button
            type="button"
            class="btn btn-perigo"
            data-confirm-link="../actions/logout.php?token=<?= urlencode(csrfToken()) ?>"
            data-confirm-title="Sair do CashPilot?"
            data-confirm-message="Sua sessão será encerrada neste dispositivo."
        >
            Sair da conta
        </button>
    </div>
<?php endif; ?>

<script>
const usernamePerfil = document.getElementById('username');

const usernameStatus = document.getElementById('usernamePerfilStatus');

const telefonePerfil = document.getElementById('telefone');

const profileThemeToggle = document.getElementById('profileThemeToggle');

const profileThemeLabel = document.getElementById('profileThemeLabel');

let timerUsername = null;


function mascaraTelefone(valor) {

    const numeros = String(valor || '').replace(/\D/g, '').slice(0, 11);


    if (numeros.length <= 2) return numeros ? `(${numeros}` : '';

    if (numeros.length <= 6) return `(${numeros.slice(0, 2)}) ${numeros.slice(2)}`;

    if (numeros.length <= 10) return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 6)}-${numeros.slice(6)}`;


    return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 7)}-${numeros.slice(7)}`;

}


usernamePerfil?.addEventListener('input', () => {
    usernamePerfil.value = usernamePerfil.value
        .toLowerCase()
        .replace(/[^a-z0-9._]/g, '')
        .slice(0, 20);

    clearTimeout(timerUsername);

    if (usernamePerfil.value.length < 3) {
        if (usernameStatus) usernameStatus.textContent = '';
        return;
    }

    timerUsername = setTimeout(async () => {
        try {
            const resposta = await fetch(
                '../actions/username_disponivel.php?username=' +
                encodeURIComponent(usernamePerfil.value)
            );
            const dados = await resposta.json();

            if (usernameStatus) {
                usernameStatus.textContent = dados.mensagem || '';
                usernameStatus.className = dados.disponivel ? 'positivo' : 'negativo';
            }
        } catch (_) {}
    }, 350);
});


telefonePerfil?.addEventListener('input', () => {
    telefonePerfil.value = mascaraTelefone(telefonePerfil.value);
});


if (telefonePerfil) {

    telefonePerfil.value = mascaraTelefone(telefonePerfil.value);

}


profileThemeToggle?.addEventListener('click', () => {
    document.getElementById('themeToggle')?.click();

    setTimeout(() => {
        const tema = document.documentElement.getAttribute('data-theme') === 'dark'
            ? 'dark'
            : 'light';

        if (profileThemeLabel) {
            profileThemeLabel.textContent = tema === 'dark'
                ? 'Modo escuro'
                : 'Modo claro';
        }
    }, 20);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
