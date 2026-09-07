<div class="doc-body">
    <h1>{{ $title }}</h1>
    {!! $content !!}
    @spppartial('partials/doc_feedback.blade.php', ['project_id' => $project_id ?? ($project['id'] ?? '')])
</div>
