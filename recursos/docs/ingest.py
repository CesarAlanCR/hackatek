import os
import sqlite3
import time
import sys
from pathlib import Path
from dotenv import load_dotenv
import openai
from PyPDF2 import PdfReader
import json

# Cargar API key desde .env
load_dotenv(os.path.join(Path(__file__).parent.parent.parent, '.env'))
OPENAI_API_KEY = os.getenv('OPENAI_API_KEY')
if not OPENAI_API_KEY:
    print('Error: OPENAI_API_KEY no configurada en .env')
    sys.exit(1)
openai.api_key = OPENAI_API_KEY

DOCS_DIR = Path(__file__).parent
DB_PATH = DOCS_DIR / 'knowledge.sqlite'
CHUNK_SIZE = 800  # caracteres por fragmento
EMBED_MODEL = 'text-embedding-ada-002'

# Crear base de datos
conn = sqlite3.connect(DB_PATH)
c = conn.cursor()
c.execute('''CREATE TABLE IF NOT EXISTS docs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT,
    chunk TEXT,
    embedding TEXT
)''')
conn.commit()

def chunk_text(text, size=CHUNK_SIZE):
    # Divide el texto en fragmentos de tamaño máximo 'size'
    chunks = []
    text = text.replace('\r', '')
    lines = text.split('\n')
    buf = ''
    for line in lines:
        if len(buf) + len(line) + 1 > size:
            chunks.append(buf)
            buf = line
        else:
            buf += ('\n' if buf else '') + line
    if buf:
        chunks.append(buf)
    return [c.strip() for c in chunks if c.strip()]

def embed_chunk(chunk):
    # Llama a la API de OpenAI para obtener el embedding
    try:
        resp = openai.Embedding.create(
            input=chunk,
            model=EMBED_MODEL
        )
        return resp['data'][0]['embedding']
    except Exception as e:
        print('Error embedding:', e)
        return None

def process_pdf(pdf_path):
    print(f'Procesando {pdf_path.name}...')
    reader = PdfReader(str(pdf_path))
    text = ''
    for page in reader.pages:
        try:
            text += page.extract_text() or ''
        except Exception:
            continue
    if not text.strip():
        print(f'Advertencia: {pdf_path.name} no tiene texto extraíble.')
        return
    chunks = chunk_text(text)
    for chunk in chunks:
        emb = embed_chunk(chunk)
        if emb:
            c.execute('INSERT INTO docs (filename, chunk, embedding) VALUES (?, ?, ?)',
                      (pdf_path.name, chunk, json.dumps(emb)))
            conn.commit()
        time.sleep(0.5)  # para evitar rate limit

if __name__ == '__main__':
    pdfs = list(DOCS_DIR.glob('*.pdf'))
    if not pdfs:
        print('No se encontraron PDFs en', DOCS_DIR)
        sys.exit(0)
    for pdf in pdfs:
        process_pdf(pdf)
    print('Ingestión completada. Puedes consultar knowledge.sqlite para búsquedas RAG.')
    conn.close()
