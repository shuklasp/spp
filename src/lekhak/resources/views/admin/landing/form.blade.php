@extends('admin.layout')

@section('content')
<div class="landing-page-form">
    <h1>{{ $title ?? 'Landing Page Settings' }}</h1>
    
    <div class="glass-panel">
        {!! $form->render() !!}
    </div>
</div>
@endsection
