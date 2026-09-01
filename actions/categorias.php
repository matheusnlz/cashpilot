<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();

validarCsrf();

$pdo = conectar();

$usuarioId = usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

if ($acao === 'criar_ajax') {

        header('Content-Type: application/json; charset=utf-8');

        $nome = trim($_POST['nome'] ?? '');

        $tipo = $_POST['tipo'] ?? 'despesa';

        $cor  = $_POST['cor'] ?? '#2F5D62';

        if (!in_array($tipo, ['receita', 'despesa'], true)) {

                $tipo = 'despesa';

    }

        if ($nome === '') {

                echo json_encode(['ok' => false, 'mensagem' => 'Informe um nome para a categoria.'], JSON_UNESCAPED_UNICODE);

                exit;

    }

        try {

                $check = $pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo=:tipo AND LOWER(nome)=LOWER(:nome) LIMIT 1');

                $check->execute(['uid'=>$usuarioId,'tipo'=>$tipo,'nome'=>$nome]);

                $existente = $check->fetchColumn();

                if ($existente) {

                        echo json_encode(['ok'=>true,'id'=>(int)$existente,'nome'=>$nome,'existente'=>true], JSON_UNESCAPED_UNICODE);

                        exit;

        }

                $stmt = $pdo->prepare('INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao) VALUES (:uid,:nome,:tipo,:cor,0)');

                $stmt->execute(['uid'=>$usuarioId,'nome'=>$nome,'tipo'=>$tipo,'cor'=>$cor]);

                echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'nome'=>$nome], JSON_UNESCAPED_UNICODE);

    }  catch (Throwable $e) {

                error_log('CashPilot/Categoria AJAX: '.$e->getMessage());

                http_response_code(500);

                echo json_encode(['ok'=>false,'mensagem'=>'Não foi possível criar a categoria.'], JSON_UNESCAPED_UNICODE);

    }

        exit;

}

if ($acao === 'excluir') {

        $id = (int) ($_POST['id'] ?? 0);

        // Não permite excluir categorias padrão do sistema
        $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id AND usuario_id = :uid AND padrao = 0');

        $stmt->execute(['id' => $id, 'uid' => $usuarioId]);

}

if ($acao === 'limite') {

        $id=(int)($_POST['id']??0);

        $limite=trim($_POST['limite_mensal']??'');

        $valor=$limite===''?null:max(0,(float)str_replace(',','.',$limite));

        $stmt=$pdo->prepare('UPDATE categorias SET limite_mensal=:limite WHERE id=:id AND usuario_id=:uid AND tipo="despesa"');

        $stmt->execute(['limite'=>$valor,'id'=>$id,'uid'=>$usuarioId]);

}

if ($acao === 'criar') {

        $nome = trim($_POST['nome'] ?? '');

        $tipo = $_POST['tipo'] ?? 'despesa';

        $cor  = $_POST['cor'] ?? '#2F5D62';

        if (!in_array($tipo, ['receita', 'despesa'], true)) {

                $tipo = 'despesa';

    }

        if ($nome !== '') {

                $stmt = $pdo->prepare(
                    'INSERT INTO categorias (usuario_id, nome, tipo, cor, padrao) VALUES (:uid, :nome, :tipo, :cor, 0)'
                );

                $stmt->execute(['uid' => $usuarioId, 'nome' => $nome, 'tipo' => $tipo, 'cor' => $cor]);

    }

}

$retorno = $_POST['retorno'] ?? 'categorias.php';

$permitidos = ['categorias.php','receitas.php','despesas.php'];

if (!in_array($retorno, $permitidos, true)) $retorno = 'categorias.php';

header('Location: ../pages/' . $retorno);

exit;
