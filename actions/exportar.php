<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

require_once __DIR__.'/../includes/relatorios_financeiros.php';

exigirLogin();

$token=$_GET['token']??'';

if(!is_string($token)||empty($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],$token)) {

    http_response_code(403);

    exit('Solicitação inválida.');

}


function cpCsvSeguro($valor): string
{
    $texto = (string) $valor;
    if ($texto !== '' && preg_match('/^[=+@\t\r]/', $texto)) {
        return "'" . $texto;
    }
    // '-' é permitido para números do relatório; em texto livre também é
    // prefixado para evitar fórmulas em planilhas.
    if ($texto !== '' && $texto[0] === '-' && !is_numeric(str_replace(',', '.', $texto))) {
        return "'" . $texto;
    }
    return $texto;
}

$pdo=conectar();

$uid=usuarioLogadoId();

$p=cpRelPeriodo($_GET);

$i=$p['inicio'];

$f=$p['fim'];

$sql="(SELECT 'receita' tipo,r.descricao,r.valor,r.data_receita data,COALESCE(c.nome,'Outros') categoria,COALESCE(ct.nome,'') conta,r.status FROM receitas r LEFT JOIN categorias c ON c.id=r.categoria_id LEFT JOIN contas ct ON ct.id=r.conta_id WHERE r.usuario_id=:a AND r.data_receita BETWEEN :i1 AND :f1) UNION ALL (SELECT 'despesa' tipo,d.descricao,d.valor,d.data_despesa data,COALESCE(c.nome,'Outros') categoria,COALESCE(ct.nome,'') conta,d.status FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id LEFT JOIN contas ct ON ct.id=d.conta_id WHERE d.usuario_id=:b AND d.data_despesa BETWEEN :i2 AND :f2) ORDER BY data DESC";

$s=$pdo->prepare($sql);

$s->execute(['a'=>$uid,'i1'=>$i,'f1'=>$f,'b'=>$uid,'i2'=>$i,'f2'=>$f]);

$mov=$s->fetchAll(PDO::FETCH_ASSOC);

$nome='cashpilot_'.date('Ymd',strtotime($i)).'_'.date('Ymd',strtotime($f)).'.csv';

header('Content-Type: text/csv; charset=UTF-8');

header('Content-Disposition: attachment; filename="'.$nome.'"');

echo "\xEF\xBB\xBF";

$o=fopen('php://output','w');

fputcsv($o,['Tipo','Data','Descrição','Categoria','Conta','Status','Valor'],';');

foreach($mov as $m) {

    fputcsv($o,[cpCsvSeguro($m['tipo']),date('d/m/Y',strtotime($m['data'])),cpCsvSeguro($m['descricao']),cpCsvSeguro($m['categoria']),cpCsvSeguro($m['conta']),cpCsvSeguro($m['status']),number_format((float)$m['valor'],2,',','.')],';');

}

fclose($o);

exit;
