# CashPilot 14.10

Versão de **preparação para hospedagem**, construída sobre a CashPilot 14.9.2 aprovada nos testes locais de segurança.

A interface, os módulos PF/MEI, importações, relatórios, Copiloto e demais funcionalidades foram preservados.

## Local
O projeto continua compatível com XAMPP e os padrões locais de banco (`localhost`, `root`, banco `cashpilot`) quando variáveis de ambiente não forem definidas.

## Produção
A versão inclui um `Dockerfile` que monta:
- Apache + PHP 8.3;
- extensões PDO MySQL, cURL, mbstring e GD;
- Python em ambiente virtual;
- dependências de `python/requirements.txt`;
- configuração PHP de produção;
- porta dinâmica para plataformas como Railway.

O PHP e os scripts Python aceitam tanto variáveis `DB_*` quanto as variáveis `MYSQL*` utilizadas pelo Railway.

## Segurança
Todas as proteções consolidadas na 14.9.2 permanecem: sessão, CSRF, POST, autorização, isolamento de usuários, PF/MEI, uploads, TLS, CSP, proteção de arquivos internos, rate limit e erros de produção.

## Healthcheck
`/health.php`

## Banco
Instalação nova: importe `database/banco.sql`.

Consulte `HOSPEDAGEM_CASHPILOT14.10.md`.
