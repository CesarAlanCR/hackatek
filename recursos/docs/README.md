Carpeta `recursos/docs`

Propósito
- Aquí puedes subir todos los PDFs, archivos .txt o .md que contienen información agrícola que quieras usar para "entrenar" o enriquecer al asistente.

Convenciones recomendadas
- Nombres: usa nombres cortos y descriptivos, preferiblemente sin espacios, por ejemplo:
  - suelos_fertilidad_2020.pdf
  - plagas_cultivo_maiz.pdf
- Formatos permitidos:
  - PDF (recomendado)
  - TXT, MD (texto plano o marcado)

Límites y recomendaciones
- Tamaño: evita archivos mayores a 20 MB. Si tienes archivos muy grandes, considéralos dividir por capítulos.
- Calidad OCR: si tus PDFs vienen escaneados, asegúrate de que tengan texto seleccionable; de lo contrario habrá que procesarlos con OCR antes de indexarlos.

Siguientes pasos (cómo proceder con la ingestión)
1. Sube tus PDFs a esta carpeta (`recursos/docs`).
2. Cuando los hayas subido, dime "listo" y yo generaré un script de ingestión que:
   - Extraerá texto de cada PDF
   - Limpiará y normalizará el texto (removerá cabeceras/pies de página repetitivos)
   - Creará embeddings (usando la API de OpenAI o un proveedor de embeddings)
   - Insertará esos embeddings en una base de vectores (ej. FAISS local o Qdrant)
3. Podremos luego adaptar el pipeline para que el chat haga búsquedas en ese índice (RAG).

Seguridad
- No subas claves de API ni datos sensibles en los PDFs.

Contacto
- Si quieres, puedo crear también un script opcional para ejecutar OCR (Tesseract) en PDFs escaneados.

---

# Ingesta de PDFs para IA agrícola

## ¿Qué hace este script?
- Extrae texto de todos los PDFs en esta carpeta
- Divide el texto en fragmentos
- Genera embeddings usando la API de OpenAI
- Guarda los textos y vectores en una base SQLite (`knowledge.sqlite`)

## Requisitos
- Python 3.8+
- Instala dependencias:

```bash
pip install openai python-dotenv PyPDF2
```

## Uso
1. Coloca tus PDFs en esta carpeta (`recursos/docs`).
2. Asegúrate de tener tu API key en `.env` en la raíz del proyecto:

```
OPENAI_API_KEY=sk-...
```

3. Ejecuta el script:

```bash
python ingest.py
```

4. El resultado será un archivo `knowledge.sqlite` con los textos y sus embeddings.

## ¿Qué sigue?
- Puedes usar este archivo para búsquedas semánticas (RAG) desde PHP o Python.
- Si tienes PDFs escaneados (sin texto), avísame y te ayudo a agregar OCR.

## Personalización
- Puedes ajustar el tamaño de fragmento (`CHUNK_SIZE`) en el script.
- El modelo de embedding usado es `text-embedding-ada-002` (puedes cambiarlo si tienes acceso a otro).

## Seguridad
- No subas PDFs con datos sensibles o claves privadas.

---

¿Dudas o quieres que te ayude a integrar la búsqueda en el chat? Dímelo y lo implemento.
