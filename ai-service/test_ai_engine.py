from extractors import extract_text
from ai_engine import generate_summary, suggest_category_and_tags, get_embedding

text = extract_text("roadmap-backend-laravel.pdf", "pdf")

print("=== RÉSUMÉ ===")
summary = generate_summary(text)
print(summary)

print("\n=== CATÉGORIE ET TAGS ===")
result = suggest_category_and_tags(text, ["Informatique", "Mathématiques", "Physique", "Gestion"])
print(result)

print("\n=== EMBEDDING (premiers éléments) ===")
embedding = get_embedding(text[:1000])
print(f"Dimension du vecteur : {len(embedding)}")
print(embedding[:5])
