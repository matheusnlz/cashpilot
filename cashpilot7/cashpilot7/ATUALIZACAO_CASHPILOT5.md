# CashPilot 5 — mudanças desta versão

## Antes de abrir o projeto
Execute `database/migrations/003_perfil_negocio.sql` no phpMyAdmin, banco `cashpilot`.

## Principais mudanças
- Dashboard detecta automaticamente Pessoa Física ou Empreendedor.
- Pessoa Física: orçamento mensal, quanto pode gastar, distribuição de gastos, metas, RadarPilot e gráfico 1M diário.
- Empreendedor: dashboard próprio por nicho, sem metas pessoais, com faturamento, despesas, lucro, margem, Régua de Caixa, RadarPilot empresarial e indicadores de estrutura.
- Nichos iniciais: Barbearia, Salão de beleza, Comércio local, Loja online, Alimentação, Prestação de serviços, Profissional autônomo e fallback Outro.
- Cadastro em etapas com questionário de contexto do negócio.
- Nova página `Negócio` para produtos/serviços e funcionários.
- Tipo da conta e nicho ficam bloqueados após o cadastro.
- Perfil permite alterar nome, e-mail, telefone, foto, senha e limite mensal (PF).
- Botão Sair vermelho e isolado no final do perfil.
- Contas, Categorias, Importação e Histórico possuem botão para voltar ao Perfil.
- Logo adaptada para fundo transparente na sidebar e versão escura na autenticação.
- Botão de recolher sidebar redesenhado e reposicionado.
- `autocomplete="off"` aplicado aos formulários e campos de escrita.
- Gráfico 1M passa a mostrar receitas/despesas por dia do mês.
- Relatórios mantêm estrutura comum, mas mudam linguagem conforme PF/Empreendedor.
- Copiloto continua com modo local e integração OpenAI preparada, sem exigir API para o site funcionar.

## API
A integração continua opcional. Não coloque chave no JavaScript ou GitHub. Use `includes/openai_config.php` quando decidir ativar.
