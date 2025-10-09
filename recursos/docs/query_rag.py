import os
import sys
import sqlite3
import json
from pathlib import Path
from dotenv import load_dotenv
import openai
import numpy as np

# Cargar API key desde .env
load_dotenv(os.path.join(Path(__file__).parent.parent.parent, '.env'))
OPENAI_API_KEY = os.getenv('OPENAI_API_KEY')
if not OPENAI_API_KEY:
    print('Error: OPENAI_API_KEY no configurada en .env')
    sys.exit(1)
openai.api_key = OPENAI_API_KEY

DB_PATH = Path(__file__).parent / 'knowledge.sqlite'
EMBED_MODEL = 'text-embedding-ada-002'
TOP_K = 4  # número de fragmentos relevantes a devolver

# Función para obtener el embedding de la pregunta
def get_embedding(text):
    resp = openai.Embedding.create(input=text, model=EMBED_MODEL)
    return np.array(resp['data'][0]['embedding'])

# Función para calcular similitud coseno
def cosine_similarity(a, b):
    a = np.array(a)
    b = np.array(b)
    return np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b))

# Consulta principal
if __name__ == '__main__':
    if len(sys.argv) < 2:
        print('Uso: python query_rag.py "tu pregunta aquí"')
        sys.exit(1)
    query = sys.argv[1]
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('SELECT id, filename, chunk, embedding FROM docs')
    rows = c.fetchall()
    query_emb = get_embedding(query)
    scored = []
    for row in rows:
        emb = json.loads(row[3])
        score = cosine_similarity(query_emb, emb)
        scored.append((score, row[2], row[1]))  # (score, chunk, filename)
    scored.sort(reverse=True)
    top_chunks = scored[:TOP_K]
    # Devuelve los fragmentos más relevantes
    for i, (score, chunk, filename) in enumerate(top_chunks, 1):
        print(f'[{i}] ({filename}) score={score:.3f}\n{chunk}\n---')
    conn.close()
