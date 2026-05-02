<?php
session_start();
if(!isset($_SESSION['user_id'])){ http_response_code(403); exit; }

$serverName=".\\SQLEXPRESS";
$connectionOptions=["Database"=>"pipeline_db","Uid"=>"","PWD"=>""];
$conn=sqlsrv_connect($serverName,$connectionOptions);

$loginId = (int)$_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$listingId){ echo '<div class="text-center p-4 text-muted">Listing not found.</div>'; exit; }

function normalizeCategoryLabel($category){
    if($category === null){
        return '';
    }

    if(stripos($category, 'Course-Specific') === 0){
        return $category;
    }

    $map = [
        'Clothing and Apparel'  => 'Clothing & Apparel',
        'Hobbies and Lifestyle' => 'Hobbies & Lifestyle',
        'Events and Tickets'    => 'Events & Tickets'
    ];

    return $map[$category] ?? $category;
}

$sqlL = "SELECT L.*, U.FIRST_NAME, U.LAST_NAME, U.USERNAME, U.CYS, U.EMAIL, UI.FILE_PATH AS SELLER_AVATAR
         FROM dbo.[LISTINGS] L
         JOIN dbo.[USERS] U ON L.USER_ID = U.USER_ID
         LEFT JOIN dbo.[USER_IMG] UI ON L.USER_ID = UI.USER_ID
         WHERE L.LISTING_ID=?";
$resL = sqlsrv_query($conn, $sqlL, [$listingId]);
$listing = sqlsrv_fetch_array($resL, SQLSRV_FETCH_ASSOC);

if(!$listing){ echo '<div class="text-center p-4 text-muted">Listing not found.</div>'; exit; }

$resImg = sqlsrv_query(
    $conn,
    "SELECT TOP 1 FILE_PATH FROM dbo.[LISTING_IMG] WHERE LISTING_ID=? ORDER BY IS_PRIMARY DESC",
    [$listingId]
);
$imgRow = sqlsrv_fetch_array($resImg, SQLSRV_FETCH_ASSOC);
$imgSrc = $imgRow['FILE_PATH'] ?? 'assets/img/no_image.png';

$resLikes = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=?", [$listingId]);
$likeRow = sqlsrv_fetch_array($resLikes, SQLSRV_FETCH_ASSOC);
$likeCount = (int)$likeRow['CNT'];

$resMyLike = sqlsrv_query(
    $conn,
    "SELECT LIKE_ID FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=? AND USER_ID=?",
    [$listingId, $loginId]
);
$iLiked = (bool)sqlsrv_fetch_array($resMyLike, SQLSRV_FETCH_ASSOC);

$resC = sqlsrv_query(
    $conn,
    "SELECT TOP 5 C.COMMENT_TEXT, C.CREATED_AT,
            U.USER_ID, U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
            UI.FILE_PATH AS AVATAR
     FROM dbo.[LISTING_COMMENTS] C
     JOIN dbo.[USERS] U ON C.USER_ID = U.USER_ID
     LEFT JOIN dbo.[USER_IMG] UI ON C.USER_ID = UI.USER_ID
     WHERE C.LISTING_ID=?
     ORDER BY C.CREATED_AT DESC",
    [$listingId]
);
$comments = [];
while($cRow = sqlsrv_fetch_array($resC, SQLSRV_FETCH_ASSOC)){
    $cRow['CREATED_AT'] = $cRow['CREATED_AT'] instanceof DateTime
        ? $cRow['CREATED_AT']->format('M d, Y g:i A')
        : date('M d, Y g:i A');
    $comments[] = $cRow;
}
$comments = array_reverse($comments);

$resCnt = sqlsrv_query($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_COMMENTS] WHERE LISTING_ID=?", [$listingId]);
$cntRow = sqlsrv_fetch_array($resCnt, SQLSRV_FETCH_ASSOC);
$totalComments = (int)$cntRow['CNT'];

$condClass = 'cond-' . strtolower(str_replace([' ','-'],'',$listing['CONDITION']));
$sellerName = htmlspecialchars($listing['FIRST_NAME'].' '.$listing['LAST_NAME']);
$categoryLabel = normalizeCategoryLabel($listing['CATEGORY']);
$isOwner = ($loginId === (int)$listing['USER_ID']);
$messageLink = 'mailto:' . rawurlencode($listing['EMAIL']) . '?subject=' . rawurlencode('Pipeline Inquiry: ' . $listing['TITLE']);
$sellerProfileLink = $isOwner ? 'storefront.php' : 'public_profile.php?id=' . (int)$listing['USER_ID'];
$datePosted = $listing['DATE_POSTED'] instanceof DateTime
    ? $listing['DATE_POSTED']->format('M d, Y')
    : date('M d, Y', strtotime($listing['DATE_POSTED']));

sqlsrv_close($conn);
?>
<link rel="stylesheet" href="assets/css/listing_modal.css">

<div class="lm-wrap">
    <div class="lm-img-col">
        <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="lm-img" alt="<?php echo htmlspecialchars($listing['TITLE']); ?>">
        <?php if($listing['STATUS']==='Sold'): ?>
        <div class="lm-sold-badge">SOLD</div>
        <?php endif; ?>
    </div>

    <div class="lm-info-col">
        <div class="lm-seller-row">
            <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="lm-seller-profile-link" aria-label="View seller profile">
                <img src="<?php echo htmlspecialchars($listing['SELLER_AVATAR'] ?? 'assets/img/default_avatar.png'); ?>" class="lm-seller-avatar" alt="Seller">
            </a>
            <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="lm-seller-text-link">
                <div class="lm-seller-name"><?php echo $sellerName; ?></div>
                <div class="lm-seller-handle">@<?php echo htmlspecialchars($listing['USERNAME']); ?></div>
            </a>
            <div class="lm-seller-actions">
                <?php if(!$isOwner): ?>
                <a href="<?php echo htmlspecialchars($messageLink); ?>" class="lm-message-btn">Message</a>
                <button type="button" class="lm-report-btn" id="lmToggleReportBtn">Report Item</button>
                <?php endif; ?>
                <a href="listing.php?id=<?php echo $listing['LISTING_ID']; ?>" class="lm-full-btn">View Full →</a>
            </div>
        </div>

        <div class="lm-badges">
            <span class="lm-cat"><?php echo htmlspecialchars($categoryLabel); ?></span>
            <span class="lm-cond <?php echo $condClass; ?>"><?php echo htmlspecialchars($listing['CONDITION']); ?></span>
            <?php if($listing['STATUS']==='Sold'): ?>
            <span class="lm-sold-pill">SOLD</span>
            <?php endif; ?>
        </div>

        <h4 class="lm-title"><?php echo htmlspecialchars($listing['TITLE']); ?></h4>
        <p class="lm-price">₱<?php echo number_format($listing['PRICE'],2); ?></p>

        <?php if($listing['DESCRIPTION']): ?>
        <p class="lm-desc"><?php echo nl2br(htmlspecialchars(mb_substr($listing['DESCRIPTION'],0,220))); ?><?php echo mb_strlen($listing['DESCRIPTION'])>220?'...':''; ?></p>
        <?php endif; ?>

        <div class="lm-meta">
            <?php if($listing['MEETUP_SPOT']): ?>
            <div class="lm-meta-card">
                <span class="lm-meta-label">📍 Meet-up</span>
                <span class="lm-meta-value"><?php echo htmlspecialchars($listing['MEETUP_SPOT']); ?></span>
            </div>
            <?php endif; ?>
            <?php if($listing['PAYMENT_METHOD']): ?>
            <div class="lm-meta-card">
                <span class="lm-meta-label">💳 Payment</span>
                <span class="lm-meta-value"><?php echo htmlspecialchars($listing['PAYMENT_METHOD']); ?></span>
            </div>
            <?php endif; ?>
            <div class="lm-meta-card">
                <span class="lm-meta-label">🧾 Posted</span>
                <span class="lm-meta-value"><?php echo $datePosted; ?></span>
            </div>
        </div>

        <div class="lm-social">
            <button class="lm-like-btn <?php echo $iLiked?'liked':''; ?>" data-id="<?php echo $listingId; ?>">
                <span class="lm-heart"><?php echo $iLiked?'❤️':'🤍'; ?></span>
                <span class="lm-like-count"><?php echo $likeCount; ?></span>
                <span><?php echo $likeCount===1?'like':'likes'; ?></span>
            </button>
        </div>

        <?php if(!$isOwner): ?>
        <div class="lm-report-panel" id="lmReportPanel" hidden>
            <form id="lmReportForm">
                <div class="lm-report-head">
                    <h3 class="lm-report-title">Report this item</h3>
                    <button type="button" class="lm-report-close" id="lmCloseReportBtn" aria-label="Close report form">×</button>
                </div>
                <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                <label class="lm-report-label" for="lmReportReason">Reason</label>
                <select id="lmReportReason" name="report_reason" class="lm-report-select" required>
                    <option value="" disabled selected>Select a reason</option>
                    <option value="Prohibited Item">Prohibited Item</option>
                    <option value="Misleading Description">Misleading Description</option>
                    <option value="Spam or Duplicate">Spam or Duplicate</option>
                    <option value="Harassment or Abuse">Harassment or Abuse</option>
                    <option value="Suspicious Pricing">Suspicious Pricing</option>
                    <option value="Other">Other</option>
                </select>
                <label class="lm-report-label" for="lmReportDetails">Details</label>
                <textarea id="lmReportDetails" name="report_details" class="lm-report-textarea" maxlength="1000" placeholder="Tell the admin what is wrong with this listing." required></textarea>
                <div class="lm-report-actions">
                    <button type="button" class="lm-report-cancel" id="lmCancelReportBtn">Cancel</button>
                    <button type="submit" class="lm-report-submit" id="lmSubmitReportBtn">Submit Report</button>
                </div>
                <div class="lm-report-feedback" id="lmReportFeedback" hidden></div>
            </form>
        </div>
        <?php endif; ?>

        <div class="lm-comments-preview">
            <div class="lm-comments-label">
                Comments
                <?php if($totalComments > 5): ?>
                <a href="listing.php?id=<?php echo $listingId; ?>" class="lm-see-all">See all <?php echo $totalComments; ?></a>
                <?php endif; ?>
            </div>

            <?php if(empty($comments)): ?>
            <p class="lm-no-comments">No comments yet.</p>
            <?php else: ?>
            <div class="lm-comments-list">
                <?php foreach($comments as $c): ?>
                <div class="lm-comment">
                    <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="lm-comment-profile-link">
                        <img src="<?php echo htmlspecialchars($c['AVATAR'] ?? 'assets/img/default_avatar.png'); ?>" class="lm-comment-avatar" alt="Avatar">
                    </a>
                    <div class="lm-comment-body">
                        <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="lm-comment-user"><?php echo htmlspecialchars($c['FIRST_NAME'].' '.$c['LAST_NAME']); ?></a>
                        <span class="lm-comment-text"><?php echo htmlspecialchars($c['COMMENT_TEXT']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){
    const btn = document.querySelector('.lm-like-btn');
    if(btn){
        btn.addEventListener('click', function(){
            const id = this.dataset.id;
            const body = new FormData();
            body.append('listing_id', id);
            fetch('like_toggle.php', { method:'POST', body })
                .then(r => r.json())
                .then(data => {
                    if(data.error) return;
                    this.classList.toggle('liked', data.liked);
                    this.querySelector('.lm-heart').textContent = data.liked ? '❤️' : '🤍';
                    this.querySelector('.lm-like-count').textContent = data.count;
                });
        });
    }

    const panel = document.getElementById('lmReportPanel');
    const toggleBtn = document.getElementById('lmToggleReportBtn');
    const cancelBtn = document.getElementById('lmCancelReportBtn');
    const closeBtn = document.getElementById('lmCloseReportBtn');
    const form = document.getElementById('lmReportForm');
    const feedback = document.getElementById('lmReportFeedback');
    const submitBtn = document.getElementById('lmSubmitReportBtn');

    function showFeedback(message, isError){
        if(!feedback){
            return;
        }

        feedback.hidden = false;
        feedback.textContent = message;
        feedback.className = 'lm-report-feedback ' + (isError ? 'is-error' : 'is-success');
    }

    if(toggleBtn && panel){
        toggleBtn.addEventListener('click', function(){
            panel.hidden = false;
        });
    }

    if(cancelBtn && panel){
        cancelBtn.addEventListener('click', function(){
            panel.hidden = true;
        });
    }

    if(closeBtn && panel){
        closeBtn.addEventListener('click', function(){
            panel.hidden = true;
        });
    }

    if(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();

            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            feedback.hidden = true;

            const body = new FormData(form);
            body.append('ajax', '1');

            fetch('report_item.php', { method:'POST', body })
                .then(r => r.json())
                .then(data => {
                    if(data.error){
                        showFeedback(data.error, true);
                        return;
                    }

                    showFeedback(data.message || 'Report submitted successfully.', false);
                    form.reset();
                })
                .catch(() => {
                    showFeedback('Could not submit your report right now. Please try again.', true);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Report';
                });
        });
    }
})();
</script>
