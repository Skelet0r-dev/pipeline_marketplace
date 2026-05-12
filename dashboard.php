<?php
session_start();

require_once __DIR__ . '/db.php';
$conn = db_connect();
if($conn==false)
    die(db_last_error());

$MAX_ATTEMPTS=3;
$COOLDOWN_SEC=60;
$error='';
$locked=false;

if(!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts']=0;
if(!isset($_SESSION['locked_until']))   $_SESSION['locked_until']=0;

// Check if locked
if($_SESSION['locked_until'] > time()){
    $locked=true;
    $remaining=$_SESSION['locked_until'] - time();
    $error='Too many failed attempts. Please wait '.$remaining.' second(s) before trying again.';
} else {
    if($_SESSION['locked_until'] > 0){
        $_SESSION['login_attempts']=0;
        $_SESSION['locked_until']=0;
    }
}

$file_path='';
$firstname='';

// Load user from session if navigating directly (not via login form)
if(!isset($_POST['stdnum']) && isset($_SESSION['user_id'])){
    $loginId=$_SESSION['user_id'];
    $sqlsess="SELECT * FROM USERS WHERE USER_ID=?";
    $resultsess=db_query($conn,$sqlsess, [$loginId]);
    $rowsess=db_fetch_assoc($resultsess);
    if($rowsess){
        $firstname=$rowsess['FIRST_NAME'];
        $sqlprofile="SELECT * FROM USER_IMG WHERE USER_ID=?";
        $resultprofile=db_query($conn,$sqlprofile, [$loginId]);
        $rowprofile=db_fetch_assoc($resultprofile);
        $file_path = $rowprofile ? $rowprofile['FILE_PATH'] : 'assets/img/avatar.png';
        $userCollege = $rowsess['COLLEGE'] ?? '';
    }
}

if(!$locked && isset($_POST['stdnum'])){
    $stdnum=trim($_POST['stdnum']);
    $password=$_POST['password'];

    $sql="SELECT * FROM USERS WHERE STD_NUM=?";
    $result=db_query($conn,$sql, [$stdnum]);
    $rowname=db_fetch_assoc($result);

    if($rowname==null){
        $_SESSION['login_attempts']++;
        $left=$MAX_ATTEMPTS - $_SESSION['login_attempts'];
        if($_SESSION['login_attempts']>=$MAX_ATTEMPTS){
            $_SESSION['locked_until']=time()+$COOLDOWN_SEC;
            $locked=true;
            $error='Too many failed attempts. Please wait '.$COOLDOWN_SEC.' second(s) before trying again.';
        } else {
            $error='Student number not found. '.$left.' attempt'.($left!=1?'s':'').' remaining.';
        }
    } else {
        $sqlpassword="SELECT * FROM USERS WHERE STD_NUM=? AND `PASSWORD`=?";
        $resultpassword=db_query($conn,$sqlpassword, [$stdnum, $password]);
        $rowpassword=db_fetch_assoc($resultpassword);

        if($rowpassword==null){
            $_SESSION['login_attempts']++;
            $left=$MAX_ATTEMPTS - $_SESSION['login_attempts'];
            if($_SESSION['login_attempts']>=$MAX_ATTEMPTS){
                $_SESSION['locked_until']=time()+$COOLDOWN_SEC;
                $locked=true;
                $error='Too many failed attempts. Please wait '.$COOLDOWN_SEC.' second(s) before trying again.';
            } else {
                $error='Wrong password. '.$left.' attempt'.($left!=1?'s':'').' remaining.';
            }
        } else {
            $_SESSION['login_attempts']=0;
            $_SESSION['locked_until']=0;
            $_SESSION['user_id']=$rowpassword['USER_ID'];

            $loginId=$rowpassword['USER_ID'];
            $firstname=$rowpassword['FIRST_NAME'];

            $sqlprofile="SELECT * FROM USER_IMG WHERE USER_ID=?";
            $resultprofile=db_query($conn,$sqlprofile, [$loginId]);
            if($resultprofile===false)
                die(db_last_error());
            $rowprofile=db_fetch_assoc($resultprofile);
            $file_path = $rowprofile ? $rowprofile['FILE_PATH'] : 'assets/img/avatar.png';
        }
    }
}

if(!isset($_POST['stdnum']) && $firstname==''){
    header("Location: dashboard.php");
    exit;
}

// Fetch all available listings for the dashboard
$dashItems = [];
$currentCategory = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$currentCollegeFilter = isset($_GET['college']) ? $_GET['college'] : 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

    $sqlDash = "SELECT L.*, I.FILE_PATH, U.USER_ID AS SELLER_ID, U.FIRST_NAME, U.LAST_NAME 
                FROM LISTINGS L
                LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
                JOIN USERS U ON L.USER_ID = U.USER_ID
                WHERE (L.STATUS = 'Available' OR L.STATUS IS NULL)";
    
    $params = [];
    if ($currentCategory !== 'all') {
        if ($currentCategory === 'Course-Specific') {
            if ($currentCollegeFilter !== 'all') {
                $sqlDash .= " AND L.CATEGORY LIKE ?";
                $params[] = "Course-Specific (" . $currentCollegeFilter . "%)";
            } else {
                $sqlDash .= " AND L.CATEGORY LIKE ?";
                $params[] = "Course-Specific%";
            }
        } else {
            $sqlDash .= " AND L.CATEGORY = ?";
            $params[] = $currentCategory;
        }
    }
    
    if ($searchQuery !== '') {
        $sqlDash .= " AND (L.TITLE LIKE ? OR L.DESCRIPTION LIKE ?)";
        $params[] = "%" . $searchQuery . "%";
        $params[] = "%" . $searchQuery . "%";
    }
    
    $sqlDash .= " ORDER BY L.DATE_POSTED DESC";
    
    $stmtDash = db_query($conn, $sqlDash, $params);
    if ($stmtDash) {
        while($row = db_fetch_assoc($stmtDash)){
            $dashItems[] = $row;
        }
    }

// Independent Carousel items (always most recent 5 available)
$carouselItems = [];
$sqlCarousel = "SELECT L.*, I.FILE_PATH AS IMG, U.FIRST_NAME, U.LAST_NAME 
                FROM LISTINGS L 
                LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
                JOIN USERS U ON L.USER_ID = U.USER_ID
                WHERE L.STATUS = 'Available'
                ORDER BY L.DATE_POSTED DESC LIMIT 5";
$stmtCarousel = db_query($conn, $sqlCarousel);
if ($stmtCarousel) {
    while($row = db_fetch_assoc($stmtCarousel)){
        $carouselItems[] = $row;
    }
}

function getCategoryStyle($cat) {
    $styles = [
        'Clothing & Apparel'   => ['👕', 'linear-gradient(135deg, #dcfce7, #86efac)'],
        'Electronics and Tech' => ['💻', 'linear-gradient(135deg, #dbeafe, #93c5fd)'],
        'Academics'            => ['📚', 'linear-gradient(135deg, #fef3c7, #fcd34d)'],
        'Hobbies & Lifestyle'  => ['🎨', 'linear-gradient(135deg, #fce7f3, #f9a8d4)'],
        'Events & Tickets'     => ['🎟️', 'linear-gradient(135deg, #ede9fe, #c4b5fd)'],
        'Course-Specific'      => ['🔬', 'linear-gradient(135deg, #ffedd5, #fdba74)'],
        'Food'                 => ['🍪', 'linear-gradient(135deg, #fef2f2, #fecaca)']
    ];
    return $styles[$cat] ?? ['📦', 'linear-gradient(135deg, #f3f4f6, #d1d5db)'];
}

db_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $firstname!='' ? 'Dashboard' : 'Login'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <?php if($firstname!=''): ?>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <?php else: ?>
    <link rel="stylesheet" href="assets/css/login.css">
    <?php endif; ?>
</head>
<body class="body">

<?php if($firstname!=''): ?>

    <!-- ── DASHBOARD ── -->
    <div class="dash-navbar">
        <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo"></a>
        
        <!-- Center Nav Links -->
        <div class="dash-nav-links">
            <a href="dashboard.php" class="dash-nav-link active">Browse Products</a>
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
                <span class="dash-name"><?php echo htmlspecialchars($firstname); ?></span>
            </div>

            <!-- Profile picture with simplified dropdown -->
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($file_path); ?>"
                     class="img-profile"
                     alt="Profile Picture"
                     id="profileBtn">

                <div class="profile-dropdown" id="profileDropdown">
                    <!-- Mobile-only nav links (hidden on desktop via CSS) -->
                    <div class="dropdown-mobile-nav">
                        <div class="dropdown-profile-header">
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Profile">
                            <div>
                                <div class="dropdown-profile-name"><?php echo htmlspecialchars($firstname); ?></div>
                                <div style="font-size:11px; color:rgba(255,255,255,0.6);">DLSU-D Student</div>
                            </div>
                        </div>
                        <a href="dashboard.php" class="dropdown-item-custom"><span class="item-icon">🛍️</span> Browse Products</a>
                        <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon">🏪</span> My Storefront</a>
                        <a href="edit_profile.php" class="dropdown-item-custom"><span class="item-icon">👤</span> My Profile</a>
                        <a href="saved_listings.php" class="dropdown-item-custom"><span class="item-icon">🔖</span> Saved Listings</a>
                        <a href="notifications.php" class="dropdown-item-custom"><span class="item-icon">🔔</span> Notifications</a>
                        <div class="dropdown-divider-custom"></div>
                    </div>
                    <!-- Always-visible items -->
                    <a href="edit_profile.php?tab=support" class="dropdown-item-custom"><span class="item-icon">💖</span> Support Us</a>
                    <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon">🚪</span> Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-header-bar"></div>

    <!-- ── DASHBOARD HERO ── -->
    <div class="dash-hero">
        <div class="dash-hero-inner">
            <!-- LEFT: Dribbble-style text -->
            <div class="dash-hero-left-alt">
                <span class="dash-eyebrow">✦ Welcome, <?php echo htmlspecialchars($firstname); ?>!</span>
                <h1 class="dash-title-main">Featured Items<br>Within Campus Reach</h1>
                <p class="dash-desc-main">Explore work from the most talented and accomplished 
                    students ready to sell their items on campus.</p>
                
                <!-- Category Tabs (Hero) -->
                <div class="dash-design-tabs desktop-only-hero">
                    <a href="dashboard.php?cat=all" class="dash-design-tab <?php echo ($currentCategory === 'all') ? 'active' : ''; ?>">
                        <img src="assets/img/cart.svg" alt="Cart" style="width: 16px; height: 16px;">
                        All Items
                    </a>
                    <a href="dashboard.php?cat=Clothing+%26+Apparel" class="dash-design-tab <?php echo ($currentCategory === 'Clothing & Apparel') ? 'active' : ''; ?>">
                        <img src="assets/img/shirts.svg" alt="Cart" style="width: 16px; height: 16px;">
                        Clothing
                    </a>
                    <a href="dashboard.php?cat=Electronics+and+Tech" class="dash-design-tab <?php echo ($currentCategory === 'Electronics and Tech') ? 'active' : ''; ?>">
                        <img src="assets/img/keyboard.svg" alt="Cart" style="width: 16px; height: 16px;">
                        Electronics
                    </a>
                    <a href="dashboard.php?cat=Academics" class="dash-design-tab <?php echo ($currentCategory === 'Academics') ? 'active' : ''; ?>">
                        <img src="assets/img/academics.svg" alt="Cart" style="width: 16px; height: 16px;">
                        Academics
                    </a>
                    <a href="dashboard.php?cat=Hobbies+%26+Lifestyle" class="dash-design-tab <?php echo ($currentCategory === 'Hobbies & Lifestyle') ? 'active' : ''; ?>">
                        <img src="assets/img/labubus.svg" alt="Cart" style="width: 16px; height: 16px;">
                        Hobbies
                    </a>
                    <a href="dashboard.php?cat=Events+%26+Tickets" class="dash-design-tab <?php echo ($currentCategory === 'Events & Tickets') ? 'active' : ''; ?>">
                        <img src="assets/img/tickets.svg" alt="Cart" style="width: 16px; height: 16px;">
                        Events
                    </a>
                    <a href="dashboard.php?cat=Course-Specific" class="dash-design-tab <?php echo ($currentCategory === 'Course-Specific') ? 'active' : ''; ?>">
                        <img src="assets/img/electronics.svg" alt="Course" style="width: 16px; height: 16px;">
                        Course-Specific
                    </a>

                    <a href="dashboard.php?cat=Food" class="dash-design-tab <?php echo ($currentCategory === 'Food') ? 'active' : ''; ?>">
                        <img src="assets/img/cookies.svg" alt="Cart" style="width: 16px; height: 16px;">
                        Food
                    </a>
                </div>

                <!-- Hero Section Sub-filters (College Pills) -->
                <?php if ($currentCategory === 'Course-Specific'): ?>
                <div class="section-pills-scroll mt-3 desktop-only-hero" style="margin-bottom: 24px;">
                    <a href="dashboard.php?cat=Course-Specific&college=all" class="section-pill <?php echo ($currentCollegeFilter === 'all') ? 'active' : ''; ?>">All Colleges</a>
                    <?php
                    $allColleges = ['CEAT', 'CLAC', 'CBAA', 'COS', 'CICS', 'COED', 'CCJE', 'CTHM', 'COL'];
                    foreach ($allColleges as $clg):
                        $active = ($currentCollegeFilter === $clg) ? 'active' : '';
                        echo '<a href="dashboard.php?cat=Course-Specific&college='.urlencode($clg).'" class="section-pill '.$active.'">'.$clg.'</a>';
                    endforeach;
                    ?>
                </div>
                <?php endif; ?>

                <!-- Search Bar -->
                <div class="dash-hero-search-wrap">
                    <form action="dashboard.php" method="GET" class="dash-hero-search-form">
                        <div class="dash-search-input-box">
                            <input type="text" name="search" placeholder="What type of item are you interested in?" class="dash-search-main-input" value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <button type="submit" class="dash-search-main-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Popular Tags -->
                <div class="dash-hero-popular desktop-only-hero">
                    <span class="pop-label">Popular:</span>
                    <div class="pop-pills">
                        <a href="dashboard.php?search=iphone" class="pop-pill">iphone</a>
                        <a href="dashboard.php?search=books" class="pop-pill">textbooks</a>
                        <a href="dashboard.php?search=clothes" class="pop-pill">clothes</a>
                        <a href="dashboard.php?search=gadgets" class="pop-pill">gadgets</a>
                        <a href="dashboard.php?search=dorm" class="pop-pill">dorm essentials</a>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Carousel -->
            <div class="dash-hero-right-alt">
                <div class="dash-carousel-wrap">
                    <button class="dc-arrow dc-arrow--prev" id="dcPrev" aria-label="Previous">↑</button>
                    <div class="dc-track-outer">
                        <div class="dc-track" id="dcTrack">
                            <?php if (!empty($carouselItems)): ?>
                                <?php foreach($carouselItems as $item): ?>
                                    <?php 
                                    $imgSrc = !empty($item['IMG']) ? htmlspecialchars($item['IMG']) : '';
                                    $price = '₱' . number_format($item['PRICE'], 2);
                                    list($emoji, $bg) = getCategoryStyle($item['CATEGORY']);
                                    ?>
                                    <div class="dc-card" onclick="window.location.href='listing.php?id=<?php echo $item['LISTING_ID']; ?>'">
                                        <div class="dc-card-img" style="background:<?php echo $bg; ?>; position: relative;">
                                            <?php if ($imgSrc): ?>
                                                <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($item['TITLE']); ?>" style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0;">
                                            <?php else: ?>
                                                <span class="dc-emoji"><?php echo $emoji; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dc-card-body">
                                            <div class="dc-tags-row">
                                                <span class="dc-tag"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                                                <span class="dc-status-tag">Available</span>
                                            </div>
                                            <p class="dc-title"><?php echo htmlspecialchars($item['TITLE']); ?></p>
                                            <p class="dc-price"><?php echo $price; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="dc-card">
                                    <div class="dc-card-img" style="background:linear-gradient(135deg,#dcfce7,#86efac)">
                                        <span class="dc-emoji">🎒</span>
                                    </div>
                                    <div class="dc-card-body">
                                        <span class="dc-tag">Featured</span>
                                        <p class="dc-title">Browse New Listings</p>
                                        <p class="dc-price">Starting low</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="dc-arrow dc-arrow--next" id="dcNext" aria-label="Next">↓</button>
                </div>
                <div class="dc-dots" id="dcDots"></div>
            </div>
        </div>
    </div>

    <!-- ── STICKY FILTERS & SEARCH ── -->
    <div class="dash-sticky-nav">
        <div class="dash-sticky-inner">

            <!-- Row 1: Search Bar -->
            <form action="dashboard.php" method="GET" class="dash-search-form" style="width: 280px; margin: 0; flex-shrink: 0;">
                <input type="hidden" name="cat" value="<?php echo htmlspecialchars($currentCategory); ?>">
                <div class="dash-search-wrapper">
                    <span class="dash-search-icon">🔍</span>
                    <input type="text" name="search" class="dash-search-input" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="dash-search-btn">Search</button>
                </div>
            </form>

            <!-- Row 2: Category pills -->
            <div class="dash-filter-pills" style="margin: 0; flex-grow: 1;">
                <a href="dashboard.php?cat=all" class="dash-pill <?php echo ($currentCategory === 'all') ? 'active' : ''; ?>">
                    <img src="assets/img/cart.svg" alt="Cart" style="width: 16px; height: 16px;"> All Items</a>
                <a href="dashboard.php?cat=Clothing+%26+Apparel" class="dash-pill <?php echo ($currentCategory === 'Clothing & Apparel') ? 'active' : ''; ?>">
                    <img src="assets/img/shirts.svg" alt="Clothing" style="width: 16px; height: 16px;"> Clothing</a>
                <a href="dashboard.php?cat=Electronics+and+Tech" class="dash-pill <?php echo ($currentCategory === 'Electronics and Tech') ? 'active' : ''; ?>">
                    <img src="assets/img/keyboard.svg" alt="Electronics" style="width: 16px; height: 16px;"> Electronics</a>
                <a href="dashboard.php?cat=Academics" class="dash-pill <?php echo ($currentCategory === 'Academics') ? 'active' : ''; ?>">
                    <img src="assets/img/academics.svg" alt="Academics" style="width: 16px; height: 16px;"> Academics</a>
                <a href="dashboard.php?cat=Hobbies+%26+Lifestyle" class="dash-pill <?php echo ($currentCategory === 'Hobbies & Lifestyle') ? 'active' : ''; ?>">
                    <img src="assets/img/labubus.svg" alt="Hobbies" style="width: 16px; height: 16px;"> Hobbies</a>
                <a href="dashboard.php?cat=Events+%26+Tickets" class="dash-pill <?php echo ($currentCategory === 'Events & Tickets') ? 'active' : ''; ?>">
                    <img src="assets/img/tickets.svg" alt="Events" style="width: 16px; height: 16px;"> Events</a>
                <a href="dashboard.php?cat=Course-Specific" class="dash-pill <?php echo ($currentCategory === 'Course-Specific') ? 'active' : ''; ?>">
                    <img src="assets/img/electronics.svg" alt="Course-Specific" style="width: 16px; height: 16px;"> Course-Specific</a>
                <a href="dashboard.php?cat=Food" class="dash-pill <?php echo ($currentCategory === 'Food') ? 'active' : ''; ?>">
                    <img src="assets/img/cookies.svg" alt="Cookies" style="width: 16px; height: 16px;"> Food</a>
            </div>

            <!-- Row 3: College pills (only for Course-Specific) -->
        </div> <!-- End dash-sticky-inner -->

        <?php if ($currentCategory === 'Course-Specific'): ?>
        <div class="dash-sticky-inner" style="padding-top: 0; border-top: 1px solid var(--border); margin-top: 10px;">
            <div class="section-filters-container active" style="width: 100%; border: none; background: transparent; padding: 10px 0; margin-bottom: 0;">
                <div class="section-filters-title">
                    <span>🏢 Filter by College</span>
                </div>
                <div class="section-pills-scroll">
                    <a href="dashboard.php?cat=Course-Specific&college=all" class="section-pill <?php echo ($currentCollegeFilter === 'all') ? 'active' : ''; ?>">All Colleges</a>
                    <?php
                    $allColleges = ['CEAT', 'CLAC', 'CBAA', 'COS', 'CICS', 'COED', 'CCJE', 'CTHM', 'COL'];
                    foreach ($allColleges as $clg):
                        $active = ($currentCollegeFilter === $clg) ? 'active' : '';
                        echo '<a href="dashboard.php?cat=Course-Specific&college='.urlencode($clg).'" class="section-pill '.$active.'">'.$clg.'</a>';
                    endforeach;
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── DASHBOARD LISTINGS ── -->
    <div class="container" id="listingsSection" style="max-width: 1700px; padding: 0 4% 60px;">
        <h3 style="font-family: 'DM Sans', sans-serif; font-size: 28px; font-weight: 800; margin-bottom: 24px; color: var(--text-dark);">
            <?php
            $categoryHeadings = [
                'all'                => 'Recent Listings',
                'Clothing & Apparel' => 'Clothing Listings',
                'Electronics and Tech' => 'Electronics Listings',
                'Academics'          => 'Academics Listings',
                'Hobbies & Lifestyle'=> 'Hobbies & Lifestyle Listings',
                'Events & Tickets'   => 'Events & Tickets Listings',
                'Course-Specific'    => 'Course-Specific Listings',
                'Food'               => 'Food Listings',
            ];
            echo $categoryHeadings[$currentCategory] ?? htmlspecialchars($currentCategory) . ' Listings';
            ?>
        </h3>
        
        <?php if(empty($dashItems)): ?>
            <div style="text-align: center; padding: 60px 0; color: var(--text-soft);">
                <div style="font-size: 48px; margin-bottom: 16px;">🛍️</div>
                <h5>No listings found</h5>
                <p>Be the first to list something!</p>
            </div>
        <?php else: ?>
            <div class="dash-listings-grid">
                <?php foreach($dashItems as $index => $item): ?>
                <?php
                    $imgSrc = !empty($item['FILE_PATH']) ? htmlspecialchars($item['FILE_PATH']) : 'assets/img/no_image.png';
                    $condClass = 'cond-' . strtolower(str_replace([' ', '-'], '', $item['CONDITION']));
                    $sellerName = htmlspecialchars($item['FIRST_NAME'] . ' ' . $item['LAST_NAME']);
                    $price = '₱' . number_format($item['PRICE'], 2);
                    $delay = $index * 0.05; // 50ms stagger
                ?>
                <div class="dash-listing-card" onclick="window.location.href='listing.php?id=<?php echo $item['LISTING_ID']; ?>'">
                    <div class="dash-listing-img-wrap">
                        <img src="<?php echo $imgSrc; ?>" class="dash-listing-img" alt="<?php echo htmlspecialchars($item['TITLE']); ?>" loading="lazy">
                        <span class="dash-listing-badge"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                        <span class="dash-listing-status-badge">Available</span>
                    </div>
                    <div class="dash-listing-body">
                        <div class="dash-listing-seller">Listed by <?php echo $sellerName; ?></div>
                        <h4 class="dash-listing-title"><?php echo htmlspecialchars($item['TITLE']); ?></h4>
                        <div class="dash-listing-footer">
                            <span class="dash-listing-price"><?php echo $price; ?></span>
                            <span class="dash-listing-cond <?php echo $condClass; ?>"><?php echo $item['CONDITION']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <!-- ── LOGIN FORM WITH ERROR ── -->
    <div class="login-header">
        <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo-light" alt="Pipeline Logo"></a>
        <div class="header-links">
            <a href="#" class="header-link" data-bs-toggle="modal" data-bs-target="#aboutModal">ABOUT US</a>
            <span class="header-sep">|</span>
            <a href="login_admin.html" class="header-link">ADMIN LOGIN</a>
        </div>
    </div>

    <!-- About Us Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; font-family: 'DM Sans', sans-serif;">
                <div class="modal-header" style="border-bottom: 1px solid #dde5b6; padding: 24px 28px 16px;">
                    <h5 class="modal-title" id="aboutModalLabel" style="font-weight: 800; font-size: 22px; color: #283618;">
                        About Pipeline
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 24px 28px; color: #3a3a3a; font-size: 15px; line-height: 1.75;">
                    Pipeline is a campus-exclusive peer-to-peer marketplace created for the students of
                    <strong style="color: #283618;">De La Salle University–Dasmariñas (DLSU-D)</strong>.
                    The platform is designed to provide a convenient and organized space where students can
                    buy and sell items within the university community. By connecting students directly with
                    one another, Pipeline aims to make everyday campus transactions simpler, faster, and more accessible.
                </div>
            </div>
        </div>
    </div>

    <div class="login-wrapper">
        <div class="login-card">

            <div class="login-left">
                <div class="login-form-header">
                    <div class="form-label-small">👤 WELCOME BACK</div>
                    <h1 class="login-title">Hello Lasallian!</h1>
                    <p class="login-sub">Please login to continue</p>
                </div>

                <div class="login-error"><?php echo htmlspecialchars($error); ?></div>

                <form action="dashboard.php" method="POST">

                    <div class="mb-3">
                        <label class="login-label">Student ID Number</label>
                        <input type="text" class="login-input" name="stdnum" placeholder="202012345" maxlength="9" value="<?php echo isset($_POST['stdnum'])?htmlspecialchars($_POST['stdnum']):''; ?>" <?php echo $locked?'disabled':''; ?>>
                    </div>

                    <div class="mb-4">
                        <label class="login-label">Password</label>
                        <input type="password" class="login-input" name="password" <?php echo $locked?'disabled':''; ?>>
                    </div>

                    <button type="submit" class="btn-login" <?php echo $locked?'disabled':''; ?>>LOGIN</button>

                </form>

                <hr class="login-hr">

                <div class="login-footer-links">
                    <a href="forgot_password.html" class="login-link">Forgot password?</a>
                    <p class="mb-0">Don't have an account? <a href="regis.html" class="login-link">Sign up here</a></p>
                </div>
            </div>

            <div class="login-divider"></div>

            <div class="login-right" style="padding-left: 80px;">
                <img src="assets/img/login_image_wframe.png" class="login-img" alt="Login Image">
            </div>

        </div>
    </div>

<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Student ID input filter
        var stdnumInput = document.getElementsByName('stdnum')[0];
        if(stdnumInput){
            stdnumInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 9);
            });
        }

        /* ── Profile Dropdown ── */
        const profileBtn = document.getElementById('profileBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            document.addEventListener('click', (e) => {
                if (!profileDropdown.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });
        }

        // Sticky navbar shadow on scroll
        const dashNav = document.querySelector('.dash-navbar');
        if(dashNav){
            window.addEventListener('scroll', function(){
                dashNav.classList.toggle('scrolled', window.scrollY > 10);
            });
        }

        // Sticky filters one-row compaction
        var stickyFilters = document.querySelector('.dash-sticky-nav');
        if(stickyFilters){
            window.addEventListener('scroll', function(){
                var rect = stickyFilters.getBoundingClientRect();
                if (rect.top <= 113) {
                    stickyFilters.classList.add('is-sticky');
                } else {
                    stickyFilters.classList.remove('is-sticky');
                }
            });
        }

        /* ── Hero Carousel (Infinite Seamless) ── */
        (function () {
            const track = document.getElementById('dcTrack');
            const prevBtn = document.getElementById('dcPrev');
            const nextBtn = document.getElementById('dcNext');
            const dotsWrap = document.getElementById('dcDots');

            if (!track || !prevBtn || !nextBtn || !dotsWrap) return;

            const originalCards = Array.from(track.children);
            if (originalCards.length === 0) return;

            // Clone for infinite loop
            originalCards.forEach(card => {
                const clone = card.cloneNode(true);
                track.appendChild(clone);
            });

            const allCards = Array.from(track.children);
            const CARD_H = 140; 
            const GAP = 16;
            const STEP = CARD_H + GAP;
            let current = 0;
            let autoTimer;
            let isTransitioning = false;

            /* Build dots based on original length only */
            originalCards.forEach((_, i) => {
                const d = document.createElement('button');
                d.className = 'dc-dot' + (i === 0 ? ' dc-dot--active' : '');
                d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
                d.addEventListener('click', () => { if(!isTransitioning) goTo(i); });
                dotsWrap.appendChild(d);
            });
            const dots = Array.from(dotsWrap.children);

            function updateDots(idx) {
                const normalized = idx % originalCards.length;
                dots.forEach((d, i) => d.classList.toggle('dc-dot--active', i === normalized));
            }

            function goTo(idx, immediate = false) {
                if (isTransitioning && !immediate) return;
                
                current = idx;
                track.style.transition = immediate ? 'none' : 'transform 0.6s cubic-bezier(0.23, 1, 0.32, 1)';
                track.style.transform = `translateY(-${current * STEP}px)`;
                
                updateDots(current);

                if (!immediate) {
                    isTransitioning = true;
                    track.addEventListener('transitionend', function handleEnd() {
                        track.removeEventListener('transitionend', handleEnd);
                        isTransitioning = false;
                        
                        // If we reached the end of the clones, jump back to start
                        if (current >= originalCards.length) {
                            goTo(0, true);
                        }
                        // If we reached the start (negative), jump to end of originals
                        // (Though prev is disabled at 0 for simplicity, we could enable it)
                    });
                }
            }

            prevBtn.addEventListener('click', () => { 
                resetAuto(); 
                if (current > 0) goTo(current - 1);
                else goTo(originalCards.length - 1); 
            });

            nextBtn.addEventListener('click', () => { 
                resetAuto(); 
                goTo(current + 1); 
            });

            function autoPlay() {
                goTo(current + 1);
            }

            function resetAuto() {
                clearInterval(autoTimer);
                autoTimer = setInterval(autoPlay, 4000);
            }

            goTo(0, true);
            autoTimer = setInterval(autoPlay, 4000);

            track.closest('.dc-track-outer').addEventListener('mouseenter', () => clearInterval(autoTimer));
            track.closest('.dc-track-outer').addEventListener('mouseleave', () => resetAuto());
        })();
    </script>


    <script>
        // Auto-scroll to results if searching or filtered
        window.addEventListener('load', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if ((urlParams.has('search') && urlParams.get('search') !== '') || (urlParams.has('cat') && urlParams.get('cat') !== 'all')) {
                const target = document.getElementById('listingsSection');
                if (target) {
                    setTimeout(() => {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 150);
                }
            }
        });
    </script>
    <!-- ── GSAP ANIMATIONS ── -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script>
        gsap.registerPlugin(ScrollTrigger);

        document.addEventListener('DOMContentLoaded', () => {
            // Initial states (force visibility)
            gsap.set([".dash-eyebrow", ".dash-title-main", ".dash-desc-main", ".dash-design-tab", ".dash-hero-search-wrap", ".dash-hero-popular"], { opacity: 1 });

            const tl = gsap.timeline({ defaults: { ease: "power3.out", duration: 0.5 }});
            
            tl.from(".dash-eyebrow", { y: 20, opacity: 0, delay: 0.2 })
              .from(".dash-title-main", { y: 30, opacity: 0 }, "-=0.3")
              .from(".dash-desc-main", { y: 20, opacity: 0 }, "-=0.3")
              .from(".dash-design-tab", { y: 15, opacity: 0, stagger: 0.03 }, "-=0.3")
              .from(".dash-hero-search-wrap", { y: 20, opacity: 0 }, "-=0.3")
              .from(".dash-hero-popular", { y: 15, opacity: 0 }, "-=0.3")
              .from(".dash-hero-right-alt", { x: 40, opacity: 0, duration: 0.7 }, "-=0.5");

            // Navbar entrance
            gsap.from(".dash-navbar", { y: -20, opacity: 0, duration: 0.5, delay: 0.1 });

            // Sticky nav reveal
            if (document.querySelector('.dash-sticky-nav')) {
                gsap.from(".dash-sticky-nav", {
                    scrollTrigger: {
                        trigger: ".dash-hero",
                        start: "bottom 80%",
                        toggleActions: "play none none reverse"
                    },
                    y: -20,
                    opacity: 0,
                    duration: 0.3
                });
            }

            // Grid Scroll Reveal
            gsap.utils.toArray('.dash-listing-card').forEach((card, i) => {
                gsap.from(card, {
                    scrollTrigger: {
                        trigger: card,
                        start: "top 92%",
                        toggleActions: "play none none none"
                    },
                    y: 40,
                    opacity: 0,
                });
            });

            // Account Created Toast
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('msg') === 'account_created') {
                // 1. Existing GSAP Toast
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%);
                    background: #087832; color: white; padding: 16px 32px;
                    border-radius: 100px; font-weight: 700; box-shadow: 0 12px 40px rgba(8, 120, 50, 0.4);
                    z-index: 10000; font-family: 'DM Sans', sans-serif; display: flex; align-items: center; gap: 12px;
                    font-size: 16px; pointer-events: none;
                `;
                toast.innerHTML = '<span style="font-size:20px">🎉</span> Account Created Successfully!';
                document.body.appendChild(toast);
                
                gsap.from(toast, { y: 100, opacity: 0, duration: 0.6, ease: "back.out(1.4)" });
                setTimeout(() => {
                    gsap.to(toast, { y: 100, opacity: 0, duration: 0.5, onComplete: () => toast.remove() });
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 4000);

                // 2. Native Browser Notification API
                if ("Notification" in window) {
                    if (Notification.permission === "granted") {
                        new Notification("Pipeline Marketplace", {
                            body: "Welcome to the community! Your account is now active.",
                            icon: "assets/img/pipeline_wireframe-removebg.png"
                        });
                    } else if (Notification.permission !== "denied") {
                        Notification.requestPermission().then(permission => {
                            if (permission === "granted") {
                                new Notification("Pipeline Marketplace", {
                                    body: "Welcome to the community! Your account is now active.",
                                    icon: "assets/img/pipeline_wireframe-removebg.png"
                                });
                            }
                        });
                    }
                }
            }

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
                toast.style.cssText = `position:fixed; top:24px; right:24px; width:320px; background:white; border-left:5px solid #087832; box-shadow:0 15px 35px rgba(0,0,0,0.15); border-radius:12px; padding:16px; display:flex; gap:12px; z-index:10001; font-family:'DM Sans', sans-serif;`;
                toast.innerHTML = `<img src="${notif.avatar}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:700;color:#087832;font-size:14px;">${notif.sender}</div><div style="color:#666;font-size:13px;">${notif.message}</div></div>`;
                document.body.appendChild(toast);
                gsap.from(toast, { x: 100, opacity: 0, duration: 0.5 });
                setTimeout(() => { gsap.to(toast, { x: 100, opacity: 0, duration: 0.5, onComplete: () => toast.remove() }); }, 6000);
            }
            setInterval(checkNotifications, 10000);
            setTimeout(checkNotifications, 2000);
        });
    </script>
</body>
</html>
