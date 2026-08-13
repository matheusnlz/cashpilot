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
    (["UBER", "99POP", "99TAXI", "CABIFY", "TAXI"], "despesa", "Transporte"),
    (["POSTO", "SHELL", "IPIRANGA", "PETROBRAS", "COMBUSTIVEL", "ALCOOL", "GASOLINA"], "despesa", "Transporte"),
    (["IFOOD", "RAPPI", "UBEREATS", "UBER EATS", "JAMESDELIVERY"], "despesa", "Alimentação"),
    (["SUPERMERCADO", "CARREFOUR", "PAODEACUCAR", "EXTRA", "ATACADAO", "MERCADO", "HORTIFRUTI"], "despesa", "Alimentação"),
    (["RESTAURANTE", "LANCHONETE", "PADARIA", "PIZZARIA"], "despesa", "Alimentação"),
    (["NETFLIX", "SPOTIFY", "DISNEY", "HBO", "PRIMEVIDEO", "YOUTUBEPREMIUM", "DEEZER"], "despesa", "Lazer"),
    (["CINEMA", "INGRESSO", "SHOW"], "despesa", "Lazer"),
    (["FARMACIA", "DROGARIA", "DROGASIL", "PACHECO", "RAIA"], "despesa", "Saúde"),
    (["HOSPITAL", "CLINICA", "CONSULTA", "PLANOSAUDE", "UNIMED"], "despesa", "Saúde"),
    (["ALUGUEL", "CONDOMINIO", "IMOBILIARIA"], "despesa", "Moradia"),
    (["ENERGIA", "CEMIG", "ENEL", "COPEL", "CPFL", "LIGHT"], "despesa", "Moradia"),
    (["SANEAMENTO", "SABESP", "COPASA", "AGUA"], "despesa", "Moradia"),
    (["INTERNET", "VIVOFIBRA", "CLARONET", "OIFIBRA", "TELEFONIA", "CELULAR"], "despesa", "Tecnologia"),
    (["ESCOLA", "FACULDADE", "UNIVERSIDADE", "CURSO", "MENSALIDADEESCOLAR"], "despesa", "Educação"),
    (["SALARIO", "FOLHADEPAGAMENTO", "PAGAMENTOSALARIAL", "REMUNERACAO"], "receita", "Salário"),
    (["PIXRECEBIDO", "TEDRECEBIDO", "TRANSFERENCIARECEBIDA", "DEPOSITO"], "receita", "Outros"),
    (["VENDA", "PAGAMENTORECEBIDO", "RECEBIMENTO"], "receita", "Vendas"),
    (["DAS", "DARF", "IMPOSTO", "INSS", "IRRF"], "despesa", "Impostos"),
    (["FORNECEDOR", "NOTAFISCAL", "NFE"], "despesa", "Fornecedores"),
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


def classificar_movimentacao(descricao: str, valor: float, categorias: list) -> dict:
    descricao_normalizada = normalizar(descricao)
    tipo_pelo_sinal = "receita" if valor >= 0 else "despesa"

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
    cursor.close()
    conexao.close()

    resultado = []
    for mov in movimentacoes:
        descricao = mov.get("descricao", "")
        valor = float(mov.get("valor", 0))
        classificacao = classificar_movimentacao(descricao, valor, categorias)
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
