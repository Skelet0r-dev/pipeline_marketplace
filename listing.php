<?php
// ============================================================
// listing.php  –  Pipeline Full Listing View
// Shows photo, seller info, item details, likes & comments
// ============================================================
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: dashboard.php"); exit; }

$serverName=".\SQLEXPRESS";
$connectionOptions=["Database"=>"pipeline_db","Uid"=>"","PWD"=>""];
$conn=sqlsrv_connect($serverName,$connectionOptions);

$loginId   = (int)$_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function normalizeCategoryBrowseParam($category){
    if($category === null){
        return 'all';
    }

    if(stripos($category, 'Course-Specific') === 0){
        return 'Course-Specific';
    }

    $map = [
        'Clothing and Apparel'  => 'Clothing & Apparel',
        'Hobbies and Lifestyle' => 'Hobbies & Lifestyle',
        'Events and Tickets'    => 'Events & Tickets'
    ];

    return $map[$category] ?? $category;
}

function displayCategoryLabel($category){
    return normalizeCategoryBrowseParam($category) === 'Course-Specific'
        ? $category
        : normalizeCategoryBrowseParam($category);
}

if(!$listingId){ header("Location: dashboard.php"); exit; }

// ── Fetch current user for navbar ──────────────────────────
$resMe = sqlsrv_query($conn,
    "SELECT FIRST_NAME FROM dbo.[USERS] WHERE USER_ID=?", [$loginId]);
$me = sqlsrv_fetch_array($resMe, SQLSRV_FETCH_ASSOC);

$resNavImg = sqlsrv_query($conn,
    "SELECT FILE_PATH FROM dbo.[USER_IMG] WHERE USER_ID=?", [$loginId]);
$navImg = sqlsrv_fetch_array($resNavImg, SQLSRV_FETCH_ASSOC);
$navFilePath = $navImg['FILE_PATH'] ?? 'assets/img/default_avatar.png';

// ── Fetch listing + seller ──────────────────────────────────
$sqlListing = "SELECT L.*,
                      U.FIRST_NAME, U.LAST_NAME, U.USERNAME, U.CYS, U.EMAIL,
                      UI.FILE_PATH AS SELLER_AVATAR
               FROM dbo.[LISTINGS] L
               JOIN dbo.[USERS] U    ON L.USER_ID = U.USER_ID
               LEFT JOIN dbo.[USER_IMG] UI ON L.USER_ID = UI.USER_ID
               WHERE L.LISTING_ID=?";
$resListing = sqlsrv_query($conn, $sqlListing, [$listingId]);
$listing    = sqlsrv_fetch_array($resListing, SQLSRV_FETCH_ASSOC);

if(!$listing){ header("Location: dashboard.php"); exit; }

// ── Fetch all listing images ────────────────────────────────
$resImgs = sqlsrv_query($conn,
    "SELECT FILE_PATH, IS_PRIMARY FROM dbo.[LISTING_IMG] WHERE LISTING_ID=? ORDER BY IS_PRIMARY DESC",
    [$listingId]);
$images = [];
while($imgRow = sqlsrv_fetch_array($resImgs, SQLSRV_FETCH_ASSOC)){
    $images[] = $imgRow;
}
if(empty($images)) $images[] = ['FILE_PATH'=>'assets/img/no_image.png','IS_PRIMARY'=>1];

// ── Like status & count ────────────────────────────────────
$resLikes = sqlsrv_query($conn,
    "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=?", [$listingId]);
$likeRow  = sqlsrv_fetch_array($resLikes, SQLSRV_FETCH_ASSOC);
$likeCount = (int)$likeRow['CNT'];

$resMyLike = sqlsrv_query($conn,
    "SELECT LIKE_ID FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=? AND USER_ID=?",
    [$listingId, $loginId]);
$iLiked = (bool)sqlsrv_fetch_array($resMyLike, SQLSRV_FETCH_ASSOC);

// ── Fetch comments ─────────────────────────────────────────
$resComments = sqlsrv_query($conn,
    "SELECT C.COMMENT_ID, C.COMMENT_TEXT, C.CREATED_AT,
            U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
            UI.FILE_PATH AS AVATAR
     FROM dbo.[LISTING_COMMENTS] C
     JOIN dbo.[USERS] U ON C.USER_ID = U.USER_ID
     LEFT JOIN dbo.[USER_IMG] UI ON C.USER_ID = UI.USER_ID
     WHERE C.LISTING_ID=?
     ORDER BY C.CREATED_AT ASC",
    [$listingId]);
$comments = [];
while($cRow = sqlsrv_fetch_array($resComments, SQLSRV_FETCH_ASSOC)){
    $cRow['CREATED_AT'] = $cRow['CREATED_AT'] instanceof DateTime
        ? $cRow['CREATED_AT']->format('M d, Y g:i A')
        : date('M d, Y g:i A');
    $comments[] = $cRow;
}

// ── Misc formatting ─────────────────────────────────────────
$datePosted = $listing['DATE_POSTED'] instanceof DateTime
    ? $listing['DATE_POSTED']->format('M d, Y')
    : date('M d, Y', strtotime($listing['DATE_POSTED']));

$condClass = 'cond-' . strtolower(str_replace([' ','-'],'',$listing['CONDITION']));
$sellerName = htmlspecialchars($listing['FIRST_NAME'].' '.$listing['LAST_NAME']);
$isOwner = ($loginId == (int)$listing['USER_ID']);
$browseCategory = normalizeCategoryBrowseParam($listing['CATEGORY']);
$categoryLabel = displayCategoryLabel($listing['CATEGORY']);
$messageLink = 'mailto:' . rawurlencode($listing['EMAIL']) . '?subject=' . rawurlencode('Pipeline Inquiry: ' . $listing['TITLE']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($listing['TITLE']); ?> – Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/listing.css">
</head>
<body class="body">

<!-- ── NAVBAR ── -->
<div class="dash-navbar">
    <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Logo">
    <div class="dash-nav-right">
        <div class="dash-greeting">
            <span class="dash-hello">Hello,</span>
            <span class="dash-name"><?php echo htmlspecialchars($me['FIRST_NAME']); ?></span>
        </div>
        <div class="profile-wrapper">
            <img src="<?php echo htmlspecialchars($navFilePath); ?>" class="img-profile" id="profileBtn" alt="Profile">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-profile-header">
                    <img src="<?php echo htmlspecialchars($navFilePath); ?>" alt="Profile">
                    <span class="dropdown-profile-name"><?php echo htmlspecialchars($me['FIRST_NAME']); ?></span>
                </div>
                <a href="dashboard.php"  class="dropdown-item-custom"><span class="item-icon">🏬</span> Browse Products</a>
                <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon">🏪</span> My Storefront</a>
                <a href="edit_profile.php" class="dropdown-item-custom"><span class="item-icon">👤</span> My Profile</a>
                <a href="purchases.php"  class="dropdown-item-custom"><span class="item-icon">🛍️</span> Purchases</a>
                <a href="settings.php"   class="dropdown-item-custom"><span class="item-icon">⚙️</span> Settings</a>
                <div class="dropdown-divider-custom"></div>
                <a href="logout.php"     class="dropdown-item-custom logout"><span class="item-icon">🚪</span> Log Out</a>
            </div>
        </div>
    </div>
</div>
<div class="dash-header-bar"></div>

<!-- ── BREADCRUMB ── -->
<div class="listing-breadcrumb-bar">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php" class="lbc-link">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="browse.php?cat=<?php echo urlencode($browseCategory); ?>" class="lbc-link"><?php echo htmlspecialchars($categoryLabel); ?></a></li>
                <li class="breadcrumb-item active lbc-active"><?php echo htmlspecialchars($listing['TITLE']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="container listing-container">
    <div class="listing-card">

        <!-- LEFT: Photo gallery -->
        <div class="listing-gallery">
            <div class="listing-main-img-wrap">
                <img src="<?php echo htmlspecialchars($images[0]['FILE_PATH']); ?>"
                     class="listing-main-img" id="mainImg"
                     alt="<?php echo htmlspecialchars($listing['TITLE']); ?>">
                <?php if($listing['STATUS']==='Sold'): ?>
                <div class="listing-sold-ribbon">SOLD</div>
                <?php endif; ?>
            </div>

            <?php if(count($images) > 1): ?>
            <div class="listing-thumbs">
                <?php foreach($images as $idx => $img): ?>
                <img src="<?php echo htmlspecialchars($img['FILE_PATH']); ?>"
                     class="listing-thumb <?php echo $idx===0?'active':''; ?>"
                     onclick="switchImg(this)"
                     alt="Thumbnail">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Details + Social -->
        <div class="listing-details">

            <!-- Seller card -->
            <div class="listing-seller-card">
                <img src="<?php echo htmlspecialchars($listing['SELLER_AVATAR'] ?? 'assets/img/default_avatar.png'); ?>"
                     class="listing-seller-avatar" alt="Seller">
                <div class="listing-seller-info">
                    <span class="listing-seller-name"><?php echo $sellerName; ?></span>
                    <span class="listing-seller-handle">@<?php echo htmlspecialchars($listing['USERNAME']); ?></span>
                    <span class="listing-seller-cys"><?php echo htmlspecialchars($listing['CYS']); ?></span>
                </div>
                <?php if(!$isOwner): ?>
                <div class="listing-action-group">
                    <a href="<?php echo htmlspecialchars($messageLink); ?>"
                       class="listing-contact-btn">Message</a>
                    <button type="button" class="listing-report-btn" id="toggleReportBtn">Report Item</button>
                </div>
                <?php endif; ?>
            </div>

            <?php if(!$isOwner): ?>
            <div class="listing-report-panel" id="reportPanel" hidden>
                <div class="listing-report-head">
                    <h3 class="listing-report-title">Report this item</h3>
                    <button type="button" class="listing-report-close" id="closeReportBtn" aria-label="Close report form">×</button>
                </div>
                <p class="listing-report-copy">Choose a reason and add details. The report will be saved for the admin review side.</p>
                <form id="reportForm" class="listing-report-form">
                    <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                    <label class="listing-report-label" for="reportReason">Reason</label>
                    <select id="reportReason" name="report_reason" class="listing-report-select" required>
                        <option value="" disabled selected>Select a reason</option>
                        <option value="Prohibited Item">Prohibited Item</option>
                        <option value="Misleading Description">Misleading Description</option>
                        <option value="Spam or Duplicate">Spam or Duplicate</option>
                        <option value="Harassment or Abuse">Harassment or Abuse</option>
                        <option value="Suspicious Pricing">Suspicious Pricing</option>
                        <option value="Other">Other</option>
                    </select>
                    <label class="listing-report-label" for="reportDetails">Details</label>
                    <textarea id="reportDetails" name="report_details" class="listing-report-textarea" rows="4" maxlength="1000" placeholder="Tell the admin what is wrong with this listing." required></textarea>
                    <div class="listing-report-actions">
                        <button type="button" class="listing-report-cancel" id="cancelReportBtn">Cancel</button>
                        <button type="submit" class="listing-report-submit" id="submitReportBtn">Submit Report</button>
                    </div>
                    <div class="listing-report-feedback" id="reportFeedback" hidden></div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Category & Condition -->
            <div class="listing-meta-row">
                <span class="listing-cat-badge"><?php echo htmlspecialchars($categoryLabel); ?></span>
                <span class="listing-cond <?php echo $condClass; ?>"><?php echo htmlspecialchars($listing['CONDITION']); ?></span>
                <?php if($listing['STATUS']==='Sold'): ?>
                <span class="listing-status-sold">SOLD</span>
                <?php endif; ?>
            </div>

            <!-- Title & Price -->
            <h1 class="listing-title"><?php echo htmlspecialchars($listing['TITLE']); ?></h1>
            <p class="listing-price">₱<?php echo number_format($listing['PRICE'],2); ?></p>

            <!-- Description -->
            <?php if($listing['DESCRIPTION']): ?>
            <p class="listing-desc"><?php echo nl2br(htmlspecialchars($listing['DESCRIPTION'])); ?></p>
            <?php endif; ?>

            <!-- Info grid -->
            <div class="listing-info-grid">
                <div class="listing-info-item">
                    <span class="listing-info-label">📍 Meet-up</span>
                    <span class="listing-info-val"><?php echo htmlspecialchars($listing['MEETUP_SPOT'] ?? '—'); ?></span>
                </div>
                <div class="listing-info-item">
                    <span class="listing-info-label">💳 Payment</span>
                    <span class="listing-info-val"><?php echo htmlspecialchars($listing['PAYMENT_METHOD'] ?? '—'); ?></span>
                </div>
                <div class="listing-info-item">
                    <span class="listing-info-label">📅 Posted</span>
                    <span class="listing-info-val"><?php echo $datePosted; ?></span>
                </div>
            </div>

            <!-- ── LIKE BUTTON ── -->
            <div class="listing-social-row">
                <button class="listing-like-btn <?php echo $iLiked?'liked':''; ?>"
                        id="likeBtn"
                        data-id="<?php echo $listingId; ?>"
                        data-liked="<?php echo $iLiked?'1':'0'; ?>">
                    <span class="like-heart"><?php echo $iLiked?'❤️':'🤍'; ?></span>
                    <span class="like-count" id="likeCount"><?php echo $likeCount; ?></span>
                    <span class="like-label"><?php echo $likeCount===1?'like':'likes'; ?></span>
                </button>
            </div>

        </div><!-- /listing-details -->
    </div><!-- /listing-card -->

    <!-- ── COMMENTS SECTION ── -->
    <div class="listing-comments-section">
        <h3 class="comments-heading">
            💬 Comments
            <span class="comments-count" id="commentsCount"><?php echo count($comments); ?></span>
        </h3>

        <!-- Comment list -->
        <div class="comments-list" id="commentsList">
            <?php if(empty($comments)): ?>
            <p class="comments-empty" id="commentsEmpty">No comments yet. Be the first to ask!</p>
            <?php else: ?>
            <?php foreach($comments as $c): ?>
            <div class="comment-item">
                <img src="<?php echo htmlspecialchars($c['AVATAR'] ?? 'assets/img/default_avatar.png'); ?>"
                     class="comment-avatar" alt="Avatar">
                <div class="comment-bubble">
                    <div class="comment-meta">
                        <span class="comment-user"><?php echo htmlspecialchars($c['FIRST_NAME'].' '.$c['LAST_NAME']); ?></span>
                        <span class="comment-handle">@<?php echo htmlspecialchars($c['USERNAME']); ?></span>
                        <span class="comment-time"><?php echo htmlspecialchars($c['CREATED_AT']); ?></span>
                    </div>
                    <p class="comment-text"><?php echo nl2br(htmlspecialchars($c['COMMENT_TEXT'])); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Comment form -->
        <div class="comment-form-wrap">
            <img src="<?php echo htmlspecialchars($navFilePath); ?>" class="comment-avatar" alt="You">
            <div class="comment-input-wrap">
                <textarea id="commentInput" class="comment-input"
                          placeholder="Ask about this item…" rows="1"
                          maxlength="1000"></textarea>
                <button class="comment-submit-btn" id="commentSubmit">Post</button>
            </div>
        </div>
        <p class="comment-char-count"><span id="charCount">0</span>/1000</p>
    </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Profile dropdown ─────────────────────────────────────
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');
if(profileBtn){
    profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
    document.addEventListener('click', () => profileDropdown.classList.remove('show'));
    profileDropdown.addEventListener('click', e => e.stopPropagation());
}

// ── Image gallery ────────────────────────────────────────
function switchImg(thumb){
    document.querySelectorAll('.listing-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    document.getElementById('mainImg').src = thumb.src;
}

// ── Like toggle ──────────────────────────────────────────
// Designed so the fetch URL can be replaced with an API endpoint
const LIKE_ENDPOINT = 'like_toggle.php'; // ← swap to API URL when ready

const likeBtn   = document.getElementById('likeBtn');
const likeCount = document.getElementById('likeCount');
const likeLabel = document.querySelector('.like-label');

likeBtn.addEventListener('click', function(){
    const listingId = this.dataset.id;
    const body = new FormData();
    body.append('listing_id', listingId);

    fetch(LIKE_ENDPOINT, { method:'POST', body })
        .then(r => r.json())
        .then(data => {
            if(data.error){ console.error(data.error); return; }
            this.dataset.liked = data.liked ? '1' : '0';
            this.classList.toggle('liked', data.liked);
            this.querySelector('.like-heart').textContent = data.liked ? '❤️' : '🤍';
            likeCount.textContent = data.count;
            likeLabel.textContent = data.count === 1 ? 'like' : 'likes';
        })
        .catch(err => console.error('Like error:', err));
});

// ── Comment submit ───────────────────────────────────────
// Designed so the fetch URL can be replaced with an API endpoint
const COMMENT_ENDPOINT = 'comment_post.php'; // ← swap to API URL when ready
const REPORT_ENDPOINT = 'report_item.php';

const commentInput  = document.getElementById('commentInput');
const commentSubmit = document.getElementById('commentSubmit');
const commentsList  = document.getElementById('commentsList');
const commentsCount = document.getElementById('commentsCount');
const charCount     = document.getElementById('charCount');
const listingId     = <?php echo $listingId; ?>;
const reportPanel   = document.getElementById('reportPanel');
const reportForm    = document.getElementById('reportForm');
const reportFeedback = document.getElementById('reportFeedback');
const toggleReportBtn = document.getElementById('toggleReportBtn');
const closeReportBtn = document.getElementById('closeReportBtn');
const cancelReportBtn = document.getElementById('cancelReportBtn');
const submitReportBtn = document.getElementById('submitReportBtn');

function setReportPanelVisibility(isVisible){
    if(!reportPanel){
        return;
    }

    reportPanel.hidden = !isVisible;
    if(isVisible){
        reportPanel.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }
}

function showReportFeedback(message, isError){
    if(!reportFeedback){
        return;
    }

    reportFeedback.hidden = false;
    reportFeedback.textContent = message;
    reportFeedback.className = 'listing-report-feedback ' + (isError ? 'is-error' : 'is-success');
}

if(toggleReportBtn){
    toggleReportBtn.addEventListener('click', function(){
        setReportPanelVisibility(true);
    });
}

if(closeReportBtn){
    closeReportBtn.addEventListener('click', function(){
        setReportPanelVisibility(false);
    });
}

if(cancelReportBtn){
    cancelReportBtn.addEventListener('click', function(){
        setReportPanelVisibility(false);
    });
}

if(reportForm){
    reportForm.addEventListener('submit', function(e){
        e.preventDefault();

        submitReportBtn.disabled = true;
        submitReportBtn.textContent = 'Submitting...';
        reportFeedback.hidden = true;

        const body = new FormData(reportForm);
        body.append('ajax', '1');

        fetch(REPORT_ENDPOINT, { method:'POST', body })
            .then(r => r.json())
            .then(data => {
                if(data.error){
                    showReportFeedback(data.error, true);
                    return;
                }

                showReportFeedback(data.message || 'Report submitted successfully.', false);
                reportForm.reset();
            })
            .catch(() => {
                showReportFeedback('Could not submit your report right now. Please try again.', true);
            })
            .finally(() => {
                submitReportBtn.disabled = false;
                submitReportBtn.textContent = 'Submit Report';
            });
    });
}

// Character counter
commentInput.addEventListener('input', function(){
    charCount.textContent = this.value.length;
    // Auto-resize
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Submit on button click or Ctrl+Enter
commentInput.addEventListener('keydown', function(e){
    if(e.key==='Enter' && (e.ctrlKey || e.metaKey)) submitComment();
});
commentSubmit.addEventListener('click', submitComment);

function submitComment(){
    const text = commentInput.value.trim();
    if(!text) return;

    commentSubmit.disabled = true;
    commentSubmit.textContent = 'Posting…';

    const body = new FormData();
    body.append('listing_id',   listingId);
    body.append('comment_text', text);

    fetch(COMMENT_ENDPOINT, { method:'POST', body })
        .then(r => r.json())
        .then(data => {
            if(data.error){ alert('Error: ' + data.error); return; }

            // Remove "no comments" placeholder
            const empty = document.getElementById('commentsEmpty');
            if(empty) empty.remove();

            // Build new comment element
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
                    <p class="comment-text">${data.comment_text.replace(/\n/g,'<br>')}</p>
                </div>`;
            commentsList.appendChild(item);

            // Scroll to new comment
            item.scrollIntoView({ behavior:'smooth', block:'nearest' });

            // Update count
            const newCount = parseInt(commentsCount.textContent||'0') + 1;
            commentsCount.textContent = newCount;

            // Reset input
            commentInput.value = '';
            commentInput.style.height = 'auto';
            charCount.textContent = '0';
        })
        .catch(err => console.error('Comment error:', err))
        .finally(() => {
            commentSubmit.disabled = false;
            commentSubmit.textContent = 'Post';
        });
}
</script>
</body>
</html>
<?php sqlsrv_close($conn); ?>
