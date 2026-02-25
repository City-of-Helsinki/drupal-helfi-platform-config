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

### Content Chunking (`ContentChunker`)

Splits long content into smaller pieces suitable for embedding models with token limits.

**Short content** is returned as a single chunk with no splitting.

**Long content** is split using a two-level strategy:

1. **Heading-based splitting** — splits on Markdown headings (`#`, `##`, `###`). Each section retains its heading.
2. **Recursive splitting** — if a section exceeds the chunk size, it is recursively split on progressively finer boundaries:
   - Paragraph breaks (`\n\n`)
   - Sentence boundaries (`. `)
   - Word boundaries (` `)
   - Hard character split (last resort)

Adjacent chunks overlap to preserve context continuity.
