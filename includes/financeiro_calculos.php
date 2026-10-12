<?php
function cp152Saldo(float $receitas, float $despesas): float
{
    return round($receitas - $despesas, 2);
}

function cp152VariacaoPercentual(float $atual, float $anterior): ?float
{
    if (abs($anterior) < 0.00001) {
        return $atual == 0.0 ? 0.0 : null;
    }
    return round((($atual - $anterior) / abs($anterior)) * 100, 2);
}

function cp152Progresso(float $atual, float $meta): float
{
    if ($meta <= 0) {
        return 0.0;
    }
    return round(max(0, min(100, ($atual / $meta) * 100)), 2);
}

function cp152Orcamento(float $gasto, float $limite): array
{
    $restante = round($limite - $gasto, 2);
    return [
        'percentual' => $limite > 0 ? round(($gasto / $limite) * 100, 2) : 0.0,
        'restante' => $restante,
        'ultrapassado' => $limite > 0 && $gasto > $limite
    ];
}

function cp152SimularReducao(float $media, float $percentual, int $meses): array
{
    $percentual = max(0, min(100, $percentual));
    $meses = max(1, min(60, $meses));
    $economia = round($media * ($percentual / 100), 2);
    return [
        'novo_valor' => round($media - $economia, 2),
        'impacto_mensal' => $economia,
        'impacto_periodo' => round($economia * $meses, 2)
    ];
}

function cp152SimularValorMensal(float $valor, int $meses): float
{
    return round(max(0, $valor) * max(1, min(60, $meses)), 2);
}
?>
