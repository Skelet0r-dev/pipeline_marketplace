// Profile dropdown
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');
if (profileBtn) {
    profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
    document.addEventListener('click', () => profileDropdown.classList.remove('show'));
    profileDropdown.addEventListener('click', e => e.stopPropagation());
}

// Image gallery
function switchImg(thumb) {
    document.querySelectorAll('.listing-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    document.getElementById('mainImg').src = thumb.src;
}

// Reaction toggle
const LIKE_ENDPOINT = 'like_toggle.php';
const REACTORS_ENDPOINT = 'reaction_reactors.php';

const reactionGroup = document.getElementById('reactionGroup');

if (reactionGroup) {
    reactionGroup.addEventListener('click', function (event) {
        const button = event.target.closest('.listing-reaction-btn');
        if (!button) return;

        if (event.target.closest('.reaction-count')) {
            event.preventDefault();
            event.stopPropagation();
            showReactors(this.dataset.id, button.dataset.reaction);
            return;
        }

        const body = new FormData();
        body.append('listing_id', this.dataset.id);
        body.append('reaction_type', button.dataset.reaction);

        fetch(LIKE_ENDPOINT, { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                if (data.error) { console.error(data.error); return; }

                this.querySelectorAll('.listing-reaction-btn').forEach(btn => {
                    const selected = data.reaction === btn.dataset.reaction;
                    btn.classList.toggle('selected', selected);
                    btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });

                Object.entries(data.counts || {}).forEach(([reaction, count]) => {
                    const countEl = this.querySelector(`[data-count-for="${reaction}"]`);
                    if (countEl) countEl.textContent = count;
                });
            })
            .catch(err => console.error('Reaction error:', err));
    });
}

function getReactorsDialog() {
    let dialog = document.getElementById('reactorsDialog');
    if (dialog) return dialog;

    dialog = document.createElement('div');
    dialog.id = 'reactorsDialog';
    dialog.className = 'reactors-dialog';
    dialog.hidden = true;
    dialog.innerHTML = `
        <div class="reactors-backdrop" data-reactors-close></div>
        <div class="reactors-panel" role="dialog" aria-modal="true" aria-labelledby="reactorsTitle">
            <div class="reactors-head">
                <h3 id="reactorsTitle">Reactions</h3>
                <button type="button" class="reactors-close" data-reactors-close aria-label="Close">&times;</button>
            </div>
            <div class="reactors-list"></div>
        </div>`;
    document.body.appendChild(dialog);
    dialog.addEventListener('click', event => {
        if (event.target.closest('[data-reactors-close]')) closeReactorsDialog();
    });
    return dialog;
}

function closeReactorsDialog() {
    const dialog = document.getElementById('reactorsDialog');
    if (!dialog) return;
    dialog.hidden = true;
    document.body.classList.remove('reactors-open');
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function showReactors(listingId, reactionType) {
    const dialog = getReactorsDialog();
    const title = dialog.querySelector('#reactorsTitle');
    const list = dialog.querySelector('.reactors-list');

    dialog.hidden = false;
    document.body.classList.add('reactors-open');
    title.textContent = 'Reactions';
    list.innerHTML = '<p class="reactors-empty">Loading...</p>';

    const params = new URLSearchParams({ listing_id: listingId, reaction_type: reactionType });
    fetch(`${REACTORS_ENDPOINT}?${params.toString()}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                list.innerHTML = `<p class="reactors-empty">${escapeHtml(data.error)}</p>`;
                return;
            }

            title.innerHTML = `${data.emoji || ''} ${escapeHtml(data.label)} reactions`;
            if (!data.users || data.users.length === 0) {
                list.innerHTML = '<p class="reactors-empty">No one has picked this reaction yet.</p>';
                return;
            }

            list.innerHTML = data.users.map(user => `
                <a class="reactor-row" href="public_profile.php?id=${encodeURIComponent(user.user_id)}">
                    <img src="${escapeHtml(user.avatar)}" class="reactor-avatar" alt="">
                    <span class="reactor-main">
                        <span class="reactor-name">${escapeHtml(user.name || user.username)}</span>
                        <span class="reactor-handle">@${escapeHtml(user.username)}</span>
                    </span>
                    <span class="reactor-time">${escapeHtml(user.created_at)}</span>
                </a>
            `).join('');
        })
        .catch(() => {
            list.innerHTML = '<p class="reactors-empty">Could not load reactions right now.</p>';
        });
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeReactorsDialog();
});
// Save toggle
const SAVE_ENDPOINT = 'save_toggle.php';
const saveBtn = document.getElementById('saveBtn');

if (saveBtn) {
    saveBtn.addEventListener('click', function () {
        const listingId = this.dataset.id;
        const body = new FormData();
        body.append('listing_id', listingId);

        fetch(SAVE_ENDPOINT, { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                if (data.error) { console.error(data.error); return; }
                this.dataset.saved = data.saved ? '1' : '0';
                this.classList.toggle('saved', data.saved);
                this.querySelector('.save-icon').textContent = data.saved ? '🔖' : '📑';
                this.querySelector('.save-label').textContent = data.saved ? 'Saved' : 'Save for Later';
            })
            .catch(err => console.error('Save error:', err));
    });
}

// ── Report modal ─────────────────────────────────────────
const REPORT_ENDPOINT = 'report_item.php';
const toggleReportBtn = document.getElementById('toggleReportBtn');
const submitReportBtn = document.getElementById('submitReportBtn');
const reportForm = document.getElementById('reportForm');
const reportFeedback = document.getElementById('reportFeedback');

let reportModal = null;
if (document.getElementById('reportModal')) {
    reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
}

if (toggleReportBtn) {
    toggleReportBtn.addEventListener('click', function () {
        reportForm.reset();
        reportFeedback.hidden = true;
        reportModal.show();
    });
}

if (submitReportBtn) {
    submitReportBtn.addEventListener('click', function () {
        if (!reportForm.reportValidity()) return;

        submitReportBtn.disabled = true;
        submitReportBtn.textContent = 'Submitting...';
        reportFeedback.hidden = true;

        const body = new FormData(reportForm);

        fetch(REPORT_ENDPOINT, { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                reportFeedback.hidden = false;
                if (data.error) {
                    reportFeedback.textContent = data.error;
                    reportFeedback.className = 'listing-report-feedback is-error';
                } else {
                    reportFeedback.textContent = data.message || 'Report submitted successfully.';
                    reportFeedback.className = 'listing-report-feedback is-success';
                    reportForm.reset();
                    setTimeout(() => reportModal.hide(), 1800);
                }
            })
            .catch(() => {
                reportFeedback.hidden = false;
                reportFeedback.textContent = 'Could not submit your report right now. Please try again.';
                reportFeedback.className = 'listing-report-feedback is-error';
            })
            .finally(() => {
                submitReportBtn.disabled = false;
                submitReportBtn.textContent = 'Submit Report';
            });
    });
}

// ── Comment submit ───────────────────────────────────────
const COMMENT_ENDPOINT = 'comment_post.php';
const COMMENT_DELETE_ENDPOINT = 'comment_delete.php';
const COMMENT_REPORT_ENDPOINT = 'report_comment.php';

const commentInput = document.getElementById('commentInput');
const commentSubmit = document.getElementById('commentSubmit');
const commentsList = document.getElementById('commentsList');
const commentsCount = document.getElementById('commentsCount');
const charCount = document.getElementById('charCount');
const commentReportForm = document.getElementById('commentReportForm');
const commentReportFeedback = document.getElementById('commentReportFeedback');
const commentReportId = document.getElementById('commentReportId');
const submitCommentReportBtn = document.getElementById('submitCommentReportBtn');

let commentReportModal = null;
if (document.getElementById('commentReportModal')) {
    commentReportModal = new bootstrap.Modal(document.getElementById('commentReportModal'));
}

if (commentInput && commentSubmit) {
    console.log('Comment elements found, attaching listeners');
    
    // Character counter
    commentInput.addEventListener('input', function () {
        charCount.textContent = this.value.length;
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Submit on button click or Ctrl+Enter
    commentInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            submitComment();
        }
    });
    
    commentSubmit.addEventListener('click', submitComment);
}

if (commentsList) {
    commentsList.addEventListener('click', function (event) {
        const deleteBtn = event.target.closest('.comment-delete-btn');
        const reportBtn = event.target.closest('.comment-report-btn');

        if (deleteBtn) {
            deleteComment(deleteBtn.dataset.commentId);
            return;
        }

        if (reportBtn && commentReportModal) {
            commentReportForm.reset();
            commentReportFeedback.hidden = true;
            commentReportId.value = reportBtn.dataset.commentId;
            commentReportModal.show();
        }
    });
}

if (submitCommentReportBtn) {
    submitCommentReportBtn.addEventListener('click', function () {
        if (!commentReportForm.reportValidity()) return;

        submitCommentReportBtn.disabled = true;
        submitCommentReportBtn.textContent = 'Submitting...';
        commentReportFeedback.hidden = true;

        fetch(COMMENT_REPORT_ENDPOINT, { method: 'POST', body: new FormData(commentReportForm) })
            .then(r => r.json())
            .then(data => {
                commentReportFeedback.hidden = false;
                if (data.error) {
                    commentReportFeedback.textContent = data.error;
                    commentReportFeedback.className = 'listing-report-feedback is-error';
                } else {
                    commentReportFeedback.textContent = data.message || 'Comment report submitted.';
                    commentReportFeedback.className = 'listing-report-feedback is-success';
                    commentReportForm.reset();
                    setTimeout(() => commentReportModal.hide(), 1600);
                }
            })
            .catch(() => {
                commentReportFeedback.hidden = false;
                commentReportFeedback.textContent = 'Could not submit this report right now. Please try again.';
                commentReportFeedback.className = 'listing-report-feedback is-error';
            })
            .finally(() => {
                submitCommentReportBtn.disabled = false;
                submitCommentReportBtn.textContent = 'Submit Report';
            });
    });
}

function deleteComment(commentId) {
    if (!commentId || !confirm('Delete this comment?')) return;

    const body = new FormData();
    body.append('comment_id', commentId);

    fetch(COMMENT_DELETE_ENDPOINT, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const item = commentsList.querySelector(`[data-comment-id="${commentId}"]`);
            if (item) item.remove();

            const currentCount = parseInt(commentsCount.textContent) || 0;
            const nextCount = Math.max(0, currentCount - 1);
            commentsCount.textContent = nextCount;

            if (nextCount === 0) {
                const empty = document.createElement('p');
                empty.className = 'comments-empty';
                empty.id = 'commentsEmpty';
                empty.textContent = 'No comments yet. Be the first to ask!';
                commentsList.appendChild(empty);
            }
        })
        .catch(() => alert('Could not delete the comment right now. Please try again.'));
}

function submitComment() {
    console.log('submitComment called');
    const container = document.querySelector('.listing-container');
    const lid = container ? container.dataset.listingId : '';
    const text = commentInput.value.trim();

    if (!lid) {
        alert('Error: Listing ID not found. Please refresh the page.');
        return;
    }
    if (!text) return;

    commentSubmit.disabled = true;
    commentSubmit.textContent = 'Posting…';

    const body = new FormData();
    body.append('listing_id', lid);
    body.append('comment_text', text);

    console.log('Sending comment for listing:', lid);

    fetch(COMMENT_ENDPOINT, { method: 'POST', body })
        .then(r => {
            if (!r.ok) throw new Error('Server returned ' + r.status);
            return r.json();
        })
        .then(data => {
            console.log('Comment response:', data);
            if (data.error) { alert('Error: ' + data.error); return; }

            const empty = document.getElementById('commentsEmpty');
            if (empty) empty.remove();

            const item = document.createElement('div');
            item.className = 'comment-item comment-item-new';
            item.dataset.commentId = data.comment_id;
            item.innerHTML = `
                <img src="${data.avatar}" class="comment-avatar" alt="Avatar">
                <div class="comment-bubble">
                    <div class="comment-meta">
                        <span class="comment-user">${data.first_name} ${data.last_name}</span>
                        <span class="comment-handle">@${data.username}</span>
                        <span class="comment-time">${data.created_at}</span>
                        <span class="comment-actions">
                            <button type="button" class="comment-action-btn comment-delete-btn" data-comment-id="${data.comment_id}">Delete</button>
                        </span>
                    </div>
                    <p class="comment-text">${data.comment_text.replace(/\n/g, '<br>')}</p>
                </div>`;
            commentsList.appendChild(item);

            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            const currentCount = parseInt(commentsCount.textContent) || 0;
            commentsCount.textContent = currentCount + 1;

            commentInput.value = '';
            commentInput.style.height = 'auto';
            charCount.textContent = '0';
        })
        .catch(err => {
            console.error('Comment error:', err);
            alert('Failed to post comment. Check your connection or try again.');
        })
        .finally(() => {
            commentSubmit.disabled = false;
            commentSubmit.textContent = 'Post';
        });
}
