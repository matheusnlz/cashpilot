<?php
/**
 * Fallback opcional para desenvolvimento local.
 *
 * O recomendado é usar GROQ_API_KEY como variável de ambiente.
 * Se o Apache/XAMPP não enxergar a variável, copie este arquivo para:
 *   includes/groq_config.php
 * e coloque sua chave abaixo.
 *
 * NUNCA envie groq_config.php para o GitHub.
 */
return [
    'api_key' => 'COLE_SUA_CHAVE_AQUI',
    'model' => 'llama-3.3-70b-versatile',
];
