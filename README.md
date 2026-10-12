# CashPilot

O CashPilot é uma plataforma web de gestão financeira criada como Trabalho de
Conclusão de Curso do Técnico em Desenvolvimento de Sistemas da ETEC Jorge
Street. O sistema atende pessoas físicas, MEIs e pequenos empreendedores,
funcionando como um copiloto para organizar movimentações, acompanhar a saúde
financeira e apoiar decisões.

O CashPilot não substitui banco, contador, consultor ou profissional financeiro.

## Equipe

- Emilly Lays Ferreira da Cruz
- Matheus Nunes Leite
- Samuel Dias Pereira
- Yago de Souza Lopes

## Tecnologias

- HTML e CSS;
- JavaScript e Chart.js;
- PHP;
- MySQL/MariaDB;
- Python para rotinas controladas de classificação, análise e previsão;
- APIs Groq, Brevo, Google OAuth e Pluggy;
- XAMPP e phpMyAdmin no desenvolvimento local;
- Docker e Railway na hospedagem.

## Funcionalidades

- cadastro, login, logout, recuperação de senha e verificação de e-mail;
- autenticação e vinculação de conta pelo Google;
- gestão de contas, receitas, despesas, categorias e transações;
- metas, orçamentos, recorrências e planejamento mensal;
- financiamentos, investimentos e análise "Posso comprar?";
- dashboards para pessoa física e MEI;
- RadarPilot, CashScore, relatórios, projeção de caixa e planos de ação;
- Copiloto financeiro integrado ao Groq, com consentimento obrigatório;
- conteúdos educativos no módulo Aprender;
- vendas, produtos e serviços, estoque, fornecedores, funcionários e custos;
- importação de extratos CSV com revisão e controle de duplicidades;
- exportação de movimentações e dos dados da conta;
- integração Pluggy preparada para Sandbox e conexão bancária;
- modo claro, modo escuro e interface responsiva.

## Open Finance

A integração com a Pluggy possui Connect Token, conexão de instituição, Items,
contas, transações, sincronização, revisão, importação, desconexão e webhook.
O Sandbox pode ser utilizado normalmente. O acesso a instituições reais depende
da aprovação e homologação da conta Pluggy. Enquanto essa liberação estiver
pendente, o CSV permanece como alternativa funcional de importação.

## Instalação no XAMPP

1. Coloque a pasta `CashPilot` dentro de `C:\xampp\htdocs`.
2. Inicie Apache e MySQL no XAMPP.
3. Crie o banco `cashpilot` no phpMyAdmin.
4. Em uma instalação nova, importe `database/banco.sql` e depois
   `database/migrations/020_cashpilot17.sql`.
5. Copie `.env.example` para `.env` e preencha somente a cópia privada.
6. Acesse `http://localhost/CashPilot/`.

Não importe `banco.sql` sobre um banco existente com dados. Em uma instalação
que já recebeu a migration `020_cashpilot17.sql`, não execute esse arquivo
novamente.

## Configuração

O arquivo `.env.example` contém todos os nomes aceitos pelo sistema. No XAMPP,
use uma cópia chamada `.env`. Na Railway, cadastre os mesmos valores na seção
**Variables** e defina `CASHPILOT_ENV=production`.

As principais configurações são:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS`;
- `CASHPILOT_SECURITY_KEY` com valor aleatório de pelo menos 32 caracteres;
- `BREVO_API_KEY`, `CASHMAIL_FROM_EMAIL` e `CASHMAIL_FROM_NAME`;
- `GOOGLE_CLIENT_ID`;
- `GROQ_API_KEY` e os modelos do Copiloto;
- `PLUGGY_CLIENT_ID`, `PLUGGY_CLIENT_SECRET` e `PLUGGY_WEBHOOK_TOKEN`;
- `CASHPILOT_PUBLIC_URL` com a URL HTTPS pública da aplicação.

Nenhuma credencial real deve ser gravada no código, enviada ao Git ou incluída
no ZIP. O `.env` já está ignorado pelo Git e seu acesso direto é bloqueado pelo
Apache.

## Banco de dados

O projeto utiliza um único banco MySQL/MariaDB. O arquivo `database/banco.sql`
representa a base completa e a pasta `database/migrations` preserva as mudanças
incrementais necessárias para atualizar instalações anteriores sem criar outro
banco ou apagar informações existentes.

## Segurança

O CashPilot utiliza hash de senhas, consultas preparadas com PDO, proteção CSRF,
controle de sessão, limitação de requisições, validação de propriedade dos dados,
cabeçalhos HTTP, proteção dos diretórios internos e validação de arquivos CSV.
Em produção, mantenha HTTPS ativo, credenciais somente nas variáveis da Railway,
logs de erro sem exibição pública e backups periódicos do banco.
