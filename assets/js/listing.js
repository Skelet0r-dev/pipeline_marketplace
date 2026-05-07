// ── Profile dropdown ─────────────────────────────────────
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');
if (profileBtn) {
    profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
    document.addEventListener('click', () => profileDropdown.classList.remove('show'));
    profileDropdown.addEventListener('click', e => e.stopPropagation());
}

// ── Image gallery ────────────────────────────────────────
function switchImg(thumb) {
    document.querySelectorAll('.listing-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    document.getElementById('mainImg').src = thumb.src;
}

// ── Like toggle ──────────────────────────────────────────
const LIKE_ENDPOINT = 'like_toggle.php';

const likeBtn = document.getElementById('likeBtn');
const likeCount = document.getElementById('likeCount');
const likeLabel = document.querySelector('.like-label');

likeBtn.addEventListener('click', function () {
    const listingId = this.dataset.id;
    const body = new FormData();
    body.append('listing_id', listingId);

    fetch(LIKE_ENDPOINT, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.error) { console.error(data.error); return; }
            this.dataset.liked = data.liked ? '1' : '0';
            this.classList.toggle('liked', data.liked);
            this.querySelector('.like-heart').textContent = data.liked ? '❤️' : '🤍';
            likeCount.textContent = data.count;
            likeLabel.textContent = data.count === 1 ? 'like' : 'likes';
        })
        .catch(err => console.error('Like error:', err));
});

// ── Save toggle ──────────────────────────────────────────
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

const commentInput = document.getElementById('commentInput');
const commentSubmit = document.getElementById('commentSubmit');
const commentsList = document.getElementById('commentsList');
const commentsCount = document.getElementById('commentsCount');
const charCount = document.getElementById('charCount');

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
            item.innerHTML = `
                <img src="${data.avatar}" class="comment-avatar" alt="Avatar">
                <div class="comment-bubble">
                    <div class="comment-meta">
                        <span class="comment-user">${data.first_name} ${data.last_name}</span>
                        <span class="comment-handle">@${data.username}</span>
                        <span class="comment-time">${data.created_at}</span>
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