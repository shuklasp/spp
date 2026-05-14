@extends('admin.layout')

@section('content')
<div class="field-form">
    <h1>Add Field to {{ $bundle }}</h1>
    <div class="glass-panel">
        {!! $form->render() !!}
    </div>
</div>
@endsection
