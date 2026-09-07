@php
    $viewId = $params['id'] ?? ($params['name'] ?? '');
    $proj = $project_id ?? ($project['id'] ?? 'spp');
    $viewResult = \App\SPPDocs\Services\ViewStudioService::executeView($proj, $viewId, $_GET ?? []);
@endphp

@spppartial('partials/views_renderer.blade.php', [
    'viewResult' => $viewResult,
    'project_id' => $proj
])