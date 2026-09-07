## `make:drupal-import`

**Purpose**: Import content nodes, taxonomy vocabularies, and media from Drupal JSON:API into SPP with zero external vendor dependencies.

### Synopsis
```bash
php spp.php make:drupal-import --url=<DrupalBaseUrl> [OPTIONS]
```

### Extended Usage
The `make:drupal-import` command connects directly to Drupal 8/9/10/11 JSON:API endpoints using pure PHP native stream contexts and cURL. It fetches content types, articles, pages, or custom bundles, automatically converts HTML markup into clean Markdown, extracts frontmatter metadata, and maps Drupal taxonomy terms into SPP taxonomy stores.

Because it relies exclusively on standard PHP HTTP streams, it requires zero external dependencies (no Guzzle, no Composer bloat) and runs efficiently across shared hosting and containers.

Practical examples:
```bash
# Full import of all articles and taxonomy tags from a remote Drupal site
php spp.php make:drupal-import --url=https://my-drupal-site.org

# Import only documentation pages from a specific Drupal bundle
php spp.php make:drupal-import --url=https://my-drupal-site.org --type=content --bundle=documentation --app=SPPDocs

# Import only taxonomy terms from a vocabulary
php spp.php make:drupal-import --url=https://my-drupal-site.org --type=taxonomy --vocabulary=categories

# Preview imported records without writing files (dry run)
php spp.php make:drupal-import --url=https://my-drupal-site.org --dry-run

# Run fixture-based mock import for testing and CI validation
php spp.php make:drupal-import --mock --dry-run
```

### Options Available
- `--url=<url>`: Base URL of the remote Drupal instance (e.g. `https://my-drupal-site.org`).
- `--type=<type>`: Target entity to import: `content`, `taxonomy`, or `all` (default: `all`).
- `--bundle=<bundle>`: Specific Drupal content type / bundle to extract (default: `article`).
- `--vocabulary=<vocab>`: Drupal taxonomy vocabulary machine name (default: `tags`).
- `--app=<appName>`: Target SPP application receiving content (default: `SPPDocs`).
- `--project=<projectId>`: Target project identifier for document storage (default: `spp`).
- `--limit=<n>`: Maximum number of records to import per request (default: `50`).
- `--dry-run`: Preview records and generated paths without saving files to disk.
- `--mock`: Use built-in test fixtures for offline testing without a live Drupal endpoint.

### Under the Hood Activity
- Connects to `/jsonapi/node/{bundle}` and `/jsonapi/taxonomy_term/{vocabulary}` using PHP stream contexts.
- Translates HTML elements into clean GitHub Flavored Markdown.
- Generates Markdown files with YAML frontmatter in `docs/{project}/{bundle}/{slug}.md`.
- Persists taxonomy terms in `data/projects/{project}/taxonomy.json`.
