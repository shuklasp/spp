<div class="sppdocs-feedback-widget" id="sppdocs-feedback-widget" style="margin-top: 3.5rem; padding-top: 1.5rem; border-top: 1px solid var(--vp-c-divider);">
    <div id="feedback-prompt" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--vp-c-bg-soft); padding: 1rem 1.5rem; border-radius: 10px; border: 1px solid var(--vp-c-divider);">
        <div style="font-size: 0.95rem; font-weight: 600; color: var(--vp-c-text-1);">
            Was this documentation page helpful?
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button type="button" onclick="submitDocFeedback('yes')" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1); border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease;">
                <span>👍</span> Yes
            </button>
            <button type="button" onclick="showFeedbackDetails('no')" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1); border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease;">
                <span>👎</span> No
            </button>
        </div>
    </div>

    <div id="feedback-details" style="display: none; margin-top: 1rem; background: var(--vp-c-bg-soft); padding: 1.25rem; border-radius: 10px; border: 1px solid var(--vp-c-divider);">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--vp-c-text-1);">
            How can we improve this article? (Optional)
        </label>
        <textarea id="feedback-comment" rows="3" placeholder="What information was missing or unclear?" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-family: inherit; font-size: 0.85rem; background: var(--vp-c-bg); color: var(--vp-c-text-1); box-sizing: border-box;"></textarea>
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.75rem;">
            <button type="button" onclick="submitDocFeedback('no')" style="padding: 0.45rem 1.2rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                Send Feedback
            </button>
        </div>
    </div>

    <div id="feedback-thanks" style="display: none; margin-top: 1rem; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #15803d; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; align-items: center; gap: 0.5rem;">
        <span>🎉</span> Thank you! Your feedback helps us continuously improve the documentation.
    </div>
</div>

<script>
function showFeedbackDetails(rating) {
    document.getElementById('feedback-prompt').style.display = 'none';
    document.getElementById('feedback-details').style.display = 'block';
}

function submitDocFeedback(rating) {
    const comment = document.getElementById('feedback-comment') ? document.getElementById('feedback-comment').value : '';
    const projectId = {{ json_encode($project_id ?? ($project['id'] ?? 'default')) }};
    const path = window.location.pathname;

    fetch('{{ \SPP\App::getBaseUrl() }}/api/feedback/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            project_id: projectId,
            path: path,
            rating: rating,
            comment: comment
        })
    }).then(res => res.json()).then(data => {
        document.getElementById('feedback-prompt').style.display = 'none';
        document.getElementById('feedback-details').style.display = 'none';
        document.getElementById('feedback-thanks').style.display = 'flex';
    }).catch(err => {
        document.getElementById('feedback-prompt').style.display = 'none';
        document.getElementById('feedback-details').style.display = 'none';
        document.getElementById('feedback-thanks').style.display = 'flex';
    });
}
</script>