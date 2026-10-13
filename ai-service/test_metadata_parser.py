import unittest

from ai_engine import _parse_metadata_response


class MetadataParserTests(unittest.TestCase):
    def test_parses_json_surrounded_by_prose_and_markdown(self):
        result = _parse_metadata_response(
            'Résultat : ```json\n{"title": "Titre utile", "summary": "Résumé utile"}\n```',
            "Texte source",
        )

        self.assertEqual(result, {"title": "Titre utile", "summary": "Résumé utile"})

    def test_uses_document_heading_when_title_is_missing(self):
        result = _parse_metadata_response(
            '{"summary": "Résumé utile"}',
            "[Page 1]\nTitre présent dans le document",
        )

        self.assertEqual(result["title"], "Titre présent dans le document")

    def test_uses_document_heading_when_response_is_not_json(self):
        result = _parse_metadata_response(
            "Réponse non structurée",
            "[Page 1]\nTitre présent dans le document",
        )

        self.assertEqual(result["title"], "Titre présent dans le document")


if __name__ == "__main__":
    unittest.main()