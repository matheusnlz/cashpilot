# CashPilot 7

## Principais mudanças
- Copiloto Groq mantido e interface visual refinada.
- Funcionários entram como compromissos recorrentes e geram despesas mensais automaticamente.
- Fornecedores podem ser pontuais ou recorrentes.
- Custos do negócio foram separados dos funcionários.
- Receitas empresariais podem ser vinculadas a produtos/serviços e quantidade vendida.
- Dashboard do empreendedor usa vendas, margem bruta e compromissos reais; a antiga régua de caixa foi substituída por compromissos do próximo mês.
- RadarPilot PF e empresarial usa alertas mais específicos.
- Categorias PF/MEI foram separadas para novos cadastros, com categorias complementares para usuários atuais.
- Receitas e despesas permitem criar categoria diretamente na página.
- Classificação de extratos ganhou regras melhores e aprende com correções feitas na revisão.
- Metas PF foram redesenhadas como lista de objetivos + painel detalhado, histórico de aportes/retiradas e Copiloto contextual.

## Banco de dados
Execute UMA VEZ no phpMyAdmin:
`database/migrations/004_cashpilot7_integracao_financeira.sql`

Faça backup do banco antes da migration.
