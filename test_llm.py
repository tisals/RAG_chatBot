import requests
import json

LLM_API_URL = "https://routellm.abacus.ai/v1/chat/completions"
LLM_API_KEY = "s2_b4609507478b48fb87e345e6019e2b3d"  # pon aquí tu key real
LLM_MODEL = "gpt-4o-mini"        # o el modelo que tengas configurado

def test_llm():
    headers = {
        "Authorization": f"Bearer {LLM_API_KEY}",
        "Content-Type": "application/json",
    }

    payload = {
        "model": LLM_MODEL,
        "messages": [
            {"role": "user", "content": "Di una frase corta y simpática sobre seguridad en el trabajo en Colombia."}
        ]
    }

    try:
        resp = requests.post(LLM_API_URL, headers=headers, json=payload, timeout=30)
        print("Status code:", resp.status_code)
        print("Raw body:", resp.text)

        resp.raise_for_status()
        data = resp.json()
        print("\nJSON parseado:")
        print(json.dumps(data, indent=2, ensure_ascii=False))

        if "choices" in data and len(data["choices"]) > 0:
            content = data["choices"][0]["message"]["content"]
            print("\n💬 Respuesta del modelo:")
            print(content)
        else:
            print("\n⚠️ No se encontró 'choices[0].message.content' en la respuesta.")
    except Exception as e:
        print("Error en la llamada al LLM:", e)

if __name__ == "__main__":
    test_llm()