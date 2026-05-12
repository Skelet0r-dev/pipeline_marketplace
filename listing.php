<?php
// ============================================================
// listing.php  –  Pipeline Full Listing View
// Shows photo, seller info, item details, likes & comments
// ============================================================
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: dashboard.php"); exit; }

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/listing_reactions.php';
$conn = db_connect();
listing_reactions_ensure_schema($conn);

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
        'Events and Tickets'    => 'Events & Tickets',
        'Books'                 => 'Academics',
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
$resMe = db_query($conn,
    "SELECT FIRST_NAME FROM USERS WHERE USER_ID=?", [$loginId]);
$me = db_fetch_assoc($resMe);

$resNavImg = db_query($conn,
    "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID=?", [$loginId]);
$navImg = db_fetch_assoc($resNavImg);
$navFilePath = $navImg['FILE_PATH'] ?? 'assets/img/avatar.png';

    
// ── Fetch listing + seller ──────────────────────────────────
$sqlListing = "SELECT L.*,
                      U.FIRST_NAME, U.LAST_NAME, U.USERNAME, U.COLLEGE, U.DEPARTMENT,U.SECTION, U.EMAIL,
                      UI.FILE_PATH AS SELLER_AVATAR
               FROM LISTINGS L
               JOIN USERS U    ON L.USER_ID = U.USER_ID
               LEFT JOIN USER_IMG UI ON L.USER_ID = UI.USER_ID
               WHERE L.LISTING_ID=?";
$resListing = db_query($conn, $sqlListing, [$listingId]);
$listing    = db_fetch_assoc($resListing);

if(!$listing){ header("Location: dashboard.php"); exit; }

// ── Fetch all listing images ────────────────────────────────
$resImgs = db_query($conn,
    "SELECT FILE_PATH, IS_PRIMARY FROM LISTING_IMG WHERE LISTING_ID=? ORDER BY IS_PRIMARY DESC",
    [$listingId]);
$images = [];
while($imgRow = db_fetch_assoc($resImgs)){
    $images[] = $imgRow;
}
if(empty($images)) $images[] = ['FILE_PATH'=>'assets/img/no_image.png','IS_PRIMARY'=>1];

// ── Like status & count ────────────────────────────────────
$reactionOptions = listing_reaction_options();
$reactionCounts = listing_reaction_counts($conn, $listingId);
$myReactionRow = listing_user_reaction($conn, $listingId, $loginId);
$myReaction = $myReactionRow['REACTION_TYPE'] ?? null;

$resMySave = db_query($conn,
    "SELECT SAVE_ID FROM LISTING_SAVED WHERE LISTING_ID=? AND USER_ID=?",
    [$listingId, $loginId]);
$iSaved = (bool)db_fetch_assoc($resMySave);

// ── Fetch comments ─────────────────────────────────────────
$resComments = db_query($conn,
    "SELECT C.COMMENT_ID, C.COMMENT_TEXT, C.CREATED_AT,
            U.USER_ID, U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
            UI.FILE_PATH AS AVATAR
     FROM LISTING_COMMENTS C
     JOIN USERS U ON C.USER_ID = U.USER_ID
     LEFT JOIN USER_IMG UI ON C.USER_ID = UI.USER_ID
     WHERE C.LISTING_ID=?
     ORDER BY C.CREATED_AT ASC",
    [$listingId]);
$comments = [];
while($cRow = db_fetch_assoc($resComments)){
    $cRow['CREATED_AT'] = $cRow['CREATED_AT'] instanceof DateTime
        ? $cRow['CREATED_AT']->format('M d, Y g:i A')
        : date('M d, Y g:i A', strtotime((string)$cRow['CREATED_AT']));
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
$sellerProfileLink = $isOwner ? 'storefront.php' : 'public_profile.php?id=' . (int)$listing['USER_ID'];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="body">

<!-- ── NAVBAR ── -->
<div class="dash-navbar">
    <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Logo"></a>
    
    <!-- Center Nav Links -->
    <div class="dash-nav-links">
        <a href="dashboard.php" class="dash-nav-link">Browse Products</a>
        <a href="storefront.php" class="dash-nav-link">My Storefront</a>
        <a href="edit_profile.php" class="dash-nav-link">My Profile</a>
        <a href="saved_listings.php" class="dash-nav-link" title="Saved Listings">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-bookmark-star-fill" viewBox="0 0 16 16" style="vertical-align: middle; margin-top: -3px;">
                <path fill-rule="evenodd" d="M2 15.5V2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.74.439L8 13.069l-5.26 2.87A.5.5 0 0 1 2 15.5M8.16 4.1a.178.178 0 0 0-.32 0l-.634 1.285a.18.18 0 0 1-.134.098l-1.42.206a.178.178 0 0 0-.098.303L6.58 6.993c.042.041.061.1.051.158L6.39 8.565a.178.178 0 0 0 .258.187l1.27-.668a.18.18 0 0 1 .165 0l1.27.668a.178.178 0 0 0 .257-.187L9.368 7.15a.18.18 0 0 1 .05-.158l1.028-1.001a.178.178 0 0 0-.098-.303l-1.42-.206a.18.18 0 0 1-.134-.098z"/>
            </svg>
        </a>

        <!-- Notification Bell moved next to Bookmark -->
        <a href="notifications.php" class="dash-nav-link" id="navNotifLink" title="Notifications">
            <div style="position:relative; display:inline-block;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-bell-fill" viewBox="0 0 16 16" style="vertical-align: middle; margin-top: -3px;">
                    <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"/>
                </svg>
                <span id="navNotifBadge" style="display:none; position:absolute; top:-5px; right:-5px; background:#ef4444; color:white; font-size:9px; font-weight:800; width:16px; height:16px; border-radius:50%; text-align:center; line-height:16px; border:1.5px solid #fff;">0</span>
            </div>
        </a>
    </div>
    <div class="dash-nav-right">
        <div class="dash-greeting">
            <span class="dash-hello">Hello,</span>
            <span class="dash-name"><?php echo htmlspecialchars($me['FIRST_NAME']); ?></span>
        </div>
        <div class="profile-wrapper">
            <img src="<?php echo htmlspecialchars($navFilePath); ?>" class="img-profile" id="profileBtn" alt="Profile">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-mobile-nav">
                    <div class="dropdown-profile-header">
                        <img src="<?php echo htmlspecialchars($navFilePath); ?>" alt="Profile">
                        <div>
                            <div class="dropdown-profile-name"><?php echo htmlspecialchars($me['FIRST_NAME']); ?></div>
                            <div style="font-size:11px; color:rgba(255,255,255,0.6);">DLSU-D Student</div>
                        </div>
                    </div>
                    <a href="dashboard.php" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-bag"></i></span> Browse Products</a>
                    <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-shop"></i></span> My Storefront</a>
                    <a href="edit_profile.php" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-person"></i></span> My Profile</a>
                    <a href="saved_listings.php" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-bookmark-fill"></i></span> Saved Listings</a>
                    <a href="notifications.php" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-bell"></i></span> Notifications</a>
                    <div class="dropdown-divider-custom"></div>
                </div>
                <a href="edit_profile.php?tab=support" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-heart-fill" style="color: #22c55e;"></i></span> Support Us</a>
                <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon"><i class="bi bi-box-arrow-right"></i></span> Log Out</a>
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
                <li class="breadcrumb-item active lbc-active"><?php echo htmlspecialchars($listing['TITLE']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="container listing-container" data-listing-id="<?php echo $listingId; ?>">
    <div class="listing-card">

        <!-- LEFT: Photo gallery -->
        <div class="listing-gallery">
            <div class="listing-main-img-wrap">
                <img src="<?php echo htmlspecialchars($images[0]['FILE_PATH']); ?>"
                     class="listing-main-img" id="mainImg" onclick="openLightbox(this.src)" style="cursor:zoom-in;"
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
                <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="listing-seller-avatar-link" aria-label="View seller profile">
                    <img src="<?php echo htmlspecialchars(!empty($listing['SELLER_AVATAR']) ? $listing['SELLER_AVATAR'] : 'assets/img/avatar.png'); ?>"
                         class="listing-seller-avatar" alt="Seller">
                </a>
                <div class="listing-seller-info">
                    <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="listing-seller-name"><?php echo $sellerName; ?></a>
                    <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="listing-seller-handle">@<?php echo htmlspecialchars($listing['USERNAME']); ?></a>
                    <span class="listing-seller-cys"><?php echo htmlspecialchars($listing['DEPARTMENT'] . ' - ' . $listing['SECTION']); ?></span>
                </div>
                <?php if(!$isOwner): ?>
                <div class="listing-action-group">
                    <a href="<?php echo htmlspecialchars($messageLink); ?>"
                       class="listing-contact-btn">Message</a>
                    <button type="button" class="listing-report-btn" id="toggleReportBtn">Report Item</button>
                </div>
                <?php endif; ?>
            </div>

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
                    <span class="listing-info-label"><i class="bi bi-geo-alt"></i> Meet-up</span>
                    <span class="listing-info-val"><?php echo htmlspecialchars($listing['MEETUP_SPOT'] ?? '—'); ?></span>
                </div>
                <div class="listing-info-item">
                    <span class="listing-info-label"><i class="bi bi-credit-card"></i> Payment</span>
                    <span class="listing-info-val"><?php echo htmlspecialchars($listing['PAYMENT_METHOD'] ?? '—'); ?></span>
                </div>
                <div class="listing-info-item">
                    <span class="listing-info-label"><i class="bi bi-calendar"></i> Posted</span>
                    <span class="listing-info-val"><?php echo $datePosted; ?></span>
                </div>
            </div>

            <!-- ── LIKE & SAVE BUTTONS ── -->
            <div class="listing-social-row">
                <div class="listing-reactions" id="reactionGroup" data-id="<?php echo $listingId; ?>">
                    <?php foreach($reactionOptions as $reactionKey => $reaction): ?>
                    <?php $isSelectedReaction = $myReaction === $reactionKey; ?>
                    <button type="button"
                            class="listing-reaction-btn <?php echo $isSelectedReaction?'selected':''; ?>"
                            data-reaction="<?php echo htmlspecialchars($reactionKey); ?>"
                            aria-pressed="<?php echo $isSelectedReaction?'true':'false'; ?>"
                            title="<?php echo htmlspecialchars($reaction['label']); ?>">
                        <span class="reaction-emoji"><?php echo $reaction['emoji']; ?></span>
                        <span class="reaction-count" data-count-for="<?php echo htmlspecialchars($reactionKey); ?>"><?php echo (int)$reactionCounts['types'][$reactionKey]; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>

                <button class="listing-save-btn <?php echo $iSaved?'saved':''; ?>"
                        id="saveBtn"
                        data-id="<?php echo $listingId; ?>"
                        data-saved="<?php echo $iSaved?'1':'0'; ?>">
                    <span class="save-icon"><?php echo $iSaved?'<i class="bi bi-bookmark-fill"></i>':'<i class="bi bi-file-earmark"></i>'; ?></span>
                    <span class="save-label"><?php echo $iSaved?'Saved':'Save for Later'; ?></span>
                </button>
            </div>

        </div><!-- /listing-details -->
    </div><!-- /listing-card -->

    <!-- ── COMMENTS SECTION ── -->
    <div class="listing-comments-section">
        <h3 class="comments-heading">
            <i class="bi bi-chat"></i> Comments
            <span class="comments-count" id="commentsCount"><?php echo count($comments); ?></span>
        </h3>

        <!-- Comment list -->
        <div class="comments-list" id="commentsList">
            <?php if(empty($comments)): ?>
            <p class="comments-empty" id="commentsEmpty">No comments yet. Be the first to ask!</p>
            <?php else: ?>
            <?php foreach($comments as $c): ?>
            <div class="comment-item" data-comment-id="<?php echo (int)$c['COMMENT_ID']; ?>">
                <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="comment-avatar-link">
                    <img src="<?php echo htmlspecialchars(!empty($c['AVATAR']) ? $c['AVATAR'] : 'assets/img/avatar.png'); ?>"
                         class="comment-avatar" alt="Avatar">
                </a>
                <div class="comment-bubble">
                    <div class="comment-meta">
                        <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="comment-user"><?php echo htmlspecialchars($c['FIRST_NAME'].' '.$c['LAST_NAME']); ?></a>
                        <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="comment-handle">@<?php echo htmlspecialchars($c['USERNAME']); ?></a>
                        <span class="comment-time"><?php echo htmlspecialchars($c['CREATED_AT']); ?></span>
                        <span class="comment-actions">
                            <?php if((int)$c['USER_ID'] === $loginId): ?>
                            <button type="button" class="comment-action-btn comment-delete-btn" data-comment-id="<?php echo (int)$c['COMMENT_ID']; ?>">Delete</button>
                            <?php else: ?>
                            <button type="button" class="comment-action-btn comment-report-btn" data-comment-id="<?php echo (int)$c['COMMENT_ID']; ?>">Report</button>
                            <?php endif; ?>
                        </span>
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

<?php if(!$isOwner): ?>
<!-- ── REPORT MODAL ── -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">Report this item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="listing-report-copy">Choose a reason and add details. The report will be saved for admin review.</p>
                <form id="reportForm">
                    <input type="hidden" name="listing_id" id="listing_id" value="<?php echo $listingId; ?>">
                    <label class="listing-report-label" for="reportReason">Reason</label>
                    <select id="reportReason" name="report_reason" class="listing-report-select form-select mb-3" required>
                        <option value="" disabled selected>Select a reason</option>
                        <option value="Prohibited Item">Prohibited Item</option>
                        <option value="Misleading Description">Misleading Description</option>
                        <option value="Spam or Duplicate">Spam or Duplicate</option>
                        <option value="Harassment or Abuse">Harassment or Abuse</option>
                        <option value="Suspicious Pricing">Suspicious Pricing</option>
                        <option value="Other">Other</option>
                    </select>
                    <label class="listing-report-label" for="reportDetails">Details</label>
                    <textarea id="reportDetails" name="report_details" class="listing-report-textarea form-control mb-3" rows="4" maxlength="1000" placeholder="Tell the admin what is wrong with this listing." required></textarea>
                    <div class="listing-report-feedback" id="reportFeedback" hidden></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="listing-report-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="listing-report-submit" id="submitReportBtn">Submit Report</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- COMMENT REPORT MODAL -->
<div class="modal fade" id="commentReportModal" tabindex="-1" aria-labelledby="commentReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentReportModalLabel">Report this comment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="listing-report-copy">Choose a reason and add details. The report will be saved for admin review.</p>
                <form id="commentReportForm">
                    <input type="hidden" name="comment_id" id="commentReportId" value="">
                    <label class="listing-report-label" for="commentReportReason">Reason</label>
                    <select id="commentReportReason" name="report_reason" class="listing-report-select form-select mb-3" required>
                        <option value="" disabled selected>Select a reason</option>
                        <option value="Harassment or Abuse">Harassment or Abuse</option>
                        <option value="Spam">Spam</option>
                        <option value="Offensive Language">Offensive Language</option>
                        <option value="Misleading Information">Misleading Information</option>
                        <option value="Other">Other</option>
                    </select>
                    <label class="listing-report-label" for="commentReportDetails">Details</label>
                    <textarea id="commentReportDetails" name="report_details" class="listing-report-textarea form-control mb-3" rows="4" maxlength="1000" placeholder="Tell the admin what is wrong with this comment." required></textarea>
                    <div class="listing-report-feedback" id="commentReportFeedback" hidden></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="listing-report-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="listing-report-submit" id="submitCommentReportBtn">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/listing.js"></script>


<!-- ── LIGHTBOX ── -->
<div class="listing-lightbox" id="listingLightbox" onclick="closeLightbox()">
    <button class="listing-lightbox-close" onclick="closeLightbox()"><i class="bi bi-x"></i></button>
    <img src="" id="lightboxImg" alt="Full view" onclick="event.stopPropagation()">
</div>
<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('listingLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('listingLightbox').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

// Real-time Notification Polling
let shownNotifIds = new Set();
function checkNotifications() {
    fetch('fetch_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('navNotifBadge');
                const count = data.notifications.length;
                if (count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = count;
                } else {
                    badge.style.display = 'none';
                }

                data.notifications.forEach(notif => {
                    if (!shownNotifIds.has(notif.id)) {
                        shownNotifIds.add(notif.id);
                        if ("Notification" in window && Notification.permission === "granted") {
                            new Notification(notif.sender, { body: notif.message, icon: notif.avatar });
                        }
                        showNotificationToast(notif);
                    }
                });
            }
        });
}

function showNotificationToast(notif) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed; top:24px; right:24px; width:320px; background:white; border-left:5px solid #087832; box-shadow:0 15px 35px rgba(0,0,0,0.15); border-radius:12px; padding:16px; display:flex; gap:12px; z-index:10001; font-family:'DM Sans', sans-serif; transition: all 0.5s ease;`;
    toast.innerHTML = `<img src="${notif.avatar}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:700;color:#087832;font-size:14px;">${notif.sender}</div><div style="color:#666;font-size:13px;">${notif.message}</div></div>`;
    document.body.appendChild(toast);
    
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(50px)';
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 10);

    setTimeout(() => { 
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        setTimeout(() => toast.remove(), 500);
    }, 6000);
}

if ("Notification" in window && Notification.permission !== "denied" && Notification.permission !== "granted") {
    Notification.requestPermission();
}
checkNotifications();
setInterval(checkNotifications, 10000);
</script>
</body>
</html>

<?php db_close($conn); ?>
