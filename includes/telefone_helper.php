<?php
/** CashPilot 15 - validação básica de telefone brasileiro. */

function cpSomenteDigitosTelefone(?string $telefone): string
{
    return preg_replace('/\D+/', '', (string) $telefone) ?? '';
}

function cpDddsBrasil(): array
{
    return [
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18,
        19,
        21,
        22,
        24,
        27,
        28,
        31,
        32,
        33,
        34,
        35,
        37,
        38,
        41,
        42,
        43,
        44,
        45,
        46,
        47,
        48,
        49,
        51,
        53,
        54,
        55,
        61,
        62,
        64,
        63,
        65,
        66,
        67,
        68,
        69,
        71,
        73,
        74,
        75,
        77,
        79,
        81,
        87,
        82,
        83,
        84,
        85,
        88,
        86,
        89,
        91,
        93,
        94,
        92,
        97,
        95,
        96,
        98,
        99
    ];
}

function cpValidarTelefoneBrasil(?string $telefone, bool $obrigatorio = false): array
{
    $digitos = cpSomenteDigitosTelefone($telefone);
    if ($digitos === '') {
        return $obrigatorio
            ? ['ok' => false, 'erro' => 'Informe um telefone válido.', 'normalizado' => '']
            : ['ok' => true, 'erro' => null, 'normalizado' => ''];
    }

    if (str_starts_with($digitos, '55') && strlen($digitos) >= 12) {
        $digitos = substr($digitos, 2);
    }

    if (!in_array(strlen($digitos), [10, 11], true)) {
        return [
            'ok' => false,
            'erro' => 'O telefone deve conter DDD e número completos.',
            'normalizado' => ''
        ];
    }

    $ddd = (int) substr($digitos, 0, 2);
    if (!in_array($ddd, cpDddsBrasil(), true)) {
        return ['ok' => false, 'erro' => 'Informe um DDD brasileiro válido.', 'normalizado' => ''];
    }

    $numero = substr($digitos, 2);
    if (preg_match('/^(\d)\1+$/', $numero)) {
        return [
            'ok' => false,
            'erro' => 'Informe um número de telefone plausível.',
            'normalizado' => ''
        ];
    }

    if (strlen($digitos) === 11 && $numero[0] !== '9') {
        return [
            'ok' => false,
            'erro' => 'Celulares brasileiros devem iniciar com 9 após o DDD.',
            'normalizado' => ''
        ];
    }

    $normalizado =
        strlen($digitos) === 11
            ? sprintf(
                '(%s) %s-%s',
                substr($digitos, 0, 2),
                substr($digitos, 2, 5),
                substr($digitos, 7, 4)
            )
            : sprintf(
                '(%s) %s-%s',
                substr($digitos, 0, 2),
                substr($digitos, 2, 4),
                substr($digitos, 6, 4)
            );

    return ['ok' => true, 'erro' => null, 'normalizado' => $normalizado];
}
