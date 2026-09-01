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

$pdoChatHistorico=null;

$usuarioChatHistorico=null;

$conversaChatHistorico=null;

$salvarChatHistorico=false;

$uiChat=null;

function responderChatbot(string $resposta, string $modo = 'local'): never
 {

        global $pdoChatHistorico,$usuarioChatHistorico,$conversaChatHistorico,$salvarChatHistorico,$uiChat;

        if(!empty($salvarChatHistorico) && $pdoChatHistorico instanceof PDO && $conversaChatHistorico && $usuarioChatHistorico) {

                try {

                        $s=$pdoChatHistorico->prepare('INSERT INTO copiloto_mensagens (conversa_id,usuario_id,papel,mensagem) VALUES (:cid,:uid,"assistente",:msg)');

                        $s->execute(['cid'=>$conversaChatHistorico,'uid'=>$usuarioChatHistorico,'msg'=>$resposta]);

                        $u=$pdoChatHistorico->prepare('UPDATE copiloto_conversas SET atualizado_em=NOW() WHERE id=:cid AND usuario_id=:uid');

                        $u->execute(['cid'=>$conversaChatHistorico,'uid'=>$usuarioChatHistorico]);

        }
        catch(Throwable $e) {

            error_log('CashPilot/Histórico IA: '.$e->getMessage());

        }

    }

        echo json_encode(['resposta'=>$resposta,'modo'=>$modo,'conversa_id'=>$conversaChatHistorico?:null,'ui'=>$uiChat],JSON_UNESCAPED_UNICODE);

        exit;

}

function somaChat(PDO $pdo, string $tabela, string $campo, int $uid, string $inicio, string $fim): float
 {

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(valor),0)
         FROM {$tabela}
         WHERE usuario_id=:uid
           AND {$campo} BETWEEN :inicio AND :fim"
        );

        $stmt->execute([
            'uid' => $uid,
            'inicio' => $inicio,
            'fim' => $fim,
        ]);

        return (float) $stmt->fetchColumn();

}

function resumoCategoriasChat(PDO $pdo, int $uid, string $inicio, string $fim): array
 {

        $stmt = $pdo->prepare(
            "SELECT COALESCE(c.nome,'Outros') AS categoria,
                SUM(d.valor) AS total
         FROM despesas d
         LEFT JOIN categorias c ON c.id=d.categoria_id
         WHERE d.usuario_id=:uid
           AND d.data_despesa BETWEEN :inicio AND :fim
         GROUP BY COALESCE(c.nome,'Outros')
         ORDER BY total DESC
         LIMIT 5"
        );

        $stmt->execute([
            'uid' => $uid,
            'inicio' => $inicio,
            'fim' => $fim,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

function respostaLocalChat(string $pergunta, array $contexto): string {

        $p=cpCopTexto($pergunta);

        $r=(float)($contexto['resumo_periodo']['receitas']??0);

        $d=(float)($contexto['resumo_periodo']['despesas']??0);

        $saldo=(float)($contexto['resumo_periodo']['resultado']??0);

        $rotulo=$contexto['periodo_interpretado']['rotulo']??'período selecionado';

        $dados=$contexto['dados_especificos']??[];

        $mei=($contexto['perfil']??'')==='mei';

        if(isset($dados['investimentos'])&&cpCopTem($p,['investimento','investimentos','carteira','aporte','patrimonio','patrimônio'])) {

                $x=$dados['investimentos'];

                return 'Sua carteira acompanhada no CashPilot soma '.formatarMoeda((float)($x['valor_atual']??0)).', com '.formatarMoeda((float)($x['valor_aplicado']??0)).' de capital aplicado. O resultado acompanhado é '.formatarMoeda((float)($x['resultado']??0)).'. Posso explicar distribuição e relação com metas e reserva, mas não indicar compra ou venda de ativos.';

    }

        if(isset($dados['cashscore'])&&cpCopTem($p,['cashscore','score','saude financeira','saúde financeira'])) {

                $c=$dados['cashscore'];

        $rv=$dados['reserva_emergencia']??($c['reserva']??[]);

                return 'Seu CashScore atual é '.(int)($c['score']??0).'/100 ('.($c['nivel']??'sem classificação').'). A reserva registrada cobre aproximadamente '.number_format((float)($rv['cobertura_meses']??0),1,',','.').' mês(es) dos gastos essenciais estimados. Abra Saúde Financeira para ver os fatores que aumentam e reduzem a nota.';

    }

        if(isset($dados['previsao_caixa'])&&cpCopTem($p,['previsao','previsão','caixa projetado'])) {

                $x=$dados['previsao_caixa'];

                return 'A projeção para os próximos '.(int)($x['dias']??30).' dias parte de '.formatarMoeda((float)($x['saldo_atual']??0)).' de saldo realizado, estima '.formatarMoeda((float)($x['receita_prevista']??0)).' em entradas e '.formatarMoeda((float)($x['compromissos_previstos']??0)).' em compromissos. O caixa projetado fica em '.formatarMoeda((float)($x['caixa_projetado']??0)).'. É uma estimativa baseada no histórico, não uma garantia.';

    }

        if($mei&&isset($dados['desempenho_negocio'])&&cpCopTem($p,['margem','ticket','produto','desempenho','venda'])) {

                $v=$dados['desempenho_negocio']['resumo']??[];

                return 'No '.$rotulo.', as vendas registradas somam '.formatarMoeda((float)($v['faturamento']??0)).', com ticket médio de '.formatarMoeda((float)($v['ticket']??0)).' e margem bruta de '.number_format((float)($v['margem']??0),1,',','.').'%.';

    }

        /* Respostas conceituais úteis mesmo se a API estiver temporariamente indisponível. */
        if(cpCopTem($p,['o que e margem','o que é margem','margem de lucro']))return 'Margem de lucro mostra quanto sobra da venda depois dos custos considerados. No CashPilot, a margem bruta dos itens usa preço de venda e custo unitário; despesas operacionais como equipe e aluguel precisam ser analisadas separadamente para entender o resultado final.';

        if(cpCopTem($p,['ticket medio','ticket médio']))return 'Ticket médio é o faturamento dividido pelo número de vendas. Ele ajuda a entender quanto, em média, cada venda gera de receita.';

        if(cpCopTem($p,['fluxo de caixa']))return 'Fluxo de caixa acompanha quando dinheiro entra e sai. Um negócio pode ter lucro e ainda enfrentar falta de caixa se pagamentos vencerem antes das entradas.';

        if(cpCopTem($p,['reserva de emergencia','reserva de emergência']))return 'Reserva de emergência é um valor separado para imprevistos. O CashPilot usa seus gastos essenciais médios como referência para estimar quantos meses sua reserva consegue cobrir.';

        if(cpCopTem($p,['orcamento','orçamento'])&&!isset($dados['orcamentos']))return 'Orçamento é um limite planejado para o uso do dinheiro. No CashPilot você pode definir limites por categoria e comparar o planejado com o gasto realizado.';

        if(cpCopTem($p,['saldo','resultado','como estou','situacao','situação','explique'])) return 'No '.$rotulo.', o CashPilot registrou '.formatarMoeda($r).' em receitas e '.formatarMoeda($d).' em despesas. O resultado é '.formatarMoeda($saldo).'.';

        if(cpCopTem($p,['gasto','gastei','despesa','custo'])) {

                $cats=$dados['categorias_despesa']??[];

        $x='No '.$rotulo.', suas despesas somam '.formatarMoeda($d).'.';

                if($cats) {

            $c=$cats[0];

            $x.=' A maior categoria registrada é '.($c['categoria']??'Outros').', com '.formatarMoeda((float)($c['total']??0)).'.';

        }

                return $x;

    }

        if(cpCopTem($p,['receita','recebi','renda','entrada'])) return 'No '.$rotulo.', as receitas registradas somam '.formatarMoeda($r).'.';

        if($mei&&isset($dados['resumo_vendas'])) {

        $v=$dados['resumo_vendas'];

        return 'No '.$rotulo.', há '.(int)($v['vendas']??0).' venda(s) vinculadas ao catálogo, com '.formatarMoeda((float)($v['receita_vendas']??0)).' em faturamento de vendas e margem bruta de '.number_format((float)($v['margem_bruta']??0),1,',','.').'%. ';

    }

        return 'A IA externa não respondeu nesta tentativa. O CashPilot continua com acesso aos dados locais, mas essa pergunta é aberta demais para eu responder com segurança sem o modelo conectado. Verifique a configuração do Groq e tente novamente.';

}

function cpMontarUIChat(string $pergunta,array $contexto):?array {

        $p=cpCopTexto($pergunta);

    $dados=$contexto['dados_especificos']??[];

    $res=$contexto['resumo_periodo']??[];

        if(isset($dados['cashscore'])) {

                $c=$dados['cashscore'];

        $rv=$dados['reserva_emergencia']??($c['reserva']??[]);

                return ['tipo'=>'score','titulo'=>'Saúde financeira','score'=>(int)($c['score']??0),'nivel'=>$c['nivel']??'','metricas'=>[
                    ['rotulo'=>'Receitas','valor'=>(float)($c['receitas_mes']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Despesas','valor'=>(float)($c['despesas_mes']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Reserva','valor'=>(float)($rv['valor_atual']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Cobertura','valor'=>(float)($rv['cobertura_meses']??0),'formato'=>'meses'],
                ]];

    }

        if(isset($dados['desempenho_negocio']) && (cpCopTem($p,['produto','margem','ticket','venda','desempenho','fornecedor','custo']))) {

                $d=$dados['desempenho_negocio'];

        $itens=array_slice($d['itens']??[],0,5);

                return ['tipo'=>'negocio','titulo'=>'Desempenho do negócio','metricas'=>[
                    ['rotulo'=>'Faturamento','valor'=>(float)($d['resumo']['faturamento']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Ticket médio','valor'=>(float)($d['resumo']['ticket']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Margem','valor'=>(float)($d['resumo']['margem']??0),'formato'=>'percentual'],
                ],'ranking'=>array_map(fn($x)=>['nome'=>$x['nome_item']??'Item','valor'=>(float)($x['faturamento']??0),'margem'=>(float)($x['margem']??0)],$itens)];

    }

        if(isset($dados['categorias_despesa']) && cpCopTem($p,['gasto','despesa','categoria','onde'])) {

                return ['tipo'=>'barras','titulo'=>'Principais despesas','itens'=>array_map(fn($x)=>['nome'=>$x['categoria']??'Outros','valor'=>(float)($x['total']??0)],array_slice($dados['categorias_despesa'],0,6))];

    }

        if(isset($dados['previsao_caixa'])) {

                $x=$dados['previsao_caixa'];

                return ['tipo'=>'previsao','titulo'=>'Previsão de caixa','metricas'=>[
                    ['rotulo'=>'Saldo atual','valor'=>(float)($x['saldo_atual']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Receita prevista','valor'=>(float)($x['receita_prevista']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Compromissos','valor'=>(float)($x['compromissos_previstos']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Projetado','valor'=>(float)($x['caixa_projetado']??0),'formato'=>'moeda'],
                ]];

    }

        if(cpCopTem($p,['saldo','resultado','explique meu mes','como estou'])) {

                return ['tipo'=>'resumo','titulo'=>$contexto['periodo_interpretado']['rotulo']??'Resumo','metricas'=>[
                    ['rotulo'=>'Receitas','valor'=>(float)($res['receitas']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Despesas','valor'=>(float)($res['despesas']??0),'formato'=>'moeda'],
                    ['rotulo'=>'Resultado','valor'=>(float)($res['resultado']??0),'formato'=>'moeda'],
                ]];

    }

        return null;

}

function cpPlanoSugeridoChat(string $pergunta,array $contexto):?array {

        $p=cpCopTexto($pergunta);

        if(!cpCopTem($p,['plano','reduzir','melhorar','o que fazer','proximos passos','ações','acoes']))return null;

        $perfil=$contexto['perfil']??'pessoa_fisica';

    $dados=$contexto['dados_especificos']??[];

        if($perfil==='mei') {

                $steps=['Revisar o principal custo ou queda de margem identificada','Definir uma meta mensurável para os próximos 30 dias','Reavaliar o resultado com os mesmos indicadores ao final do período'];

                if(isset($dados['desempenho_negocio']['fornecedores'][0]))$steps[0]='Revisar custos e condições do fornecedor '.($dados['desempenho_negocio']['fornecedores'][0]['nome']??'principal');

                return ['titulo'=>'Plano de melhoria do negócio','descricao'=>'Plano criado a partir da análise do Copiloto.','itens'=>$steps];

    }

        $steps=['Revisar a categoria de maior gasto e definir um limite realista','Separar um valor para reserva ou meta no início do mês','Comparar o resultado novamente em 30 dias'];

        if(isset($dados['categorias_despesa'][0]))$steps[0]='Revisar gastos de '.($dados['categorias_despesa'][0]['categoria']??'maior categoria').' e definir um limite mensal';

        return ['titulo'=>'Plano para melhorar minhas finanças','descricao'=>'Plano criado a partir da análise do Copiloto.','itens'=>$steps];

}

function chamarCopilotoIAChat(string $pergunta, array $contexto): ?string
 {

        $mensagens = montarMensagensCopiloto(
            $pergunta,
            $contexto
        );

        return enviarParaIA($mensagens);

}

try {

        exigirLogin();
exigirPost();

        validarCsrf();

        $pergunta = trim((string) ($_POST['mensagem'] ?? ''));

        if ($pergunta === '') {

                responderChatbot('Escreva uma pergunta para o Copiloto.');

    }

        if (mb_strlen($pergunta) > 800) {

                responderChatbot('Para eu analisar melhor, envie uma pergunta mais curta.');

    }

        $pdo = conectar();

        $usuarioId = (int) usuarioLogadoId();

        $salvarHistorico = !empty($_POST['salvar_historico']);

        $conversaId = (int)($_POST['conversa_id'] ?? 0);

        $historicoConversa = [];

        if($salvarHistorico) {

                try {

                        if($conversaId>0) {

                                $cv=$pdo->prepare('SELECT id FROM copiloto_conversas WHERE id=:id AND usuario_id=:uid');

                                $cv->execute(['id'=>$conversaId,'uid'=>$usuarioId]);

                                if(!$cv->fetchColumn())$conversaId=0;

            }

                        if($conversaId===0) {

                                $titulo=mb_substr(preg_replace('/\s+/',' ',$pergunta),0,70);

                                $cv=$pdo->prepare('INSERT INTO copiloto_conversas (usuario_id,titulo) VALUES (:uid,:titulo)');

                                $cv->execute(['uid'=>$usuarioId,'titulo'=>$titulo?:'Nova conversa']);

                                $conversaId=(int)$pdo->lastInsertId();

            }

                        $h=$pdo->prepare('SELECT papel,mensagem FROM copiloto_mensagens WHERE conversa_id=:cid AND usuario_id=:uid ORDER BY id DESC LIMIT 20');

                        $h->execute(['cid'=>$conversaId,'uid'=>$usuarioId]);

                        $historicoConversa=array_reverse($h->fetchAll(PDO::FETCH_ASSOC));

                        $hm=$pdo->prepare('INSERT INTO copiloto_mensagens (conversa_id,usuario_id,papel,mensagem) VALUES (:cid,:uid,"usuario",:msg)');

                        $hm->execute(['cid'=>$conversaId,'uid'=>$usuarioId,'msg'=>$pergunta]);

                        $pdoChatHistorico=$pdo;

            $usuarioChatHistorico=$usuarioId;

            $conversaChatHistorico=$conversaId;

            $salvarChatHistorico=true;

        }
        catch(Throwable $e) {

            error_log('CashPilot/Histórico Copiloto: '.$e->getMessage());

        }

    }

        $stmt=$pdo->prepare('SELECT nome,tipo_perfil,nicho FROM usuarios WHERE id=:uid LIMIT 1');

        $stmt->execute(['uid'=>$usuarioId]);

        $usuario=$stmt->fetch(PDO::FETCH_ASSOC)?:[];

        $tipoPerfil=(string)($usuario['tipo_perfil']??'pessoa_fisica');

        $paginaOrigem=trim((string)($_POST['pagina']??''));

        $contexto=cpMontarContextoCopiloto($pdo,$usuarioId,$usuario,$pergunta,$historicoConversa,$paginaOrigem);

        $uiChat=cpMontarUIChat($pergunta,$contexto);

        $planoChat=cpPlanoSugeridoChat($pergunta,$contexto);

        if($planoChat) {

                if(!$uiChat)$uiChat=['tipo'=>'texto'];

                $uiChat['plano']=$planoChat;

    }

        try {

                $aula=cpVideoRelacionado($pdo,$tipoPerfil,$pergunta);

                if($aula)$contexto['aula_relacionada']=$aula;

    }
    catch(Throwable $e) {

    }

        $respostaIA = chamarCopilotoIAChat($pergunta, $contexto);

        if ($respostaIA !== null && trim($respostaIA) !== '') {

                responderChatbot($respostaIA, 'groq');

    }

        responderChatbot(
            respostaLocalChat($pergunta, $contexto),
            'local'
        );

}
 catch (Throwable $e) {

        error_log('CashPilot/Copiloto fatal: ' . $e->getMessage());

        responderChatbot(
            'O Copiloto encontrou uma dificuldade técnica ao consultar seus dados. Atualize a página e tente novamente.',
            'erro'
        );

}
