@extends('layouts.app')

@section('content')
<div class="element-details-wrapper">
    <nav class="breadcrumb">
        <a href="{{ $base_url }}">← Back to Periodic Table</a>
    </nav>
    <!-- Render the external partial per SPP framework rules -->
    @spppartial('partials/element_details.php', ['element' => $element, 'wiki' => $wiki])
</div>
@endsection
