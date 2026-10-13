from extractors import extract_text

# Remplace par un vrai chemin vers un fichier test sur ton ordinateur
text = extract_text("roadmap-backend-laravel.pdf", "pdf")
print(text[:500])  # affiche les 500 premiers caractères
print(f"\n\nLongueur totale extraite : {len(text)} caractères")
