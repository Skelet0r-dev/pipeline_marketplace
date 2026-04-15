<?php
// ============================================================
// listing_modal.php  –  Pipeline Quick-View Modal Fragment
// Called via AJAX fetch() from browse.php
// Returns HTML fragment only (no full page structure)
// ============================================================
session_start();
if(!isset($_SESSION['user_id'])){ http_response_code(403); exit; }

$serverName=".\SQLEXPRESS";
$connectionOptions=["Database"=>"pipeline_db","Uid"=>"","PWD"=>""];
$conn=sqlsrv_connect($serverName,$connectionOptions);

$loginId   = (int)$_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$listingId){ echo '<div class="text-center p-4 text-muted">Listing not found.</div>'; exit; }

// ── Fetch listing + seller info ─────────────────────────────
$sqlL = "SELECT L.*,
                U.FIRST_NAME, U.LAST_NAME, U.USERNAME, U.CYS,
                UI.FILE_PATH AS SELLER_AVATAR
         FROM dbo.[LISTINGS] L
         JOIN dbo.[USERS] U ON L.USER_ID = U.USER_ID
         LEFT JOIN dbo.[USER_IMG] UI ON L.USER_ID = UI.USER_ID
         WHERE L.LISTING_ID=?";
$resL   = sqlsrv_query($conn, $sqlL, [$listingId]);
$listing = sqlsrv_fetch_array($resL, SQLSRV_FETCH_ASSOC);

if(!$listing){ echo '<div class="text-center p-4 text-muted">Listing not found.</div>'; exit; }

// ── Primary image ───────────────────────────────────────────
$resImg = sqlsrv_query($conn,
    "SELECT TOP 1 FILE_PATH FROM dbo.[LISTING_IMG] WHERE LISTING_ID=? ORDER BY IS_PRIMARY DESC",
    [$listingId]);
$imgRow  = sqlsrv_fetch_array($resImg, SQLSRV_FETCH_ASSOC);
$imgSrc  = $imgRow['FILE_PATH'] ?? 'assets/img/no_image.png';

// ── Like status & count ─────────────────────────────────────
$resLikes = sqlsrv_query($conn,
    "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=?", [$listingId]);
$likeRow  = sqlsrv_fetch_array($resLikes, SQLSRV_FETCH_ASSOC);
$likeCount = (int)$likeRow['CNT'];

$resMyLike = sqlsrv_query($conn,
    "SELECT LIKE_ID FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=? AND USER_ID=?",
    [$listingId, $loginId]);
$iLiked = (bool)sqlsrv_fetch_array($resMyLike, SQLSRV_FETCH_ASSOC);

// ── Comments (latest 5 shown in modal) ─────────────────────
$resC = sqlsrv_query($conn,
    "SELECT TOP 5 C.COMMENT_TEXT, C.CREATED_AT,
            U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
            UI.FILE_PATH AS AVATAR
     FROM dbo.[LISTING_COMMENTS] C
     JOIN dbo.[USERS] U ON C.USER_ID = U.USER_ID
     LEFT JOIN dbo.[USER_IMG] UI ON C.USER_ID = UI.USER_ID
     WHERE C.LISTING_ID=?
     ORDER BY C.CREATED_AT DESC",
    [$listingId]);
$comments = [];
while($cRow = sqlsrv_fetch_array($resC, SQLSRV_FETCH_ASSOC)){
    $cRow['CREATED_AT'] = $cRow['CREATED_AT'] instanceof DateTime
        ? $cRow['CREATED_AT']->format('M d, Y g:i A')
        : date('M d, Y g:i A');
    $comments[] = $cRow;
}
$comments = array_reverse($comments); // oldest first

// ── Total comment count ─────────────────────────────────────
$resCnt = sqlsrv_query($conn,
    "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_COMMENTS] WHERE LISTING_ID=?", [$listingId]);
$cntRow = sqlsrv_fetch_array($resCnt, SQLSRV_FETCH_ASSOC);
$totalComments = (int)$cntRow['CNT'];

$condClass  = 'cond-' . strtolower(str_replace([' ','-'],'',$listing['CONDITION']));
$sellerName = htmlspecialchars($listing['FIRST_NAME'].' '.$listing['LAST_NAME']);
$datePosted = $listing['DATE_POSTED'] instanceof DateTime
    ? $listing['DATE_POSTED']->format('M d, Y')
    : date('M d, Y', strtotime($listing['DATE_POSTED']));

sqlsrv_close($conn);
?>

<!-- ── Modal: two-column layout ── -->
<div class="lm-wrap">

    <!-- Left: Image -->
    <div class="lm-img-col">
        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
             class="lm-img"
             alt="<?php echo htmlspecialchars($listing['TITLE']); ?>">
        <?php if($listing['STATUS']==='Sold'): ?>
        <div class="lm-sold-badge">SOLD</div>
        <?php endif; ?>
    </div>

    <!-- Right: Info + social -->
    <div class="lm-info-col">

        <!-- Seller -->
        <div class="lm-seller-row">
            <img src="<?php echo htmlspecialchars($listing['SELLER_AVATAR'] ?? 'assets/img/default_avatar.png'); ?>"
                 class="lm-seller-avatar" alt="Seller">
            <div>
                <div class="lm-seller-name"><?php echo $sellerName; ?></div>
                <div class="lm-seller-handle">@<?php echo htmlspecialchars($listing['USERNAME']); ?></div>
            </div>
            <a href="listing.php?id=<?php echo $listing['LISTING_ID']; ?>" class="lm-full-btn">View Full →</a>
        </div>

        <!-- Badges -->
        <div class="lm-badges">
            <span class="lm-cat"><?php echo htmlspecialchars($listing['CATEGORY']); ?></span>
            <span class="lm-cond <?php echo $condClass; ?>"><?php echo htmlspecialchars($listing['CONDITION']); ?></span>
            <?php if($listing['STATUS']==='Sold'): ?>
            <span class="lm-sold-pill">SOLD</span>
            <?php endif; ?>
        </div>

        <!-- Title & price -->
        <h4 class="lm-title"><?php echo htmlspecialchars($listing['TITLE']); ?></h4>
        <p class="lm-price">₱<?php echo number_format($listing['PRICE'],2); ?></p>

        <!-- Description -->
        <?php if($listing['DESCRIPTION']): ?>
        <p class="lm-desc"><?php echo nl2br(htmlspecialchars(mb_substr($listing['DESCRIPTION'],0,220))); ?><?php echo mb_strlen($listing['DESCRIPTION'])>220?'…':''; ?></p>
        <?php endif; ?>

        <!-- Meta -->
        <div class="lm-meta">
            <?php if($listing['MEETUP_SPOT']): ?>
            <span class="lm-meta-item">📍 <?php echo htmlspecialchars($listing['MEETUP_SPOT']); ?></span>
            <?php endif; ?>
            <?php if($listing['PAYMENT_METHOD']): ?>
            <span class="lm-meta-item">💳 <?php echo htmlspecialchars($listing['PAYMENT_METHOD']); ?></span>
            <?php endif; ?>
            <span class="lm-meta-item">📅 <?php echo $datePosted; ?></span>
        </div>

        <!-- Like -->
        <div class="lm-social">
            <button class="lm-like-btn <?php echo $iLiked?'liked':''; ?>"
                    data-id="<?php echo $listingId; ?>">
                <span class="lm-heart"><?php echo $iLiked?'❤️':'🤍'; ?></span>
                <span class="lm-like-count"><?php echo $likeCount; ?></span>
                <span><?php echo $likeCount===1?'like':'likes'; ?></span>
            </button>
        </div>

        <!-- Comments preview -->
        <div class="lm-comments-preview">
            <div class="lm-comments-label">
                💬 Comments
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
                    <img src="<?php echo htmlspecialchars($c['AVATAR'] ?? 'assets/img/default_avatar.png'); ?>"
                         class="lm-comment-avatar" alt="Avatar">
                    <div class="lm-comment-body">
                        <span class="lm-comment-user"><?php echo htmlspecialchars($c['FIRST_NAME'].' '.$c['LAST_NAME']); ?></span>
                        <span class="lm-comment-text"><?php echo htmlspecialchars($c['COMMENT_TEXT']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /lm-info-col -->
</div>

<!-- Inline styles scoped to modal fragment -->
<style>
.lm-wrap{display:flex;min-height:360px;}
.lm-img-col{position:relative;flex:0 0 42%;background:#f4f1e8;overflow:hidden;}
.lm-img{width:100%;height:100%;object-fit:cover;display:block;}
.lm-sold-badge{position:absolute;top:14px;right:14px;background:#c0392b;color:#fff;font-size:11px;font-weight:800;border-radius:8px;padding:5px 14px;letter-spacing:1px;}
.lm-info-col{flex:1;padding:24px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;max-height:540px;}
.lm-seller-row{display:flex;align-items:center;gap:10px;}
.lm-seller-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #dde5b6;flex-shrink:0;}
.lm-seller-name{font-size:14px;font-weight:700;color:#283618;}
.lm-seller-handle{font-size:11px;color:#606c38;font-weight:600;}
.lm-full-btn{margin-left:auto;background:#283618;color:#fefae0;text-decoration:none;border-radius:30px;padding:7px 16px;font-size:12px;font-weight:700;white-space:nowrap;transition:background 0.15s;}
.lm-full-btn:hover{background:#606c38;color:#fefae0;}
.lm-badges{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.lm-cat{background:rgba(40,54,24,.10);color:#283618;font-size:10px;font-weight:700;border-radius:20px;padding:3px 10px;}
.lm-cond{font-size:10px;font-weight:700;border-radius:20px;padding:3px 10px;}
.cond-new{background:#e8f4fd;color:#0a5fa8;}
.cond-likenew{background:#e6f4ea;color:#1e6b2e;}
.cond-used{background:#fff8e1;color:#a07000;}
.lm-sold-pill{background:#fde8e6;color:#c0392b;font-size:10px;font-weight:800;border-radius:20px;padding:3px 10px;}
.lm-title{font-size:20px;font-weight:800;color:#283618;margin:0;line-height:1.25;}
.lm-price{font-size:22px;font-weight:800;color:#606c38;margin:0;}
.lm-desc{font-size:13px;color:#666;line-height:1.6;margin:0;}
.lm-meta{display:flex;flex-wrap:wrap;gap:8px;}
.lm-meta-item{font-size:12px;color:#888;font-weight:500;}
/* Like btn */
.lm-like-btn{display:flex;align-items:center;gap:7px;background:#f7f9f3;border:2px solid #e0e8d0;border-radius:30px;padding:8px 18px;font-family:"DM Sans",sans-serif;font-size:14px;font-weight:700;color:#606c38;cursor:pointer;transition:background 0.18s,border-color 0.18s,transform 0.15s;}
.lm-like-btn:hover{background:#eef3e5;transform:scale(1.04);}
.lm-like-btn.liked{background:#fff0f0;border-color:#e87373;color:#c0392b;}
.lm-heart{font-size:18px;transition:transform 0.2s cubic-bezier(.34,1.56,.64,1);}
.lm-like-btn:active .lm-heart{transform:scale(1.4);}
.lm-like-count{font-size:16px;font-weight:800;}
/* Comments */
.lm-comments-preview{border-top:1px solid #f0ede3;padding-top:12px;}
.lm-comments-label{font-size:13px;font-weight:700;color:#283618;margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.lm-see-all{font-size:12px;color:#606c38;text-decoration:none;font-weight:600;margin-left:auto;}
.lm-see-all:hover{text-decoration:underline;}
.lm-no-comments{font-size:13px;color:#bbb;margin:0;}
.lm-comments-list{display:flex;flex-direction:column;gap:10px;}
.lm-comment{display:flex;align-items:flex-start;gap:8px;}
.lm-comment-avatar{width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid #dde5b6;flex-shrink:0;}
.lm-comment-body{background:#f7f9f3;border-radius:0 10px 10px 10px;padding:8px 12px;flex:1;font-size:12px;min-width:0;}
.lm-comment-user{font-weight:700;color:#283618;margin-right:6px;}
.lm-comment-text{color:#555;}
</style>

<script>
// Like toggle in modal (same endpoint as listing.php)
(function(){
    const btn = document.querySelector('.lm-like-btn');
    if(!btn) return;
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
})();
</script>