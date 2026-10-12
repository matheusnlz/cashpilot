<?php
/** Ajustes do TCC em um único local. Janelas fixas em segundos. */
return [
    'acoes' => [
        'login.php' => [20, 900, 100, 86400],
        'chatbot.php' => [10, 60, 80, 86400],
        'recuperar_senha.php' => [10, 900, 30, 86400],
        'email_verificacao.php' => [10, 900, 40, 86400],
        'cadastro.php' => [5, 3600, 15, 86400],
        'importar_upload.php' => [5, 60, 30, 86400],
        'importar_confirmar.php' => [10, 60, 60, 86400],
        'exportar.php' => [5, 60, 30, 86400],
        'exportar_dados.php' => [3, 900, 10, 86400],
        'copiloto_consentimento.php' => [10, 60, 30, 86400],
        'copiloto_status.php' => [3, 60, 20, 86400],
        'pluggy_sync.php' => [3, 60, 30, 86400],
        'pluggy_connect_token.php' => [5, 60, 30, 86400],
        'pluggy_item_save.php' => [5, 60, 30, 86400],
        'pluggy_importar.php' => [5, 60, 30, 86400],
        'pluggy_eventos_processar.php' => [3, 60, 30, 86400]
    ],
    'email' => ['por_usuario_15_min' => 3, 'por_usuario_dia' => 10, 'global_dia' => 200],
    'groq' => ['por_usuario_dia' => 100, 'global_dia' => 500, 'global_30_dias' => 5000],
    'retencao' => ['conversas_dias' => 90, 'payload_webhook_dias' => 30]
];
