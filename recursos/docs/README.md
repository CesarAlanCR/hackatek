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
