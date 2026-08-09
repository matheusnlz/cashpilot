<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Perfil';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$stmt = $pdo->prepare('SELECT nome, email, tipo_perfil, renda_mensal, data_cadastro FROM usuarios WHERE id = :uid');
$stmt->execute(['uid' => $usuarioId]);
$usuario = $stmt->fetch();

$mensagem = $_SESSION['mensagem_perfil'] ?? null;
unset($_SESSION['mensagem_perfil']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Meu perfil</h1>
        <p>Gerencie suas informações de conta.</p>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="alerta-mensagem sucesso" style="max-width:520px;"><?= limpar($mensagem) ?></div>
<?php endif; ?>

<div class="grade-dupla">
    <div class="cartao">
        <h3 style="margin-bottom:16px;">Dados pessoais</h3>
        <form action="../actions/perfil.php" method="POST">
            <input type="hidden" name="acao" value="atualizar_dados">
            <div class="form-grupo">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required value="<?= limpar($usuario['nome']) ?>">
            </div>
            <div class="form-grupo">
                <label for="email">E-mail</label>
                <input type="email" value="<?= limpar($usuario['email']) ?>" disabled>
            </div>
            <div class="form-linha">
                <div class="form-grupo">
                    <label for="tipo_perfil">Perfil</label>
                    <select id="tipo_perfil" name="tipo_perfil">
                        <option value="pessoa_fisica" <?= $usuario['tipo_perfil'] === 'pessoa_fisica' ? 'selected' : '' ?>>Pessoa física</option>
                        <option value="mei" <?= $usuario['tipo_perfil'] === 'mei' ? 'selected' : '' ?>>MEI / Pequeno empreendedor</option>
                    </select>
                </div>
                <div class="form-grupo">
                    <label for="renda_mensal">Renda / faturamento mensal (R$)</label>
                    <input type="number" step="0.01" id="renda_mensal" name="renda_mensal" value="<?= $usuario['renda_mensal'] ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primario">Salvar alterações</button>
        </form>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;">Alterar senha</h3>
        <form action="../actions/perfil.php" method="POST">
            <input type="hidden" name="acao" value="alterar_senha">
            <div class="form-grupo">
                <label for="senha_atual">Senha atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required>
            </div>
            <div class="form-grupo">
                <label for="senha_nova">Nova senha</label>
                <input type="password" id="senha_nova" name="senha_nova" required minlength="6">
            </div>
            <button type="submit" class="btn btn-secundario">Alterar senha</button>
        </form>

        <p style="font-size:12.5px; color:var(--cor-texto-suave); margin-top:20px;">
            Conta criada em <?= date('d/m/Y', strtotime($usuario['data_cadastro'])) ?>
        </p>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
