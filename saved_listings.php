<?php
// ============================================================
// saved_listings.php  –  Pipeline User Bookmarks
// Shows all items the current user has bookmarked
// ============================================================
session_start();

require_once __DIR__ . '/db.php';
$conn = db_connect();
if ($conn == false) die(db_last_error());

// Check if logged in (Mirroring dashboard.php logic)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); // Or wherever your login is
    exit;
}

$loginId = $_SESSION['user_id'];

// Fetch user name for navbar
$sqlUser = "SELECT FIRST_NAME FROM USERS WHERE USER_ID = ?";
$resUser = db_query($conn, $sqlUser, [$loginId]);
$me = db_fetch_assoc($resUser);
$firstname = $me['FIRST_NAME'] ?? 'User';

// Fetch user profile pic
$sqlProfile = "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID = ?";
$resProfile = db_query($conn, $sqlProfile, [$loginId]);
$rowProfile = db_fetch_assoc($resProfile);
$navFilePath = $rowProfile ? $rowProfile['FILE_PATH'] : 'assets/img/avatar.png';

// Fetch saved items
$savedItems = [];
$sqlSaved = "SELECT L.*, I.FILE_PATH, U.USER_ID AS SELLER_ID, U.FIRST_NAME, U.LAST_NAME 
            FROM LISTING_SAVED S
            JOIN LISTINGS L ON S.LISTING_ID = L.LISTING_ID
            LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
            JOIN USERS U ON L.USER_ID = U.USER_ID
            WHERE S.USER_ID = ?
            ORDER BY S.CREATED_AT DESC";

$stmtSaved = db_query($conn, $sqlSaved, [$loginId]);
if ($stmtSaved) {
    while($row = db_fetch_assoc($stmtSaved)){
        $savedItems[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Listings – Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700;9..40,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .saved-header {
            padding: 40px 4% 20px;
            max-width: 1700px;
            margin: 0 auto;
        }
        .saved-title {
            font-size: 32px;
            font-weight: 800;
            color: #283618;
            margin-bottom: 8px;
        }
        .saved-subtitle {
            color: #666;
            font-size: 16px;
        }
        .empty-saved {
            text-align: center;
            padding: 100px 20px;
            background: #fff;
            border-radius: 20px;
            border: 2px dashed #dde5b6;
        }
        .status-available { background: #166534 !important; }
        .status-sold { background: #991b1b !important; }
    </style>
</head>
<body class="body">

    <!-- Navbar -->
    <div class="dash-navbar">
        <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Logo"></a>
        
        <div class="dash-nav-links">
            <a href="dashboard.php" class="dash-nav-link">Browse Products</a>
            <a href="storefront.php" class="dash-nav-link">My Storefront</a>
            <a href="edit_profile.php" class="dash-nav-link">My Profile</a>
            <a href="saved_listings.php" class="dash-nav-link active" title="Saved Listings">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-bookmark-star-fill" viewBox="0 0 16 16" style="vertical-align: middle; margin-top: -3px;">
                    <path fill-rule="evenodd" d="M2 15.5V2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.74.439L8 13.069l-5.26 2.87A.5.5 0 0 1 2 15.5M8.16 4.1a.178.178 0 0 0-.32 0l-.634 1.285a.18.18 0 0 1-.134.098l-1.42.206a.178.178 0 0 0-.098.303L6.58 6.993c.042.041.061.1.051.158L6.39 8.565a.178.178 0 0 0 .258.187l1.27-.668a.18.18 0 0 1 .165 0l1.27.668a.178.178 0 0 0 .257-.187L9.368 7.15a.18.18 0 0 1 .05-.158l1.028-1.001a.178.178 0 0 0-.098-.303l-1.42-.206a.18.18 0 0 1-.134-.098z"/>
                </svg>
            </a>
        </div>

        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo htmlspecialchars($firstname); ?></span>
            </div>
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($navFilePath); ?>" class="img-profile" id="profileBtn" alt="Profile">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-profile-header">
                        <img src="<?php echo htmlspecialchars($navFilePath); ?>" alt="Profile">
                        <span class="dropdown-profile-name"><?php echo htmlspecialchars($firstname); ?></span>
                    </div>
                    <a href="dashboard.php"  class="dropdown-item-custom"><span class="item-icon">🏬</span> Browse Products</a>
                    <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon">🏪</span> My Storefront</a>
                    <a href="edit_profile.php" class="dropdown-item-custom"><span class="item-icon">👤</span> My Profile</a>
                    <div class="dropdown-divider-custom"></div>
                    <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon">🚪</span> Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-header-bar"></div>

    <div class="saved-header">
        <h1 class="saved-title">🔖 Your Bookmarks</h1>
        <p class="saved-subtitle">Items you've saved for later. Only you can see this list.</p>
    </div>

    <div class="container" style="max-width: 1700px; padding: 20px 4% 60px;">
        
        <?php if (empty($savedItems)): ?>
            <div class="empty-saved">
                <div style="font-size: 48px; margin-bottom: 16px;">📑</div>
                <h3>No saved listings yet</h3>
                <p>Browse products and click the save button to add them here.</p>
                <a href="dashboard.php" class="btn btn-success mt-3" style="background:#087832; border:none; border-radius:30px; padding:10px 24px;">Start Browsing</a>
            </div>
        <?php else: ?>
            <div class="dash-listings-grid">
                <?php foreach ($savedItems as $item): 
                    $price = number_format($item['PRICE'], 2);
                    $img = !empty($item['FILE_PATH']) ? $item['FILE_PATH'] : 'assets/img/no_image.png';
                    $condClass = 'cond-' . strtolower(str_replace([' ', '-'], '', $item['CONDITION']));
                ?>
                    <div class="dash-listing-card" onclick="window.location.href='listing.php?id=<?php echo $item['LISTING_ID']; ?>'">
                        <div class="dash-listing-img-wrap">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="dash-listing-img" alt="Item">
                            <span class="dash-listing-badge"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                            <?php 
                                $status = $item['STATUS'] ?? 'Available';
                                $statusClass = ($status === 'Sold') ? 'status-sold' : 'status-available';
                            ?>
                            <span class="dash-listing-status-badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>
                        <div class="dash-listing-body">
                            <div class="dash-listing-seller">@<?php echo htmlspecialchars($item['USERNAME'] ?? 'user'); ?></div>
                            <h3 class="dash-listing-title"><?php echo htmlspecialchars($item['TITLE']); ?></h3>
                            <div class="dash-listing-footer">
                                <span class="dash-listing-price">₱<?php echo $price; ?></span>
                                <span class="dash-listing-cond <?php echo $condClass; ?>"><?php echo htmlspecialchars($item['CONDITION']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
        const profileBtn = document.getElementById('profileBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileBtn) {
            profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
            document.addEventListener('click', () => profileDropdown.classList.remove('show'));
        }
    </script>
</body>
</html>
<?php db_close($conn); ?>
