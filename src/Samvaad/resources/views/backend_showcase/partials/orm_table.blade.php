<h3 style="margin-top: 0;">Current Items in Database</h3>
@if(empty($items))
    <p>No showcase items found in the database. Create one above!</p>
@else
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="border-bottom: 1px solid var(--surface-border);">
                <th style="padding: 1rem 0;">ID</th>
                <th style="padding: 1rem 0;">Title</th>
                <th style="padding: 1rem 0;">Description</th>
                <th style="padding: 1rem 0;">Status</th>
                <th style="padding: 1rem 0;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                <td style="padding: 1rem 0;">{{ $item->id }}</td>
                <td style="padding: 1rem 0; font-weight: 600;">{{ $item->title }}</td>
                <td style="padding: 1rem 0; color: var(--text-muted);">{{ $item->description }}</td>
                <td style="padding: 1rem 0;">
                    <span style="background: rgba(255,255,255,0.1); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                        {{ $item->status }}
                    </span>
                </td>
                <td style="padding: 1rem 0;">
                    <button class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" 
                            hx-delete="<?= \SPP\App::url('backend-showcase/orm/delete/' . $item->id, 'samvaad') ?>" 
                            hx-target="#orm-results">Delete</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif
