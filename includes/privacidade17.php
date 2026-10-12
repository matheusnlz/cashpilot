<?php
/** Exportação dos dados vinculados à conta, sem hashes de senha/códigos/segredos. */
function cp17ConsultasExportacao(): array
{
    $consultas = [
        'cadastro' => 'SELECT id, nome, username, email, email_verificado, telefone,
            tipo_perfil, nicho, objetivo_pessoal, tema_preferido, renda_mensal,
            limite_gastos_mensal, data_cadastro FROM usuarios WHERE id = ?',
        'venda_itens' => 'SELECT vi.* FROM venda_itens vi INNER JOIN vendas v
            ON v.id = vi.venda_id WHERE v.usuario_id = ?',
        'plano_acao_itens' => 'SELECT pi.* FROM plano_acao_itens pi INNER JOIN planos_acao p
            ON p.id = pi.plano_id WHERE p.usuario_id = ?'
    ];
    $tabelas = [
        'usuario_oauth',
        'usuario_apresentacoes',
        'perfil_negocio',
        'categorias',
        'contas',
        'fornecedores',
        'produtos_servicos',
        'funcionarios',
        'custos_negocio',
        'importacoes',
        'receitas',
        'despesas',
        'metas',
        'meta_movimentacoes',
        'recorrencias_pf',
        'planejamento_mensal',
        'financiamentos_simulados',
        'vendas',
        'movimentacoes_estoque',
        'classificacao_regras',
        'copiloto_conversas',
        'copiloto_mensagens',
        'aprender_progresso',
        'reserva_emergencia',
        'desafios_economia',
        'planos_acao',
        'investimentos',
        'investimento_movimentacoes',
        'planejamento_categoria_mensal',
        'planejamento_destino_mensal',
        'perfil_investidor',
        'cp_privacidade'
    ];
    foreach ($tabelas as $tabela) {
        // Lista fechada: nomes jamais vêm do navegador.
        $consultas[$tabela] = 'SELECT * FROM `' . $tabela . '` WHERE usuario_id = ?';
    }
    // Campos explícitos excluem caches e payloads de provedores.
    $consultas['pluggy_items'] = 'SELECT pluggy_item_id, connector_id, instituicao_nome,
        status, criado_em, atualizado_em FROM pluggy_items WHERE usuario_id = ?';
    $consultas['pluggy_accounts'] = 'SELECT pluggy_account_id, pluggy_item_id, nome,
        tipo, subtipo, moeda, saldo, conta_id FROM pluggy_accounts WHERE usuario_id = ?';
    $consultas['pluggy_transactions'] = 'SELECT pluggy_transaction_id, pluggy_account_id,
        descricao, valor, tipo_sugerido, tipo_externo, data_transacao, categoria_externa
        FROM pluggy_transactions WHERE usuario_id = ?';
    return $consultas;
}

function cp17ExportarDados(PDO $pdo, int $uid): array
{
    $dados = ['versao' => '17', 'gerado_em' => date(DATE_ATOM), 'dados' => []];
    $pdo->beginTransaction();
    try {
        foreach (cp17ConsultasExportacao() as $nome => $sql) {
            $s = $pdo->prepare($sql);
            $s->execute([$uid]);
            $dados['dados'][$nome] = $s->fetchAll(PDO::FETCH_ASSOC);
        }
        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
    return $dados;
}
