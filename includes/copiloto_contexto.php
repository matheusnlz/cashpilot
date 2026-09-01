<?php

require_once __DIR__ . '/cashpilot14_financeiro.php';

/*
 * CashPilot 11.3 — camada de contexto do Copiloto.
 * A IA interpreta; cálculos e consultas dos dados pessoais acontecem aqui.
 */

function cpCopTexto(string $texto): string {

    $t=mb_strtolower(trim($texto));

    $map=['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];

    return strtr($t,$map);
}


function cpCopTem(string $texto, array $termos): bool {

    foreach($termos as $termo) if(str_contains($texto,cpCopTexto($termo))) return true;

    return false;
}


function cpCopPeriodo(string $pergunta): array {

    $p=cpCopTexto($pergunta);
$hoje=new DateTimeImmutable('today');

    $inicio=$hoje->modify('first day of this month');
$fim=$hoje->modify('last day of this month');
$rotulo='mês atual';


    if(preg_match('/ultim(?:o|os|a|as)\\s+(\\d{1,2})\\s+mes/', $p,$m)){

        $n=max(1,min(24,(int)$m[1]));
$inicio=$hoje->modify('first day of this month')->modify('-'.($n-1).' months');
$fim=$hoje->modify('last day of this month');
$rotulo='últimos '.$n.' meses';
}
 elseif(cpCopTem($p,['ultimos 3 meses','trimestre'])){

        $inicio=$hoje->modify('first day of this month')->modify('-2 months');
$rotulo='últimos 3 meses';
}
 elseif(cpCopTem($p,['ultimos 6 meses','semestre'])){

        $inicio=$hoje->modify('first day of this month')->modify('-5 months');
$rotulo='últimos 6 meses';
}
 elseif(cpCopTem($p,['ultimos 12 meses','ultimo ano'])){

        $inicio=$hoje->modify('first day of this month')->modify('-11 months');
$rotulo='últimos 12 meses';
}
 elseif(cpCopTem($p,['mes passado','mes anterior'])){

        $inicio=$hoje->modify('first day of last month');
$fim=$hoje->modify('last day of last month');
$rotulo='mês anterior';
}
 elseif(cpCopTem($p,['este ano','ano atual','no ano','meu ano','esse ano'])){

        $inicio=$hoje->setDate((int)$hoje->format('Y'),1,1);
$fim=$hoje->setDate((int)$hoje->format('Y'),12,31);
$rotulo='ano atual';
}
 elseif(cpCopTem($p,['ontem'])){

        $inicio=$hoje->modify('-1 day');
$fim=$inicio;
$rotulo='ontem';
}
 elseif(cpCopTem($p,['esta semana','essa semana','ultimos 7 dias','últimos 7 dias'])){

        $inicio=$hoje->modify('-6 days');
$fim=$hoje;
$rotulo='últimos 7 dias';
}
 elseif(cpCopTem($p,['hoje'])){

        $inicio=$hoje;
$fim=$hoje;
$rotulo='hoje';
}
 elseif(preg_match('/ultim(?:o|os|a|as)\\s+(\\d{1,3})\\s+dias/', $p,$m)){

        $n=max(1,min(365,(int)$m[1]));
$inicio=$hoje->modify('-'.($n-1).' days');
$fim=$hoje;
$rotulo='últimos '.$n.' dias';
}


    return ['inicio'=>$inicio->format('Y-m-d'),'fim'=>$fim->format('Y-m-d'),'rotulo'=>$rotulo];
}


function cpCopIntencoes(string $pergunta, string $perfil): array {

    $p=cpCopTexto($pergunta);
$i=[];

    $map=[
      'despesas'=>['gasto','gastei','despesa','custo','compras','onde foi meu dinheiro','reduzir gasto'],
      'receitas'=>['receita','recebi','renda','entrada','ganhei'],
      'saldo'=>['saldo','resultado','sobrou','sobra','caixa','como estou','situacao','situação','explique meu mes','explique meu mês'],
      'categorias'=>['categoria','alimentacao','alimentação','transporte','lazer','moradia','assinatura','onde gasto','maior gasto'],
      'movimentacoes'=>['transacao','transação','movimentacao','movimentação','compra de','paguei','historico','histórico'],
      'metas'=>['meta','objetivo','juntar','economizar para','guardar para'],
      'orcamento'=>['orcamento','orçamento','limite','posso gastar','quanto posso gastar'],
      'recorrencias'=>['recorrencia','recorrência','assinatura','fixo','fixos','mensalidade'],
      'planejamento'=>['planejamento','planejar','proximo mes','próximo mês','planejado','realizado'],
      'investimentos'=>['investimento','investimentos','carteira','aporte','aportes','renda fixa','tesouro','acao','ação','acoes','ações','fii','fiis','etf','cripto'],
      'patrimonio'=>['patrimonio','patrimônio','patrimonio liquido','patrimônio líquido'],
      'financiamento'=>['financiamento','financiar','parcela','parcelas','juros'],
      'cashscore'=>['cashscore','score financeiro','nota financeira','saude financeira','saúde financeira'],
      'reserva'=>['reserva de emergencia','reserva de emergência','reserva financeira','cobertura de emergencia'],
      'desafios'=>['desafio de economia','desafio','economizar em 30 dias'],
      'planos_acao'=>['plano de acao','plano de ação','acoes praticas','ações práticas','o que fazer','proximos passos','próximos passos'],
      'comparacao'=>['compare','comparar','comparacao','comparação','versus','vs.'],
    ];

    if($perfil==='mei'){

      $map += [
        'vendas'=>['venda','vendas','faturamento','ticket'],
        'margem'=>['margem','lucro bruto','rentabilidade'],
        'estoque'=>['estoque','reposicao','reposição','produto faltando'],
        'produtos'=>['produto','servico','serviço','mais vendido','menos vendido','precificacao','precificação'],
        'funcionarios'=>['funcionario','funcionário','funcionarios','funcionários','equipe','salario','salário','contratar','demitir','folha'],
        'fornecedores'=>['fornecedor','fornecedores'],
        'custos_negocio'=>['custo fixo','custos fixos','custo variavel','custo variável','estrutura de custos'],
        'previsao_caixa'=>['previsao de caixa','previsão de caixa','caixa projetado','projecao de caixa','projeção de caixa','proximos 30 dias'],
        'desempenho'=>['desempenho','ticket medio','ticket médio','top produto','top produtos','produto mais lucrativo','produto com maior margem'],
      ];
}

    foreach($map as $nome=>$termos) if(cpCopTem($p,$termos)) $i[]=$nome;

    if(!$i)$i=['saldo'];

    return array_values(array_unique($i));
}


function cpCopSoma(PDO $pdo,string $tabela,string $campo,int $uid,string $inicio,string $fim): float {

    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM {$tabela} WHERE usuario_id=:uid AND {$campo} BETWEEN :inicio AND :fim");

    $s->execute(['uid'=>$uid,'inicio'=>$inicio,'fim'=>$fim]);
return (float)$s->fetchColumn();
}


function cpCopCategorias(PDO $pdo,int $uid,string $tipo,string $inicio,string $fim,int $limite=12): array {

    $tabela=$tipo==='receita'?'receitas':'despesas';
$alias=$tipo==='receita'?'r':'d';
$campo=$tipo==='receita'?'data_receita':'data_despesa';

    $s=$pdo->prepare("SELECT COALESCE(c.nome,'Outros') categoria,COALESCE(SUM({$alias}.valor),0) total,COUNT(*) quantidade FROM {$tabela} {$alias} LEFT JOIN categorias c ON c.id={$alias}.categoria_id WHERE {$alias}.usuario_id=:uid AND {$alias}.{$campo} BETWEEN :inicio AND :fim GROUP BY COALESCE(c.nome,'Outros') ORDER BY total DESC LIMIT {$limite}");

    $s->execute(['uid'=>$uid,'inicio'=>$inicio,'fim'=>$fim]);
return $s->fetchAll(PDO::FETCH_ASSOC);
}


function cpCopMovimentacoes(PDO $pdo,int $uid,string $inicio,string $fim,int $limite=30): array {

    $sql="(SELECT 'receita' tipo,descricao,valor,data_receita data FROM receitas WHERE usuario_id=:u1 AND data_receita BETWEEN :i1 AND :f1) UNION ALL (SELECT 'despesa' tipo,descricao,valor,data_despesa data FROM despesas WHERE usuario_id=:u2 AND data_despesa BETWEEN :i2 AND :f2) ORDER BY data DESC LIMIT {$limite
}
";

    $s=$pdo->prepare($sql);
$s->execute(['u1'=>$uid,'i1'=>$inicio,'f1'=>$fim,'u2'=>$uid,'i2'=>$inicio,'f2'=>$fim]);
return $s->fetchAll(PDO::FETCH_ASSOC);
}


function cpCopMovimentacoesRelacionadas(PDO $pdo,int $uid,string $pergunta,string $inicio,string $fim): array {

    $stop=['quanto','gastei','gasto','paguei','recebi','compra','compras','despesa','despesas','receita','receitas','ultimo','ultimos','ultima','ultimas','mes','meses','hoje','ontem','esse','este','essa','esta','meu','minha','meus','minhas','com','para','qual','quais','foi','foram','valor','total'];

    $tokens=preg_split('/\\s+/',cpCopTexto($pergunta));

    $tokens=array_values(array_filter(array_unique(array_map(fn($x)=>preg_replace('/[^a-z0-9_-]/','',$x),$tokens)),fn($x)=>mb_strlen($x)>=3&&!in_array($x,$stop,true)));

    if(!$tokens)return [];

    $movs=cpCopMovimentacoes($pdo,$uid,$inicio,$fim,180);
$out=[];

    foreach($movs as $m){
$desc=cpCopTexto((string)$m['descricao']);
$score=0;
foreach($tokens as $tok)if(str_contains($desc,$tok))$score++;
if($score>0){
$m['relevancia']=$score;
$out[]=$m;
}
}

    usort($out,fn($a,$b)=>($b['relevancia']<=>$a['relevancia']) ?: strcmp($b['data'],$a['data']));

    return array_slice($out,0,25);
}


function cpCopCategoriaMencionada(PDO $pdo,int $uid,string $pergunta): ?array {

    $p=cpCopTexto($pergunta);
$s=$pdo->prepare('SELECT id,nome,tipo FROM categorias WHERE usuario_id=:uid ORDER BY CHAR_LENGTH(nome) DESC');
$s->execute(['uid'=>$uid]);

    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $c) if(str_contains($p,cpCopTexto($c['nome']))) return $c;
return null;
}


function cpCopResumoCategoria(PDO $pdo,int $uid,array $categoria,string $inicio,string $fim): array {

    $tipo=$categoria['tipo'];
$t=$tipo==='receita'?'receitas':'despesas';
$campo=$tipo==='receita'?'data_receita':'data_despesa';

    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) total,COUNT(*) quantidade,COALESCE(AVG(valor),0) media,MAX(valor) maior FROM {$t} WHERE usuario_id=:uid AND categoria_id=:cid AND {$campo} BETWEEN :inicio AND :fim");

    $s->execute(['uid'=>$uid,'cid'=>$categoria['id'],'inicio'=>$inicio,'fim'=>$fim]);
$r=$s->fetch(PDO::FETCH_ASSOC)?:[];
return ['nome'=>$categoria['nome'],'tipo'=>$tipo]+$r;
}


function cpCopEntidadesMencionadas(PDO $pdo,int $uid,string $pergunta,string $perfil): array {

    if($perfil!=='mei')return [];
$p=cpCopTexto($pergunta);
$out=[];

    $consultas=[
      'produto'=>['SELECT id,nome,tipo,preco_venda,custo_unitario,estoque_atual,estoque_minimo,controlar_estoque FROM produtos_servicos WHERE usuario_id=:uid AND ativo=1','nome'],
      'funcionario'=>['SELECT id,nome,cargo,salario_base,outros_custos,dia_pagamento FROM funcionarios WHERE usuario_id=:uid AND ativo=1','nome'],
      'fornecedor'=>['SELECT id,nome,descricao,valor_padrao,periodicidade,dia_vencimento FROM fornecedores WHERE usuario_id=:uid AND ativo=1','nome'],
    ];

    foreach($consultas as $tipo=>[$sql,$campo]){
try{
$s=$pdo->prepare($sql);
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll(PDO::FETCH_ASSOC) as $x){
$n=cpCopTexto((string)$x[$campo]);
if(mb_strlen($n)>=3&&str_contains($p,$n)){
$out[$tipo]=$x;
break;
}
}
}
catch(Throwable $e){
}
}

    return $out;
}


function cpCopIntencoesPagina(string $pagina, string $perfil): array {

    $pagina = basename(trim($pagina));


    $mapa = [
        'dashboard.php' => ['saldo', 'categorias'],
        'receitas.php' => ['receitas', 'categorias', 'movimentacoes'],
        'despesas.php' => ['despesas', 'categorias', 'movimentacoes'],
        'transacoes.php' => ['movimentacoes', 'receitas', 'despesas'],
        'metas.php' => ['metas', 'planejamento'],
        'orcamentos.php' => ['orcamento', 'despesas', 'categorias'],
        'recorrencias.php' => ['recorrencias', 'planejamento'],
        'planejamento.php' => ['planejamento', 'saldo', 'investimentos'],
        'investimentos.php' => ['investimentos', 'patrimonio', 'metas', 'reserva'],
        'financiamentos.php' => ['financiamento', 'planejamento'],
        'posso_comprar.php' => ['planejamento', 'saldo', 'metas'],
        'saude_financeira.php' => ['cashscore', 'reserva', 'planejamento'],
        'visao_financeira.php' => ['saldo', 'planejamento', 'metas', 'investimentos', 'patrimonio', 'cashscore', 'reserva', 'previsao_caixa'],
        'relatorios.php' => ['comparacao', 'saldo', 'categorias'],
        'radar.php' => ['saldo', 'planos_acao'],
        'planos_acao.php' => ['planos_acao'],
    ];


    if ($perfil === 'mei') {

        $mapa += [
            'negocio.php' => ['saldo', 'vendas', 'margem', 'custos_negocio'],
            'mei.php' => ['saldo', 'vendas', 'margem', 'previsao_caixa'],
            'desempenho.php' => ['desempenho', 'vendas', 'margem', 'previsao_caixa'],
            'projecao_caixa.php' => ['previsao_caixa', 'saldo', 'custos_negocio'],
            'produtos_servicos.php' => ['produtos', 'estoque', 'margem'],
            'vendas.php' => ['vendas', 'margem', 'produtos'],
            'funcionarios.php' => ['funcionarios', 'custos_negocio'],
            'fornecedores.php' => ['fornecedores', 'custos_negocio'],
            'custos.php' => ['custos_negocio', 'saldo'],
        ];
}


    return $mapa[$pagina] ?? [];
}


function cpMontarContextoCopiloto(PDO $pdo,int $uid,array $usuario,string $pergunta,array $historico=[],string $pagina=''): array {

    $perfil=(string)($usuario['tipo_perfil']??'pessoa_fisica');
$periodo=cpCopPeriodo($pergunta);
$intencoes=array_values(array_unique(array_merge(cpCopIntencoes($pergunta,$perfil),cpCopIntencoesPagina($pagina,$perfil))));

    $inicio=$periodo['inicio'];
$fim=$periodo['fim'];

    $receitas=cpCopSoma($pdo,'receitas','data_receita',$uid,$inicio,$fim);
$despesas=cpCopSoma($pdo,'despesas','data_despesa',$uid,$inicio,$fim);

    $mesInicio=date('Y-m-01');
$mesFim=date('Y-m-t');
$antInicio=date('Y-m-01',strtotime('first day of last month'));
$antFim=date('Y-m-t',strtotime('last day of last month'));

    $contexto=[
      'origem'=>'CashPilot 11.3','pagina_origem'=>$pagina,'perfil'=>$perfil,'nicho'=>$usuario['nicho']??null,
      'periodo_interpretado'=>$periodo,
      'resumo_periodo'=>['receitas'=>$receitas,'despesas'=>$despesas,'resultado'=>$receitas-$despesas],
      'mes_atual'=>['receitas'=>cpCopSoma($pdo,'receitas','data_receita',$uid,$mesInicio,$mesFim),'despesas'=>cpCopSoma($pdo,'despesas','data_despesa',$uid,$mesInicio,$mesFim)],
      'mes_anterior'=>['receitas'=>cpCopSoma($pdo,'receitas','data_receita',$uid,$antInicio,$antFim),'despesas'=>cpCopSoma($pdo,'despesas','data_despesa',$uid,$antInicio,$antFim)],
      'intencoes_detectadas'=>$intencoes,
      'historico_conversa'=>$historico,
      'dados_especificos'=>[],
      'observacoes'=>[],
    ];

    $contexto['mes_atual']['resultado']=$contexto['mes_atual']['receitas']-$contexto['mes_atual']['despesas'];
$contexto['mes_anterior']['resultado']=$contexto['mes_anterior']['receitas']-$contexto['mes_anterior']['despesas'];


    if(array_intersect($intencoes,['despesas','categorias','saldo','orcamento'])) $contexto['dados_especificos']['categorias_despesa']=cpCopCategorias($pdo,$uid,'despesa',$inicio,$fim);

    if(array_intersect($intencoes,['receitas','categorias','saldo'])) $contexto['dados_especificos']['categorias_receita']=cpCopCategorias($pdo,$uid,'receita',$inicio,$fim);

    if(array_intersect($intencoes,['movimentacoes','despesas','receitas'])) $contexto['dados_especificos']['movimentacoes_recentes']=cpCopMovimentacoes($pdo,$uid,$inicio,$fim,35);

    if(array_intersect($intencoes,['movimentacoes','despesas','receitas'])){
$rel=cpCopMovimentacoesRelacionadas($pdo,$uid,$pergunta,$inicio,$fim);
if($rel)$contexto['dados_especificos']['movimentacoes_relacionadas']=$rel;
}


    $cat=cpCopCategoriaMencionada($pdo,$uid,$pergunta);
if($cat)$contexto['dados_especificos']['categoria_mencionada']=cpCopResumoCategoria($pdo,$uid,$cat,$inicio,$fim);


    if ($perfil === 'mei') {

      try {

        $perfilNegocioAtual = cpPerfilNegocio($pdo, $uid);

        $contexto['perfil_negocio'] = [
          'nome_negocio' => $perfilNegocioAtual['nome_negocio'] ?? null,
          'oferta' => $perfilNegocioAtual['oferta'] ?? null,
          'operacao' => $perfilNegocioAtual['operacao'] ?? null,
          'publico_principal' => $perfilNegocioAtual['publico_alvo'] ?? null,
          'objetivo_principal' => $perfilNegocioAtual['objetivo_principal'] ?? null,
        ];
}
 catch (Throwable $e) {
}
}


    if($perfil==='pessoa_fisica'){

      if(array_intersect($intencoes,['metas','saldo','planejamento'])){
try{
$s=$pdo->prepare('SELECT titulo,valor_meta,valor_atual,prazo,concluida FROM metas WHERE usuario_id=:uid ORDER BY concluida,prazo IS NULL,prazo LIMIT 10');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['metas']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['orcamento','despesas','categorias'])){
try{
$s=$pdo->prepare('SELECT c.nome,c.limite_mensal,COALESCE(SUM(d.valor),0) gasto FROM categorias c LEFT JOIN despesas d ON d.categoria_id=c.id AND d.usuario_id=:u1 AND d.data_despesa BETWEEN :i AND :f WHERE c.usuario_id=:u2 AND c.tipo="despesa" AND c.limite_mensal IS NOT NULL GROUP BY c.id,c.nome,c.limite_mensal ORDER BY gasto DESC');
$s->execute(['u1'=>$uid,'i'=>$mesInicio,'f'=>$mesFim,'u2'=>$uid]);
$contexto['dados_especificos']['orcamentos']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['recorrencias','saldo','planejamento'])){
try{
$s=$pdo->prepare('SELECT nome,valor,tipo,periodicidade,proxima_data FROM recorrencias_pf WHERE usuario_id=:uid AND ativo=1 ORDER BY proxima_data LIMIT 20');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['recorrencias']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(in_array('planejamento',$intencoes,true)){

        try{

          $s=$pdo->prepare('SELECT * FROM planejamento_mensal WHERE usuario_id=:uid ORDER BY competencia DESC LIMIT 3');

          $s->execute(['uid'=>$uid]);

          $contexto['dados_especificos']['planejamento']=$s->fetchAll(PDO::FETCH_ASSOC);

          $contexto['dados_especificos']['planejamento_mes_atual']=cp14ResumoPlanejamento($pdo,$uid,date('Y-m'));
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['investimentos','patrimonio','planejamento','metas','reserva'])){

        try{

          $contexto['dados_especificos']['investimentos']=cp14ResumoInvestimentos($pdo,$uid);

          $contexto['dados_especificos']['carteira_investimentos']=array_slice(cp14ListarInvestimentos($pdo,$uid),0,20);

          $contexto['dados_especificos']['patrimonio_acompanhado']=cp14PatrimonioPF($pdo,$uid);
}
catch(Throwable $e){
}
}

      if(in_array('financiamento',$intencoes,true)){
try{
$s=$pdo->prepare('SELECT nome,valor_bem,entrada,taxa_mensal,parcelas,valor_parcela,total_pago,total_juros,criado_em FROM financiamentos_simulados WHERE usuario_id=:uid ORDER BY criado_em DESC LIMIT 10');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['financiamentos_salvos']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['cashscore','reserva','saldo','planejamento','planos_acao'])){
try{
$contexto['dados_especificos']['cashscore']=cpCashScore($pdo,$uid);
$contexto['dados_especificos']['reserva_emergencia']=cpReservaResumo($pdo,$uid);
}
catch(Throwable $e){
}
}

      if(in_array('desafios',$intencoes,true)){
try{
$contexto['dados_especificos']['desafios_economia']=cpDesafiosEconomia($pdo,$uid);
}
catch(Throwable $e){
}
}

      if(in_array('planos_acao',$intencoes,true)){
try{
$contexto['dados_especificos']['planos_acao']=cpPlanosAtivos($pdo,$uid,8);
}
catch(Throwable $e){
}
}
}
else{

      try{
cpSincronizarCustosRecorrentesMes($pdo,$uid);
}
catch(Throwable $e){
}

      $contexto['dados_especificos']['perfil_negocio']=cpPerfilNegocio($pdo,$uid);

      if(array_intersect($intencoes,['vendas','margem','produtos','saldo'])){

        $contexto['dados_especificos']['resumo_vendas']=cpResumoVendas($pdo,$uid,$inicio,$fim);

        try{
$s=$pdo->prepare('SELECT vi.nome_item,SUM(vi.quantidade) quantidade,SUM(vi.preco_unitario*vi.quantidade) faturamento,SUM((vi.preco_unitario-vi.custo_unitario)*vi.quantidade) lucro_bruto FROM venda_itens vi JOIN vendas v ON v.id=vi.venda_id WHERE v.usuario_id=:uid AND v.data_venda BETWEEN :inicio AND :fim GROUP BY vi.nome_item ORDER BY faturamento DESC LIMIT 12');
$s->execute(['uid'=>$uid,'inicio'=>$inicio,'fim'=>$fim]);
$contexto['dados_especificos']['ranking_itens']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['estoque','produtos'])){
try{
$s=$pdo->prepare('SELECT p.nome,p.preco_venda,p.custo_unitario,p.estoque_atual,p.estoque_minimo,p.controlar_estoque,f.nome fornecedor FROM produtos_servicos p LEFT JOIN fornecedores f ON f.id=p.fornecedor_id WHERE p.usuario_id=:uid AND p.ativo=1 ORDER BY p.controlar_estoque DESC,p.estoque_atual ASC,p.nome LIMIT 30');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['estoque_produtos']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['funcionarios','saldo'])){
try{
$s=$pdo->prepare('SELECT nome,cargo,salario_base,outros_custos,dia_pagamento FROM funcionarios WHERE usuario_id=:uid AND ativo=1 ORDER BY nome');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['funcionarios']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['fornecedores','saldo'])){
try{
$s=$pdo->prepare('SELECT nome,descricao,valor_padrao,periodicidade,intervalo_dias,dia_vencimento FROM fornecedores WHERE usuario_id=:uid AND ativo=1 ORDER BY nome');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['fornecedores']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['custos_negocio','despesas','saldo','funcionarios','fornecedores'])){
$contexto['dados_especificos']['compromissos_mensais']=cpCompromissosMensais($pdo,$uid);
try{
$s=$pdo->prepare('SELECT descricao,valor,recorrente,dia_vencimento FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 ORDER BY recorrente DESC,valor DESC');
$s->execute(['uid'=>$uid]);
$contexto['dados_especificos']['custos_negocio']=$s->fetchAll(PDO::FETCH_ASSOC);
}
catch(Throwable $e){
}
}

      if(array_intersect($intencoes,['desempenho','vendas','margem','produtos','fornecedores','custos_negocio','previsao_caixa','planos_acao'])){

        try{
$contexto['dados_especificos']['desempenho_negocio']=cpDesempenhoNegocio($pdo,$uid,$inicio,$fim);
}
catch(Throwable $e){
}
}

      if(in_array('previsao_caixa',$intencoes,true)||in_array('saldo',$intencoes,true)){
try{
$contexto['dados_especificos']['previsao_caixa']=cpPrevisaoCaixaNegocio($pdo,$uid,30);
}
catch(Throwable $e){
}
}

      if(in_array('planos_acao',$intencoes,true)){
try{
$contexto['dados_especificos']['planos_acao']=cpPlanosAtivos($pdo,$uid,8);
}
catch(Throwable $e){
}
}

      $ent=cpCopEntidadesMencionadas($pdo,$uid,$pergunta,$perfil);
if($ent)$contexto['dados_especificos']['entidades_mencionadas']=$ent;
}


    return $contexto;
}?>
