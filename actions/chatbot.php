<?php
/*
 * Endpoint JSON do Copiloto.
 * Erros e warnings não são impressos na resposta, pois isso quebraria o JSON
 * consumido pelo JavaScript. Detalhes técnicos continuam indo para o log do PHP.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/ia.php';

require_once __DIR__ . '/../includes/negocio_financeiro.php';

require_once __DIR__ . '/../includes/inteligencia_financeira.php';

require_once __DIR__ . '/../includes/aprender_helper.php';

require_once __DIR__ . '/../includes/copiloto_contexto.php';

require_once __DIR__ . '/../includes/copiloto_prompt.php';

header('Content-Type: application/json; charset=utf-8');

$pdoChatHistorico = null;

$usuarioChatHistorico = null;

$conversaChatHistorico = null;

$salvarChatHistorico = false;

$uiChat = null;

function responderChatbot(string $resposta, string $modo = 'local'): never
{
    global $pdoChatHistorico,
        $usuarioChatHistorico,
        $conversaChatHistorico,
        $salvarChatHistorico,
        $uiChat;

    if (
        !empty($salvarChatHistorico) &&
        $pdoChatHistorico instanceof PDO &&
        $conversaChatHistorico &&
        $usuarioChatHistorico
    ) {
        try {
            $s = $pdoChatHistorico->prepare(
                'INSERT INTO
                        copiloto_mensagens (conversa_id, usuario_id, papel, mensagem)
                    VALUES
                        (:cid, :uid, "assistente", :msg)'
            );

            $s->execute([
                'cid' => $conversaChatHistorico,
                'uid' => $usuarioChatHistorico,
                'msg' => $resposta
            ]);

            $u = $pdoChatHistorico->prepare(
                'UPDATE copiloto_conversas SET atualizado_em=NOW() WHERE id=:cid AND usuario_id=:uid'
            );

            $u->execute(['cid' => $conversaChatHistorico, 'uid' => $usuarioChatHistorico]);
        } catch (Throwable $e) {
            cp17Log('HistoricoIA', $e);
        }
    }

    echo json_encode(
        [
            'resposta' => $resposta,
            'modo' => $modo,
            'conversa_id' => $conversaChatHistorico ?: null,
            'ui' => $uiChat
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit();
}

function limparRespostaTecnicaCopiloto(string $resposta): string
{
    $chavesInternas = [
        'intencoes_detectadas',
        'dados_especificos',
        'resumo_periodo',
        'periodo_interpretado',
        'saldo_atual',
        'mes_atual',
        'mes_anterior',
        'regra_saldo',
        'indicadores_financeiros'
    ];

    $padraoChaves = implode('|', array_map('preg_quote', $chavesInternas));

    $resposta = preg_replace(
        '/\(?\s*`?(?:' . $padraoChaves . ')`?\s*:\s*(?:\[[^\]]*\]|\{[^}]*\}|[^)\n]+)\s*\)?/iu',
        '',
        $resposta
    ) ?? $resposta;

    $substituicoes = [
        'intencoes_detectadas' => 'assunto identificado',
        'dados_especificos' => 'dados analisados',
        'resumo_periodo' => 'resumo do período',
        'periodo_interpretado' => 'período analisado',
        'saldo_atual' => 'saldo atual',
        'mes_atual' => 'mês atual',
        'mes_anterior' => 'mês anterior',
        'regra_saldo' => 'cálculo do saldo',
        'indicadores_financeiros' => 'indicadores financeiros'
    ];

    $resposta = str_ireplace(array_keys($substituicoes), array_values($substituicoes), $resposta);
    $resposta = preg_replace('/[ \t]{2,}/u', ' ', $resposta) ?? $resposta;
    $resposta = preg_replace('/\n{3,}/u', "\n\n", $resposta) ?? $resposta;

    return trim($resposta);
}

function somaChat(
    PDO $pdo,
    string $tabela,
    string $campo,
    int $uid,
    string $inicio,
    string $fim
): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(valor),0)
         FROM {$tabela}
         WHERE usuario_id=:uid
           AND {$campo} BETWEEN :inicio AND :fim"
    );

    $stmt->execute([
        'uid' => $uid,
        'inicio' => $inicio,
        'fim' => $fim
    ]);

    return (float) $stmt->fetchColumn();
}

function resumoCategoriasChat(PDO $pdo, int $uid, string $inicio, string $fim): array
{
    $stmt = $pdo->prepare(
        'SELECT
                COALESCE(c.nome, \'Outros\') AS categoria,
                SUM(d.valor) AS total
            FROM
                despesas d
                LEFT JOIN categorias c ON c.id = d.categoria_id
            WHERE
                d.usuario_id = :uid
                AND d.data_despesa BETWEEN :inicio AND :fim
                AND d.status = \'efetivado\'
                AND d.data_despesa <= CURRENT_DATE()
            GROUP BY
                COALESCE(c.nome, \'Outros\')
            ORDER BY
                total DESC
            LIMIT
                5'
    );

    $stmt->execute([
        'uid' => $uid,
        'inicio' => $inicio,
        'fim' => $fim
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cpMontarUIChat(string $pergunta, array $contexto): ?array
{
    $p = cpCopTexto($pergunta);

    $dados = $contexto['dados_especificos'] ?? [];

    $res = $contexto['resumo_periodo'] ?? [];

    if (isset($dados['cashscore'])) {
        $c = $dados['cashscore'];

        $rv = $dados['reserva_emergencia'] ?? ($c['reserva'] ?? []);

        return [
            'tipo' => 'score',
            'titulo' => 'Saúde financeira',
            'score' => (int) ($c['score'] ?? 0),
            'nivel' => $c['nivel'] ?? '',
            'metricas' => [
                [
                    'rotulo' => 'Receitas',
                    'valor' => (float) ($c['receitas_mes'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Despesas',
                    'valor' => (float) ($c['despesas_mes'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Reserva',
                    'valor' => (float) ($rv['valor_atual'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Cobertura',
                    'valor' => (float) ($rv['cobertura_meses'] ?? 0),
                    'formato' => 'meses'
                ]
            ]
        ];
    }

    if (
        isset($dados['desempenho_negocio']) &&
        cpCopTem($p, ['produto', 'margem', 'ticket', 'venda', 'desempenho', 'fornecedor', 'custo'])
    ) {
        $d = $dados['desempenho_negocio'];

        $itens = array_slice($d['itens'] ?? [], 0, 5);

        return [
            'tipo' => 'negocio',
            'titulo' => 'Desempenho do negócio',
            'metricas' => [
                [
                    'rotulo' => 'Faturamento',
                    'valor' => (float) ($d['resumo']['faturamento'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Ticket médio',
                    'valor' => (float) ($d['resumo']['ticket'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Margem',
                    'valor' => (float) ($d['resumo']['margem'] ?? 0),
                    'formato' => 'percentual'
                ]
            ],
            'ranking' => array_map(
                fn($x) => [
                    'nome' => $x['nome_item'] ?? 'Item',
                    'valor' => (float) ($x['faturamento'] ?? 0),
                    'margem' => (float) ($x['margem'] ?? 0)
                ],
                $itens
            )
        ];
    }

    if (
        isset($dados['evolucao_mensal']) &&
        cpCopTem($p, [
            'evolucao',
            'evolução',
            'ultimos meses',
            'últimos meses',
            'compare',
            'comparar',
            'receitas e despesas',
            'entradas e despesas'
        ])
    ) {
        return [
            'tipo' => 'grafico_linhas',
            'titulo' => 'Evolução dos últimos 6 meses',
            'series' => $dados['evolucao_mensal']
        ];
    }

    if (
        isset($dados['categorias_despesa']) &&
        cpCopTem($p, ['gasto', 'despesa', 'categoria', 'onde'])
    ) {
        return [
            'tipo' => 'barras',
            'titulo' => 'Principais despesas',
            'itens' => array_map(
                fn($x) => [
                    'nome' => $x['categoria'] ?? 'Outros',
                    'valor' => (float) ($x['total'] ?? 0)
                ],
                array_slice($dados['categorias_despesa'], 0, 6)
            )
        ];
    }

    if (isset($dados['previsao_caixa'])) {
        $x = $dados['previsao_caixa'];

        return [
            'tipo' => 'previsao',
            'titulo' => 'Previsão de caixa',
            'metricas' => [
                [
                    'rotulo' => 'Saldo atual',
                    'valor' => (float) ($x['saldo_atual'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Receita prevista',
                    'valor' => (float) ($x['receita_prevista'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Compromissos',
                    'valor' => (float) ($x['compromissos_previstos'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Projetado',
                    'valor' => (float) ($x['caixa_projetado'] ?? 0),
                    'formato' => 'moeda'
                ]
            ]
        ];
    }

    if (cpCopTem($p, ['saldo', 'resultado', 'explique meu mes', 'como estou'])) {
        return [
            'tipo' => 'resumo',
            'titulo' => $contexto['periodo_interpretado']['rotulo'] ?? 'Resumo',
            'metricas' => [
                [
                    'rotulo' => 'Receitas',
                    'valor' => (float) ($res['receitas'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Despesas',
                    'valor' => (float) ($res['despesas'] ?? 0),
                    'formato' => 'moeda'
                ],
                [
                    'rotulo' => 'Resultado',
                    'valor' => (float) ($res['resultado'] ?? 0),
                    'formato' => 'moeda'
                ]
            ]
        ];
    }

    return null;
}

function cpPlanoSugeridoChat(string $pergunta, array $contexto): ?array
{
    $p = cpCopTexto($pergunta);

    if (
        !cpCopTem($p, [
            'plano',
            'reduzir',
            'melhorar',
            'o que fazer',
            'proximos passos',
            'ações',
            'acoes'
        ])
    ) {
        return null;
    }

    $perfil = $contexto['perfil'] ?? 'pessoa_fisica';

    $dados = $contexto['dados_especificos'] ?? [];

    if ($perfil === 'mei') {
        $steps = [
            'Revisar o principal custo ou queda de margem identificada',
            'Definir uma meta mensurável para os próximos 30 dias',
            'Reavaliar o resultado com os mesmos indicadores ao final do período'
        ];

        if (isset($dados['desempenho_negocio']['fornecedores'][0])) {
            $steps[0] =
                'Revisar custos e condições do fornecedor ' .
                ($dados['desempenho_negocio']['fornecedores'][0]['nome'] ?? 'principal');
        }

        return [
            'titulo' => 'Plano de melhoria do negócio',
            'descricao' => 'Plano criado a partir da análise do Copiloto.',
            'itens' => $steps
        ];
    }

    $steps = [
        'Revisar a categoria de maior gasto e definir um limite realista',
        'Separar um valor para reserva ou meta no início do mês',
        'Comparar o resultado novamente em 30 dias'
    ];

    if (isset($dados['categorias_despesa'][0])) {
        $steps[0] =
            'Revisar gastos de ' .
            ($dados['categorias_despesa'][0]['categoria'] ?? 'maior categoria') .
            ' e definir um limite mensal';
    }

    return [
        'titulo' => 'Plano para melhorar minhas finanças',
        'descricao' => 'Plano criado a partir da análise do Copiloto.',
        'itens' => $steps
    ];
}

function chamarCopilotoIAChat(string $pergunta, array $contexto): array
{
    if (!cp17PermiteIA(conectar(), (int) usuarioLogadoId())) {
        return [
            'ok' => false,
            'motivo' => 'sem_autorizacao',
            'response' => null,
            'http_status' => null
        ];
    }
    // Não enviar detalhes de funcionários, fornecedores, lançamentos ou contas.
    $contextoMinimo = array_intersect_key(
        $contexto,
        array_flip([
            'perfil',
            'periodo_interpretado',
            'saldo_atual',
            'regra_saldo',
            'resumo_periodo',
            'mes_atual',
            'mes_anterior'
        ])
    );

    $dados = $contexto['dados_especificos'] ?? [];
    $indicadoresPermitidos = [
        'cashscore',
        'reserva_emergencia',
        'planejamento_mes_atual',
        'investimentos',
        'patrimonio_acompanhado',
        'previsao_caixa',
        'resumo_vendas'
    ];

    foreach ($indicadoresPermitidos as $indicador) {
        if (array_key_exists($indicador, $dados)) {
            $contextoMinimo['indicadores_financeiros'][$indicador] = $dados[$indicador];
        }
    }

    if (isset($dados['desempenho_negocio']['resumo'])) {
        $contextoMinimo['indicadores_financeiros']['desempenho_negocio'] =
            $dados['desempenho_negocio']['resumo'];
    }
    $mensagens = montarMensagensCopiloto($pergunta, $contextoMinimo);

    $resultado = enviarParaIADetalhado($mensagens);

    if (!empty($resultado['ok'])) {
        $resultado['motivo'] = 'respondido';
        $resultado['http_status'] = (int) (
            ($resultado['used_fallback'] ?? false)
                ? ($resultado['fallback']['http_status'] ?? 0)
                : ($resultado['primary']['http_status'] ?? 0)
        );

        return $resultado;
    }

    $ultimaTentativa = $resultado['fallback'] ?? $resultado['primary'] ?? [];
    $resultado['motivo'] = 'indisponivel';
    $resultado['http_status'] = isset($ultimaTentativa['http_status'])
        ? (int) $ultimaTentativa['http_status']
        : null;

    return $resultado;
}

try {
    exigirLogin();
    exigirPost();

    validarCsrf();

    if (!cashpilotLimitarRequisicoesSessao('copiloto', 20, 60)) {
        http_response_code(429);
        responderChatbot('Muitas mensagens em pouco tempo. Aguarde um instante e tente novamente.');
    }

    $pergunta = trim((string) ($_POST['mensagem'] ?? ''));

    if ($pergunta === '') {
        responderChatbot('Escreva uma pergunta para o Copiloto.');
    }

    if (mb_strlen($pergunta) > 800) {
        responderChatbot('Para eu analisar melhor, envie uma pergunta mais curta.');
    }

    $pdo = conectar();

    $usuarioId = (int) usuarioLogadoId();

    if (!cp17PermiteIA($pdo, $usuarioId)) {
        http_response_code(403);
        responderChatbot(
            'Aceite os termos de uso da inteligência artificial na página do Copiloto para continuar.',
            'consentimento_pendente'
        );
    }

    $salvarHistorico = !empty($_POST['salvar_historico']);

    $conversaId = (int) ($_POST['conversa_id'] ?? 0);

    $historicoConversa = [];

    if ($salvarHistorico) {
        try {
            if ($conversaId > 0) {
                $cv = $pdo->prepare(
                    'SELECT id FROM copiloto_conversas WHERE id=:id AND usuario_id=:uid'
                );

                $cv->execute(['id' => $conversaId, 'uid' => $usuarioId]);

                if (!$cv->fetchColumn()) {
                    $conversaId = 0;
                }
            }

            if ($conversaId === 0) {
                $titulo = mb_substr(preg_replace('/\s+/', ' ', $pergunta), 0, 70);

                $cv = $pdo->prepare(
                    'INSERT INTO copiloto_conversas (usuario_id,titulo) VALUES (:uid,:titulo)'
                );

                $cv->execute(['uid' => $usuarioId, 'titulo' => $titulo ?: 'Nova conversa']);

                $conversaId = (int) $pdo->lastInsertId();
            }

            $h = $pdo->prepare(
                'SELECT
                        papel,
                        mensagem
                    FROM
                        copiloto_mensagens
                    WHERE
                        conversa_id = :cid
                        AND usuario_id = :uid
                    ORDER BY
                        id DESC
                    LIMIT
                        20'
            );

            $h->execute(['cid' => $conversaId, 'uid' => $usuarioId]);

            $historicoConversa = array_reverse($h->fetchAll(PDO::FETCH_ASSOC));

            $hm = $pdo->prepare(
                'INSERT INTO
                        copiloto_mensagens (conversa_id, usuario_id, papel, mensagem)
                    VALUES
                        (:cid, :uid, "usuario", :msg)'
            );

            $hm->execute(['cid' => $conversaId, 'uid' => $usuarioId, 'msg' => $pergunta]);

            $pdoChatHistorico = $pdo;

            $usuarioChatHistorico = $usuarioId;

            $conversaChatHistorico = $conversaId;

            $salvarChatHistorico = true;
        } catch (Throwable $e) {
            cp17Log('HistoricoCopiloto', $e);
        }
    }

    $stmt = $pdo->prepare('SELECT nome,tipo_perfil,nicho FROM usuarios WHERE id=:uid LIMIT 1');

    $stmt->execute(['uid' => $usuarioId]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $tipoPerfil = (string) ($usuario['tipo_perfil'] ?? 'pessoa_fisica');

    $paginaOrigem = trim((string) ($_POST['pagina'] ?? ''));

    $contexto = cpMontarContextoCopiloto(
        $pdo,
        $usuarioId,
        $usuario,
        $pergunta,
        $historicoConversa,
        $paginaOrigem
    );

    $uiChat = cpMontarUIChat($pergunta, $contexto);

    $planoChat = cpPlanoSugeridoChat($pergunta, $contexto);

    if ($planoChat) {
        if (!$uiChat) {
            $uiChat = ['tipo' => 'texto'];
        }

        $uiChat['plano'] = $planoChat;
    }

    try {
        $aula = cpVideoRelacionado($pdo, $tipoPerfil, $pergunta);

        if ($aula) {
            $contexto['aula_relacionada'] = $aula;
        }
    } catch (Throwable $e) {
    }

    $resultadoIA = chamarCopilotoIAChat($pergunta, $contexto);

    $respostaIA = $resultadoIA['response'] ?? null;

    if (is_string($respostaIA) && trim($respostaIA) !== '') {
        responderChatbot(limparRespostaTecnicaCopiloto($respostaIA), 'groq');
    }

    $statusIA = (int) ($resultadoIA['http_status'] ?? 0);

    if ($statusIA === 429) {
        http_response_code(429);
        responderChatbot(
            'O Copiloto atingiu um limite temporário de uso. Aguarde um pouco e tente novamente.',
            'erro'
        );
    }

    http_response_code(503);
    responderChatbot(
        'A inteligência artificial não respondeu nesta tentativa. Aguarde alguns instantes e tente novamente.',
        'erro'
    );
} catch (Throwable $e) {
    cp17Log('Copiloto', $e);

    responderChatbot(
        'O Copiloto encontrou uma dificuldade técnica ao consultar seus dados. Atualize a página e tente novamente.',
        'erro'
    );
}
