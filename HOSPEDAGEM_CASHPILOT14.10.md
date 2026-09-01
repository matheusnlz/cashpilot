# CashPilot 14.10 — Hospedagem

Esta versão preserva as funcionalidades da 14.9.2 e adiciona a infraestrutura necessária para executar PHP, Apache e Python no mesmo container.

## Estratégia recomendada

Aplicação:
- Railway Web Service construído pelo `Dockerfile`.

Banco:
- serviço MySQL separado no mesmo projeto Railway.

O CashPilot aceita as variáveis `DB_*` tradicionais e também `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER` e `MYSQLPASSWORD`.

## Arquivos de produção

- `Dockerfile`
- `.dockerignore`
- `docker/entrypoint.sh`
- `docker/apache-cashpilot.conf`
- `docker/php-production.ini`
- `health.php`
- `.env.example`

## Variáveis obrigatórias na aplicação

- `CASHPILOT_ENV=production`
- `CASHPILOT_SECURITY_KEY`
- credenciais MySQL
- `GROQ_API_KEY` se o Copiloto externo estiver ativo
- `BREVO_API_KEY` para e-mails
- `GOOGLE_CLIENT_ID` para login Google

O caminho Python já é definido pelo Dockerfile como `/opt/cashpilot-venv/bin/python`.

## Banco

Em uma instalação nova, importe `database/banco.sql` no MySQL hospedado.

Não exponha phpMyAdmin/Adminer permanentemente na internet. Caso use uma ferramenta administrativa para a importação inicial, remova ou restrinja o acesso depois.

## Healthcheck

Configure no provedor:

`/health.php`

O endpoint verifica extensões PHP essenciais e uma conexão simples com o MySQL. Ele retorna apenas `ok` ou `unavailable`, sem revelar credenciais ou mensagens internas.

## Importante

A etapa 14.10 prepara o código. A aprovação final da produção só acontece depois do primeiro deploy e dos testes reais em HTTPS.
