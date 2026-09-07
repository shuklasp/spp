{{-- Page Not Found View Fragment --}}
<div class="sppdocs-empty-page-box">
    <h2>Page Does Not Exist</h2>
    <p>This wiki page has not been created yet.</p>
    @if(!empty($create_url))
        <a href="{{ $create_url }}" class="sppdocs-btn-create-page">Create Page</a>
    @endif
</div>
