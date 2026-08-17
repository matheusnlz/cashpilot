<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
$tituloPagina = 'Contas'; $pdo = conectar(); $usuarioId = usuarioLogadoId();
$stmt = $pdo->prepare('SELECT c.*, c.saldo_inicial + COALESCE((SELECT SUM(r.valor) FROM receitas r WHERE r.conta_id=c.id AND r.usuario_id=c.usuario_id),0) - COALESCE((SELECT SUM(d.valor) FROM despesas d WHERE d.conta_id=c.id AND d.usuario_id=c.usuario_id),0) AS saldo_atual FROM contas c WHERE c.usuario_id=:uid ORDER BY c.padrao DESC,c.nome');
$stmt->execute(['uid'=>$usuarioId]); $contas=$stmt->fetchAll();
require_once __DIR__ . '/../includes/header.php'; require_once __DIR__ . '/../includes/navbar.php';
?>
<a href="perfil.php" class="voltar-perfil">← Voltar para Meu Perfil</a>
<div class="topo-pagina"><div><h1>Contas</h1><p>Organize as contas e carteiras usadas pelas suas movimentações.</p></div></div>
<div class="grade-dupla"><div class="cartao"><h3 style="margin-bottom:16px;">Minhas contas</h3>
<?php if (!$contas): ?><p class="texto-vazio">Nenhuma conta cadastrada.</p><?php else: ?><table><thead><tr><th>Conta</th><th>Tipo</th><th>Saldo</th><th></th></tr></thead><tbody>
<?php foreach($contas as $c): ?><tr><td><?=limpar($c['nome'])?><?= $c['padrao'] ? ' <span class="badge">Principal</span>' : '' ?></td><td><?=limpar(ucfirst($c['tipo']))?></td><td class="<?=$c['saldo_atual']>=0?'positivo':'negativo'?>"><?=formatarMoeda((float)$c['saldo_atual'])?></td><td><?php if(!$c['padrao']): ?><form autocomplete="off" action="../actions/contas.php" method="POST" onsubmit="return confirm('Excluir esta conta? As movimentações serão mantidas sem conta.');"><?= csrfCampo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="excluir" type="submit">Excluir</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
<div class="cartao"><h3 style="margin-bottom:16px;">Nova conta</h3><form autocomplete="off" action="../actions/contas.php" method="POST"><?= csrfCampo() ?><input type="hidden" name="acao" value="criar"><div class="form-grupo"><label>Nome</label><input name="nome" required maxlength="80" placeholder="Ex: Nubank"></div><div class="form-grupo"><label>Tipo</label><select name="tipo"><option value="corrente">Conta corrente</option><option value="poupanca">Poupança</option><option value="carteira">Carteira</option><option value="empresarial">Empresarial</option><option value="outra">Outra</option></select></div><div class="form-grupo"><label>Saldo inicial (R$)</label><input autocomplete="off" name="saldo_inicial" type="number" step="0.01" min="0" value="0"></div><button class="btn btn-primario btn-bloco" type="submit">Adicionar conta</button></form></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
