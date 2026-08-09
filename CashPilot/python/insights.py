"""
CashPilot - Módulo de Insights Financeiros
============================================
Analisa receitas, despesas, categorias e metas de um usuário e gera
mensagens automáticas em linguagem simples, interpretando os dados
em vez de apenas exibi-los.

Uso:
    python3 insights.py <usuario_id>

Saída:
    JSON impresso em stdout, no formato:
    {"insights": [{"tipo": "info", "mensagem": "..."}, ...]}

Este script é chamado a partir do PHP (dashboard.php) via shell_exec.
"""

import sys
import json
from datetime import date
from calendar import monthrange

from conexao import conectar


def intervalo_mes(referencia: date):
    inicio = referencia.replace(day=1)
    fim = referencia.replace(day=monthrange(referencia.year, referencia.month)[1])
    return inicio, fim


def mes_anterior(referencia: date) -> date:
    primeiro_dia_atual = referencia.replace(day=1)
    ultimo_dia_mes_anterior = primeiro_dia_atual.fromordinal(primeiro_dia_atual.toordinal() - 1)
    return ultimo_dia_mes_anterior


def somar(cursor, tabela: str, campo_data: str, usuario_id: int, inicio: date, fim: date) -> float:
    cursor.execute(
        f"SELECT COALESCE(SUM(valor), 0) AS total FROM {tabela} "
        f"WHERE usuario_id = %s AND {campo_data} BETWEEN %s AND %s",
        (usuario_id, inicio, fim),
    )
    resultado = cursor.fetchone()
    return float(resultado["total"])


def variacao_percentual(atual: float, anterior: float):
    if anterior <= 0:
        return None
    return round(((atual - anterior) / anterior) * 100, 1)


def categoria_principal(cursor, usuario_id: int, inicio: date, fim: date):
    cursor.execute(
        """
        SELECT c.nome, SUM(d.valor) AS total
        FROM despesas d
        LEFT JOIN categorias c ON c.id = d.categoria_id
        WHERE d.usuario_id = %s AND d.data_despesa BETWEEN %s AND %s
        GROUP BY c.nome
        ORDER BY total DESC
        LIMIT 1
        """,
        (usuario_id, inicio, fim),
    )
    return cursor.fetchone()


def gerar_insights(usuario_id: int) -> list:
    insights = []
    hoje = date.today()
    inicio_atual, fim_atual = intervalo_mes(hoje)
    inicio_anterior, fim_anterior = intervalo_mes(mes_anterior(hoje))

    conexao = conectar()
    cursor = conexao.cursor(dictionary=True)

    receitas_atual = somar(cursor, "receitas", "data_receita", usuario_id, inicio_atual, fim_atual)
    receitas_anterior = somar(cursor, "receitas", "data_receita", usuario_id, inicio_anterior, fim_anterior)
    despesas_atual = somar(cursor, "despesas", "data_despesa", usuario_id, inicio_atual, fim_atual)
    despesas_anterior = somar(cursor, "despesas", "data_despesa", usuario_id, inicio_anterior, fim_anterior)

    saldo_atual = receitas_atual - despesas_atual

    # --- Insight: variação de despesas em relação ao mês anterior ---
    var_despesas = variacao_percentual(despesas_atual, despesas_anterior)
    if var_despesas is not None:
        if var_despesas > 0:
            insights.append({
                "tipo": "alerta" if var_despesas >= 15 else "info",
                "mensagem": f"Suas despesas aumentaram {var_despesas:.0f}% em relação ao mês anterior.",
            })
        elif var_despesas < 0:
            insights.append({
                "tipo": "info",
                "mensagem": f"Suas despesas caíram {abs(var_despesas):.0f}% em relação ao mês anterior. Bom trabalho!",
            })

    # --- Insight: percentual da renda comprometido com despesas ---
    if receitas_atual > 0:
        percentual_comprometido = round((despesas_atual / receitas_atual) * 100, 0)
        if percentual_comprometido >= 90:
            insights.append({
                "tipo": "alerta",
                "mensagem": f"Você já comprometeu {percentual_comprometido:.0f}% da sua renda deste mês com despesas.",
            })
        else:
            insights.append({
                "tipo": "info",
                "mensagem": f"Você comprometeu {percentual_comprometido:.0f}% da sua renda deste mês com despesas.",
            })

    # --- Insight: categoria de maior gasto ---
    principal = categoria_principal(cursor, usuario_id, inicio_atual, fim_atual)
    if principal and principal["nome"] and despesas_atual > 0:
        percentual_categoria = round((float(principal["total"]) / despesas_atual) * 100, 0)
        insights.append({
            "tipo": "info",
            "mensagem": f"{principal['nome']} é sua maior categoria de gastos, representando {percentual_categoria:.0f}% das despesas do mês.",
        })

    # --- Insight: fluxo de caixa do mês ---
    if despesas_atual > 0 or receitas_atual > 0:
        if saldo_atual >= 0:
            insights.append({
                "tipo": "info",
                "mensagem": f"Seu fluxo de caixa está positivo neste mês, com saldo de R$ {saldo_atual:,.2f}.".replace(",", "#").replace(".", ",").replace("#", "."),
            })
        else:
            insights.append({
                "tipo": "alerta",
                "mensagem": f"Seu fluxo de caixa está negativo neste mês, {abs(saldo_atual):,.2f} R$ a mais de despesas do que receitas.".replace(",", "#").replace(".", ",").replace("#", "."),
            })

    # --- Insight: progresso das metas ---
    cursor.execute(
        "SELECT titulo, valor_meta, valor_atual, prazo FROM metas "
        "WHERE usuario_id = %s AND concluida = 0 ORDER BY prazo IS NULL, prazo ASC LIMIT 1",
        (usuario_id,),
    )
    meta = cursor.fetchone()
    if meta and meta["valor_meta"] > 0:
        percentual_meta = round((float(meta["valor_atual"]) / float(meta["valor_meta"])) * 100, 0)
        insights.append({
            "tipo": "info",
            "mensagem": f"Sua meta \"{meta['titulo']}\" está {percentual_meta:.0f}% concluída.",
        })

    cursor.close()
    conexao.close()

    if not insights:
        insights.append({
            "tipo": "info",
            "mensagem": "Cadastre receitas e despesas para começar a receber insights personalizados.",
        })

    return insights


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"insights": [], "erro": "usuario_id não informado"}))
        sys.exit(1)

    try:
        usuario_id_arg = int(sys.argv[1])
        resultado = {"insights": gerar_insights(usuario_id_arg)}
        print(json.dumps(resultado, ensure_ascii=False))
    except Exception as erro:
        print(json.dumps({"insights": [], "erro": str(erro)}, ensure_ascii=False))
        sys.exit(1)
