import hashlib
import hmac
import os
from pathlib import Path

from fastapi import Depends, FastAPI, Header, HTTPException, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parents[1] / ".env")

from extractors import extract_text
from ai_engine import generate_metadata, suggest_category_and_tags, answer_question
from vector_store import index_resource, search_similar_chunks, delete_resource_chunks

app = FastAPI(title="Microservice IA - Bibliothèque Digitale")


def verify_service_token(authorization: str | None = Header(default=None)) -> None:
    expected = os.getenv("AI_SERVICE_TOKEN")
    if not expected and os.getenv("APP_KEY"):
        expected = hmac.new(
            os.environ["APP_KEY"].encode(),
            b"bibliotheque-ai-service",
            hashlib.sha256,
        ).hexdigest()

    if not expected:
        raise HTTPException(status_code=503, detail="Authentification du service non configurée")

    scheme, _, token = (authorization or "").partition(" ")
    if scheme.lower() != "bearer" or not token or not hmac.compare_digest(token, expected):
        raise HTTPException(status_code=401, detail="Non autorisé")

# Catch general ValueError exceptions (e.g. from unsupported file types) to return 422 instead of 500
@app.exception_handler(ValueError)
async def value_error_handler(request: Request, exc: ValueError):
    return JSONResponse(
        status_code=422,
        content={"detail": str(exc)},
    )


class AnalyzeRequest(BaseModel):
    resource_id: int
    file_path: str
    file_type: str
    existing_categories: list[str] = []


class AnalyzeResponse(BaseModel):
    summary: str
    suggested_category: str | None
    suggested_tags: list[str]
    chunks: list[dict]


class SummarizeRequest(BaseModel):
    file_path: str
    file_type: str


class SummarizeResponse(BaseModel):
    title: str
    summary: str


class AskRequest(BaseModel):
    resource_id: int
    question: str


class AskResponse(BaseModel):
    answer: str
    sources: list[dict]


@app.post("/analyze", response_model=AnalyzeResponse, dependencies=[Depends(verify_service_token)])
def analyze_resource(payload: AnalyzeRequest):
    try:
        text = extract_text(payload.file_path, payload.file_type)
    except FileNotFoundError:
        raise HTTPException(status_code=404, detail="Fichier introuvable sur le disque")
    except Exception as e:
        raise HTTPException(status_code=422, detail=f"Erreur de lecture du document: {str(e)}")

    if not text.strip():
        raise HTTPException(status_code=422, detail="Aucun texte n'a pu être extrait de ce document")

    metadata = generate_metadata(text)
    category_data = suggest_category_and_tags(text, payload.existing_categories)
    chunks = index_resource(payload.resource_id, text)

    return AnalyzeResponse(
        summary=metadata.get("summary", ""),
        suggested_category=category_data.get("category"),
        suggested_tags=category_data.get("tags", []),
        chunks=chunks,
    )


@app.post("/summarize", response_model=SummarizeResponse, dependencies=[Depends(verify_service_token)])
def summarize_file(payload: SummarizeRequest):
    try:
        text = extract_text(payload.file_path, payload.file_type)
    except FileNotFoundError:
        raise HTTPException(status_code=404, detail="Fichier introuvable sur le disque")
    except Exception as e:
        raise HTTPException(status_code=422, detail=f"Erreur de lecture du document: {str(e)}")

    if not text.strip():
        # Fixed mojibake characters in response
        raise HTTPException(status_code=422, detail="Aucun texte n'a pu être extrait")

    metadata = generate_metadata(text)
    return SummarizeResponse(
        title=metadata.get("title", ""),
        summary=metadata.get("summary", "")
    )


@app.post("/ask", response_model=AskResponse, dependencies=[Depends(verify_service_token)])
def ask_question(payload: AskRequest):
    relevant_chunks = search_similar_chunks(payload.resource_id, payload.question)

    if not relevant_chunks:
        raise HTTPException(
            status_code=404,
            detail="Aucun contenu indexé trouvé pour cette ressource. A-t-elle bien été analysée ?"
        )

    answer = answer_question(payload.question, relevant_chunks)

    sources = [
        {"embedding_ref": c["embedding_ref"], "chunk_index": c["chunk_index"]}
        for c in relevant_chunks
    ]

    return AskResponse(answer=answer, sources=sources)


@app.delete("/resources/{resource_id}", dependencies=[Depends(verify_service_token)])
def delete_resource_index(resource_id: int):
    delete_resource_chunks(resource_id)
    return {"message": "Index supprimé"}


@app.get("/health")
def health_check():
    return {"status": "ok"}
