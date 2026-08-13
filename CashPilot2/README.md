# CashPilot

Plataforma web de gestão financeira para pessoas físicas e pequenos empreendedores (MEIs). Projeto de TCC — desenvolvido exclusivamente com **HTML, CSS, JavaScript, PHP, MySQL e Python**.

> "Um copiloto financeiro que ajuda o usuário a entender para onde seu dinheiro está indo e como pode administrá-lo melhor."

## Funcionalidades

- Cadastro e login com sessões PHP (senhas com `password_hash`/`password_verify`)
- Dashboard com saldo, receitas, despesas, gráfico de evolução (Chart.js) e **insights automáticos**
- CRUD completo de Receitas e Despesas
- Categorias personalizáveis (com categorias padrão criadas automaticamente no cadastro)
- Metas financeiras com barra de progresso
- Relatórios com evolução mensal e distribuição de gastos por categoria
- Área específica para MEI (entradas, saídas, fluxo de caixa, principais custos)
- Perfil do usuário (dados e senha)
- **Importação de extrato bancário (CSV)** com fluxo completo: upload → validação → pré-visualização → classificação automática → revisão → confirmação → histórico
- **Classificação automática de movimentações** por regras de palavras-chave em Python (não é IA generativa), com fallback e sempre editável pelo usuário antes de salvar
- **Contas** (múltiplas carteiras/bancos por usuário) e **Histórico de importações**
- Módulo de análise em **Python** (fora do fluxo de requisição, chamado via `shell_exec`/`proc_open`) que:
  - gera insights em linguagem natural (`insights.py`)
  - calcula séries para os gráficos (`analise.py`)
  - projeta o cumprimento de metas com base no ritmo de economia (`previsao.py`)
  - classifica movimentações importadas por palavra-chave (`classificar.py`)

## Requisitos

- PHP 8+ com extensão PDO MySQL
- MySQL / MariaDB
- Python 3.9+ com os pacotes em `python/requirements.txt`
- Servidor local: XAMPP, Laragon, MAMP ou `php -S`

## Instalação

1. **Banco de dados**
   Se for uma instalação nova, importe apenas `database/banco.sql` (já contém o schema completo, incluindo `contas` e `importacoes`):

   ```bash
   mysql -u root -p < database/banco.sql
   ```

   Se você já tinha uma instalação anterior do CashPilot (sem as tabelas de importação), rode a migração incremental por cima do banco existente:

   ```bash
   mysql -u root -p < database/migrations/001_importacao_extrato.sql
   ```

2. **Credenciais do banco**
   Ajuste, se necessário, `database/conexao.php` (PHP) e `python/conexao.py` (Python) com o usuário/senha do seu MySQL.

3. **Dependências Python**

   ```bash
   cd python
   pip install -r requirements.txt --break-system-packages
   ```

   > O PHP chama os scripts Python via `shell_exec("python3 ...")`. Garanta que o comando `python3` esteja no PATH do servidor web (no Windows/XAMPP pode ser necessário usar o caminho completo do executável em `pages/dashboard.php` e `pages/relatorios.php`).

4. **Servidor**

   Aponte o servidor para a raiz do projeto (`CashPilot/`) e acesse `index.php`. Com o servidor embutido do PHP, por exemplo:

   ```bash
   php -S localhost:8000
   ```

   Depois acesse `http://localhost:8000`.

5. **Criar uma conta** em `/pages/cadastro.php` — o sistema já cria automaticamente as categorias padrão de receita e despesa para o novo usuário.

## Estrutura do projeto

```
CashPilot/
├── assets/            # CSS, JS e imagens
├── database/           # conexao.php + banco.sql + migrations/
├── includes/           # header, navbar, footer, auth.php, csv_helper.php
├── pages/               # telas do sistema (dashboard, receitas, despesas, importar, ...)
├── actions/             # processamento de formulários (POST) — nunca renderizam HTML
├── python/              # insights.py, analise.py, previsao.py, classificar.py
├── index.php
└── logout.php
```

## Fluxo de importação de extrato

```
CSV do banco
   ↓ upload (pages/importar.php)
Validação (extensão, MIME, tamanho, colunas) — includes/csv_helper.php
   ↓
Leitura/parsing do CSV
   ↓
Classificação automática — python/classificar.py (regras de palavra-chave)
   ↓
Detecção de possíveis duplicatas (hash do arquivo + comparação de lançamentos)
   ↓
Pré-visualização e revisão (pages/importar_revisao.php) — usuário pode corrigir tudo
   ↓
Confirmação (actions/importar_confirmar.php) — grava em receitas/despesas
   ↓
Histórico de importações (pages/importacoes.php) + Dashboard atualizado
```

## Sobre o módulo de insights

A primeira versão **não usa IA generativa externa** — os insights são gerados por regras estatísticas simples em Python (variação percentual mês a mês, participação da categoria no total de gastos, progresso de metas, fluxo de caixa). Isso mantém o projeto dentro do escopo de tecnologias autorizadas e demonstra domínio de análise de dados com Python + MySQL.

## Escopo e próximos passos (fora do MVP)

- Sistema de pagamento real para o plano Premium (não implementado; planos são apresentados apenas como proposta)
- Autenticação de dois fatores
- Exportação de relatórios em PDF/Excel
- Testes automatizados

## Segurança implementada

- Senhas com hash (`password_hash`, algoritmo padrão do PHP)
- Consultas parametrizadas via PDO (proteção contra SQL Injection)
- Sessões PHP para autenticação, com `session_regenerate_id` no login/cadastro
- Escape de saída (`htmlspecialchars`) em todo conteúdo gerado pelo usuário
- Todas as queries filtram por `usuario_id`, garantindo isolamento de dados entre usuários
