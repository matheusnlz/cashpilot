<?php

function caminhoPythonCashPilot(): string
 {

        $configurado = getenv('CASHPILOT_PYTHON_PATH');

        if (
            is_string($configurado) &&
            trim($configurado) !== ''
        ) {

                return trim($configurado);

    }

        return PHP_OS_FAMILY === 'Windows'
            ? 'python'
            : 'python3';

}


/**
 * Executa um processo local sem passar por um shell.
 * Retorna stdout/stderr/status e encerra processos que excederem o tempo.
 */
function executarProcessoCashPilot(
    array $comando,
    ?string $diretorio = null,
    ?string $entrada = null,
    int $timeoutSegundos = 20
): array {
    $processo = @proc_open(
        $comando,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $diretorio
    );

    if (!is_resource($processo)) {
        return [
            'ok' => false,
            'status' => -1,
            'stdout' => '',
            'stderr' => 'Não foi possível iniciar o processo local.',
            'timeout' => false,
        ];
    }

    if ($entrada !== null && $entrada !== '') {
        fwrite($pipes[0], $entrada);
    }
    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $inicio = microtime(true);
    $stdout = '';
    $stderr = '';
    $timeout = false;
    $ultimoStatus = null;

    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        $status = proc_get_status($processo);
        $ultimoStatus = $status;
        if (!$status['running']) {
            break;
        }

        if ((microtime(true) - $inicio) >= max(1, $timeoutSegundos)) {
            $timeout = true;
            proc_terminate($processo);
            usleep(150000);

            $status = proc_get_status($processo);
            if ($status['running']) {
                proc_terminate($processo, 9);
            }
            break;
        }

        usleep(20000);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $codigo = proc_close($processo);
    if (
        $codigo === -1
        && is_array($ultimoStatus)
        && isset($ultimoStatus['exitcode'])
        && (int) $ultimoStatus['exitcode'] >= 0
    ) {
        $codigo = (int) $ultimoStatus['exitcode'];
    }

    return [
        'ok' => !$timeout && $codigo === 0,
        'status' => $codigo,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'timeout' => $timeout,
    ];
}
