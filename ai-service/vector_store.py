import os
import chromadb
from dotenv import load_dotenv
from ai_engine import get_embedding, get_embeddings_batch

load_dotenv()

CHROMA_DIR = os.getenv("CHROMA_PERSIST_DIR", "./chroma_data")

client = chromadb.PersistentClient(path=CHROMA_DIR)
collection = client.get_or_create_collection(name="document_chunks")


def chunk_text(text: str, chunk_size: int = 1000, overlap: int = 150) -> list[str]:
    """
    Découpe un texte en morceaux (chunks) de taille fixe, avec un léger
    chevauchement pour ne pas couper une idée au milieu entre deux chunks.
    """
    chunks = []
    start = 0
    text_length = len(text)

    while start < text_length:
        end = start + chunk_size
        chunk = text[start:end].strip()
        if chunk:
            chunks.append(chunk)
        start += chunk_size - overlap

    return chunks


def index_resource(resource_id: int, text: str) -> list[dict]:
    """
    Découpe le texte d'une ressource, calcule les embeddings, et les stocke
    dans Chroma. Retourne la liste des chunks avec leur référence,
    à renvoyer à Laravel pour qu'il crée les lignes document_chunks.
    """
    chunks = chunk_text(text)
    if not chunks:
        return []
        
    stored_chunks = []
    
    # M2: Get embeddings in batch instead of sequentially to avoid rate limits / slowness
    embeddings = get_embeddings_batch(chunks, task_type="retrieval_document")
    
    chunk_ids = []
    documents = []
    metadatas = []
    
    for index, (chunk, embedding) in enumerate(zip(chunks, embeddings)):
        chunk_id = f"resource-{resource_id}-chunk-{index}"
        
        chunk_ids.append(chunk_id)
        documents.append(chunk)
        metadatas.append({"resource_id": resource_id, "chunk_index": index})

        stored_chunks.append({
            "embedding_ref": chunk_id,
            "text": chunk,
            "chunk_index": index,
        })
        
    # B8: Use upsert instead of add to support re-indexing without throwing "ID already exists"
    collection.upsert(
        ids=chunk_ids,
        embeddings=embeddings,
        documents=documents,
        metadatas=metadatas,
    )

    return stored_chunks


def search_similar_chunks(resource_id: int, query: str, top_k: int = 4) -> list[dict]:
    """
    Cherche les chunks les plus pertinents pour une question donnée,
    en se limitant aux chunks de la ressource concernée.
    """
    # M1: Use retrieval_query for search queries for better vector similarity matching
    query_embedding = get_embedding(query, task_type="retrieval_query")

    results = collection.query(
        query_embeddings=[query_embedding],
        n_results=top_k,
        where={"resource_id": resource_id},
    )

    matches = []
    if results["ids"] and results["ids"][0]:
        for i, chunk_id in enumerate(results["ids"][0]):
            matches.append({
                "embedding_ref": chunk_id,
                "text": results["documents"][0][i],
                "chunk_index": results["metadatas"][0][i]["chunk_index"],
            })

    return matches


def delete_resource_chunks(resource_id: int) -> None:
    """
    Supprime tous les chunks d'une ressource de Chroma (utile si la ressource
    est supprimée côté Laravel, pour ne pas laisser de données orphelines).
    """
    collection.delete(where={"resource_id": resource_id})
