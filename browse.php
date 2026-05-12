<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: dashboard.php"); exit; }

require_once __DIR__ . '/db.php';
$conn = db_connect();

$category = isset($_GET['cat']) ? trim($_GET['cat']) : 'all';
$search   = isset($_GET['q'])   ? trim($_GET['q']) : '';
$loginId  = $_SESSION['user_id'];

$categoryAliases = [
    'Clothing and Apparel'   => 'Clothing & Apparel',
    'Hobbies and Lifestyle'  => 'Hobbies & Lifestyle',
    'Events and Tickets'     => 'Events & Tickets',
    'Course-Specific (CCJE)' => 'Course-Specific',
    'Course-Specific (COED)' => 'Course-Specific',
    'Course-Specific (COL)'  => 'Course-Specific',
    'Course-Specific (CICS)' => 'Course-Specific',
    'Course-Specific (COS)'  => 'Course-Specific',
    'Course-Specific (CTHM)' => 'Course-Specific',
    'Course-Specific (CBAA)' => 'Course-Specific',
    'Course-Specific (CLAC)' => 'Course-Specific',
    'Course-Specific (CEAT)' => 'Course-Specific',
];

function displayCategoryLabel($category){
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

if(isset($categoryAliases[$category])){
    $category = $categoryAliases[$category];
} elseif(stripos($category, 'Course-Specific') === 0){
    $category = 'Course-Specific';
}

// Fetch current user info for Navbar
$sqlUser = "SELECT FIRST_NAME FROM USERS WHERE USER_ID=?";
$resUser = db_query($conn, $sqlUser, [$loginId]);
$userRow = db_fetch_assoc($resUser);

// Fetch Profile Image for Navbar
$sqlImgNavbar = "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID=?";
$resImgNav = db_query($conn, $sqlImgNavbar, [$loginId]);
$navImgRow = db_fetch_assoc($resImgNav);
$nav_file_path = $navImgRow['FILE_PATH'] ?? 'assets/img/avatar.png';

// BUILD THE MAIN QUERY (treat legacy listings without status as available)
$sql = "SELECT L.*, I.FILE_PATH, U.USER_ID AS SELLER_ID, U.FIRST_NAME, U.LAST_NAME 
        FROM LISTINGS L
        LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
        JOIN USERS U ON L.USER_ID = U.USER_ID
        WHERE (L.`STATUS` = 'Available' OR L.`STATUS` IS NULL)";

$params = [];

if($category !== 'all'){
    if($category == 'Course-Specific'){
        $sql .= " AND L.CATEGORY LIKE 'Course-Specific%'";
    } elseif($category == 'Clothing & Apparel'){
        $sql .= " AND L.CATEGORY IN (?, ?)";
        $params[] = 'Clothing & Apparel';
        $params[] = 'Clothing and Apparel';
    } elseif($category == 'Hobbies & Lifestyle'){
        $sql .= " AND L.CATEGORY IN (?, ?)";
        $params[] = 'Hobbies & Lifestyle';
        $params[] = 'Hobbies and Lifestyle';
    } elseif($category == 'Events & Tickets'){
        $sql .= " AND L.CATEGORY IN (?, ?)";
        $params[] = 'Events & Tickets';
        $params[] = 'Events and Tickets';
    } else {
        $sql .= " AND L.CATEGORY = ?";
        $params[] = $category;
    }
}

// Search filter
if($search !== ''){
    $sql .= " AND (L.TITLE LIKE ? OR L.DESCRIPTION LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$stmt = !empty($params)
        ? db_query($conn, $sql, $params)
        : db_query($conn, $sql);

// Collect all rows into array for count
$items = [];
while($row = db_fetch_assoc($stmt)){
    $items[] = $row;
}

// Category display labels
$catLabels = [
    'all'                => 'All Campus Listings',
    'Academics'          => 'Academics',
    'Electronics and Tech' => 'Electronics & Tech',
    'Clothing & Apparel' => 'Clothing & Apparel',
    'Hobbies & Lifestyle'=> 'Hobbies & Lifestyle',
    'Food'               => 'Food',
    'Events & Tickets'   => 'Events & Tickets',
    'Course-Specific'    => 'Course-Specific',
];
$displayTitle = $catLabels[$category] ?? htmlspecialchars($category);

// Category accent colors (for the top bar tint)
$catColors = [
    'all'                => '#606c38',
    'Academics'          => '#936639',
    'Electronics and Tech' => '#051d40',
    'Clothing & Apparel' => '#5b2a86',
    'Hobbies & Lifestyle'=> '#b44981',
    'Food'               => '#b88917',
    'Events & Tickets'   => '#222518',
    'Course-Specific'    => '#000000',
];
$accentColor = $catColors[$category] ?? '#606c38';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse <?php echo $displayTitle; ?> – Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/browse.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="body">

    <!-- ── NAVBAR ── -->
    <div class="dash-navbar">
        <a href="index.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Logo"></a>
        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo htmlspecialchars($userRow['FIRST_NAME']); ?></span>
            </div>
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($nav_file_path); ?>" class="img-profile" id="profileBtn" alt="Profile">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-mobile-nav">
                        <div class="dropdown-profile-header">
                            <img src="<?php echo htmlspecialchars($nav_file_path); ?>" alt="Profile">
                            <div>
                                <div class="dropdown-profile-name"><?php echo htmlspecialchars($userRow['FIRST_NAME']); ?></div>
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

    <!-- ── BROWSE HEADER ── -->
    <div class="browse-hero" style="--accent: <?php echo $accentColor; ?>">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php" class="browse-breadcrumb-link">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active browse-breadcrumb-active">Browse</li>
                </ol>
            </nav>

            <div class="browse-hero-row">
                <div>
                    <h2 class="browse-title"><?php echo $displayTitle; ?></h2>
                    <p class="browse-subtitle">
                        <?php echo count($items); ?> listing<?php echo count($items)!=1?'s':''; ?> from fellow Lasallians
                        <?php if($search): ?>
                            &nbsp;·&nbsp; Results for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                    </p>
                </div>

                <!-- ── SEARCH BAR ── -->
                <form method="GET" action="browse.php" class="browse-search-form">
                    <input type="hidden" name="cat" value="<?php echo htmlspecialchars($category); ?>">
                    <div class="browse-search-wrap">
                        <span class="browse-search-icon"><i class="bi bi-search"></i></span>
                        <input
                            type="text"
                            name="q"
                            class="browse-search-input"
                            placeholder="Search in <?php echo $displayTitle; ?>…"
                            value="<?php echo htmlspecialchars($search); ?>"
                            autocomplete="off">
                        <?php if($search): ?>
                        <a href="browse.php?cat=<?php echo urlencode($category); ?>" class="browse-search-clear" title="Clear search"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                        <button type="submit" class="browse-search-btn">Search</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── INSTAGRAM-STYLE FEED ── -->
    <div class="container browse-feed-container">

        <?php if(empty($items)): ?>
        <div class="browse-empty">
            <div class="browse-empty-icon"><i class="bi bi-bag"></i></div>
            <h5>No listings found</h5>
            <p class="text-muted">
                <?php echo $search ? 'Try a different search term.' : 'Be the first to list something in this category!'; ?>
            </p>
        </div>
        <?php else: ?>

        <div class="ig-grid">
            <?php foreach($items as $item): ?>
            <?php
                $imgSrc = !empty($item['FILE_PATH']) ? htmlspecialchars($item['FILE_PATH']) : 'assets/img/no_image.png';
                $condClass = 'cond-' . strtolower(str_replace([' ', '-'], '', $item['CONDITION']));
                $sellerName = htmlspecialchars($item['FIRST_NAME'] . ' ' . $item['LAST_NAME']);
                $price = '₱' . number_format($item['PRICE'], 2);
            ?>
            <!-- IG Card -->
            <div class="ig-card" data-id="<?php echo $item['LISTING_ID']; ?>">

                <!-- Image area -->
                <div class="ig-img-wrap">
                    <img src="<?php echo $imgSrc; ?>" class="ig-img" alt="<?php echo htmlspecialchars($item['TITLE']); ?>" loading="lazy">
                    <span class="ig-cat-badge"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                    <div class="ig-overlay">
                        <a href="listing.php?id=<?php echo $item['LISTING_ID']; ?>" class="ig-overlay-btn">View Listing</a>
                    </div>
                </div>

                <!-- Card body -->
                <div class="ig-body">
                    <div class="ig-seller-row">
                        <span class="ig-seller-dot"></span>
                        <a class="ig-seller" href="public_profile.php?id=<?php echo (int)$item['SELLER_ID']; ?>" onclick="event.stopPropagation();"><?php echo $sellerName; ?></a>
                    </div>
                    <p class="ig-title"><?php echo htmlspecialchars($item['TITLE']); ?></p>
                    <div class="ig-footer">
                        <span class="ig-price"><?php echo $price; ?></span>
                        <span class="ig-cond <?php echo $condClass; ?>"><?php echo $item['CONDITION']; ?></span>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- ── LISTING QUICK-VIEW MODAL ── -->
    <div class="modal fade" id="listingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content ig-modal-content">
                <button type="button" class="btn-close ig-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="ig-modal-body" id="modalBody">
                    <div class="text-center p-5 text-muted">Loading…</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if(profileBtn && profileDropdown){
            profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
            document.addEventListener('click', () => profileDropdown.classList.remove('show'));
            profileDropdown.addEventListener('click', e => e.stopPropagation());
        }

        // Card stagger animation on load
        document.querySelectorAll('.ig-card').forEach((card, i) => {
            card.style.animationDelay = (i * 0.06) + 's';
        });

        // Quick-view modal on card click (excluding the "View Listing" button)
        const modal = new bootstrap.Modal(document.getElementById('listingModal'));

        function initializeListingModalInteractions(scope){
            if(!scope) return;

            const reactions = scope.querySelector('.lm-reactions');
            const reactorsPanel = scope.querySelector('[data-reactors-panel]');
            const reactorsTitle = scope.querySelector('[data-reactors-title]');
            const reactorsList = scope.querySelector('[data-reactors-list]');
            const reactorsClose = scope.querySelector('[data-reactors-close]');

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
                if(!reactorsPanel || !reactorsTitle || !reactorsList) return;
                reactorsPanel.classList.add('open');
                reactorsTitle.textContent = 'Reactions';
                reactorsList.innerHTML = '<p class="lm-reactors-empty">Loading...</p>';

                const params = new URLSearchParams({ listing_id: listingId, reaction_type: reactionType });
                fetch('reaction_reactors.php?' + params.toString())
                    .then(r => r.json())
                    .then(data => {
                        if(data.error){
                            reactorsList.innerHTML = `<p class="lm-reactors-empty">${escapeHtml(data.error)}</p>`;
                            return;
                        }
                        reactorsTitle.innerHTML = `${data.emoji || ''} ${escapeHtml(data.label)} reactions`;
                        if(!data.users || data.users.length === 0){
                            reactorsList.innerHTML = '<p class="lm-reactors-empty">No one has picked this reaction yet.</p>';
                            return;
                        }
                        reactorsList.innerHTML = data.users.map(user => `
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
                        reactorsList.innerHTML = '<p class="lm-reactors-empty">Could not load reactions right now.</p>';
                    });
            }

            if(reactorsClose && reactorsPanel && !reactorsClose.dataset.bound){
                reactorsClose.dataset.bound = '1';
                reactorsClose.addEventListener('click', () => reactorsPanel.classList.remove('open'));
            }

            if(reactions && !reactions.dataset.bound){
                reactions.dataset.bound = '1';
                reactions.addEventListener('click', function(event){
                    const reactionBtn = event.target.closest('.lm-reaction-btn');
                    if(!reactionBtn) return;

                    if(event.target.closest('.lm-reaction-count')){
                        event.preventDefault();
                        event.stopPropagation();
                        showReactors(this.dataset.id, reactionBtn.dataset.reaction);
                        return;
                    }

                    const body = new FormData();
                    body.append('listing_id', this.dataset.id);
                    body.append('reaction_type', reactionBtn.dataset.reaction);

                    fetch('like_toggle.php', { method:'POST', body })
                        .then(r => r.json())
                        .then(data => {
                            if(data.error) return;

                            this.querySelectorAll('.lm-reaction-btn').forEach(btn => {
                                const selected = data.reaction === btn.dataset.reaction;
                                btn.classList.toggle('selected', selected);
                                btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
                            });

                            Object.entries(data.counts || {}).forEach(([reaction, count]) => {
                                const countEl = this.querySelector(`[data-count-for="${reaction}"]`);
                                if(countEl) countEl.textContent = count;
                            });
                        });
                });
            }
        }

        document.querySelectorAll('.ig-card').forEach(card => {
            card.addEventListener('click', function(e){
                // Don't intercept clicks on the direct link button
                if(e.target.classList.contains('ig-overlay-btn')) return;
                const id = this.dataset.id;
                if(!id) return;
                const modalBody = document.getElementById('modalBody');
                modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-success" role="status"></div></div>';
                modal.show();
                fetch('listing_modal.php?id=' + id)
                    .then(r => r.text())
                    .then(html => {
                        modalBody.innerHTML = html;
                        initializeListingModalInteractions(modalBody);
                    })
                    .catch(() => { modalBody.innerHTML = '<div class="text-center p-4 text-muted">Could not load listing.</div>'; });
            });
        });
    </script>
</body>
</html>
