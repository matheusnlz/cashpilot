-- CashPilot 15 — biblioteca inicial com vídeos previamente verificados.
USE cashpilot;

INSERT INTO aprender_trilhas (titulo, descricao, perfil, ordem, ativo) VALUES
('Primeiros passos financeiros','Organize seu dinheiro e entenda os fundamentos para tomar decisões financeiras melhores.','ambos',10,1),
('Score e vida financeira','Entenda score, crédito e hábitos que afetam sua vida financeira.','pessoa_fisica',20,1),
('Finanças do MEI','Conteúdos essenciais para organizar o caixa de um pequeno negócio.','mei',30,1)
ON DUPLICATE KEY UPDATE titulo=VALUES(titulo);

INSERT INTO aprender_videos (titulo, descricao, youtube_video_id, categoria, perfil, tags, ordem, ativo) VALUES
('Cidadania Financeira','Introdução à educação e cidadania financeira.','GIAH5Tr8a8Q','Educação financeira','ambos','educação financeira dinheiro finanças organização planejamento orçamento renda gastos cidadania financeira',10,1),
('Planilha de gastos: como fazer seu controle financeiro','Controle de renda, despesas e orçamento mensal.','fxkAvkcNPp8','Controle financeiro','ambos','controle financeiro gastos despesas receitas renda orçamento organização dinheiro planejamento mensal',20,1),
('Como aumentar o Score','Conteúdo introdutório sobre hábitos e score de crédito.','Z29iHEeA2vQ','Crédito','pessoa_fisica','score crédito serasa organização financeira dívidas pagamento histórico financeiro',30,1),
('O que é Score 2.0?','Explicação sobre o funcionamento do score.','uYIt9LeXwvg','Crédito','pessoa_fisica','score crédito pontuação serasa histórico financeiro pagamento',40,1),
('Capital de Giro','Conteúdo do Sebrae sobre capital de giro para pequenos negócios.','opns6qee_CA','MEI','mei','capital de giro mei negócio caixa fluxo de caixa custos planejamento empreendedor',50,1)
ON DUPLICATE KEY UPDATE titulo=VALUES(titulo), descricao=VALUES(descricao), categoria=VALUES(categoria), perfil=VALUES(perfil), tags=VALUES(tags), ativo=1;

INSERT IGNORE INTO aprender_trilha_videos (trilha_id, video_id, ordem)
SELECT t.id,v.id,10 FROM aprender_trilhas t JOIN aprender_videos v ON v.youtube_video_id='GIAH5Tr8a8Q' WHERE t.titulo='Primeiros passos financeiros';
INSERT IGNORE INTO aprender_trilha_videos (trilha_id, video_id, ordem)
SELECT t.id,v.id,20 FROM aprender_trilhas t JOIN aprender_videos v ON v.youtube_video_id='fxkAvkcNPp8' WHERE t.titulo='Primeiros passos financeiros';
INSERT IGNORE INTO aprender_trilha_videos (trilha_id, video_id, ordem)
SELECT t.id,v.id,10 FROM aprender_trilhas t JOIN aprender_videos v ON v.youtube_video_id='Z29iHEeA2vQ' WHERE t.titulo='Score e vida financeira';
INSERT IGNORE INTO aprender_trilha_videos (trilha_id, video_id, ordem)
SELECT t.id,v.id,20 FROM aprender_trilhas t JOIN aprender_videos v ON v.youtube_video_id='uYIt9LeXwvg' WHERE t.titulo='Score e vida financeira';
INSERT IGNORE INTO aprender_trilha_videos (trilha_id, video_id, ordem)
SELECT t.id,v.id,10 FROM aprender_trilhas t JOIN aprender_videos v ON v.youtube_video_id='opns6qee_CA' WHERE t.titulo='Finanças do MEI';
