"""
CashPilot - conexão Python com MySQL.

Aceita tanto as variáveis DB_* usadas pelo projeto quanto as variáveis
MYSQL* fornecidas automaticamente por provedores como Railway.
"""

import os

import mysql.connector


def _env(*names: str, default: str = "") -> str:
    for name in names:
        value = os.getenv(name)
        if value is not None and value.strip() != "":
            return value.strip()
    return default


def conectar():
    """Abre e retorna uma conexão com o banco de dados MySQL."""
    config = {
        "host": _env("DB_HOST", "MYSQLHOST", default="localhost"),
        "port": int(_env("DB_PORT", "MYSQLPORT", default="3306")),
        "user": _env("DB_USER", "MYSQLUSER", default="root"),
        "password": _env("DB_PASS", "MYSQLPASSWORD", default=""),
        "database": _env("DB_NAME", "MYSQLDATABASE", default="cashpilot"),
        "charset": "utf8mb4",
        "use_unicode": True,
    }

    return mysql.connector.connect(**config)
