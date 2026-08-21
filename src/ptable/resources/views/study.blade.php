@extends('layouts.app')

@section('content')
<div class="element-study-wrapper">
    <nav class="breadcrumb">
        <a href="{{ $base_url }}/element/{{ $element['symbol'] }}">← Back to {{ $element['name'] }} Details</a>
    </nav>
    <!-- Render the external partial per SPP framework rules -->
    @spppartial('partials/element_study.php', ['element' => $element, 'wiki' => $wiki])
</div>
@endsection
