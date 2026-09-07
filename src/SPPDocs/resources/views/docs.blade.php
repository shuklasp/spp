@extends('layouts.base')

@section('title', $title)

@section('meta')
@if(!empty($frontmatter['description']))
    <meta name="description" content="{{ htmlspecialchars($frontmatter['description']) }}">
    <meta property="og:description" content="{{ htmlspecialchars($frontmatter['description']) }}">
    <meta name="twitter:description" content="{{ htmlspecialchars($frontmatter['description']) }}">
@endif
    <meta property="og:title" content="{{ htmlspecialchars($title) }}">
    <meta property="og:type" content="article">
    <meta property="og:image" content="{{ \SPP\App::getBaseUrl() }}/og/card?project={{ rawurlencode($project_id ?? '') }}&title={{ rawurlencode($title) }}&desc={{ rawurlencode($frontmatter['description'] ?? '') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ htmlspecialchars($title) }}">
    <meta name="twitter:image" content="{{ \SPP\App::getBaseUrl() }}/og/card?project={{ rawurlencode($project_id ?? '') }}&title={{ rawurlencode($title) }}&desc={{ rawurlencode($frontmatter['description'] ?? '') }}">
@if(!empty($frontmatter['author']))
    <meta name="author" content="{{ htmlspecialchars($frontmatter['author']) }}">
@endif
    <!-- Schema.org JSON-LD TechArticle Microdata -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "TechArticle",
      "headline": {{ json_encode($title) }},
      "description": {{ json_encode($frontmatter['description'] ?? '') }},
      "author": {
        "@type": "Person",
        "name": {{ json_encode($frontmatter['author'] ?? 'Documentation Team') }}
      },
      "publisher": {
        "@type": "Organization",
        "name": {{ json_encode($project['title'] ?? ($project_id ?? 'SPPDocs')) }}
      }
    }
    </script>
@endsection
@section('sidebar')
    <!-- Inject sidebar partial modularly -->
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $version, 'current_path' => $current_path ?? null])
@endsection

@section('content')
    <!-- 
      Inject content partial so the initial page load perfectly matches 
      the structure expected by subsequent HTMX swaps! 
    -->
    @spppartial('partials/doc-content.blade.php', ['title' => $title, 'content' => $content])
@endsection
