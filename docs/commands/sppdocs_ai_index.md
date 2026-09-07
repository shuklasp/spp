## sppdocs:ai:index

**Purpose**: Indexes documentation markdown pages into semantic section chunks and generates vector signatures for natural language AI search and RAG synthesis.

### Synopsis

```bash
php spp.php sppdocs:ai:index [--project=<project-id>] [--provider=<offline|openai|gemini|anthropic|ollama|custom>]
```

### Extended Usage

`sppdocs:ai:index` parses all markdown documentation files (`.md`, `.markdown`) within the target project's pages directory. It decomposes documents into semantic sections bounded by headings (`#`, `##`, `###`), extracts clean prose text, strips code block markers, and calculates BM25 term frequency matrices and smoothed inverse document frequencies (IDF).

When queried from the search palette or API (`/api/search/ask`), this generated vector index enables conversational question-answering with exact markdown section citations without requiring cloud APIs. If a cloud or local LLM provider is specified, high-density vector representations can also be computed.

#### Examples

```bash
# Index default project using zero-cloud offline vectorization
php spp.php sppdocs:ai:index --project=demo-app

# Re-index project with local Ollama embeddings
php spp.php sppdocs:ai:index --project=demo-app --provider=ollama
```

### Options Available

- `--project=<project-id>`: Identifier of the target project to index (default: `demo-app`).
- `--provider=<provider>`: Vector/LLM provider to associate with the index (`offline`, `openai`, `gemini`, `anthropic`, `ollama`, `custom`; default: `offline`).

### Under the Hood Activity

1. Reads project configuration from `etc/sppdocs.yml` or `docs/<project-id>/project.yml`.
2. Recursively traverses the documentation pages directory and parses markdown headers.
3. Computes BM25/TF-IDF token matrices and builds `docs/<project-id>/vector-index.json`.
4. Enforces CLI SAPI guarding (`isCLIOnly()`), preventing unauthorized execution from web entrypoints.
