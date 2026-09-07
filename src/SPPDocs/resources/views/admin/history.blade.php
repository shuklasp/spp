<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Revision History: {{ $filename }}</title>
    <link rel="stylesheet" href="@url('css/admin.css')">
</head>
<body class="editor-body">
    <div class="editor-nav">
        <div><a href="{{ \SPP\App::getBaseUrl() }}/admin/project/{{ $project_id }}">&larr; Back to {{ $project['title'] }}</a></div>
        <div>History: {{ $filename }}</div>
    </div>
    
    <div class="history-container">
        <h2 style="margin-top:0;">Revision History</h2>
        <p style="color:#64748b;">Previous versions of <strong>{{ $filename }}</strong></p>
        
        @if(empty($revisions))
            <p style="padding:2rem; text-align:center; background:#f1f5f9; border-radius:4px;">No revisions found for this file.</p>
        @else
            <div>
                @foreach($revisions as $rev)
                <div class="history-rev-item">
                    <div>
                        <strong>{{ $rev['date'] }}</strong>
                        <div style="font-size:0.85rem; color:#64748b;">Timestamp: {{ $rev['timestamp'] }}</div>
                    </div>
                    <div>
                        <button class="adm-btn adm-btn-primary adm-btn-sm" onclick="restore('{{ $rev['timestamp'] }}')">Restore</button>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        function restore(timestamp) {
            if (!confirm('Are you sure you want to overwrite the live page with this older revision?')) return;
            
            const formData = new FormData();
            formData.append('project', '{{ $project_id }}');
            formData.append('type', '{{ $type }}');
            formData.append('file', '{{ $filename }}');
            formData.append('timestamp', timestamp);
            
            fetch('{{ \SPP\App::getBaseUrl() }}/admin/history/restore', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ $_SESSION['sppdocs_csrf'] ?? '' }}'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Revision restored successfully!');
                    window.location.href = '{{ \SPP\App::getBaseUrl() }}/admin/project/{{ $project_id }}';
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>

