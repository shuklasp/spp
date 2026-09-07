## sppdocs:build

**Purpose**: Pre-renders dynamic SPPDocs markdown documentation into pure static HTML/CSS/JS bundles for edge CDN hosting.

### Synopsis

```bash
php spp.php sppdocs:build [--project=<id>] [--out=<path>] [--zip]
```

### Extended Usage

`sppdocs:build` bridges the gap between SPPDocs' dynamic PHP runtime and modern Jamstack/edge static site generators like VitePress and Docusaurus.

When executed, this command:
1. Traverses the target project's markdown documentation tree and frontmatter metadata.
2. Compiles CommonMark syntax, GitHub-flavored alert callouts, and embedded interactive Blade components (`::: component`) into self-contained HTML files.
3. Bundles all required CSS stylesheets and media assets locally into `./assets/`, ensuring 100% offline and edge portability.
4. Generates a standalone `search-index.json` compatible with client-side full-text search engines (e.g. Lunr.js / MiniSearch).
5. Optionally creates a deployable `.zip` archive ready for upload to Cloudflare Pages, GitHub Pages, Vercel, or AWS S3.

### Options Available

- `--project=<id>`: The project ID to compile (default: `demo-app`). Must correspond to an entry in `src/SPPDocs/etc/sppdocs.yml`.
- `--out=<path>`: Custom destination directory for static files (default: `src/SPPDocs/dist/<projectId>/`).
- `--zip`: Packages the resulting static directory into `src/SPPDocs/dist/<projectId>.zip`.
- `--help`, `-h`: Displays usage information and supported flags.

### Under the Hood Activity

- **Filesystem Reads**:
  - Reads `src/SPPDocs/etc/sppdocs.yml` and the target project YAML definition.
  - Recursively reads markdown files from `pages_dir` and `resources/css/*.css`.
- **Filesystem Writes**:
  - Creates the destination output directory (`src/SPPDocs/dist/<projectId>/`).
  - Writes static `.html` files, copies stylesheets to `./assets/`, and writes `search-index.json`.
  - If `--zip` is enabled, writes the compressed archive via PHP `ZipArchive`.
- **Zero Outbound HTTP Calls**:
  - Compilation is executed 100% locally on disk without third-party network requests.
