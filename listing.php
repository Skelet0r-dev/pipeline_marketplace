<?php
// ============================================================
// listing.php  –  Pipeline Full Listing View
// Shows photo, seller info, item details, likes & comments
// ============================================================
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: dashboard.php"); exit; }

require_once __DIR__ . '/db.php';
$conn = db_connect();

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
$resMe = db_query($conn,
    "SELECT FIRST_NAME FROM USERS WHERE USER_ID=?", [$loginId]);
$me = db_fetch_assoc($resMe);

$resNavImg = db_query($conn,
    "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID=?", [$loginId]);
$navImg = db_fetch_assoc($resNavImg);
$navFilePath = $navImg['FILE_PATH'] ?? 'assets/img/default_avatar.png';

// ── Fetch listing + seller ──────────────────────────────────
$sqlListing = "SELECT L.*,
                      U.FIRST_NAME, U.LAST_NAME, U.USERNAME, U.CYS, U.EMAIL,
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
$resLikes = db_query($conn,
    "SELECT COUNT(*) AS CNT FROM LISTING_LIKES WHERE LISTING_ID=?", [$listingId]);
$likeRow  = db_fetch_assoc($resLikes);
$likeCount = (int)$likeRow['CNT'];

$resMyLike = db_query($conn,
    "SELECT LIKE_ID FROM LISTING_LIKES WHERE LISTING_ID=? AND USER_ID=?",
    [$listingId, $loginId]);
$iLiked = (bool)db_fetch_assoc($resMyLike);

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
                <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="listing-seller-avatar-link" aria-label="View seller profile">
                    <img src="<?php echo htmlspecialchars($listing['SELLER_AVATAR'] ?? 'assets/img/default_avatar.png'); ?>"
                         class="listing-seller-avatar" alt="Seller">
                </a>
                <div class="listing-seller-info">
                    <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="listing-seller-name"><?php echo $sellerName; ?></a>
                    <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="listing-seller-handle">@<?php echo htmlspecialchars($listing['USERNAME']); ?></a>
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
                <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="comment-avatar-link">
                    <img src="<?php echo htmlspecialchars($c['AVATAR'] ?? 'assets/img/default_avatar.png'); ?>"
                         class="comment-avatar" alt="Avatar">
                </a>
                <div class="comment-bubble">
                    <div class="comment-meta">
                        <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="comment-user"><?php echo htmlspecialchars($c['FIRST_NAME'].' '.$c['LAST_NAME']); ?></a>
                        <a href="public_profile.php?id=<?php echo (int)$c['USER_ID']; ?>" class="comment-handle">@<?php echo htmlspecialchars($c['USERNAME']); ?></a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/listing.js"></script>
</body>
</html>
<?php db_close($conn); ?>