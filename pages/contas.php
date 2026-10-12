<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/financeiro_regras.php';

exigirLogin();

$tituloPagina = 'Contas';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$stmt = $pdo->prepare('SELECT * FROM contas WHERE usuario_id=:uid ORDER BY padrao DESC,nome');

$stmt->execute(['uid' => $usuarioId]);
$contas = $stmt->fetchAll();
foreach ($contas as &$conta) {
    $conta['saldo_atual'] = cpSaldoRealizado($pdo, $usuarioId, (int) $conta['id']);
}
unset($conta);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<?php if (!empty($_SESSION['mensagem_contas'])): ?>

<div class="alerta-mensagem erro" role="alert"><?= limpar($_SESSION['mensagem_contas']) ?></div>

<?php unset($_SESSION['mensagem_contas']);endif; ?>

<a href="perfil.php" class="voltar-perfil">← Voltar para Meu Perfil</a>
<div class="topo-pagina">
    <div>
        <h1>Contas</h1>
        <p>Organize as contas e carteiras usadas pelas suas movimentações.</p>
    </div>
    <a href="open_finance.php" class="btn btn-secundario">Conectar seu banco</a>
</div>
<div class="grade-dupla">
    <div class="cartao">
        <h3 style="margin-bottom:16px;">Minhas contas</h3>

        <?php if (!$contas): ?>

        <div class="cp168-empty">
            <span class="cp168-empty-icon"><?= cpIcon('wallet') ?></span>
            <h3>Nenhuma conta cadastrada</h3>
            <p>Crie uma conta ou carteira para acompanhar os saldos das suas movimentações.</p>
            <a class="btn btn-secundario" href="#novaConta">Cadastrar conta</a>
        </div>

        <?php else: ?>

        <table>
            <thead>
                <tr>
                    <th>Conta</th>
                    <th>Tipo</th>
                    <th>Saldo</th>
                    <th>
                    </th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($contas as $c): ?>

                <tr>
                    <td><?= limpar($c['nome']) ?><?= $c['padrao']
                    ? ' <span class="badge">Principal</span>'
                    : '' ?></td>
                    <td><?= limpar(ucfirst($c['tipo'])) ?></td>
                    <td class="<?= $c['saldo_atual'] >= 0
                    ? 'positivo'
                    : 'negativo' ?>"><?= formatarMoeda((float) $c['saldo_atual']) ?></td>
                    <td>

                        <?php if (!$c['padrao']): ?>

                        <form autocomplete="off" action="../actions/contas.php" method="POST"
                            data-confirm="Excluir esta conta? As movimentações serão mantidas sem conta.">
                            <?= csrfCampo() ?>

                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="excluir" type="submit">Excluir</button>
                        </form>

                        <?php endif; ?>

                    </td>
                </tr>

                <?php endforeach; ?>

            </tbody>
        </table>

        <?php endif; ?>

    </div>
    <div class="cartao" id="novaConta">
        <h3 style="margin-bottom:16px;">Nova conta</h3>
        <form autocomplete="off" action="../actions/contas.php" method="POST"><?= csrfCampo() ?>

            <input type="hidden" name="acao" value="criar">
            <div class="form-grupo">
                <label>Nome</label>
                <input name="nome" required maxlength="80" placeholder="Ex: Nubank">
            </div>
            <div class="form-grupo">
                <label>Tipo</label>
                <select name="tipo">
                    <option value="corrente">Conta corrente</option>
                    <option value="poupanca">Poupança</option>
                    <option value="carteira">Carteira</option>
                    <option value="empresarial">Empresarial</option>
                    <option value="outra">Outra</option>
                </select>
            </div>
            <div class="form-grupo">
                <label>Saldo inicial (R$)</label>
                <input autocomplete="off" name="saldo_inicial" type="number" step="0.01" min="0"
                    value="0">
            </div>
            <button class="btn btn-primario btn-bloco" type="submit">Adicionar conta</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
