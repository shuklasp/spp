@extends('admin.layout')

@section('content')
<div class="content-type-form">
    <h1>Edit Content Type</h1>
    <div class="glass-panel">
        {!! $form->render() !!}
    </div>
</div>
@endsection
