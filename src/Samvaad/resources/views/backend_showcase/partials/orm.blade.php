<div class="glass-panel">
    <h2>ORM & Active Record</h2>
    <p>The SPP framework features a powerful, lightweight ORM called <code>SPPEntity</code>. It provides Active Record capabilities, attribute-based field mapping, and secure query building.</p>

    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3 style="margin-top: 0;">Create a Showcase Item</h3>
        <form hx-post="<?= \SPP\App::url('backend-showcase/orm/create', 'samvaad') ?>" hx-target="#orm-results">
            <div class="form-group">
                <label class="form-label">Item Title</label>
                <input type="text" name="title" class="form-input" required placeholder="E.g., Learn SPPEntity">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-input" placeholder="Brief details...">
            </div>
            <button type="submit" class="btn btn-success">Save to Database</button>
        </form>
    </div>

    <div id="orm-results">
        @include('backend_showcase.partials.orm_table', ['items' => $items ?? []])
    </div>
</div>
