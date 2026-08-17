# CashPilot — versão de evolução pontual

Esta versão mantém a estrutura original do projeto e adiciona melhorias pontuais para o TCC.

## Principais mudanças

- Sidebar recolhível, com logo compacta e foto/iniciais do usuário.
- Contas, Importar Extrato, Importações e Categorias retirados da sidebar e concentrados em Meu Perfil.
- Sair da conta movido para Meu Perfil.
- Meu Perfil reorganizado como área de configurações.
- Tipo de conta não pode mais ser alterado diretamente no perfil.
- Cadastro de MEI/empreendedor passou a perguntar o nicho do negócio.
- Dashboard com cartões interativos, RadarPilot e filtros de 1, 3, 6 e 12 meses no gráfico.
- Metas com previsão, leitura do valor restante e ações rápidas.
- Receitas e Despesas limitadas a 20 registros por página, mantendo os filtros existentes.
- Relatórios com alternância entre distribuição de Gastos e Entradas.
- Área MEI redesenhada pontualmente para foco em faturamento, despesas, lucro, margem, resultado anual e régua de caixa.
- Copiloto CashPilot disponível como chat contextual com os dados financeiros do usuário.
- Avatar de usuário salvo em `uploads/avatars`.

## Banco de dados

Para uma instalação existente, execute:

`database/migrations/002_perfil_nicho_avatar.sql`

A migration adiciona `usuarios.nicho` e `usuarios.avatar_path`.

Para uma instalação nova, o `database/banco.sql` já contém as duas colunas.

## Observação sobre o Copiloto

A interface e o fluxo de conversa foram preparados dentro da estrutura atual do projeto, sem exigir uma API externa. O arquivo `actions/chatbot.php` usa os dados do usuário e regras de contexto. Para transformar o Copiloto em uma IA generativa real, o próximo passo é conectar esse endpoint a um provedor/modelo de IA, sem precisar refazer a interface.

## Validação

Os arquivos PHP foram verificados com `php -l` nesta versão. A execução completa depende do ambiente local (Apache/PHP/MySQL/Python) e da aplicação da migration do banco.


## CashPilot 4 — novas melhorias
- Logo oficial integrada à sidebar e telas de autenticação.
- RadarPilot com ação “Entender e agir”, conectada ao Copiloto.
- Copiloto preparado para OpenAI Responses API, com fallback local quando não houver chave.
- Contexto enviado à IA usa dados agregados do usuário (receitas, despesas, categorias e metas), evitando enviar a base inteira.
- Metas podem abrir uma conversa contextualizada com o Copiloto.
- Área MEI ganhou Régua de Caixa baseada na média do histórico recente e tratamento de acordo com o nicho.
- Relatórios agora possuem períodos 1M/3M/6M/12M e uma leitura rápida dos dados.
- Exportação de receitas e despesas em CSV disponível em Meu Perfil.

### Ativar IA generativa no Copiloto
1. Copie `includes/openai_config.example.php` para `includes/openai_config.php`.
2. Coloque sua API key no arquivo copiado.
3. O arquivo `includes/openai_config.php` está no `.gitignore` e não deve ser enviado ao GitHub.
4. É necessário que a extensão cURL do PHP esteja habilitada.

Sem uma API key, o Copiloto continua funcionando no modo local baseado em regras.

### Banco de dados
Esta atualização não exige novas tabelas nem nova migration além das migrations já presentes na versão anterior.

## Copiloto com Groq (CashPilot 6)
A integração de IA foi centralizada em `includes/ia.php` e usa `GROQ_API_KEY` no backend. Consulte `INTEGRACAO_GROQ.md` para configuração. O fallback local continua ativo caso o provedor esteja indisponível.
