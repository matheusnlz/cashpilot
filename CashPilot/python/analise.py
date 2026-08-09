"""
CashPilot - Módulo de Análise de Dados
==========================================
Gera séries de dados usadas nos gráficos e relatórios:
- Evolução mensal de receitas x despesas (últimos N meses)
- Distribuição de despesas por categoria no mês atual

Uso:
    python3 analise.py <usuario_id> [meses]

Saída (JSON):
    {
      "evolucao_mensal": [{"mes": "2026-03", "receitas": 0, "despesas": 0}, ...],
      "gastos_por_categoria": [{"categoria": "Alimentação", "total": 0}, ...]
    }
"""

import sys
import json
from datetime import date
from calendar import monthrange
from dateutil.relativedelta import relativedelta

from conexao import conectar


def evolucao_mensal(cursor, usuario_id: int, meses: int) -> list:
    serie = []
    hoje = date.today()

    for i in range(meses - 1, -1, -1):
        referencia = hoje - relativedelta(months=i)
        inicio = referencia.replace(day=1)
        fim = referencia.replace(day=monthrange(referencia.year, referencia.month)[1])

        cursor.execute(
            "SELECT COALESCE(SUM(valor), 0) AS total FROM receitas "
            "WHERE usuario_id = %s AND data_receita BETWEEN %s AND %s",
            (usuario_id, inicio, fim),
        )
        total_receitas = float(cursor.fetchone()["total"])

        cursor.execute(
            "SELECT COALESCE(SUM(valor), 0) AS total FROM despesas "
            "WHERE usuario_id = %s AND data_despesa BETWEEN %s AND %s",
            (usuario_id, inicio, fim),
        )
        total_despesas = float(cursor.fetchone()["total"])

        serie.append({
            "mes": referencia.strftime("%Y-%m"),
            "receitas": total_receitas,
            "despesas": total_despesas,
        })

    return serie


def gastos_por_categoria(cursor, usuario_id: int) -> list:
    hoje = date.today()
    inicio = hoje.replace(day=1)
    fim = hoje.replace(day=monthrange(hoje.year, hoje.month)[1])

    cursor.execute(
        """
        SELECT COALESCE(c.nome, 'Sem categoria') AS categoria, SUM(d.valor) AS total
        FROM despesas d
        LEFT JOIN categorias c ON c.id = d.categoria_id
        WHERE d.usuario_id = %s AND d.data_despesa BETWEEN %s AND %s
        GROUP BY categoria
        ORDER BY total DESC
        """,
        (usuario_id, inicio, fim),
    )
    linhas = cursor.fetchall()
    return [{"categoria": l["categoria"], "total": float(l["total"])} for l in linhas]


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"erro": "usuario_id não informado"}))
        sys.exit(1)

    usuario_id_arg = int(sys.argv[1])
    meses_arg = int(sys.argv[2]) if len(sys.argv) > 2 else 6

    try:
        conexao = conectar()
        cursor = conexao.cursor(dictionary=True)

        resultado = {
            "evolucao_mensal": evolucao_mensal(cursor, usuario_id_arg, meses_arg),
            "gastos_por_categoria": gastos_por_categoria(cursor, usuario_id_arg),
        }

        cursor.close()
        conexao.close()

        print(json.dumps(resultado, ensure_ascii=False))
    except Exception as erro:
        print(json.dumps({"erro": str(erro)}, ensure_ascii=False))
        sys.exit(1)
