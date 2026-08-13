<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Metas';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$stmt = $pdo->prepare('SELECT * FROM metas WHERE usuario_id = :uid ORDER BY concluida, prazo IS NULL, prazo ASC');
$stmt->execute(['uid' => $usuarioId]);
$metas = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Metas financeiras</h1>
        <p>Defina objetivos e acompanhe sua evolução até alcançá-los.</p>
    </div>
</div>

<div class="grade-dupla">
    <div>
        <?php if (empty($metas)): ?>
            <div class="cartao"><p class="texto-vazio">Você ainda não criou nenhuma meta.</p></div>
        <?php else: ?>
            <div class="grade-metas">
                <?php foreach ($metas as $m):
                    $percentual = $m['valor_meta'] > 0 ? min(100, ($m['valor_atual'] / $m['valor_meta']) * 100) : 0;
                ?>
                    <div class="cartao meta-card">
                        <h3><?= limpar($m['titulo']) ?></h3>
                        <div class="meta-valores">
                            <?= formatarMoeda((float) $m['valor_atual']) ?> de <?= formatarMoeda((float) $m['valor_meta']) ?>
                            <?php if ($m['prazo']): ?> · até <?= date('d/m/Y', strtotime($m['prazo'])) ?><?php endif; ?>
                        </div>
                        <div class="barra-progresso">
                            <div class="preenchido" style="width: <?= $percentual ?>%;"></div>
                        </div>
                        <div class="meta-percentual"><?= number_format($percentual, 0) ?>% concluída<?= $m['concluida'] ? ' · Concluída 🎉' : '' ?></div>

                        <form action="../actions/metas.php" method="POST" style="margin-top:14px; display:flex; gap:8px;">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="atualizar_valor">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <input type="number" step="0.01" name="valor_atual" value="<?= $m['valor_atual'] ?>" style="width:100%; padding:8px 10px; border:1px solid var(--cor-borda); border-radius:8px;">
                            <button type="submit" class="btn btn-secundario">Atualizar</button>
                        </form>
                        <form action="../actions/metas.php" method="POST" onsubmit="return confirm('Excluir esta meta?');" style="margin-top:8px;">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button type="submit" class="excluir" style="border:none;background:none;font-size:12px;">Excluir meta</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;">Nova meta</h3>
        <form action="../actions/metas.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="criar">
            <div class="form-grupo">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" placeholder="Ex: Comprar notebook" required>
            </div>
            <div class="form-grupo">
                <label for="valor_meta">Valor da meta (R$)</label>
                <input type="number" step="0.01" min="0.01" id="valor_meta" name="valor_meta" required>
            </div>
            <div class="form-grupo">
                <label for="valor_atual">Valor já acumulado (R$)</label>
                <input type="number" step="0.01" min="0" id="valor_atual" name="valor_atual" value="0">
            </div>
            <div class="form-grupo">
                <label for="prazo">Prazo</label>
                <input type="date" id="prazo" name="prazo">
            </div>
            <button type="submit" class="btn btn-primario btn-bloco">Criar meta</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
