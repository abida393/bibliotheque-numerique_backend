from extractors import extract_text
from vector_store import index_resource, search_similar_chunks, delete_resource_chunks

text = extract_text("roadmap-backend-laravel.pdf", "pdf")

print("=== INDEXATION ===")
chunks = index_resource(resource_id=999, text=text)
print(f"{len(chunks)} chunks créés")
for c in chunks[:2]:
    print(c["embedding_ref"], "-", c["text"][:80], "...")

print("\n=== RECHERCHE ===")
question = "Quel est le sujet principal de ce document ?"
results = search_similar_chunks(resource_id=999, query=question)
for r in results:
    print(f"[{r['embedding_ref']}] {r['text'][:100]}...")

print("\n=== NETTOYAGE ===")
delete_resource_chunks(resource_id=999)
print("Chunks supprimés")
