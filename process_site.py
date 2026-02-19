"""
Script mejorado para generar base de conocimientos desde URLs de deseguridad.net usando un LLM.

Mejoras:
- Clasifica cada URL en tipo de página: servicio / legal / blog / inicio / otros.
- Usa plantillas de preguntas diferentes según el tipo de página.
- Evita duplicar preguntas muy similares (ej. tiempo / demora).
- Genera CSV compatible con el plugin RAG Chatbot: question;answer;category;source;source_url
"""

import csv
import requests
from urllib.parse import urlparse
from bs4 import BeautifulSoup

# ==========================
# CONFIGURACIÓN GENERAL
# ==========================

# URLs del sitio que quieres procesar
URLS = [
    #"https://deseguridad.net/",
    # Servicios
    "https://deseguridad.net/servicios/",
    "https://deseguridad.net/riesgo-psicosocial/",
    "https://deseguridad.net/mediciones-higienicas/",
    "https://deseguridad.net/analisis-de-puesto-de-trabajo/",
    "https://deseguridad.net/seguridad-en-el-trabajo/",
    "https://deseguridad.net/examenes-ocupacionales/",
    "https://deseguridad.net/capacitacion-enfocada-en-los-riesgos/",
    "https://deseguridad.net/pausas-estrategicas-2/",
    "https://deseguridad.net/indicadores-sgsst/",
    "https://deseguridad.net/ee-mm-sgsst/",
    "https://deseguridad.net/profesiograma/",
    "https://deseguridad.net/plan-estrategico-de-seguridad-vial/",
    "https://deseguridad.net/sistema-informacion-sst/",
    # Legales
    "https://deseguridad.net/politica-de-tratamiento-de-datos/",
    "https://deseguridad.net/terminos-y-condiciones/",
    # Blog (ejemplos – luego puedes ampliar)
    # "https://deseguridad.net/blog/ejemplo-articulo-1/",
]

# Archivo de salida (CSV)
OUTPUT_CSV = "./deseguridad_knowledge_base.csv"

# Timeout para las peticiones HTTP
HTTP_TIMEOUT = 20


# ==========================
# CONFIG LLM (RouteLLM / OpenAI-like)
# ==========================

LLM_API_URL = "https://routellm.abacus.ai/v1/chat/completions"
LLM_API_KEY = "s2_b4609507478b48fb87e345e6019e2b3d"   # <-- pon aquí tu API key válida de RouteLLM
LLM_MODEL = "gpt-4o-mini"        # o el modelo que uses
LLM_TIMEOUT = 30


# ==========================
# CLASIFICACIÓN DE PÁGINAS
# ==========================

def classify_page_type(url: str, category: str) -> str:
    """
    Clasifica la página en:
    - 'servicio'
    - 'legal'
    - 'blog'
    - 'inicio'
    - 'otro'

    Se apoya en la categoría (primer segmento de la ruta) y en palabras clave.
    """
    parsed = urlparse(url)
    path = parsed.path.strip("/").lower()

    if path == "" or path == "inicio":
        return "inicio"

    if "blog" in path or "/categoria/" in path or "/category/" in path:
        return "blog"

    # Legales
    if "terminos" in path or "condiciones" in path or "tratamiento-de-datos" in path or "politica" in path:
        return "legal"

    # Servicios: aquí puedes ir afinando según tu estructura
    if category in [
        "servicios",
        "riesgo-psicosocial",
        "mediciones-higienicas",
        "analisis-de-puesto-de-trabajo",
        "seguridad-en-el-trabajo",
        "examenes-ocupacionales",
        "capacitacion-enfocada-en-los-riesgos",
        "pausas-estrategicas-2",
        "indicadores-sgsst",
        "ee-mm-sgsst",
        "profesiograma",
        "plan-estrategico-de-seguridad-vial",
        "sistema-informacion-sst",
    ]:
        return "servicio"

    # Fallback
    return "otro"


# ==========================
# HELPERS: CATEGORÍA Y TÍTULO
# ==========================

def get_category_and_url(url: str) -> tuple:
    """
    A partir de la URL, define una 'categoría' simple
    y devuelve la propia URL como source_url.
    """
    parsed = urlparse(url)
    path = parsed.path.strip("/")

    if not path:
        category = "inicio"
    else:
        parts = path.split("/")
        category = parts[0]

    return category, url


def normalize_title(raw_title: str) -> str:
    """
    Limpia el título quitando sufijos comunes del sitio.
    """
    if not raw_title:
        return ""

    title = raw_title.replace("- DeSeguridad.net", "").strip()
    title = title.replace("| DeSeguridad.net", "").strip()
    return title


# ==========================
# PLANTILLAS DE PREGUNTAS POR TIPO
# ==========================

def generate_service_questions(title: str) -> list:
    base = title if title else "este servicio"
    
    return [
        f"¿Qué hace exactamente paso a paso el servicio de {base}?",
        f"¿Para qué sirve exactamente el servicio de {base}?",
        f"¿Quién lo hace o quién presta el servicio de {base}?",
        f"¿Para quién es recomendable el servicio de {base}?",
        f"¿Dónde se presta o aplica el servicio de {base}?",
        # Unificamos tiempo en UNA sola pregunta para evitar duplicados
        f"¿Cuánto tiempo suele tardar o cuánto se demora el servicio de {base}?",
        f"¿Cuáles son los precios o rangos de inversión del servicio de {base}?",
        f"¿Qué incluye el servicio de {base}?",
        f"¿Tiene licencia el servicio de {base}?",
        f"¿Cómo puedo contratar o solicitar más información sobre {base}?",
    ]


def generate_legal_questions(title: str) -> list:
    base = title if title else "este documento"

    return [
        f"¿Qué es {base} y para qué sirve?",
        f"¿Qué información cubre exactamente {base}?",
        f"¿Qué derechos y deberes tiene el usuario según {base}?",
        f"¿Qué datos personales se tratan según {base}?",
        f"¿Cómo puede un usuario ejercer sus derechos de protección de datos según {base}?",
    ]


def generate_blog_questions(title: str) -> list:
    base = title if title else "este artículo del blog"

    return [
        f"¿De qué trata {base} en pocas palabras?",
        f"¿Qué problema o necesidad aborda {base}?",
        f"¿Cuáles son las principales recomendaciones o conclusiones de {base}?",
        f"¿Qué relación tiene {base} con la seguridad y salud en el trabajo?",
        f"¿Qué debería hacer una empresa después de leer {base}?",
    ]


def generate_home_questions(title: str) -> list:
    base = title if title else "Deseguridad.net"

    return [
        f"¿Qué ofrece exactamente {base} a las empresas en Colombia?",
        f"¿Qué tipo de servicios de seguridad y salud en el trabajo ofrece {base}?",
        f"¿Por qué una empresa debería trabajar con {base}?",
        f"¿Cómo puedo empezar a trabajar con {base}?",
    ]


def generate_other_questions(title: str) -> list:
    base = title if title else "esta página"

    return [
        f"¿De qué trata {base} y qué información principal ofrece?",
        f"¿Cómo se relaciona {base} con los servicios de Deseguridad.net?",
    ]


def generate_questions_by_type(page_type: str, title: str) -> list:
    if page_type == "servicio":
        return generate_service_questions(title)
    elif page_type == "legal":
        return generate_legal_questions(title)
    elif page_type == "blog":
        return generate_blog_questions(title)
    elif page_type == "inicio":
        return generate_home_questions(title)
    else:
        return generate_other_questions(title)


# ==========================
# LLM
# ==========================

def call_llm(prompt: str) -> str:
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
        resp = requests.post(
            LLM_API_URL,
            headers=headers,
            json=payload,
            timeout=LLM_TIMEOUT
        )
        resp.raise_for_status()
        data = resp.json()

        if "choices" in data and len(data["choices"]) > 0:
            return data["choices"][0]["message"]["content"].strip()
        else:
            return ""
    except Exception as e:
        print(f"  ⚠️  Error al llamar al LLM: {e}")
        return ""


def generate_answer_with_llm(question: str, full_text: str, title: str, page_type: str) -> str:
    """
    Genera una respuesta específica para la pregunta usando el LLM,
    basada en el contenido de la página.
    """
    text_chunk = full_text[:3000]

    # Ajustamos ligeramente el rol según el tipo de página,
    # pero sin reinventar el prompt completo.
    tipo_descriptivo = {
        "servicio": "un servicio ofrecido por Deseguridad.net",
        "legal": "un documento legal o de tratamiento de datos",
        "blog": "un artículo del blog de Deseguridad.net",
        "inicio": "la página principal de Deseguridad.net",
        "otro": "una página informativa de Deseguridad.net",
    }.get(page_type, "una página de Deseguridad.net")

    prompt = f"""Eres un asistente experto en los servicios y contenidos de Deseguridad.net
(consultoría, mediciones, calibraciones, riesgos, normativa, etc.).

A partir del siguiente contenido de {tipo_descriptivo} titulado "{title}", responde de forma clara,
concreta y profesional la siguiente pregunta.

Muy importante:
- Usa solo la información que aparece en el contenido.
- Si el contenido NO tiene información suficiente para responder algo (por ejemplo, licencias,
certificados, precios o tiempos), informa que pueden ampliar la información cotizando en la página web.
- No inventes datos ni normativas que no aparezcan aquí.
- Responde en un solo bloque de texto, sin listas numeradas a menos que el contenido lo sugiera claramente.
- Responde como si fueras parte del equipo de Deseguridad.net, intentando acortar el camino del usuario con pasos adicionales, como contactar nuevamente a la empresa.

Contenido:
{text_chunk}

Pregunta:
{question}

Respuesta:"""

    answer = call_llm(prompt)

    if not answer:
        answer = (
            f"Para ampliar información sobre '{title}'. Te recomendamos cotizar directamente en {source_url}, para obtener una respuesta personalizada a tu caso."
        )

    return answer


# ==========================
# HTML DESDE URL
# ==========================

def fetch_html(url: str) -> str:
    try:
        resp = requests.get(url, timeout=HTTP_TIMEOUT)
        resp.raise_for_status()
        return resp.text
    except Exception as e:
        print(f"  ⚠️  Error al descargar {url}: {e}")
        return ""


def extract_html_content_from_url(url: str) -> tuple:
    html = fetch_html(url)
    if not html:
        return "", ""

    soup = BeautifulSoup(html, "html.parser")

    for selector in ["header", "footer", "nav", "aside"]:
        for tag in soup.find_all(selector):
            tag.decompose()

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

    raw_title = soup.find("title").get_text().strip() if soup.find("title") else ""
    title = normalize_title(raw_title)

    main_container = soup.find("main") or soup.find("article") or soup.body

    if not main_container:
        print("  ⚠️  No se encontró <main>, <article> ni <body> útil.")
        return title, ""

    text_parts = []
    for tag in main_container.find_all(["h1", "h2", "h3", "h4", "p", "li"]):
        txt = tag.get_text(strip=True)
        if txt:
            text_parts.append(txt)

    full_text = " ".join(text_parts)

    print(f"  → Título detectado: '{title}'")
    print(f"  → Longitud de texto extraído: {len(full_text)} caracteres")

    return title, full_text


# ==========================
# MAIN
# ==========================

def main():
    knowledge_base = []

    print("Procesando URLs del sitio con LLM (versión mejorada por tipo de página)...\n")
    print(f"Salida: {OUTPUT_CSV}\n")

    for url in URLS:
        print(f"🌐 Procesando URL: {url}")

        category, source_url = get_category_and_url(url)
        page_type = classify_page_type(url, category)
        print(f"  → Tipo de página detectado: {page_type} | Categoría: {category}")

        title, text = extract_html_content_from_url(url)
        source = "Web (HTML)"

        if not text:
            print("  ⚠️  Texto vacío después de limpiar header/footer. Se omite.\n")
            continue

        preguntas = generate_questions_by_type(page_type, title)
        print(f"  → Generando {len(preguntas)} respuestas con LLM...")

        for pregunta in preguntas:
            respuesta = generate_answer_with_llm(pregunta, text, title, page_type)

            knowledge_base.append({
                "question": pregunta,
                "answer": respuesta,
                "category": category,
                "source": source,
                "source_url": source_url
            })

        print(f"  ✅ {len(preguntas)} preguntas generadas para esta página.\n")

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
    print("  2. 'Importar FAQs' → selecciona el CSV")
    print("  3. Modo 'Agregar' o 'Reemplazar' según lo que necesites")


if __name__ == "__main__":
    main()