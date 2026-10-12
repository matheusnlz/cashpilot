-- CashPilot 15.4 - categorias complementares
-- Pode ser executada uma vez em bancos existentes. Evita duplicatas por usuario/tipo/nome.

INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao)
SELECT u.id, x.nome, x.tipo, x.cor, 1
FROM usuarios u
JOIN (
 SELECT 'Freelance' nome,'receita' tipo,'#3B6E71' cor UNION ALL
 SELECT 'Benefícios','receita','#5A6E5D' UNION ALL
 SELECT 'Reembolsos','receita','#6B7280' UNION ALL
 SELECT 'Restaurantes e delivery','despesa','#B5654A' UNION ALL
 SELECT 'Combustível','despesa','#7A6A53' UNION ALL
 SELECT 'Aplicativos e mobilidade','despesa','#6B7280' UNION ALL
 SELECT 'Energia e água','despesa','#4B5945' UNION ALL
 SELECT 'Internet e telefone','despesa','#3B6E71' UNION ALL
 SELECT 'Seguros','despesa','#4B5563' UNION ALL
 SELECT 'Cuidados pessoais','despesa','#765D69' UNION ALL
 SELECT 'Pets','despesa','#8A7B5A' UNION ALL
 SELECT 'Viagens','despesa','#3B6E71' UNION ALL
 SELECT 'Presentes e doações','despesa','#8A7B5A' UNION ALL
 SELECT 'Taxas e tarifas','despesa','#5A5A5A'
) x
WHERE u.tipo_perfil <> 'mei'
AND NOT EXISTS (SELECT 1 FROM categorias c WHERE c.usuario_id=u.id AND c.tipo=x.tipo AND c.nome=x.nome);

INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao)
SELECT u.id, x.nome, x.tipo, x.cor, 1
FROM usuarios u
JOIN (
 SELECT 'PIX e transferências recebidas' nome,'receita' tipo,'#3B6E71' cor UNION ALL
 SELECT 'Comissões','receita','#5A6E5D' UNION ALL
 SELECT 'Reembolsos','receita','#6B7280' UNION ALL
 SELECT 'Contabilidade','despesa','#4B5563' UNION ALL
 SELECT 'Taxas bancárias','despesa','#5A5A5A' UNION ALL
 SELECT 'Maquininha e meios de pagamento','despesa','#3B6E71' UNION ALL
 SELECT 'Software e assinaturas','despesa','#4B5945' UNION ALL
 SELECT 'Manutenção','despesa','#7A6A53' UNION ALL
 SELECT 'Fretes e entregas','despesa','#8A7B5A' UNION ALL
 SELECT 'Pró-labore','despesa','#765D69'
) x
WHERE u.tipo_perfil='mei'
AND NOT EXISTS (SELECT 1 FROM categorias c WHERE c.usuario_id=u.id AND c.tipo=x.tipo AND c.nome=x.nome);
