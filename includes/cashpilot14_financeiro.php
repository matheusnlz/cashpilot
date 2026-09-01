<?php

/**
 * CashPilot 14
 * Funções novas de investimentos, patrimônio, planejamento
 * e projeção detalhada de caixa.
 */

function cp14TabelaExiste(PDO $pdo, string $tabela): bool
 {

        try {

                $stmt = $pdo->prepare(
                    'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :tabela'
                );

                $stmt->execute([
                    'tabela' => $tabela,
                ]);

                return (int) $stmt->fetchColumn() > 0;

    }  catch (Throwable $e) {

                return false;

    }

}

function cp14ResumoInvestimentos(PDO $pdo, int $usuarioId): array
 {

        $resumo = [
            'quantidade' => 0,
            'valor_aplicado' => 0.0,
            'valor_atual' => 0.0,
            'resultado' => 0.0,
            'rentabilidade' => null,
            'classes' => [],
            'aportes_mes' => 0.0,
        ];

        if (!cp14TabelaExiste($pdo, 'investimentos')) {

                return $resumo;

    }

        try {

                $stmt = $pdo->prepare(
                    'SELECT
                COUNT(*) AS quantidade,
                COALESCE(SUM(valor_aplicado), 0) AS aplicado,
                COALESCE(SUM(valor_atual), 0) AS atual
             FROM investimentos
             WHERE usuario_id = :uid
               AND ativo = 1'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                ]);

                $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $resumo['quantidade'] = (int) ($linha['quantidade'] ?? 0);

                $resumo['valor_aplicado'] = (float) ($linha['aplicado'] ?? 0);

                $resumo['valor_atual'] = (float) ($linha['atual'] ?? 0);

                $resumo['resultado'] =
                    $resumo['valor_atual'] - $resumo['valor_aplicado'];

                if ($resumo['valor_aplicado'] > 0) {

                        $resumo['rentabilidade'] =
                            $resumo['resultado']
                            / $resumo['valor_aplicado']
                            * 100;

        }

                $stmt = $pdo->prepare(
                    'SELECT
                classe,
                COUNT(*) AS quantidade,
                COALESCE(SUM(valor_atual), 0) AS valor
             FROM investimentos
             WHERE usuario_id = :uid
               AND ativo = 1
             GROUP BY classe
             ORDER BY valor DESC'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                ]);

                $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($classes as &$classe) {

                        $valor = (float) $classe['valor'];

                        $classe['valor'] = $valor;

                        $classe['percentual'] =
                            $resumo['valor_atual'] > 0
                                ? $valor / $resumo['valor_atual'] * 100
                                : 0;

        }

                unset($classe);

                $resumo['classes'] = $classes;

                if (cp14TabelaExiste($pdo, 'investimento_movimentacoes')) {

                        $stmt = $pdo->prepare(
                            'SELECT COALESCE(SUM(valor), 0)
                 FROM investimento_movimentacoes
                 WHERE usuario_id = :uid
                   AND tipo = "aporte"
                   AND data_movimentacao BETWEEN :inicio AND :fim'
                        );

                        $stmt->execute([
                            'uid' => $usuarioId,
                            'inicio' => date('Y-m-01'),
                            'fim' => date('Y-m-t'),
                        ]);

                        $resumo['aportes_mes'] =
                            (float) $stmt->fetchColumn();

        }

    }  catch (Throwable $e) {

                error_log(
                    'CashPilot14/InvestimentosResumo: '
                    . $e->getMessage()
                );

    }

        return $resumo;

}

function cp14ListarInvestimentos(
    PDO $pdo,
    int $usuarioId,
    bool $somenteAtivos = true
): array {

        if (!cp14TabelaExiste($pdo, 'investimentos')) {

                return [];

    }

        $sql =
            'SELECT
            i.*,
            m.titulo AS meta_titulo
         FROM investimentos i
         LEFT JOIN metas m
           ON m.id = i.meta_id
          AND m.usuario_id = i.usuario_id
         WHERE i.usuario_id = :uid';

        if ($somenteAtivos) {

                $sql .= ' AND i.ativo = 1';

    }

        $sql .=
            ' ORDER BY
            i.ativo DESC,
            i.valor_atual DESC,
            i.nome ASC';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'uid' => $usuarioId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

function cp14MovimentacoesInvestimento(
    PDO $pdo,
    int $usuarioId,
    int $investimentoId,
    int $limite = 30
): array {

        if (!cp14TabelaExiste($pdo, 'investimento_movimentacoes')) {

                return [];

    }

        $limite = max(1, min(100, $limite));

        $stmt = $pdo->prepare(
            'SELECT *
         FROM investimento_movimentacoes
         WHERE usuario_id = :uid
           AND investimento_id = :iid
         ORDER BY data_movimentacao DESC, id DESC
         LIMIT ' . $limite
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'iid' => $investimentoId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

function cp14NomeClasseInvestimento(string $classe): string
 {

        $nomes = [
            'renda_fixa' => 'Renda fixa',
            'tesouro' => 'Tesouro Direto',
            'acoes' => 'Ações',
            'fiis' => 'FIIs',
            'etfs' => 'ETFs',
            'fundos' => 'Fundos',
            'cripto' => 'Criptomoedas',
            'poupanca' => 'Poupança',
            'outros' => 'Outros',
        ];

        return $nomes[$classe] ?? 'Outros';

}

function cp14PatrimonioPF(PDO $pdo, int $usuarioId): array
 {

        $investimentos = cp14ResumoInvestimentos(
            $pdo,
            $usuarioId
        );

        $saldo = 0.0;

        try {

                $stmt = $pdo->prepare(
                    'SELECT
                (
                    SELECT COALESCE(SUM(valor), 0)
                    FROM receitas
                    WHERE usuario_id = :u1
                )
                -
                (
                    SELECT COALESCE(SUM(valor), 0)
                    FROM despesas
                    WHERE usuario_id = :u2
                )'
                );

                $stmt->execute([
                    'u1' => $usuarioId,
                    'u2' => $usuarioId,
                ]);

                $saldo = (float) $stmt->fetchColumn();

    }  catch (Throwable $e) {

                error_log(
                    'CashPilot14/Patrimonio: '
                    . $e->getMessage()
                );

    }

        return [
            'saldo_financeiro' => $saldo,
            'investimentos' => $investimentos['valor_atual'],
            'patrimonio_acompanhado' =>
                $saldo + $investimentos['valor_atual'],
            'observacao' =>
                'Este valor considera o saldo registrado e os investimentos '
                . 'acompanhados no CashPilot. Simulações de financiamento '
                . 'não são tratadas como dívidas reais.',
        ];

}

function cp14PlanejamentoCategorias(
    PDO $pdo,
    int $usuarioId,
    string $competencia
): array {

        if (!cp14TabelaExiste(
            $pdo,
            'planejamento_categoria_mensal'
        )) {

                return [];

    }

        $stmt = $pdo->prepare(
            'SELECT
            p.categoria_id,
            p.valor_limite,
            c.nome,
            c.cor
         FROM planejamento_categoria_mensal p
         JOIN categorias c
           ON c.id = p.categoria_id
          AND c.usuario_id = p.usuario_id
         WHERE p.usuario_id = :uid
           AND p.competencia = :competencia
         ORDER BY c.nome'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'competencia' => $competencia,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

function cp14PlanejamentoDestinos(
    PDO $pdo,
    int $usuarioId,
    string $competencia
): array {

        $destinos = [
            'investimentos' => 0.0,
            'reserva' => 0.0,
        ];

        if (!cp14TabelaExiste(
            $pdo,
            'planejamento_destino_mensal'
        )) {

                return $destinos;

    }

        $stmt = $pdo->prepare(
            'SELECT tipo, valor_planejado
         FROM planejamento_destino_mensal
         WHERE usuario_id = :uid
           AND competencia = :competencia'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'competencia' => $competencia,
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {

                $tipo = (string) $linha['tipo'];

                if (array_key_exists($tipo, $destinos)) {

                        $destinos[$tipo] =
                            (float) $linha['valor_planejado'];

        }

    }

        return $destinos;

}

function cp14ResumoPlanejamento(
    PDO $pdo,
    int $usuarioId,
    string $competencia
): array {

        $inicio = $competencia . '-01';

        $fim = date(
            'Y-m-t',
            strtotime($inicio)
        );

        $planejamento = [];

        try {

                $stmt = $pdo->prepare(
                    'SELECT *
             FROM planejamento_mensal
             WHERE usuario_id = :uid
               AND competencia = :competencia
             LIMIT 1'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                    'competencia' => $competencia,
                ]);

                $planejamento =
                    $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    }  catch (Throwable $e) {

                // A página apresenta estado vazio.

    }

        $destinos = cp14PlanejamentoDestinos(
            $pdo,
            $usuarioId,
            $competencia
        );

        $categorias = [];

        try {

                $stmt = $pdo->prepare(
                    'SELECT
                c.id,
                c.nome,
                c.cor,
                COALESCE(p.valor_limite, 0) AS planejado,
                COALESCE(SUM(d.valor), 0) AS realizado
             FROM categorias c
             LEFT JOIN planejamento_categoria_mensal p
               ON p.categoria_id = c.id
              AND p.usuario_id = c.usuario_id
              AND p.competencia = :competencia
             LEFT JOIN despesas d
               ON d.categoria_id = c.id
              AND d.usuario_id = c.usuario_id
              AND d.data_despesa BETWEEN :inicio AND :fim
             WHERE c.usuario_id = :uid
               AND c.tipo = "despesa"
             GROUP BY
                c.id,
                c.nome,
                c.cor,
                p.valor_limite
             ORDER BY
                planejado DESC,
                realizado DESC,
                c.nome'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                    'competencia' => $competencia,
                    'inicio' => $inicio,
                    'fim' => $fim,
                ]);

                $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }  catch (Throwable $e) {

                error_log(
                    'CashPilot14/PlanejamentoCategorias: '
                    . $e->getMessage()
                );

    }

        $receitaEsperada =
            (float) ($planejamento['receita_esperada'] ?? 0);

        $fixos =
            (float) ($planejamento['gastos_fixos_estimados'] ?? 0);

        $metas =
            (float) ($planejamento['valor_metas'] ?? 0);

        $investimentos =
            (float) ($destinos['investimentos'] ?? 0);

        $reserva =
            (float) ($destinos['reserva'] ?? 0);

        $limitesCategorias = 0.0;

        $realizadoCategorias = 0.0;

        foreach ($categorias as &$categoria) {

                $categoria['planejado'] =
                    (float) $categoria['planejado'];

                $categoria['realizado'] =
                    (float) $categoria['realizado'];

                $limitesCategorias +=
                    $categoria['planejado'];

                $realizadoCategorias +=
                    $categoria['realizado'];

                $categoria['percentual'] =
                    $categoria['planejado'] > 0
                        ? min(
                            100,
                            $categoria['realizado']
                            / $categoria['planejado']
                            * 100
                        )
                        : null;

    }

        unset($categoria);

        $comprometido =
            $fixos
            + $metas
            + $investimentos
            + $reserva
            + $limitesCategorias;

        return [
            'planejamento' => $planejamento,
            'destinos' => $destinos,
            'categorias' => $categorias,
            'receita_esperada' => $receitaEsperada,
            'fixos' => $fixos,
            'metas' => $metas,
            'investimentos' => $investimentos,
            'reserva' => $reserva,
            'limites_categorias' => $limitesCategorias,
            'realizado_categorias' => $realizadoCategorias,
            'comprometido_planejado' => $comprometido,
            'disponivel_planejado' =>
                $receitaEsperada - $comprometido,
            'inicio' => $inicio,
            'fim' => $fim,
        ];

}

function cp14ProjecaoCaixaDetalhada(
    PDO $pdo,
    int $usuarioId,
    int $dias = 30
): array {

        $dias = max(7, min(90, $dias));

        $hoje = new DateTimeImmutable('today');

        $fim = $hoje->modify('+' . $dias . ' days');

        $saldo = 0.0;

        $mediaMensal = 0.0;

        $compromissos = [];

        try {

                $stmt=$pdo->prepare('SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id=:u1 AND data_receita<=:h1) - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id=:u2 AND data_despesa<=:h2)');

                $stmt->execute(['u1'=>$usuarioId,'h1'=>$hoje->format('Y-m-d'),'u2'=>$usuarioId,'h2'=>$hoje->format('Y-m-d')]);

                $saldo=(float)$stmt->fetchColumn();

                $inicioMedia=date('Y-m-01',strtotime('first day of -3 months'));

                $fimMedia=date('Y-m-t',strtotime('last day of last month'));

                $stmt=$pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id=:uid AND data_receita BETWEEN :i AND :f');

                $stmt->execute(['uid'=>$usuarioId,'i'=>$inicioMedia,'f'=>$fimMedia]);

                $mediaMensal=(float)$stmt->fetchColumn()/3;

                /* Fonte única de saídas: despesas futuras já sincronizadas/registradas.
           Isso evita somar novamente funcionário, fornecedor ou custo recorrente. */
                $stmt=$pdo->prepare('SELECT data_despesa AS data, descricao, valor, COALESCE(origem_tipo,"manual") AS tipo FROM despesas WHERE usuario_id=:uid AND data_despesa>:hoje AND data_despesa<=:fim ORDER BY data_despesa,id');

                $stmt->execute(['uid'=>$usuarioId,'hoje'=>$hoje->format('Y-m-d'),'fim'=>$fim->format('Y-m-d')]);

                foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {

                        $compromissos[]=['data'=>$linha['data'],'descricao'=>$linha['descricao'],'valor'=>(float)$linha['valor'],'tipo'=>$linha['tipo']];

        }

    }  catch(Throwable $e) {

                error_log('CashPilot143/ProjecaoCaixa: '.$e->getMessage());

    }

        $receitaPrevista=$mediaMensal*($dias/30);

        $compromissosPrevistos=array_sum(array_column($compromissos,'valor'));

        $receitaDiaria=$receitaPrevista/max(1,$dias);

        $saldoSerie=$saldo;
    $serie=[];
    $menorSaldo=$saldo;
    $menorData=$hoje->format('Y-m-d');
    $primeiroNegativo=null;

        for($indice=1; $indice<=$dias; $indice++) {

                $data=$hoje->modify('+'.$indice.' days');
        $dataTexto=$data->format('Y-m-d');
        $saidas=0.0;

                foreach($compromissos as $c) if($c['data']===$dataTexto) $saidas+=(float)$c['valor'];

                $saldoSerie += $receitaDiaria-$saidas;

                if($saldoSerie<$menorSaldo) {
            $menorSaldo=$saldoSerie;
            $menorData=$dataTexto;

        }

                if($saldoSerie<0 && $primeiroNegativo===null)$primeiroNegativo=$dataTexto;

                $serie[]=['data'=>$dataTexto,'entrada_estimada'=>$receitaDiaria,'saidas_registradas'=>$saidas,'saldo_projetado'=>$saldoSerie];

    }

        return [
            'saldo_atual'=>$saldo,'receita_prevista'=>$receitaPrevista,
            'compromissos_previstos'=>$compromissosPrevistos,
            'caixa_projetado'=>$saldo+$receitaPrevista-$compromissosPrevistos,
            'dias'=>$dias,'metodo'=>'média das receitas dos 3 meses completos anteriores + despesas futuras registradas',
            'serie'=>$serie,'compromissos'=>$compromissos,'menor_saldo'=>$menorSaldo,'menor_data'=>$menorData,
            'primeiro_negativo'=>$primeiroNegativo,
            'observacao'=>'A projeção é uma estimativa baseada nos dados cadastrados no CashPilot. Entradas usam a média dos três meses completos anteriores; saídas futuras mantêm as datas registradas.'
        ];

}

/** CashPilot 14.3 - status legível do planejamento por categoria. */
function cp143StatusPlanejamento(float $planejado, float $realizado): array
 {

        if ($planejado <= 0) {

                return ['sem_plano', 'Sem limite definido', null];

    }

        $percentual = ($realizado / $planejado) * 100;

        if ($percentual >= 100) {

                return ['ultrapassado', 'Limite ultrapassado', $percentual];

    }

        if ($percentual >= 80) {

                return ['atencao', 'Atenção', $percentual];

    }

        return ['normal', 'Dentro do planejado', $percentual];

}

/**
 * CashPilot 14.3 - visão integrada PF.
 * Reúne dados existentes; não cria previsões ou valores fictícios.
 */
function cp143VisaoFinanceiraPF(PDO $pdo, int $usuarioId): array
 {

        $competencia = date('Y-m');

        $inicio = date('Y-m-01');

        $fim = date('Y-m-t');

        $receitas = 0.0;

        $despesas = 0.0;

        try {

                $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id=:uid AND data_receita BETWEEN :i AND :f');

                $stmt->execute(['uid'=>$usuarioId,'i'=>$inicio,'f'=>$fim]);

                $receitas = (float) $stmt->fetchColumn();

                $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id=:uid AND data_despesa BETWEEN :i AND :f');

                $stmt->execute(['uid'=>$usuarioId,'i'=>$inicio,'f'=>$fim]);

                $despesas = (float) $stmt->fetchColumn();

    }  catch (Throwable $e) {

                error_log('CashPilot143/VisaoPF: '.$e->getMessage());

    }

        $planejamento = cp14ResumoPlanejamento($pdo, $usuarioId, $competencia);

        $patrimonio = cp14PatrimonioPF($pdo, $usuarioId);

        $investimentos = cp14ResumoInvestimentos($pdo, $usuarioId);

        $alertas = [];

        $positivos = [];

        $categoriasPlanejadas = 0;

        $categoriasDentro = 0;

        foreach ($planejamento['categorias'] as $categoria) {

                $status = cp143StatusPlanejamento((float)$categoria['planejado'], (float)$categoria['realizado']);

                if ((float)$categoria['planejado'] <= 0) continue;

                $categoriasPlanejadas++;

                if ($status[0] === 'normal') $categoriasDentro++;

                if ($status[0] === 'atencao') {

                        $alertas[] = 'A categoria '.$categoria['nome'].' já utilizou '.number_format((float)$status[2], 0, ',', '.').'% do limite do mês.';

        }  elseif ($status[0] === 'ultrapassado') {

                        $alertas[] = 'A categoria '.$categoria['nome'].' ultrapassou o planejamento em '.formatarMoeda(max(0, (float)$categoria['realizado']-(float)$categoria['planejado'])).'.';

        }

    }

        if ($receitas > 0 && $receitas >= $despesas) {

                $positivos[] = 'As receitas do mês estão acima das despesas registradas.';

    }  elseif ($despesas > $receitas && $despesas > 0) {

                $alertas[] = 'As despesas do mês estão acima das receitas em '.formatarMoeda($despesas-$receitas).'.';

    }

        if ($categoriasPlanejadas > 0 && $categoriasDentro === $categoriasPlanejadas) {

                $positivos[] = 'Todas as categorias planejadas permanecem dentro dos limites definidos.';

    }

        $metas = [];

        try {

                $stmt=$pdo->prepare('SELECT id,titulo,valor_meta,valor_atual,prazo FROM metas WHERE usuario_id=:uid AND concluida=0 ORDER BY prazo IS NULL,prazo ASC LIMIT 4');

                $stmt->execute(['uid'=>$usuarioId]);

                $metas=$stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($metas as &$m) {

                        $m['percentual']=(float)$m['valor_meta']>0?min(100,(float)$m['valor_atual']/(float)$m['valor_meta']*100):0;

                        $m['falta']=max(0,(float)$m['valor_meta']-(float)$m['valor_atual']);

                        $m['mensal_necessario']=null;

                        if (!empty($m['prazo']) && strtotime($m['prazo']) > time() && $m['falta'] > 0) {

                                $agora=new DateTimeImmutable('today');
                 $prazo=new DateTimeImmutable($m['prazo']);

                                $diff=$agora->diff($prazo);
                 $meses=max(1,$diff->y*12+$diff->m+($diff->d>0?1:0));

                                $m['mensal_necessario']=$m['falta']/$meses;

            }

        }

                unset($m);

    }  catch (Throwable $e) {

    }

        $reserva = function_exists('cpReservaResumo') ? cpReservaResumo($pdo,$usuarioId) : ['cobertura_meses'=>0,'valor_atual'=>0];

        if (($reserva['cobertura_meses'] ?? 0) < 1) $alertas[]='A reserva registrada ainda cobre menos de um mês de gastos essenciais.';

        elseif (($reserva['cobertura_meses'] ?? 0) >= 3) $positivos[]='A reserva registrada já cobre pelo menos três meses de gastos essenciais.';

        return [
            'receitas'=>$receitas,'despesas'=>$despesas,'resultado'=>$receitas-$despesas,
            'planejamento'=>$planejamento,'patrimonio'=>$patrimonio,'investimentos'=>$investimentos,
            'reserva'=>$reserva,'metas'=>$metas,'alertas'=>array_slice($alertas,0,6),'positivos'=>array_slice($positivos,0,5),
            'categorias_planejadas'=>$categoriasPlanejadas,'categorias_dentro'=>$categoriasDentro,
        ];

}

/** CashPilot 14.3 - visão integrada do negócio. */
function cp143VisaoFinanceiraMEI(PDO $pdo, int $usuarioId): array
 {

        $inicio=date('Y-m-01');
     $fim=date('Y-m-t');

        $total=function(string $tabela,string $campo) use($pdo,$usuarioId,$inicio,$fim):float {

                $stmt=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM {$tabela} WHERE usuario_id=:uid AND {$campo} BETWEEN :i AND :f");

                $stmt->execute(['uid'=>$usuarioId,'i'=>$inicio,'f'=>$fim]);
         return (float)$stmt->fetchColumn();

    };

        try {
         $receitas=$total('receitas','data_receita');
         $despesas=$total('despesas','data_despesa');

    }  catch(Throwable $e) {
        $receitas=0;
        $despesas=0;

    }

        $projecao=cp14ProjecaoCaixaDetalhada($pdo,$usuarioId,30);

        $alertas=[];
    $positivos=[];

        if ($receitas>=$despesas && $receitas>0) $positivos[]='O resultado registrado do mês está positivo.';

        elseif ($despesas>$receitas) $alertas[]='As despesas do mês superam as receitas em '.formatarMoeda($despesas-$receitas).'.';

        if ((float)$projecao['menor_saldo']<0) $alertas[]='A projeção indica possibilidade de caixa negativo por volta de '.date('d/m/Y',strtotime($projecao['menor_data'])).'.';

        else $positivos[]='Com os dados atuais, a projeção de 30 dias permanece positiva.';

        if ((float)$projecao['compromissos_previstos']>0) $alertas[]='Há '.formatarMoeda((float)$projecao['compromissos_previstos']).' em saídas futuras registradas nos próximos 30 dias.';

        return ['receitas'=>$receitas,'despesas'=>$despesas,'resultado'=>$receitas-$despesas,'projecao'=>$projecao,'alertas'=>$alertas,'positivos'=>$positivos];

}
