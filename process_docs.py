"""
Script para generar base de conocimientos desde HTML, PDF y DOCX.
Usa un LLM para generar respuestas específicas a cada pregunta.
Salida: CSV compatible con el plugin RAG Chatbot para WordPress.

Uso:
    python process_site.py

Requisitos:
    pip install beautifulsoup4 pdfplumber python-docx requests
"""

import os
import csv
import json
import requests
from bs4 import BeautifulSoup

# Para PDFs y DOCX
try:
    import pdfplumber
except ImportError:
    pdfplumber = None
    print("ADVERTENCIA: pdfplumber no está instalado. No se procesarán PDFs.")

try:
    import docx
except ImportError:
    docx = None
    print("ADVERTENCIA: python-docx no está instalado. No se procesarán DOCX.")


# ==========================
# CONFIGURACIÓN
# ==========================

# Carpeta raíz donde están los archivos del sitio (HTML, PDF, DOCX)
ROOT_DIR = "./deseguridad_site"  # Ajusta esta ruta a tu carpeta local

# Archivo de salida (CSV)
OUTPUT_CSV = "./deseguridad_knowledge_base.csv"

# URL base del sitio (para construir source_url)
BASE_URL = "https://deseguridad.net"

# ==========================
# CONFIGURACIÓN LLM (RouteLLM / Abacus.AI)
# ==========================

# Endpoint de tu API LLM (ej. RouteLLM)
LLM_API_URL = "https://routellm.abacus.ai/v1/chat/completions"

# Tu API Key
LLM_API_KEY = "s2_b4609507478b48fb87e345e6019e2b3d"  # Reemplaza con tu key real

# Modelo a usar (opcional, según tu API)
LLM_MODEL = "gpt-4o-mini"  # Ajusta según lo que soporte tu endpoint

# Timeout para llamadas al LLM (segundos)
LLM_TIMEOUT = 30


# ==========================
# HELPERS GENERALES
# ==========================

def get_category_and_url(file_path: str) -> tuple:
    """
    Extrae la categoría desde la ruta del archivo
    y construye la URL fuente.
    """
    relative_path = file_path.replace(ROOT_DIR, "").lstrip(os.sep)
    dir_name = os.path.dirname(relative_path)

    category = dir_name.strip(os.sep).replace(os.sep, "/") if dir_name else "general"

    filename = os.path.basename(file_path)

    if category == "general":
        url_fuente = f"{BASE_URL}/{filename}"
    else:
        url_fuente = f"{BASE_URL}/{category}/{filename}"

    # Quitar extensión .html de la URL
    if url_fuente.endswith(".html"):
        url_fuente = url_fuente[:-5]

    return category, url_fuente


def normalize_title(raw_title: str) -> str:
    """
    Limpia el título quitando sufijos comunes del sitio.
    """
    if not raw_title:
        return ""

    title = raw_title.replace("- DeSeguridad.net", "").strip()
    title = title.replace("| DeSeguridad.net", "").strip()
    return title


def generate_question_templates(title: str) -> list:
    """
    Genera un listado de preguntas tipo para un servicio/tema.
    Ahora incluye las nuevas preguntas solicitadas.
    """
    base = title if title else "este servicio"

    templates = [
        f"¿Qué hace exactamente paso a paso el servicio de {base}?",
        f"¿Para qué sirve exactamente el servicio de {base}?",
        f"¿Quién lo hace o quién presta el servicio de {base}?",
        f"¿Para quién es recomendable el servicio de {base}?",
        f"¿Dónde se presta o aplica el servicio de {base}?",
        f"¿Cuánto Demora o cuanto tiempo suele tardar el servicio de {base}?",
        f"¿Cuáles son los precios o rangos de inversión del servicio de {base}?",
        f"¿Qué incluye el servicio de {base}?",
        f"¿Tiene licencia el servicio de {base}?",
        f"¿Cómo puedo contratar o solicitar más información sobre {base}?"
    ]

    return templates


# ==========================
# LLAMADA AL LLM
# ==========================

def call_llm(prompt: str) -> str:
    """
    Llama al LLM configurado y devuelve la respuesta.
    """
    headers = {
        "Authorization": f"Bearer {LLM_API_KEY}",
        "Content-Type": "application/json"
    }

    payload = {
        "model": LLM_MODEL,
        "messages": [
            {
                "role": "user",
                "content": prompt
            }
        ]
    }

    try:
        response = requests.post(
            LLM_API_URL,
            headers=headers,
            json=payload,
            timeout=LLM_TIMEOUT
        )
        response.raise_for_status()
        data = response.json()

        # Extraer respuesta según formato OpenAI
        if "choices" in data and len(data["choices"]) > 0:
            return data["choices"][0]["message"]["content"].strip()
        else:
            return ""

    except Exception as e:
        print(f"  ⚠️  Error al llamar al LLM: {e}")
        return ""


def generate_answer_with_llm(question: str, full_text: str, title: str) -> str:
    """
    Genera una respuesta específica para la pregunta usando el LLM.
    """
    # Limitar el texto a 3000 caracteres para no saturar el prompt
    text_chunk = full_text[:3000]

    prompt = f"""Eres un asistente experto en servicios de seguridad y salud en el trabajo.

A partir del siguiente contenido de una página web sobre "{title}", responde de forma clara, concisa y profesional la siguiente pregunta.

Si el contenido no tiene información suficiente para responder, indica la url en donde puede ampliar la información cotizando el servicio

Contenido:
{text_chunk}

Pregunta:
{question}

Respuesta:"""

    answer = call_llm(prompt)

    if not answer:
        # Fallback si el LLM falla
        answer = f"para ampliar la información especifica de {title} realiza una cotización en {url_fuente} y nospondremos en contacto para detallar y personalizar la respuesta a tus necesidades."

    return answer


# ==========================
# EXTRACCIÓN HTML (sin header/footer)
# ==========================

def extract_html_content(file_path: str) -> tuple:
    """
    Lee un HTML, elimina header/footer/nav/aside y devuelve:
    - title (limpio)
    - texto principal concatenado
    """
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            content = f.read()
    except (UnicodeDecodeError, FileNotFoundError):
        return "", ""

    soup = BeautifulSoup(content, "html.parser")

    # Eliminar bloques típicos de cabecera, pie y navegación
    for selector in ["header", "footer", "nav", "aside"]:
        for tag in soup.find_all(selector):
            tag.decompose()

    # Eliminar clases/ids típicos de themes
    for css_selector in [
        ".site-header",
        ".site-footer",
        "#site-header",
        "#site-footer",
        ".main-navigation",
        ".menu-principal",
        ".elementor-location-header",
        ".elementor-location-footer"
    ]:
        for tag in soup.select(css_selector):
            tag.decompose()

    # Título
    raw_title = soup.find("title").get_text().strip() if soup.find("title") else ""
    title = normalize_title(raw_title)

    # Intentar quedarnos con <main> o <article> si existen
    main_container = soup.find("main") or soup.find("article") or soup.body

    if not main_container:
        return title, ""

    # Tomamos párrafos y encabezados dentro del contenedor principal
    text_parts = []
    for tag in main_container.find_all(["h1", "h2", "h3", "h4", "p", "li"]):
        txt = tag.get_text(strip=True)
        if txt:
            text_parts.append(txt)

    full_text = " ".join(text_parts)
    return title, full_text


# ==========================
# EXTRACCIÓN PDF
# ==========================

def extract_pdf_content(file_path: str) -> tuple:
    """
    Extrae texto de un PDF completo.
    Título = nombre de archivo sin extensión.
    """
    if pdfplumber is None:
        return "", ""

    title = os.path.splitext(os.path.basename(file_path))[0]

    text_parts = []
    try:
        with pdfplumber.open(file_path) as pdf:
            for page in pdf.pages:
                txt = page.extract_text() or ""
                txt = txt.strip()
                if txt:
                    text_parts.append(txt)
    except Exception as e:
        print(f"Error al procesar PDF {file_path}: {e}")
        return title, ""

    full_text = " ".join(text_parts)
    return title, full_text


# ==========================
# EXTRACCIÓN DOCX
# ==========================

def extract_docx_content(file_path: str) -> tuple:
    """
    Extrae texto de un DOCX.
    Título = nombre de archivo sin extensión.
    """
    if docx is None:
        return "", ""

    title = os.path.splitext(os.path.basename(file_path))[0]

    try:
        document = docx.Document(file_path)
    except Exception as e:
        print(f"Error al procesar DOCX {file_path}: {e}")
        return title, ""

    text_parts = []
    for para in document.paragraphs:
        txt = para.text.strip()
        if txt:
            text_parts.append(txt)

    full_text = " ".join(text_parts)
    return title, full_text


# ==========================
# MAIN
# ==========================

def main():
    """
    Recorre ROOT_DIR buscando archivos .html, .pdf y .docx.
    Para cada uno:
    - Extrae título y contenido (sin headers/footers en HTML).
    - Genera varias preguntas tipo.
    - Usa el LLM para generar respuestas específicas a cada pregunta.
    - Guarda todo en un CSV con formato: question;answer;category;source;source_url
    """
    knowledge_base = []

    print(f"Procesando archivos en: {ROOT_DIR}")
    print(f"Salida: {OUTPUT_CSV}\n")

    for dirpath, _, filenames in os.walk(ROOT_DIR):
        for filename in filenames:
            file_path = os.path.join(dirpath, filename)
            ext = os.path.splitext(filename)[1].lower()

            if ext not in [".html", ".pdf", ".docx"]:
                continue

            print(f"📄 Procesando: {file_path}")

            category, url_fuente = get_category_and_url(file_path)

            # Según el tipo de archivo, extraemos contenido
            if ext == ".html":
                title, text = extract_html_content(file_path)
                source = "Web (HTML)"
            elif ext == ".pdf":
                title, text = extract_pdf_content(file_path)
                source = "Documento PDF"
            elif ext == ".docx":
                title, text = extract_docx_content(file_path)
                source = "Documento DOCX"
            else:
                continue

            if not text:
                print(f"  → Sin contenido útil, omitido.\n")
                continue

            # Generamos preguntas tipo a partir del título
            preguntas = generate_question_templates(title)

            print(f"  → Generando {len(preguntas)} respuestas con LLM...")

            for pregunta in preguntas:
                # Generar respuesta específica con el LLM
                respuesta = generate_answer_with_llm(pregunta, text, title)

                knowledge_base.append({
                    "question": pregunta,
                    "answer": respuesta,
                    "category": category,
                    "source": source,
                    "source_url": url_fuente
                })

            print(f"  ✅ {len(preguntas)} preguntas generadas.\n")

    # ------------------------------
    # Guardar en CSV
    # ------------------------------
    with open(OUTPUT_CSV, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.writer(f, delimiter=";")
        writer.writerow(["question", "answer", "category", "source", "source_url"])

        for item in knowledge_base:
            writer.writerow([
                item["question"],
                item["answer"],
                item["category"],
                item["source"],
                item["source_url"],
            ])

    print(f"\n✅ Base de conocimientos creada con {len(knowledge_base)} registros.")
    print(f"📄 Archivo generado: {OUTPUT_CSV}")
    print("\nPróximo paso:")
    print("  1. Abre el admin de WordPress → RAG Chatbot → Base de Conocimientos")
    print("  2. Haz clic en 'Importar FAQs'")
    print("  3. Selecciona el archivo CSV generado")
    print("  4. Elige modo 'Agregar' o 'Reemplazar' según necesites")


if __name__ == "__main__":
    main()