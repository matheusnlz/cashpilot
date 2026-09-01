"""
CashPilot - Classificação automática de movimentações
==========================================================
Recebe uma lista de movimentações (descrição + valor) extraídas de um
extrato CSV e sugere tipo (receita/despesa) e categoria, usando regras
de palavras-chave. Não é IA generativa — é um classificador baseado em
regras e no histórico de categorias do próprio usuário.

Uso:
    python3 classificar.py <usuario_id>
    (lê um JSON pela stdin: [{"descricao": "...", "valor": -45.9}, ...])

Saída (stdout, JSON):
    [{"descricao": "...", "valor": -45.9, "tipo": "despesa",
      "categoria_id": 12, "categoria_nome": "Transporte", "confianca": "alta"}, ...]
"""

import sys
import json
import unicodedata

from conexao import conectar

# ------------------------------------------------------------------
# Regras de classificação por palavra-chave.
# Cada entrada: (lista de palavras-chave, tipo, nome da categoria)
# As palavras-chave são comparadas em maiúsculas, sem acento.
# ------------------------------------------------------------------
REGRAS = [
    # Transporte
    (["UBER", "99POP", "99 APP", "CABIFY", "TAXI", "METRO", "METRÔ", "CPTM", "BILHETE UNICO", "BILHETE ÚNICO"], "despesa", "Transporte"),
    (["SHELL", "IPIRANGA", "PETROBRAS", "POSTO", "GASOLINA", "ETANOL", "COMBUSTIVEL", "COMBUSTÍVEL", "PEDAGIO", "PEDÁGIO"], "despesa", "Transporte"),

    # Alimentação e mercado
    (["IFOOD", "RAPPI", "UBER EATS", "RESTAURANTE", "LANCHONETE", "PIZZARIA", "PADARIA", "MCDONALD", "BURGER KING", "OUTBACK"], "despesa", "Alimentação"),
    (["CARREFOUR", "ATACADAO", "ATACADÃO", "ASSAI", "ASSAÍ", "SUPERMERCADO", "MERCADO", "HORTIFRUTI", "COOP", "PAO DE ACUCAR", "PÃO DE AÇÚCAR", "DIA BRASIL"], "despesa", "Mercado"),

    # Assinaturas e lazer
    (["NETFLIX", "SPOTIFY", "DISNEY", "HBO", "MAX.COM", "PRIME VIDEO", "YOUTUBE PREMIUM", "DEEZER", "APPLE.COM/BILL", "GOOGLE ONE"], "despesa", "Assinaturas"),
    (["CINEMA", "INGRESSO", "SHOW", "STEAM", "PLAYSTATION", "XBOX", "EPIC GAMES", "RI HAPPY"], "despesa", "Lazer"),

    # Saúde
    (["FARMACIA", "FARMÁCIA", "DROGARIA", "DROGASIL", "RAIA", "HOSPITAL", "CLINICA", "CLÍNICA", "CONSULTA", "LABORATORIO", "LABORATÓRIO"], "despesa", "Saúde"),

    # Moradia e contas
    (["ALUGUEL", "CONDOMINIO", "CONDOMÍNIO", "IMOBILIARIA", "IMOBILIÁRIA"], "despesa", "Moradia"),
    (["ENEL", "CPFL", "SABESP", "ENERGIA", "AGUA", "ÁGUA", "INTERNET", "TELEFONIA", "VIVO", "CLARO", "TIM", "OI FIBRA"], "despesa", "Contas e serviços"),

    # Educação
    (["ESCOLA", "FACULDADE", "UNIVERSIDADE", "CURSO", "UDEMY", "ALURA", "DIO", "COURSERA"], "despesa", "Educação"),

    # Compras
    (["AMAZON", "MERCADO LIVRE", "MERCADOLIVRE", "SHOPEE", "SHEIN", "MAGALU", "MAGAZINE LUIZA", "ALIEXPRESS", "TEMU"], "despesa", "Compras"),

    # Receitas
    (["SALARIO", "SALÁRIO", "FOLHA DE PAGAMENTO", "REMUNERACAO", "REMUNERAÇÃO", "PROVENTOS"], "receita", "Salário"),
    (["PIX RECEBIDO", "TED RECEBIDO", "TRANSFERENCIA RECEBIDA", "TRANSFERÊNCIA RECEBIDA", "DEPOSITO", "DEPÓSITO", "CREDITO EM CONTA", "CRÉDITO EM CONTA"], "receita", "Outros"),
    (["VENDA", "PAGAMENTO RECEBIDO", "RECEBIMENTO", "MAQUININHA", "PAGSEGURO", "MERCADO PAGO RECEBIDO"], "receita", "Vendas"),

    # Tributos / negócio
    (["DAS", "DARF", "IMPOSTO", "INSS", "IRRF", "ISS", "ICMS"], "despesa", "Impostos"),
    (["FORNECEDOR", "NOTA FISCAL", "NFE", "NF-E"], "despesa", "Fornecedores"),

    # Operações bancárias genéricas de saída
    (["PIX ENVIADO", "PIX REALIZADO", "PIX PAGO", "TED ENVIADA", "TRANSFERENCIA ENVIADA", "TRANSFERÊNCIA ENVIADA", "BOLETO PAGO", "PAGAMENTO DE BOLETO", "DEBITO AUTOMATICO", "DÉBITO AUTOMÁTICO", "TARIFA BANCARIA", "TARIFA BANCÁRIA"], "despesa", "Outros"),
]



def normalizar(texto: str) -> str:
    """Maiúsculas, sem acento, sem espaços — para comparação de palavras-chave."""
    texto = texto.upper()
    texto = unicodedata.normalize("NFKD", texto).encode("ASCII", "ignore").decode("ASCII")
    return texto


def buscar_categorias_usuario(cursor, usuario_id: int) -> list:
    cursor.execute(
        "SELECT id, nome, tipo FROM categorias WHERE usuario_id = %s",
        (usuario_id,),
    )
    return cursor.fetchall()


def encontrar_categoria(categorias: list, nome_sugerido: str, tipo: str):
    """Procura, nas categorias do usuário, uma com nome igual (case-insensitive)
    ao sugerido pela regra. Se não encontrar, cai para 'Outros' do mesmo tipo."""
    nome_normalizado = normalizar(nome_sugerido)

    for cat in categorias:
        if cat["tipo"] == tipo and normalizar(cat["nome"]) == nome_normalizado:
            return cat

    for cat in categorias:
        if cat["tipo"] == tipo and normalizar(cat["nome"]) == "OUTROS":
            return cat

    return None


def buscar_regras_usuario(cursor, usuario_id: int) -> list:
    try:
        cursor.execute("SELECT termo, tipo, categoria_id FROM classificacao_regras WHERE usuario_id = %s ORDER BY CHAR_LENGTH(termo) DESC", (usuario_id,))
        return cursor.fetchall()
    except Exception:
        return []


def classificar_movimentacao(descricao: str, valor: float, categorias: list, regras_usuario=None) -> dict:
    descricao_normalizada = normalizar(descricao)
    tipo_pelo_sinal = "receita" if valor >= 0 else "despesa"
    for regra in (regras_usuario or []):
        termo = normalizar(str(regra.get("termo", "")))
        if termo and termo in descricao_normalizada:
            cat = next((c for c in categorias if int(c["id"]) == int(regra["categoria_id"]) and c["tipo"] == regra["tipo"]), None)
            if cat:
                return {"tipo": regra["tipo"], "categoria_id": cat["id"], "categoria_nome": cat["nome"], "confianca": "personalizada"}

    for palavras_chave, tipo_regra, nome_categoria in REGRAS:
        for palavra in palavras_chave:
            if palavra in descricao_normalizada:
                categoria = encontrar_categoria(categorias, nome_categoria, tipo_regra)
                return {
                    "tipo": tipo_regra,
                    "categoria_id": categoria["id"] if categoria else None,
                    "categoria_nome": categoria["nome"] if categoria else nome_categoria,
                    "confianca": "alta",
                }

    # Nenhuma regra encontrada: usa o sinal do valor e cai em "Outros"
    categoria = encontrar_categoria(categorias, "Outros", tipo_pelo_sinal)
    return {
        "tipo": tipo_pelo_sinal,
        "categoria_id": categoria["id"] if categoria else None,
        "categoria_nome": categoria["nome"] if categoria else "Outros",
        "confianca": "baixa",
    }


def classificar_lote(usuario_id: int, movimentacoes: list) -> list:
    conexao = conectar()
    cursor = conexao.cursor(dictionary=True)
    categorias = buscar_categorias_usuario(cursor, usuario_id)
    regras_usuario = buscar_regras_usuario(cursor, usuario_id)
    cursor.close()
    conexao.close()

    resultado = []
    for mov in movimentacoes:
        descricao = mov.get("descricao", "")
        valor = float(mov.get("valor", 0))
        classificacao = classificar_movimentacao(descricao, valor, categorias, regras_usuario)
        resultado.append({
            "descricao": descricao,
            "valor": valor,
            "data": mov.get("data"),
            **classificacao,
        })

    return resultado


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"erro": "usuario_id não informado"}))
        sys.exit(1)

    try:
        usuario_id_arg = int(sys.argv[1])
        entrada = sys.stdin.read()
        movimentacoes_arg = json.loads(entrada) if entrada.strip() else []

        resultado = classificar_lote(usuario_id_arg, movimentacoes_arg)
        print(json.dumps(resultado, ensure_ascii=False))
    except Exception as erro:
        print(json.dumps({"erro": str(erro)}, ensure_ascii=False))
        sys.exit(1)
