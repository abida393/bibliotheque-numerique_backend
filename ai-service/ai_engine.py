import os
import json
from google import genai
from google.genai import types
from dotenv import load_dotenv

load_dotenv()

client = genai.Client(api_key=os.getenv("GEMINI_API_KEY"))

SUMMARY_MODEL = "gemini-3.5-flash-lite"
EMBEDDING_MODEL = "gemini-embedding-2"

def _fallback_title(text: str) -> str:
    for line in text.splitlines():
        candidate = " ".join(line.split()).strip(" -#")
        if not candidate:
            continue
        if candidate.startswith(("[Page ", "[Slide ")) and candidate.endswith("]"):
            continue
        return candidate[:180]
    return "Document sans titre"


def _parse_metadata_response(raw: str, source_text: str) -> dict:
    metadata = None
    try:
        metadata = json.loads(raw)
    except json.JSONDecodeError:
        decoder = json.JSONDecoder()
        for start, character in enumerate(raw):
            if character != "{":
                continue
            try:
                metadata, _ = decoder.raw_decode(raw, start)
            except json.JSONDecodeError:
                continue
            if isinstance(metadata, dict):
                break

    if not isinstance(metadata, dict):
        metadata = {}

    title = str(metadata.get("title") or "").strip()
    summary = str(metadata.get("summary") or "").strip()
    return {
        "title": title or _fallback_title(source_text),
        "summary": summary,
    }


def generate_metadata(text: str) -> dict:
    """
    Génère un titre et un résumé général du document.
    """
    excerpt = text[:15000]

    prompt = (
        "Tu es un assistant qui extrait les métadonnées de documents. "
        "En te basant sur le document suivant, génère un titre pertinent. Si le document est un CV ou n'a pas de titre explicite, déduis-en un (ex: 'CV de [Nom]', ou 'Document sur [Sujet]'). Tu DOIS obligatoirement fournir un titre. "
        "Génère également un résumé en 3 à 5 phrases claires en français.\n\n"
        "Réponds UNIQUEMENT avec un objet JSON de cette forme exacte (les clés 'title' et 'summary' sont obligatoires) :\n"
        '{"title": "Le titre généré", "summary": "Le résumé généré"}\n\n'
        f"Document :\n{excerpt}"
    )

    response = client.models.generate_content(
        model=SUMMARY_MODEL,
        contents=prompt,
        config=types.GenerateContentConfig(
            response_mime_type="application/json",
            response_schema={
                "type": "OBJECT",
                "properties": {
                    "title": {"type": "STRING"},
                    "summary": {"type": "STRING"},
                },
                "required": ["title", "summary"],
            },
        ),
    )
    return _parse_metadata_response(response.text or "", text)


def suggest_category_and_tags(text: str, existing_categories: list[str]) -> dict:
    """
    Suggère une catégorie (parmi celles existantes) et une liste de tags.
    """
    excerpt = text[:8000]
    categories_str = ", ".join(existing_categories)

    prompt = (
        "Tu analyses un document académique pour une bibliothèque numérique. "
        f"Voici la liste des catégories existantes : {categories_str}.\n\n"
        "En te basant sur le contenu ci-dessous, réponds UNIQUEMENT avec un objet JSON "
        "de cette forme exacte, sans texte avant ni après, sans balises markdown :\n"
        '{"category": "nom_de_la_categorie_la_plus_pertinente", "tags": ["tag1", "tag2", "tag3"]}\n\n'
        f"Document :\n{excerpt}"
    )

    response = client.models.generate_content(
        model=SUMMARY_MODEL,
        contents=prompt,
    )
    raw = response.text.strip()
    raw = raw.removeprefix("```json").removeprefix("```").removesuffix("```").strip()

    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return {"category": None, "tags": []}


def get_embedding(text: str, task_type: str = "retrieval_document") -> list[float]:
    """
    Calcule le vecteur d'embedding d'un morceau de texte (chunk) ou d'une question.
    """
    result = client.models.embed_content(
        model=EMBEDDING_MODEL,
        contents=text,
        config=types.EmbedContentConfig(
            task_type=task_type,
            output_dimensionality=768,
        ),
    )
    return result.embeddings[0].values

def get_embeddings_batch(texts: list[str], task_type: str = "retrieval_document") -> list[list[float]]:
    """
    Calcule les vecteurs d'embedding d'une liste de textes en batch.
    """
    if not texts:
        return []
    
    result = client.models.embed_content(
        model=EMBEDDING_MODEL,
        contents=texts,
        config=types.EmbedContentConfig(
            task_type=task_type,
            output_dimensionality=768,
        ),
    )
    return [e.values for e in result.embeddings]


def answer_question(question: str, context_chunks: list[dict]) -> str:
    """
    Génère une réponse en langage naturel à partir de la question de
    l'utilisateur et des chunks les plus pertinents trouvés dans le document.
    """
    if not context_chunks:
        return "Je n'ai pas trouvé d'information pertinente dans ce document pour répondre à cette question."

    context_text = "\n\n---\n\n".join(
        f"[Extrait {i+1}]\n{chunk['text']}" for i, chunk in enumerate(context_chunks)
    )

    prompt = (
        "Tu réponds à une question en te basant UNIQUEMENT sur les extraits de document fournis ci-dessous. "
        "Si les extraits ne contiennent pas l'information nécessaire pour répondre, dis-le clairement "
        "plutôt que d'inventer une réponse. Réponds en français, de façon claire et concise.\n\n"
        f"Extraits du document :\n{context_text}\n\n"
        f"Question : {question}\n\n"
        "Réponse :"
    )

    response = client.models.generate_content(
        model=SUMMARY_MODEL,
        contents=prompt,
    )
    return response.text.strip()
