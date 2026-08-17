# CashPilot 6 — Integração do Copiloto com Groq

O Copiloto agora usa o Groq como provedor principal de IA e mantém o modo local do CashPilot como fallback.

## Fluxo

`Chat do CashPilot → actions/chatbot.php → includes/ia.php → Groq → resposta`

Se o Groq estiver sem conexão, sem chave, atingir um limite ou retornar erro, o CashPilot continua respondendo com as regras locais existentes.

## Configuração recomendada no Windows

A chave deve ficar em uma variável de ambiente chamada `GROQ_API_KEY`.

No PowerShell, depois de configurá-la, feche e abra novamente o **XAMPP Control Panel** antes de iniciar/reiniciar o Apache. Isso é importante porque o Apache herda as variáveis de ambiente do processo que o iniciou.

O modelo padrão desta versão é:

`llama-3.3-70b-versatile`

Você pode definir outro modelo compatível usando a variável `GROQ_MODEL`.

## Se o XAMPP não encontrar a variável

Existe `includes/groq_config.example.php`.

1. Copie para `includes/groq_config.php`.
2. Coloque a chave somente no arquivo copiado.
3. Não envie esse arquivo ao GitHub.

O `.gitignore` já ignora `includes/groq_config.php`.

## Requisitos

- PHP com extensão cURL habilitada.
- Conexão com a internet.
- Chave Groq válida.

## Como saber se funcionou

Abra o Copiloto e faça uma pergunta contextual, como:

- `Explique meu mês e diga onde devo prestar mais atenção.`
- `Quais são os principais riscos do meu caixa neste mês?`

Se a chamada à IA falhar, o chat continuará funcionando com o fallback local em vez de quebrar o sistema.

## Segurança

Nunca coloque a chave Groq no JavaScript, HTML ou em arquivos enviados ao GitHub. A chave deve permanecer no backend.
