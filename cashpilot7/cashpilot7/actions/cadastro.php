<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../pages/cadastro.php'); exit; }
validarCsrf();

$nome=trim($_POST['nome']??'');$email=trim($_POST['email']??'');$telefone=trim($_POST['telefone']??'');$senha=$_POST['senha']??'';
$tipo=$_POST['tipo_perfil']??'pessoa_fisica';$tipo=in_array($tipo,['pessoa_fisica','mei'],true)?$tipo:'pessoa_fisica';
$nicho=$tipo==='mei'?trim($_POST['nicho']??''):null;
$limite=$tipo==='pessoa_fisica'&&($_POST['limite_gastos_mensal']??'')!==''?(float)str_replace(',','.',$_POST['limite_gastos_mensal']):null;
$_SESSION['dados_cadastro']=['nome'=>$nome,'email'=>$email,'telefone'=>$telefone];
if($nome===''||$email===''||$senha===''){$_SESSION['erro_cadastro']='Preencha os campos obrigatórios.';header('Location: ../pages/cadastro.php');exit;}
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$_SESSION['erro_cadastro']='Informe um e-mail válido.';header('Location: ../pages/cadastro.php');exit;}
if(strlen($senha)<6){$_SESSION['erro_cadastro']='A senha deve ter no mínimo 6 caracteres.';header('Location: ../pages/cadastro.php');exit;}
if($tipo==='mei'&&$nicho===''){$_SESSION['erro_cadastro']='Selecione o nicho do negócio.';header('Location: ../pages/cadastro.php');exit;}
$pdo=conectar();$stmt=$pdo->prepare('SELECT id FROM usuarios WHERE email=:email LIMIT 1');$stmt->execute(['email'=>$email]);if($stmt->fetch()){$_SESSION['erro_cadastro']='Este e-mail já está cadastrado.';header('Location: ../pages/cadastro.php');exit;}
try{
$pdo->beginTransaction();
$stmt=$pdo->prepare('INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo_perfil,nicho,onboarding_concluido,limite_gastos_mensal) VALUES (:nome,:email,:telefone,:senha,:tipo,:nicho,1,:limite)');
$stmt->execute(['nome'=>$nome,'email'=>$email,'telefone'=>$telefone?:null,'senha'=>password_hash($senha,PASSWORD_DEFAULT),'tipo'=>$tipo,'nicho'=>$nicho,'limite'=>$limite]);
$uid=(int)$pdo->lastInsertId();
if($tipo==='mei'){
 $stmt=$pdo->prepare('INSERT INTO perfil_negocio (usuario_id,nome_negocio,oferta,operacao,publico_alvo,canal_principal,objetivo_principal) VALUES (:uid,:nome,:oferta,:operacao,:publico,:canal,:objetivo)');
 $stmt->execute(['uid'=>$uid,'nome'=>trim($_POST['nome_negocio']??''),'oferta'=>$_POST['oferta']??'servicos','operacao'=>$_POST['operacao']??'presencial','publico'=>trim($_POST['publico_alvo']??'')?:null,'canal'=>trim($_POST['canal_principal']??'')?:null,'objetivo'=>trim($_POST['objetivo_principal']??'')?:null]);
}
$pdo->commit();
unset($_SESSION['dados_cadastro']);session_regenerate_id(true);$_SESSION['usuario_id']=$uid;$_SESSION['usuario_nome']=$nome;$_SESSION['usuario_tipo']=$tipo;$_SESSION['usuario_avatar']='';header('Location: ../pages/dashboard.php');exit;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['erro_cadastro']='Não foi possível criar a conta. Verifique se a atualização do banco foi executada.';header('Location: ../pages/cadastro.php');exit;}
