<?php

/** Reusa apenas a preparacao SQL nesta requisicao; resultados sempre sao consultados novamente. */
function cpPrepararConsultaReutilizavel(PDO $pdo, string $sql): PDOStatement|false
{
    static $porConexao = null;
    $porConexao ??= new WeakMap();
    $consultas = $porConexao[$pdo] ?? [];
    if (isset($consultas[$sql])) {
        $consultas[$sql]->closeCursor();
        return $consultas[$sql];
    }
    $stmt = $pdo->prepare($sql);
    if ($stmt !== false) {
        $consultas[$sql] = $stmt;
        $porConexao[$pdo] = $consultas;
    }
    return $stmt;
}
/** Regras compartilhadas de caixa realizado. Previsões não entram no saldo. */
function cpNormalizarValorFinanceiro(mixed $valor, bool $permitirNegativo = false): ?float
{
    if (is_array($valor) || is_object($valor) || $valor === null) {
        return null;
    }

    $texto = trim((string) $valor);
    if ($texto === '') {
        return null;
    }

    if (str_contains($texto, ',') && str_contains($texto, '.')) {
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    } elseif (str_contains($texto, ',')) {
        $texto = str_replace(',', '.', $texto);
    }

    if (!is_numeric($texto)) {
        return null;
    }

    $numero = (float) $texto;
    if (!is_finite($numero) || (!$permitirNegativo && $numero < 0)) {
        return null;
    }

    return round($numero, 2);
}

function cpValidarDataFinanceira(string $data): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $objeto !== false && $objeto->format('Y-m-d') === $data;
}

function cpValidarCompetencia(string $competencia): bool
{
    if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) {
        return false;
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $competencia . '-01');
    return $data !== false && $data->format('Y-m') === $competencia;
}

function cpTextoLimitado(mixed $valor, int $limite): string
{
    return mb_substr(trim(is_scalar($valor) ? (string) $valor : ''), 0, max(0, $limite));
}

function cpCorHexValida(string $cor): bool
{
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $cor) === 1;
}

function cpValidarOrigemFinanceira(string $tabela, string $campo): void
{
    $campos = ['receitas' => 'data_receita', 'despesas' => 'data_despesa'];
    if (($campos[$tabela] ?? null) !== $campo) {
        throw new InvalidArgumentException('Origem financeira inválida.');
    }
}

function cpTotalRealizado(
    PDO $pdo,
    string $tabela,
    string $campo,
    int $usuarioId,
    string $inicio,
    string $fim
): float {
    cpValidarOrigemFinanceira($tabela, $campo);
    $fim = min($fim, date('Y-m-d'));
    $stmt = cpPrepararConsultaReutilizavel($pdo,
        "SELECT COALESCE(SUM(valor), 0) FROM {$tabela}
         WHERE usuario_id = :uid AND status = 'efetivado'
           AND {$campo} BETWEEN :inicio AND :fim"
    );
    $stmt->execute(['uid' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim]);
    return round((float) $stmt->fetchColumn(), 2);
}

function cpSaldoRealizado(PDO $pdo, int $usuarioId, ?int $contaId = null): float
{
    $parametros = [
        'uid_contas' => $usuarioId,
        'uid_receitas' => $usuarioId,
        'uid_despesas' => $usuarioId,
        'hoje_receitas' => date('Y-m-d'),
        'hoje_despesas' => date('Y-m-d')
    ];
    $filtroContas = $contaId === null ? '' : ' AND id = :conta_inicial';
    $filtroReceitas = $contaId === null ? '' : ' AND conta_id = :conta_receitas';
    $filtroDespesas = $contaId === null ? '' : ' AND conta_id = :conta_despesas';
    if ($contaId !== null) {
        $parametros += [
            'conta_inicial' => $contaId,
            'conta_receitas' => $contaId,
            'conta_despesas' => $contaId
        ];
    }
    $stmt = cpPrepararConsultaReutilizavel($pdo,
        "SELECT
            (SELECT COALESCE(SUM(saldo_inicial), 0) FROM contas
             WHERE usuario_id = :uid_contas{$filtroContas}) AS saldo_inicial,
            (SELECT COALESCE(SUM(valor), 0) FROM receitas
             WHERE usuario_id = :uid_receitas AND status = 'efetivado'
               AND data_receita <= :hoje_receitas{$filtroReceitas}) AS receitas,
            (SELECT COALESCE(SUM(valor), 0) FROM despesas
             WHERE usuario_id = :uid_despesas AND status = 'efetivado'
               AND data_despesa <= :hoje_despesas{$filtroDespesas}) AS despesas"
    );
    $stmt->execute($parametros);
    $linha = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo = (float) $linha['saldo_inicial'];
    $saldo += (float) $linha['receitas'];
    $saldo -= (float) $linha['despesas'];
    return round($saldo, 2);
}

/** Compara dias equivalentes, limitando fevereiro e meses de 30 dias. */
function cpFimComparacaoMesAnterior(?string $hoje = null): string
{
    $atual = new DateTimeImmutable($hoje ?? 'today');
    $anterior = $atual->modify('first day of last month');
    $dia = min((int) $atual->format('d'), (int) $anterior->format('t'));
    return $anterior
        ->setDate((int) $anterior->format('Y'), (int) $anterior->format('m'), $dia)
        ->format('Y-m-d');
}

/** Reserva é uma destinação; só investimentos externos ao saldo são adicionados. */
function cpComporPatrimonio(float $saldo, float $investimentosForaDoSaldo): float
{
    return round($saldo + $investimentosForaDoSaldo, 2);
}
