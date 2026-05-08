<?php
session_start();
if(!isset($_SESSION['user_id'])){ http_response_code(403); exit; }

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/listing_reactions.php';
$conn = db_connect();
listing_reactions_ensure_schema($conn);

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
         FROM LISTINGS L
         JOIN USERS U ON L.USER_ID = U.USER_ID
         LEFT JOIN USER_IMG UI ON L.USER_ID = UI.USER_ID
         WHERE L.LISTING_ID=?";
$resL = db_query($conn, $sqlL, [$listingId]);
$listing = db_fetch_assoc($resL);

if(!$listing){ echo '<div class="text-center p-4 text-muted">Listing not found.</div>'; exit; }

$resImg = db_query(
    $conn,
    "SELECT FILE_PATH FROM LISTING_IMG WHERE LISTING_ID=? ORDER BY IS_PRIMARY DESC LIMIT 1",
    [$listingId]
);
$imgRow = db_fetch_assoc($resImg);
$imgSrc = $imgRow['FILE_PATH'] ?? 'assets/img/no_image.png';

$reactionOptions = listing_reaction_options();
$reactionCounts = listing_reaction_counts($conn, $listingId);
$myReactionRow = listing_user_reaction($conn, $listingId, $loginId);
$myReaction = $myReactionRow['REACTION_TYPE'] ?? null;

$resC = db_query(
    $conn,
    "SELECT C.COMMENT_TEXT, C.CREATED_AT,
            U.USER_ID, U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
            UI.FILE_PATH AS AVATAR
     FROM LISTING_COMMENTS C
     JOIN USERS U ON C.USER_ID = U.USER_ID
     LEFT JOIN USER_IMG UI ON C.USER_ID = UI.USER_ID
     WHERE C.LISTING_ID=?
     ORDER BY C.CREATED_AT DESC
     LIMIT 5",
    [$listingId]
);
$comments = [];
while($cRow = db_fetch_assoc($resC)){
    $cRow['CREATED_AT'] = $cRow['CREATED_AT'] instanceof DateTime
        ? $cRow['CREATED_AT']->format('M d, Y g:i A')
        : date('M d, Y g:i A', strtotime((string)$cRow['CREATED_AT']));
    $comments[] = $cRow;
}
$comments = array_reverse($comments);

$resCnt = db_query($conn, "SELECT COUNT(*) AS CNT FROM LISTING_COMMENTS WHERE LISTING_ID=?", [$listingId]);
$cntRow = db_fetch_assoc($resCnt);
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

db_close($conn);
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
                <img src="<?php echo htmlspecialchars($listing['SELLER_AVATAR'] ?? 'assets/img/avatar.png'); ?>" class="lm-seller-avatar" alt="Seller">
            </a>
            <a href="<?php echo htmlspecialchars($sellerProfileLink); ?>" class="lm-seller-text-link">
                <div class="lm-seller-name"><?php echo $sellerName; ?></div>
                <div class="lm-seller-handle">@<?php echo htmlspecialchars($listing['USERNAME']); ?></div>
            </a>
            <div class="lm-seller-actions">
                <?php if(!$isOwner): ?>
                <a href="<?php echo htmlspecialchars($messageLink); ?>" class="lm-message-btn">Message</a>
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
            <div class="lm-reactions" data-id="<?php echo $listingId; ?>">
                <?php foreach($reactionOptions as $reactionKey => $reaction): ?>
                <?php $isSelectedReaction = $myReaction === $reactionKey; ?>
                <button type="button"
                        class="lm-reaction-btn <?php echo $isSelectedReaction?'selected':''; ?>"
                        data-reaction="<?php echo htmlspecialchars($reactionKey); ?>"
                        aria-pressed="<?php echo $isSelectedReaction?'true':'false'; ?>"
                        title="<?php echo htmlspecialchars($reaction['label']); ?>">
                    <span class="lm-reaction-emoji"><?php echo $reaction['emoji']; ?></span>
                    <span class="lm-reaction-count" data-count-for="<?php echo htmlspecialchars($reactionKey); ?>"><?php echo (int)$reactionCounts['types'][$reactionKey]; ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="lm-reactors-panel" data-reactors-panel>
                <div class="lm-reactors-head">
                    <p class="lm-reactors-title" data-reactors-title>Reactions</p>
                    <button type="button" class="lm-reactors-close" data-reactors-close aria-label="Close">&times;</button>
                </div>
                <div class="lm-reactors-list" data-reactors-list></div>
            </div>
        </div>

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
                        <img src="<?php echo htmlspecialchars($c['AVATAR'] ?? 'assets/img/avatar.png'); ?>" class="lm-comment-avatar" alt="Avatar">
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
    const reactions = document.querySelector('.lm-reactions');
    if(!reactions) return;
    const panel = document.querySelector('[data-reactors-panel]');
    const title = document.querySelector('[data-reactors-title]');
    const list = document.querySelector('[data-reactors-list]');
    const closeBtn = document.querySelector('[data-reactors-close]');

    function escapeHtml(value){
        return String(value || '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function showReactors(listingId, reactionType){
        if(!panel || !title || !list) return;
        panel.classList.add('open');
        title.textContent = 'Reactions';
        list.innerHTML = '<p class="lm-reactors-empty">Loading...</p>';

        const params = new URLSearchParams({ listing_id: listingId, reaction_type: reactionType });
        fetch('reaction_reactors.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                if(data.error){
                    list.innerHTML = `<p class="lm-reactors-empty">${escapeHtml(data.error)}</p>`;
                    return;
                }
                title.innerHTML = `${data.emoji || ''} ${escapeHtml(data.label)} reactions`;
                if(!data.users || data.users.length === 0){
                    list.innerHTML = '<p class="lm-reactors-empty">No one has picked this reaction yet.</p>';
                    return;
                }
                list.innerHTML = data.users.map(user => `
                    <a class="lm-reactor-row" href="public_profile.php?id=${encodeURIComponent(user.user_id)}">
                        <img src="${escapeHtml(user.avatar)}" class="lm-reactor-avatar" alt="">
                        <span class="lm-reactor-main">
                            <span class="lm-reactor-name">${escapeHtml(user.name || user.username)}</span>
                            <span class="lm-reactor-handle">@${escapeHtml(user.username)}</span>
                        </span>
                        <span class="lm-reactor-time">${escapeHtml(user.created_at)}</span>
                    </a>
                `).join('');
            })
            .catch(() => {
                list.innerHTML = '<p class="lm-reactors-empty">Could not load reactions right now.</p>';
            });
    }

    if(closeBtn && panel){
        closeBtn.addEventListener('click', () => panel.classList.remove('open'));
    }

    reactions.addEventListener('click', function(event){
        const btn = event.target.closest('.lm-reaction-btn');
        if(!btn) return;

        if(event.target.closest('.lm-reaction-count')){
            event.preventDefault();
            event.stopPropagation();
            showReactors(this.dataset.id, btn.dataset.reaction);
            return;
        }

        const body = new FormData();
        body.append('listing_id', this.dataset.id);
        body.append('reaction_type', btn.dataset.reaction);

        fetch('like_toggle.php', { method:'POST', body })
            .then(r => r.json())
            .then(data => {
                if(data.error) return;

                this.querySelectorAll('.lm-reaction-btn').forEach(reactionBtn => {
                    const selected = data.reaction === reactionBtn.dataset.reaction;
                    reactionBtn.classList.toggle('selected', selected);
                    reactionBtn.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });

                Object.entries(data.counts || {}).forEach(([reaction, count]) => {
                    const countEl = this.querySelector(`[data-count-for="${reaction}"]`);
                    if(countEl) countEl.textContent = count;
                });
            });
    });
})();
</script>
