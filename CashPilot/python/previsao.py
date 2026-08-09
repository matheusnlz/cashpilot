"""
CashPilot - Módulo de Previsão de Metas
===========================================
Estima, com base no ritmo médio de economia dos últimos meses
(receitas - despesas), se uma meta financeira será atingida dentro
do prazo definido pelo usuário.

Uso:
    python3 previsao.py <usuario_id> <meta_id>

Saída (JSON):
    {"mensagem": "...", "meses_estimados": 4, "dentro_do_prazo": true}
"""

import sys
import json
from datetime import date
from calendar import monthrange
from dateutil.relativedelta import relativedelta

from conexao import conectar

MESES_HISTORICO = 3


def ritmo_medio_economia(cursor, usuario_id: int) -> float:
    hoje = date.today()
    total = 0.0

    for i in range(MESES_HISTORICO):
        referencia = hoje - relativedelta(months=i)
        inicio = referencia.replace(day=1)
        fim = referencia.replace(day=monthrange(referencia.year, referencia.month)[1])

        cursor.execute(
            "SELECT COALESCE(SUM(valor), 0) AS total FROM receitas "
            "WHERE usuario_id = %s AND data_receita BETWEEN %s AND %s",
            (usuario_id, inicio, fim),
        )
        receitas = float(cursor.fetchone()["total"])

        cursor.execute(
            "SELECT COALESCE(SUM(valor), 0) AS total FROM despesas "
            "WHERE usuario_id = %s AND data_despesa BETWEEN %s AND %s",
            (usuario_id, inicio, fim),
        )
        despesas = float(cursor.fetchone()["total"])

        total += (receitas - despesas)

    return total / MESES_HISTORICO


def prever_meta(usuario_id: int, meta_id: int) -> dict:
    conexao = conectar()
    cursor = conexao.cursor(dictionary=True)

    cursor.execute(
        "SELECT titulo, valor_meta, valor_atual, prazo FROM metas WHERE id = %s AND usuario_id = %s",
        (meta_id, usuario_id),
    )
    meta = cursor.fetchone()

    if not meta:
        cursor.close()
        conexao.close()
        return {"erro": "Meta não encontrada."}

    restante = float(meta["valor_meta"]) - float(meta["valor_atual"])

    if restante <= 0:
        cursor.close()
        conexao.close()
        return {"mensagem": f"A meta \"{meta['titulo']}\" já foi concluída.", "meses_estimados": 0, "dentro_do_prazo": True}

    ritmo = ritmo_medio_economia(cursor, usuario_id)
    cursor.close()
    conexao.close()

    if ritmo <= 0:
        return {
            "mensagem": f"No ritmo atual de gastos, a meta \"{meta['titulo']}\" não deve ser atingida. "
                        f"Tente reduzir despesas ou aumentar receitas.",
            "meses_estimados": None,
            "dentro_do_prazo": False,
        }

    meses_estimados = restante / ritmo
    dentro_do_prazo = True

    if meta["prazo"]:
        meses_disponiveis = (meta["prazo"].year - date.today().year) * 12 + (meta["prazo"].month - date.today().month)
        dentro_do_prazo = meses_estimados <= max(meses_disponiveis, 0)

    mensagem = (
        f"Mantendo esse ritmo de economia, você deve atingir a meta \"{meta['titulo']}\" "
        f"em aproximadamente {meses_estimados:.0f} meses."
    )
    if meta["prazo"] and not dentro_do_prazo:
        mensagem += " Nesse ritmo, o prazo definido não será suficiente."

    return {
        "mensagem": mensagem,
        "meses_estimados": round(meses_estimados, 1),
        "dentro_do_prazo": dentro_do_prazo,
    }


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"erro": "informe usuario_id e meta_id"}))
        sys.exit(1)

    try:
        resultado = prever_meta(int(sys.argv[1]), int(sys.argv[2]))
        print(json.dumps(resultado, ensure_ascii=False))
    except Exception as erro:
        print(json.dumps({"erro": str(erro)}, ensure_ascii=False))
        sys.exit(1)
