<?php

/**
 * Funções de personalização do CashPilot 11.1.
 */

function cpPerfilEmpreendedor(PDO $pdo, int $usuarioId): array
 {

        $stmt = $pdo->prepare(
            'SELECT
            u.nicho,
            p.nome_negocio,
            p.oferta,
            p.operacao,
            p.publico_alvo,
            p.canal_principal,
            p.objetivo_principal
         FROM usuarios u
         LEFT JOIN perfil_negocio p ON p.usuario_id = u.id
         WHERE u.id = :uid'
        );

        $stmt->execute(['uid' => $usuarioId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

}

function cpTextoNormalizado(string $texto): string
 {

        $texto = mb_strtolower(trim($texto));

        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;

        return preg_replace('/[^a-z0-9]+/', ' ', $texto) ?: '';

}

function cpGrupoNichoPersonalizado(string $nicho): string
 {

        $texto = cpTextoNormalizado($nicho);

        $grupos = [
            'beleza' => ['barbearia', 'beleza', 'estetica', 'salao'],
            'alimentacao' => ['alimentacao', 'restaurante', 'lanchonete', 'confeitaria', 'padaria'],
            'comercio' => ['comercio', 'loja', 'e commerce', 'artesanato'],
            'automotivo' => ['oficina', 'automotivo'],
            'saude' => ['saude', 'bem estar', 'academia', 'fitness', 'pet'],
            'servicos' => ['servico', 'consultoria', 'limpeza', 'manutencao', 'reparo', 'fotografia', 'marketing', 'design'],
            'tecnologia' => ['tecnologia', 'software', 'digital'],
            'educacao' => ['educacao', 'curso'],
            'construcao' => ['construcao', 'reforma'],
            'logistica' => ['transporte', 'logistica', 'turismo', 'hospedagem'],
        ];

        foreach ($grupos as $grupo => $termos) {

                foreach ($termos as $termo) {

                        if (str_contains($texto, $termo)) {

                                return $grupo;

            }

        }

    }

        return 'geral';

}

function cpFocoEmpreendedor(array $perfil): array
 {

        $objetivo = cpTextoNormalizado((string) ($perfil['objetivo_principal'] ?? ''));

        $grupo = cpGrupoNichoPersonalizado((string) ($perfil['nicho'] ?? ''));

        $foco = [
            'titulo' => 'Visão geral do negócio',
            'descricao' => 'Acompanhe os principais indicadores e use o Copiloto para aprofundar o que merece atenção.',
            'metricas' => ['resultado', 'faturamento', 'margem', 'vendas'],
            'grupo_nicho' => $grupo,
        ];

        if (str_contains($objetivo, 'margem') || str_contains($objetivo, 'lucro')) {

                $foco['titulo'] = 'Foco atual: melhorar lucro e margem';

                $foco['descricao'] = 'O CashPilot prioriza margem, custos e rentabilidade dos produtos e serviços.';

                $foco['metricas'] = ['margem', 'lucro', 'custos', 'produtos'];

    }  elseif (str_contains($objetivo, 'estoque')) {

                $foco['titulo'] = 'Foco atual: controlar estoque';

                $foco['descricao'] = 'O CashPilot dá mais atenção a estoque mínimo, reposição, produtos e fornecedores.';

                $foco['metricas'] = ['estoque', 'produtos', 'fornecedores', 'vendas'];

    }  elseif (str_contains($objetivo, 'caixa')) {

                $foco['titulo'] = 'Foco atual: melhorar fluxo de caixa';

                $foco['descricao'] = 'O CashPilot prioriza compromissos, entradas, saídas e projeção de caixa.';

                $foco['metricas'] = ['caixa', 'resultado', 'compromissos', 'previsao'];

    }  elseif (str_contains($objetivo, 'custo') || str_contains($objetivo, 'despesa')) {

                $foco['titulo'] = 'Foco atual: organizar custos';

                $foco['descricao'] = 'O CashPilot prioriza custos fixos, variáveis, fornecedores e estrutura recorrente.';

                $foco['metricas'] = ['custos', 'fornecedores', 'resultado', 'margem'];

    }  elseif (str_contains($objetivo, 'venda') || str_contains($objetivo, 'faturamento') || str_contains($objetivo, 'cliente')) {

                $foco['titulo'] = 'Foco atual: crescer vendas';

                $foco['descricao'] = 'O CashPilot prioriza faturamento, ticket médio, vendas e itens com melhor desempenho.';

                $foco['metricas'] = ['faturamento', 'ticket', 'vendas', 'produtos'];

    }  elseif (str_contains($objetivo, 'equipe')) {

                $foco['titulo'] = 'Foco atual: organizar equipe';

                $foco['descricao'] = 'O CashPilot dá mais contexto para custos da equipe e impacto das contratações.';

                $foco['metricas'] = ['equipe', 'custos', 'resultado', 'caixa'];

    }  elseif (str_contains($objetivo, 'previs')) {

                $foco['titulo'] = 'Foco atual: ganhar previsibilidade';

                $foco['descricao'] = 'O CashPilot prioriza tendências, recorrências e projeções de caixa.';

                $foco['metricas'] = ['previsao', 'caixa', 'recorrencias', 'resultado'];

    }

        return $foco;

}

function cpVideoCombinaPerfil(array $video, array $perfil): int
 {

        $pontos = 0;

        $nicho = cpTextoNormalizado((string) ($perfil['nicho'] ?? ''));

        $objetivo = cpTextoNormalizado((string) ($perfil['objetivo_principal'] ?? ''));

        $nichosVideo = cpTextoNormalizado((string) ($video['nichos'] ?? ''));

        $objetivosVideo = cpTextoNormalizado((string) ($video['objetivos'] ?? ''));

        if ($nichosVideo !== '') {

                foreach (array_filter(explode(' ', $nicho)) as $termo) {

                        if (mb_strlen($termo) >= 4 && str_contains($nichosVideo, $termo)) {

                                $pontos += 3;

            }

        }

    }

        if ($objetivosVideo !== '') {

                foreach (array_filter(explode(' ', $objetivo)) as $termo) {

                        if (mb_strlen($termo) >= 4 && str_contains($objetivosVideo, $termo)) {

                                $pontos += 2;

            }

        }

    }

        return $pontos;

}
