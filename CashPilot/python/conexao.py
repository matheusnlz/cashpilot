"""
CashPilot - Conexão Python com o MySQL
Usado pelos scripts de análise (insights.py, analise.py, previsao.py).
"""

import mysql.connector

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "cashpilot",
}


def conectar():
    """Abre e retorna uma conexão com o banco de dados MySQL."""
    return mysql.connector.connect(**DB_CONFIG)
