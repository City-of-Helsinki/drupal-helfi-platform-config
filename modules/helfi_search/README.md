# Helfi Search

Drupal module that converts content entities into vector embeddings for semantic search. Integrates with Search API and Elasticsearch.

## Text Pipeline

The text pipeline converts Drupal entities into embedding-ready text. It is orchestrated by `TextPipeline::process()`, which passes each entity through six stages:

```
Entity
  │
  ▼
HtmlExtractor ─── Fetches the entity's canonical URL via HTTP
  │
  ▼
HtmlCleaner ───── Strips non-content elements from the HTML
  │
  ▼
MarkdownConverter ── Converts clean HTML to Markdown
  │
  ▼
TextNormalizer ─── Whitespace normalization
  │
  ▼
ContentChunker ─── Splits long content into overlapping chunks
  │
  ▼
MetadataComposer ── Prepends entity metadata labels to each chunk
  │
  ▼
EmbeddingsModelInterface ── Converts chunks to vectors
  │
  ▼
Vectors
```
