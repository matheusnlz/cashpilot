import json
import re
import sys
from collections import Counter, defaultdict
from datetime import datetime

try:
    import pdfplumber
except Exception:
    pdfplumber = None

try:
    from pypdf import PdfReader
except Exception:
    try:
        from PyPDF2 import PdfReader
    except Exception:
        PdfReader = None

MONTHS = {
    'JAN': 1, 'FEV': 2, 'MAR': 3, 'ABR': 4, 'MAI': 5, 'JUN': 6,
    'JUL': 7, 'AGO': 8, 'SET': 9, 'OUT': 10, 'NOV': 11, 'DEZ': 12,
    'JANUARY': 1, 'FEBRUARY': 2, 'MARCH': 3, 'APRIL': 4, 'MAY': 5, 'JUNE': 6,
    'JULY': 7, 'AUGUST': 8, 'SEPTEMBER': 9, 'OCTOBER': 10, 'NOVEMBER': 11, 'DECEMBER': 12,
}

DATE_FULL = re.compile(r"\b(\d{1,2}[/-]\d{1,2}[/-]\d{2,4})\b")
DATE_SHORT = re.compile(r"\b(\d{1,2}[/-]\d{1,2})\b")
DATE_MONTH_NAME = re.compile(r"\b(\d{1,2})\s+(JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ)(?:\s+(\d{4}))?\b", re.I)
MONEY = re.compile(
    r"(?<!\d)([-+]?\s*(?:R\$\s*)?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{2}|\.\d{2})\s*[DCdc]?)(?!\d)"
)

BALANCE_TERMS = (
    'saldo anterior', 'saldo do dia', 'saldo diário', 'saldo diario', 'saldo disponível',
    'saldo disponivel', 'saldo atual', 'saldo final', 'saldo inicial', 'saldo em conta',
    'saldo total', 'saldo',
)
SUMMARY_TERMS = (
    'total de entradas', 'total entradas', 'total de saídas', 'total de saidas',
    'total saídas', 'total saidas', 'resumo do período', 'resumo do periodo',
    'limite disponível', 'limite disponivel', 'limite utilizado', 'rendimento',
    'rendimentos', 'juros acumulados', 'fechamento', 'total',
)
EXPENSE_WORDS = (
    'compra', 'pagamento', 'pix enviado', 'pix realizado', 'pix pago', 'transferencia enviada',
    'transferência enviada', 'ted enviada', 'doc enviado', 'saque', 'debito', 'débito', 'tarifa',
    'taxa', 'boleto pago', 'cartao', 'cartão', 'fatura', 'débito automático',
    'debito automatico', 'pagto', 'pgto', 'enviado para', 'posto', 'mercado', 'ifood',
)
INCOME_WORDS = (
    'pix recebido', 'transferencia recebida', 'transferência recebida', 'ted recebida',
    'deposito', 'depósito', 'salario', 'salário', 'recebimento', 'crédito em conta',
    'credito em conta', 'estorno', 'cashback', 'recebido de',
)
BANK_MARKERS = {
    'nubank': ('nubank', 'nu pagamentos', 'conta nu'),
    'inter': ('banco inter', 'inter&co', 'inter co'),
    'itau': ('itaú', 'itau unibanco', 'itaú unibanco'),
    'bradesco': ('bradesco',),
    'santander': ('santander',),
    'bb': ('banco do brasil',),
    'caixa': ('caixa econômica', 'caixa economica', 'caixa tem'),
}


def emit(payload):
    sys.stdout.buffer.write(json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode('utf-8'))
    sys.stdout.flush()


def fail(message, bank='auto', warnings=None, meta=None):
    emit({
        'sucesso': False,
        'erro': message,
        'linhas': [],
        'ignoradas': 0,
        'banco': bank,
        'avisos': warnings or [],
        'meta': meta or {},
    })


def norm(text):
    return re.sub(r'\s+', ' ', (text or '')).strip()


def detect_bank(text, selected):
    selected = (selected or 'auto').lower().strip()
    if selected not in ('', 'auto', 'outro'):
        return selected
    low = text.lower()
    for bank, markers in BANK_MARKERS.items():
        if any(marker in low for marker in markers):
            return bank
    return 'generico'


def infer_year(text):
    years = [int(y) for y in re.findall(r'\b(20\d{2})\b', text)]
    if years:
        return Counter(years).most_common(1)[0][0]
    return datetime.now().year


def parse_date_token(text, default_year):
    m = DATE_FULL.search(text)
    if m:
        value = m.group(1)
        for fmt in ('%d/%m/%Y', '%d-%m-%Y', '%d/%m/%y', '%d-%m-%y'):
            try:
                return datetime.strptime(value, fmt).strftime('%Y-%m-%d'), m
            except ValueError:
                pass

    m = DATE_MONTH_NAME.search(text)
    if m:
        day = int(m.group(1))
        month = MONTHS.get(m.group(2).upper())
        year = int(m.group(3)) if m.group(3) else default_year
        if month:
            try:
                return datetime(year, month, day).strftime('%Y-%m-%d'), m
            except ValueError:
                pass

    m = DATE_SHORT.search(text)
    if m:
        value = m.group(1)
        for sep in ('/', '-'):
            if sep in value:
                try:
                    day, month = map(int, value.split(sep))
                    return datetime(default_year, month, day).strftime('%Y-%m-%d'), m
                except Exception:
                    pass
    return None, None


def money_value(raw):
    raw = (raw or '').strip().upper()
    debit = raw.startswith('-') or raw.endswith('D')
    credit = raw.startswith('+') or raw.endswith('C')
    clean = re.sub(r'[^0-9,.-]', '', raw)
    last_comma = clean.rfind(',')
    last_dot = clean.rfind('.')

    if last_comma >= 0 and last_dot >= 0:
        if last_comma > last_dot:
            clean = clean.replace('.', '').replace(',', '.')
        else:
            clean = clean.replace(',', '')
    elif last_comma >= 0:
        decimals = len(clean) - last_comma - 1
        clean = clean.replace('.', '')
        clean = clean.replace(',', '.') if decimals <= 2 else clean.replace(',', '')
    elif last_dot >= 0:
        decimals = len(clean) - last_dot - 1
        if decimals > 2:
            clean = clean.replace('.', '')

    try:
        value = float(clean)
    except Exception:
        return None
    if debit:
        return -abs(value)
    if credit:
        return abs(value)
    return value


def is_summary(text):
    low = (text or '').lower()
    has_transaction_word = any(word in low for word in EXPENSE_WORDS + INCOME_WORDS)
    if has_transaction_word:
        return False
    if any(term in low for term in SUMMARY_TERMS):
        return True
    if any(term in low for term in BALANCE_TERMS):
        # Distingue "Saldo do dia R$ 100" de uma transação que apenas mostra
        # o saldo ao final, como "NETFLIX R$ 39,90 D Saldo R$ 420".
        stripped = low
        stripped = DATE_FULL.sub(' ', stripped)
        stripped = DATE_SHORT.sub(' ', stripped)
        stripped = DATE_MONTH_NAME.sub(' ', stripped)
        stripped = MONEY.sub(' ', stripped)
        for term in BALANCE_TERMS:
            stripped = stripped.replace(term, ' ')
        stripped = re.sub(r'[^a-zà-ÿ]+', ' ', stripped).strip()
        return len(stripped) < 3
    return False


def cut_balance_context(segment):
    low = segment.lower()
    positions = [low.find(term) for term in BALANCE_TERMS if low.find(term) >= 0]
    if positions:
        before = segment[:min(positions)].strip(' -|·•:')
        if MONEY.search(before):
            return before
    return segment


def infer_type(description, value, raw_amount):
    raw = (raw_amount or '').strip().upper()
    if raw.startswith('-') or raw.endswith('D') or (value is not None and value < 0):
        return 'despesa', 'alta'
    if raw.startswith('+') or raw.endswith('C'):
        return 'receita', 'alta'
    low = description.lower()
    if any(word in low for word in EXPENSE_WORDS):
        return 'despesa', 'alta'
    if any(word in low for word in INCOME_WORDS):
        return 'receita', 'alta'
    return 'receita', 'baixa'


def choose_amount(segment):
    matches = list(MONEY.finditer(segment))
    if not matches:
        return None, None
    explicit = [m for m in matches if m.group(1).strip().upper().startswith(('-', '+')) or m.group(1).strip().upper().endswith(('D', 'C'))]
    chosen = explicit[-1] if explicit else matches[-1]
    return chosen, money_value(chosen.group(1))


def clean_description(segment, amount_match):
    if amount_match:
        description = (segment[:amount_match.start()] + ' ' + segment[amount_match.end():]).strip()
    else:
        description = segment
    description = re.sub(r'\b(?:saldo|valor|lançamento|lancamento)\b\s*[:\-]?', ' ', description, flags=re.I)
    description = re.sub(r'\s+', ' ', description).strip(' -|·•:')
    return description[:180] or 'Movimentação importada'


def row_confidence(date_ok, description, raw_amount, type_confidence, bank):
    score = 0
    score += 25 if date_ok else 0
    score += 20 if len(description.strip()) >= 3 else 8
    raw = (raw_amount or '').strip().upper()
    explicit = raw.startswith(('-', '+')) or raw.endswith(('D', 'C'))
    score += 30 if explicit else 18
    score += 20 if type_confidence == 'alta' else 8
    score += 5 if bank != 'generico' else 0
    return max(0, min(100, score))


def parse_block(block, default_year, bank):
    joined = norm(' '.join(x for x in block if x.strip()))
    if not joined or is_summary(joined):
        return None
    date, date_match = parse_date_token(joined, default_year)
    if not date or not date_match:
        return None

    segment = cut_balance_context(joined[date_match.end():].strip())
    if not segment or is_summary(segment):
        return None

    amount_match, value = choose_amount(segment)
    if amount_match is None or value is None or abs(value) < 0.00001:
        return None

    description = clean_description(segment, amount_match)
    if is_summary(description):
        return None
    movement_type, type_confidence = infer_type(description, value, amount_match.group(1))
    score = row_confidence(True, description, amount_match.group(1), type_confidence, bank)

    return {
        'data': date,
        'descricao': description,
        'valor': -abs(value) if movement_type == 'despesa' else abs(value),
        'tipo': movement_type,
        'tipo_confianca': type_confidence,
        'confianca_leitura': score,
        'origem_pdf': joined[:300],
    }


def lines_from_words(words):
    grouped = defaultdict(list)
    for word in words or []:
        try:
            y = round(float(word.get('top', 0)) / 3.0) * 3
            grouped[y].append(word)
        except Exception:
            continue
    lines = []
    for y in sorted(grouped):
        row = sorted(grouped[y], key=lambda w: float(w.get('x0', 0)))
        text = norm(' '.join(str(w.get('text', '')) for w in row))
        if text:
            lines.append(text)
    return lines


def extract_with_pdfplumber(path):
    page_texts = []
    layout_lines = []
    page_count = 0
    with pdfplumber.open(path) as pdf:
        page_count = len(pdf.pages)
        for page in pdf.pages:
            try:
                text = page.extract_text(x_tolerance=2, y_tolerance=3) or ''
            except Exception:
                text = ''
            page_texts.append(text)
            try:
                words = page.extract_words(use_text_flow=False, keep_blank_chars=False)
                layout_lines.extend(lines_from_words(words))
            except Exception:
                pass
    normal_lines = [norm(line) for text in page_texts for line in text.splitlines() if norm(line)]
    # Escolhe a representação com mais sinais de transação.
    def usefulness(lines):
        return sum(1 for line in lines if (DATE_FULL.search(line) or DATE_SHORT.search(line) or DATE_MONTH_NAME.search(line)) and MONEY.search(line))
    chosen = layout_lines if usefulness(layout_lines) > usefulness(normal_lines) else normal_lines
    return '\n'.join(page_texts), chosen, page_count


def extract_with_pypdf(path):
    if PdfReader is None:
        raise RuntimeError('Nenhum leitor PDF disponível')
    reader = PdfReader(path, strict=False)
    if getattr(reader, 'is_encrypted', False):
        try:
            unlocked = reader.decrypt('')
        except Exception:
            unlocked = 0
        if not unlocked:
            raise PermissionError('encrypted')
    texts = []
    for page in reader.pages:
        try:
            texts.append(page.extract_text() or '')
        except Exception:
            texts.append('')
    lines = [norm(line) for text in texts for line in text.splitlines() if norm(line)]
    return '\n'.join(texts), lines, len(reader.pages)


def build_blocks(lines, default_year):
    blocks = []
    current = []
    for line in lines:
        _, match = parse_date_token(line, default_year)
        if match:
            if current:
                blocks.append(current)
            current = [line]
        elif current:
            # Evita anexar páginas inteiras de rodapé/cabeçalho a uma transação.
            if len(current) < 5:
                current.append(line)
    if current:
        blocks.append(current)
    return blocks


def main():
    if len(sys.argv) < 2:
        fail('Nenhum arquivo PDF foi informado.')
        return

    path = sys.argv[1]
    selected_bank = (sys.argv[2] if len(sys.argv) > 2 else 'auto').lower().strip()
    warnings = []

    text = ''
    lines = []
    pages = 0
    engine = None

    if pdfplumber is not None:
        try:
            text, lines, pages = extract_with_pdfplumber(path)
            engine = 'pdfplumber'
        except PermissionError:
            fail('Este PDF é protegido por senha. Exporte um extrato sem senha e tente novamente.', selected_bank)
            return
        except Exception as exc:
            print(f'pdfplumber error: {exc}', file=sys.stderr)
            warnings.append('O pdfplumber não conseguiu ler o documento; foi usado o leitor de compatibilidade.')

    if not text.strip():
        try:
            text, lines, pages = extract_with_pypdf(path)
            engine = 'pypdf'
        except PermissionError:
            fail('Este PDF é protegido por senha. Exporte um extrato sem senha e tente novamente.', selected_bank)
            return
        except Exception as exc:
            print(f'pypdf error: {exc}', file=sys.stderr)
            fail('Não foi possível abrir este PDF. Verifique se ele é um extrato PDF válido.', selected_bank, warnings)
            return

    if not text.strip() and not lines:
        fail(
            'Este PDF parece ser escaneado ou composto apenas por imagens. A versão 14.4 ainda não executa OCR automaticamente; use CSV/OFX ou um PDF com texto selecionável.',
            selected_bank,
            warnings,
            {'motor': engine or 'nenhum', 'paginas': pages},
        )
        return

    bank = detect_bank(text + '\n' + '\n'.join(lines[:80]), selected_bank)
    default_year = infer_year(text)
    blocks = build_blocks(lines, default_year)
    rows = []
    ignored = 0
    seen = set()

    for block in blocks:
        row = parse_block(block, default_year, bank)
        if row is None:
            ignored += 1
            continue
        key = (row['data'], re.sub(r'\W+', '', row['descricao'].lower()), round(abs(row['valor']), 2), row['tipo'])
        if key in seen:
            continue
        seen.add(key)
        rows.append(row)

    if not rows:
        fail(
            'O PDF foi lido, mas nenhuma movimentação foi reconhecida com segurança. Tente selecionar o banco manualmente ou prefira o CSV/OFX disponibilizado pelo banco.',
            bank,
            warnings,
            {'motor': engine, 'paginas': pages, 'ano_inferido': default_year},
        )
        return

    average = round(sum(row['confianca_leitura'] for row in rows) / len(rows))
    low = sum(1 for row in rows if row['confianca_leitura'] < 70)
    if low:
        warnings.append(f'{low} movimentação(ões) tiveram leitura com confiança baixa e devem ser revisadas.')

    emit({
        'sucesso': True,
        'erro': None,
        'linhas': rows,
        'ignoradas': ignored,
        'banco': bank,
        'avisos': warnings,
        'meta': {
            'motor': engine,
            'paginas': pages,
            'ano_inferido': default_year,
            'confianca_media': average,
        },
    })


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'CashPilot PDF unexpected error: {exc}', file=sys.stderr)
        fail('O leitor de PDF encontrou um erro inesperado ao processar o arquivo.')
