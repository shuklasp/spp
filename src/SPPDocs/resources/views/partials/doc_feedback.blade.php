@php
    $effectiveProjectId = $project_id ?? ($project['id'] ?? '');
    $appBaseUrl = \SPP\App::getBaseUrl();
@endphp

<div class="sppdocs-feedback-widget" id="sppdocs-feedback-widget" 
     data-project-id="{{ htmlspecialchars($effectiveProjectId) }}"
     data-base-url="{{ htmlspecialchars($appBaseUrl) }}"
     style="margin-top: 3.5rem; padding-top: 1.5rem; border-top: 1px solid var(--vp-c-divider);">
    <div id="feedback-prompt" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--vp-c-bg-soft); padding: 1rem 1.5rem; border-radius: 10px; border: 1px solid var(--vp-c-divider);">
        <div style="font-size: 0.95rem; font-weight: 600; color: var(--vp-c-text-1); display: flex; align-items: center; gap: 0.5rem;">
            <span>💡</span> Was this documentation page helpful?
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button type="button" id="feedback-btn-yes" onclick="submitDocFeedback('yes')" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 1.1rem; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1); border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.2s ease;">
                <span style="font-size: 1rem;">👍</span> Yes
            </button>
            <button type="button" id="feedback-btn-no" onclick="showFeedbackDetails('no')" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 1.1rem; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1); border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.2s ease;">
                <span style="font-size: 1rem;">👎</span> No
            </button>
        </div>
    </div>

    <div id="feedback-details" style="display: none; margin-top: 1rem; background: var(--vp-c-bg-soft); padding: 1.25rem; border-radius: 10px; border: 1px solid var(--vp-c-divider);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <label style="font-size: 0.875rem; font-weight: 600; color: var(--vp-c-text-1);">
                How can we improve this article? <span style="font-weight: 400; color: var(--vp-c-text-2);">(Optional)</span>
            </label>
            <button type="button" onclick="cancelFeedbackDetails()" style="background: none; border: none; font-size: 0.8rem; color: var(--vp-c-text-2); cursor: pointer; text-decoration: underline;">
                Cancel
            </button>
        </div>
        <textarea id="feedback-comment" rows="3" placeholder="What information was missing or unclear?" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-family: inherit; font-size: 0.875rem; background: var(--vp-c-bg); color: var(--vp-c-text-1); box-sizing: border-box; resize: vertical;"></textarea>
        <div style="display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: 0.75rem;">
            <button type="button" id="feedback-btn-skip" onclick="submitDocFeedback('no', true)" style="padding: 0.45rem 1rem; background: transparent; color: var(--vp-c-text-2); border: 1px solid var(--vp-c-divider); border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                Skip Comment
            </button>
            <button type="button" id="feedback-btn-submit" onclick="submitDocFeedback('no')" style="padding: 0.45rem 1.3rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                Send Feedback
            </button>
        </div>
    </div>

    <div id="feedback-thanks" style="display: none; margin-top: 1rem; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #15803d; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; align-items: center; gap: 0.6rem;">
        <span style="font-size: 1.25rem;">🎉</span> <span>Thank you! Your feedback helps us continuously improve our documentation.</span>
    </div>
</div>

<style>
#feedback-btn-yes:hover {
    border-color: #22c55e !important;
    color: #15803d !important;
    background: rgba(34, 197, 94, 0.08) !important;
    transform: translateY(-1px);
}
#feedback-btn-no:hover {
    border-color: #ef4444 !important;
    color: #b91c1c !important;
    background: rgba(239, 68, 68, 0.08) !important;
    transform: translateY(-1px);
}
</style>

<script>
window.showFeedbackDetails = function(rating) {
    var promptEl = document.getElementById('feedback-prompt');
    var detailsEl = document.getElementById('feedback-details');
    if (promptEl) promptEl.style.display = 'none';
    if (detailsEl) {
        detailsEl.style.display = 'block';
        var textarea = document.getElementById('feedback-comment');
        if (textarea) textarea.focus();
    }
};

window.cancelFeedbackDetails = function() {
    var promptEl = document.getElementById('feedback-prompt');
    var detailsEl = document.getElementById('feedback-details');
    if (detailsEl) detailsEl.style.display = 'none';
    if (promptEl) promptEl.style.display = 'flex';
};

window.submitDocFeedback = function(rating, skipComment) {
    var widget = document.getElementById('sppdocs-feedback-widget');
    var commentEl = document.getElementById('feedback-comment');
    var comment = (commentEl && !skipComment) ? commentEl.value.trim() : '';

    var projectId = '';
    if (widget && widget.dataset && widget.dataset.projectId) {
        projectId = widget.dataset.projectId.trim();
    }
    if (!projectId) {
        var urlParams = new URLSearchParams(window.location.search);
        projectId = urlParams.get('project') || urlParams.get('projectId') || '';
    }
    if (!projectId) {
        var match = window.location.pathname.match(/\/(?:admin\/)?(?:project|blog|docs)\/([^\/\?]+)/);
        if (match && match[1]) {
            projectId = decodeURIComponent(match[1]);
        }
    }
    if (!projectId) {
        projectId = 'demo-app';
    }

    var baseUrl = '';
    if (widget && widget.dataset && widget.dataset.baseUrl !== undefined) {
        baseUrl = widget.dataset.baseUrl.replace(/\/$/, '');
    }
    var submitUrl = (baseUrl ? baseUrl : '') + '/api/feedback/submit';

    var btnYes = document.getElementById('feedback-btn-yes');
    var btnNo = document.getElementById('feedback-btn-no');
    var btnSubmit = document.getElementById('feedback-btn-submit');
    if (btnYes) btnYes.disabled = true;
    if (btnNo) btnNo.disabled = true;
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Sending...';
    }

    var payload = {
        project_id: projectId,
        path: window.location.pathname,
        rating: rating,
        comment: comment
    };

    fetch(submitUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    }).then(function(res) {
        return res.json().catch(function() { return {}; });
    }).then(function(data) {
        var promptEl = document.getElementById('feedback-prompt');
        var detailsEl = document.getElementById('feedback-details');
        var thanksEl = document.getElementById('feedback-thanks');
        if (promptEl) promptEl.style.display = 'none';
        if (detailsEl) detailsEl.style.display = 'none';
        if (thanksEl) thanksEl.style.display = 'flex';
        try {
            sessionStorage.setItem('sppdocs_rated_' + window.location.pathname, rating);
        } catch (e) {}
    }).catch(function(err) {
        var promptEl = document.getElementById('feedback-prompt');
        var detailsEl = document.getElementById('feedback-details');
        var thanksEl = document.getElementById('feedback-thanks');
        if (promptEl) promptEl.style.display = 'none';
        if (detailsEl) detailsEl.style.display = 'none';
        if (thanksEl) thanksEl.style.display = 'flex';
    });
};

(function() {
    try {
        var savedRating = sessionStorage.getItem('sppdocs_rated_' + window.location.pathname);
        if (savedRating) {
            var promptEl = document.getElementById('feedback-prompt');
            var thanksEl = document.getElementById('feedback-thanks');
            if (promptEl) promptEl.style.display = 'none';
            if (thanksEl) thanksEl.style.display = 'flex';
        }
    } catch (e) {}
})();
</script>