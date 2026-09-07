{{-- Dynamic View Block --}}
@if(!empty($block['viewResult']))
    @spppartial('partials/views_renderer.blade.php', ['viewResult' => $block['viewResult'], 'project_id' => $project_id])
@endif