<?php

/**
 * Opcional para desenvolvimento local.
 *
 * 1. Copie este arquivo para groq_config.php.
 * 2. Coloque sua chave somente na cópia.
 * 3. NÃO envie groq_config.php ao GitHub.
 *
 * Preferencialmente, continue usando a variável de ambiente GROQ_API_KEY.
 */
return [
    'api_key' => 'COLE_SUA_CHAVE_AQUI',
    'model' => 'openai/gpt-oss-120b',
    'fallback_model' => 'openai/gpt-oss-20b',
    'timeout' => 45,
];
