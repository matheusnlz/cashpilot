<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/email_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        header('Location: ../pages/cadastro.php');

        exit;

}

validarCsrf();

$nome = trim($_POST['nome'] ?? '');

$username = mb_strtolower(ltrim(trim($_POST['username'] ?? ''), '@'));

$email = mb_strtolower(trim($_POST['email'] ?? ''));

$telefone = trim($_POST['telefone'] ?? '');

$senha = $_POST['senha'] ?? '';

$googlePending = $_SESSION['google_pending'] ?? null;

if ($googlePending) {

        $nome = trim((string) ($googlePending['nome'] ?? $nome));

        $email = mb_strtolower(trim((string) ($googlePending['email'] ?? $email)));

}

$tipo = in_array(
    $_POST['tipo_perfil'] ?? '',
    ['pessoa_fisica', 'mei'],
    true
) ? $_POST['tipo_perfil'] : 'pessoa_fisica';

$objetivoPessoal = $tipo === 'pessoa_fisica'
    ? trim($_POST['objetivo_pessoal'] ?? '')
    : null;

$limite = null;

if (
    $tipo === 'pessoa_fisica' &&
    ($_POST['limite_gastos_mensal'] ?? '') !== ''
) {

        $limite = max(
            0,
            (float) str_replace(',', '.', $_POST['limite_gastos_mensal'])
        );

}

$_SESSION['dados_cadastro'] = [
    'nome' => $nome,
    'username' => $username,
    'email' => $email,
    'telefone' => $telefone,
    'tipo_perfil' => $tipo,
];

if ($nome === '' || $email === '' || $username === '' || (!$googlePending && $senha === '')) {

        $_SESSION['erro_cadastro'] = 'Preencha os campos obrigatórios.';

        header('Location: ../pages/cadastro.php');

        exit;

}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $_SESSION['erro_cadastro'] = 'Informe um e-mail válido.';

        header('Location: ../pages/cadastro.php');

        exit;

}

if (!preg_match('/^[a-z0-9._]{3,20}$/', $username)) {

        $_SESSION['erro_cadastro'] =
            'O nome de usuário deve ter 3 a 20 caracteres e usar apenas letras, números, ponto ou _.';

        header('Location: ../pages/cadastro.php');

        exit;

}

if (!$googlePending && strlen($senha) < 6) {

        $_SESSION['erro_cadastro'] = 'A senha deve ter no mínimo 6 caracteres.';

        header('Location: ../pages/cadastro.php');

        exit;

}

$nicho = null;

$nomeNegocio = null;

$oferta = 'servicos';

$operacao = 'presencial';

$publicoAlvo = null;

$canalPrincipal = null;

$objetivoPrincipal = null;

if ($tipo === 'mei') {

        $nichoSelecionado = trim($_POST['nicho'] ?? '');

        $nichoOutro = trim($_POST['nicho_outro'] ?? '');

        $nicho = $nichoSelecionado === 'Outro'
            ? $nichoOutro
            : $nichoSelecionado;

        $nomeNegocio = trim($_POST['nome_negocio'] ?? '');

        $ofertasPermitidas = ['produtos', 'servicos', 'ambos'];

        $operacoesPermitidas = ['presencial', 'online', 'hibrido', 'domicilio'];

        $publicosPermitidos = [
            'Público geral',
            'Empresas / B2B',
            'Famílias',
            'Crianças e responsáveis',
            'Jovens e adolescentes',
            'Adultos',
            'Principalmente homens',
            'Principalmente mulheres',
            'Público de maior poder aquisitivo',
            'Público local / regional',
        ];

        $objetivosPermitidos = [
            'Aumentar vendas e faturamento',
            'Melhorar lucro e margem',
            'Organizar despesas e custos',
            'Melhorar o fluxo de caixa',
            'Controlar estoque',
            'Organizar equipe',
            'Fidelizar e aumentar clientes',
            'Ganhar previsibilidade',
            'Entender melhor meu negócio',
        ];

        $oferta = in_array($_POST['oferta'] ?? '', $ofertasPermitidas, true)
            ? $_POST['oferta']
            : 'servicos';

        $operacao = in_array($_POST['operacao'] ?? '', $operacoesPermitidas, true)
            ? $_POST['operacao']
            : 'presencial';

        $publicoAlvo = in_array(
            $_POST['publico_alvo'] ?? '',
            $publicosPermitidos,
            true
        ) ? $_POST['publico_alvo'] : 'Público geral';

        $canalPrincipal = trim($_POST['canal_principal'] ?? '');

        $objetivoPrincipal = in_array(
            $_POST['objetivo_principal'] ?? '',
            $objetivosPermitidos,
            true
        ) ? $_POST['objetivo_principal'] : 'Entender melhor meu negócio';

        if ($nomeNegocio === '') {

                $_SESSION['erro_cadastro'] = 'Informe o nome do negócio.';

                header('Location: ../pages/cadastro.php');

                exit;

    }

        if ($nicho === '') {

                $_SESSION['erro_cadastro'] = 'Selecione ou informe o nicho do negócio.';

                header('Location: ../pages/cadastro.php');

                exit;

    }

        if (mb_strlen($nicho) > 80) {

                $_SESSION['erro_cadastro'] = 'O nicho informado é muito longo.';

                header('Location: ../pages/cadastro.php');

                exit;

    }

}

$pdo = conectar();

$stmt = $pdo->prepare(
    'SELECT id
     FROM usuarios
     WHERE email = :email OR username = :username
     LIMIT 1'
);

$stmt->execute([
    'email' => $email,
    'username' => $username,
]);

if ($stmt->fetch()) {

        $_SESSION['erro_cadastro'] = 'E-mail ou nome de usuário já está em uso.';

        header('Location: ../pages/cadastro.php');

        exit;

}

try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (
            nome,
            username,
            username_alterado_em,
            email,
            email_verificado,
            email_verificado_em,
            telefone,
            senha_hash,
            tipo_perfil,
            nicho,
            objetivo_pessoal,
            onboarding_concluido,
            limite_gastos_mensal,
            tema_preferido
         ) VALUES (
            :nome,
            :username,
            NOW(),
            :email,
            :email_verificado,
            :email_verificado_em,
            :telefone,
            :senha,
            :tipo,
            :nicho,
            :objetivo_pessoal,
            0,
            :limite,
            "light"
         )'
        );

        $stmt->execute([
            'nome' => $nome,
            'username' => $username,
            'email' => $email,
            'telefone' => $telefone !== '' ? $telefone : null,
            'email_verificado' => $googlePending ? 1 : 0,
            'email_verificado_em' => $googlePending ? date('Y-m-d H:i:s') : null,
            'senha' => password_hash($googlePending ? bin2hex(random_bytes(24)) : $senha, PASSWORD_DEFAULT),
            'tipo' => $tipo,
            'nicho' => $nicho,
            'objetivo_pessoal' => $objetivoPessoal,
            'limite' => $limite,
        ]);

        $usuarioId = (int) $pdo->lastInsertId();

        if ($tipo === 'mei') {

                $stmt = $pdo->prepare(
                    'INSERT INTO perfil_negocio (
                usuario_id,
                nome_negocio,
                oferta,
                operacao,
                publico_alvo,
                canal_principal,
                objetivo_principal
             ) VALUES (
                :uid,
                :nome,
                :oferta,
                :operacao,
                :publico,
                :canal,
                :objetivo
             )'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                    'nome' => $nomeNegocio,
                    'oferta' => $oferta,
                    'operacao' => $operacao,
                    'publico' => $publicoAlvo,
                    'canal' => $canalPrincipal !== '' ? $canalPrincipal : null,
                    'objetivo' => $objetivoPrincipal,
                ]);

    }

        if ($googlePending) {

                $pdo->prepare(
                    'INSERT INTO usuario_oauth (usuario_id, provider, provider_user_id, email_provider)
             VALUES (:uid, "google", :sub, :email)'
                )->execute([
                    'uid' => $usuarioId,
                    'sub' => $googlePending['sub'],
                    'email' => $googlePending['email'],
                ]);

    }

        $pdo->commit();

        unset($_SESSION['dados_cadastro'], $_SESSION['google_pending']);

        session_regenerate_id(true);
renovarCsrf();

        $_SESSION['usuario_id'] = $usuarioId;

        $_SESSION['usuario_nome'] = $nome;

        $_SESSION['usuario_tipo'] = $tipo;

        $_SESSION['usuario_avatar'] = '';

        $_SESSION['usuario_username'] = $username;

        $_SESSION['tema_preferido'] = 'light';

        $_SESSION['onboarding_concluido'] = 0;

        $_SESSION['email_verificado'] = $googlePending ? 1 : 0;

        if (!$googlePending) {

                $envio = cpEnviarCodigo($pdo, $usuarioId, 'confirmacao_email', $email);

                if ($envio['ok']) {

                        $_SESSION['email_reenvios'] = 0;

        }  else {

                        $_SESSION['email_aviso_envio'] = $envio['erro'];

        }

    }

        header('Location: ../pages/boas_vindas.php');

        exit;

}  catch (Throwable $e) {

        if ($pdo->inTransaction()) {

                $pdo->rollBack();

    }

        error_log('CashPilot/Cadastro 11.1: ' . $e->getMessage());

        $_SESSION['erro_cadastro'] =
            'Não foi possível criar a conta. Confirme se as migrations do CashPilot, incluindo a 011, foram executadas.';

        header('Location: ../pages/cadastro.php');

        exit;

}
