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
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($firstname != '') {
    $sqlDash = "SELECT L.*, I.FILE_PATH, U.USER_ID AS SELLER_ID, U.FIRST_NAME, U.LAST_NAME 
                FROM LISTINGS L
                LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
                JOIN USERS U ON L.USER_ID = U.USER_ID
                WHERE (L.STATUS = 'Available' OR L.STATUS IS NULL)";
    
    $params = [];
    if ($currentCategory !== 'all') {
        $sqlDash .= " AND L.CATEGORY = ?";
        $params[] = $currentCategory;
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
        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo htmlspecialchars($firstname); ?></span>
            </div>

            <!-- Profile picture with dropdown -->
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($file_path); ?>"
                     class="img-profile"
                     alt="Profile Picture"
                     id="profileBtn">

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-profile-header">
                        <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Profile">
                        <span class="dropdown-profile-name"><?php echo htmlspecialchars($firstname); ?></span>
                    </div>

                    <a href="dashboard.php" class="dropdown-item-custom">
                        <span class="item-icon">🏬</span> Browse Products
                    </a>
                    <a href="storefront.php" class="dropdown-item-custom">
                        <span class="item-icon">🏪</span> My Storefront
                    </a>
                    <a href="edit_profile.php" class="dropdown-item-custom">
                        <span class="item-icon">👤</span> My Profile
                    </a>

                    <div class="dropdown-divider-custom"></div>

                    <a href="logout.php" class="dropdown-item-custom logout">
                        <span class="item-icon">🚪</span> Log Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-header-bar"></div>

    <!-- ── DASHBOARD HERO ── -->
    <div class="dash-hero">

        <!-- LEFT: headline + categories -->
        <div class="dash-hero-left">
            <span class="dash-eyebrow">✦ Welcome back, <?php echo htmlspecialchars($firstname); ?>!</span>
            <h1 class="h1">Everything You Need,</h1>
            <h1 class="h1">Within Campus Reach</h1>

            </div>
        </div>  
    </div>

    <!-- ── STICKY FILTERS & SEARCH ── -->
    <div class="dash-sticky-nav">
        <div class="dash-sticky-inner" style="flex-direction: column; align-items: flex-start; gap: 10px;">

            <!-- Row 1: Search Bar -->
            <form action="dashboard.php" method="GET" class="dash-search-form" style="width: 300px; margin: 0;">
                <input type="hidden" name="cat" value="<?php echo htmlspecialchars($currentCategory); ?>">
                <div class="dash-search-wrapper">
                    <span class="dash-search-icon">🔍</span>
                    <input type="text" name="search" class="dash-search-input" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="dash-search-btn">Search</button>
                </div>
            </form>

            <!-- Row 2: Category pills -->
            <div class="dash-filter-pills" style="margin: 0; width: 100%;">
                <a href="dashboard.php?cat=all" class="dash-pill <?php echo ($currentCategory === 'all') ? 'active' : ''; ?>">
                    <img src="assets/img/cart.svg" alt="Cart" style="width: 16px; height: 16px;"> All Items</a>
                <a href="dashboard.php?cat=Clothing+%26+Apparel" class="dash-pill <?php echo ($currentCategory === 'Clothing & Apparel') ? 'active' : ''; ?>">
                    <img src="assets/img/shirts.svg" alt="Clothing" style="width: 16px; height: 16px;"> Clothing</a>
                <a href="dashboard.php?cat=Electronics" class="dash-pill <?php echo ($currentCategory === 'Electronics and Tech') ? 'active' : ''; ?>">
                    <img src="assets/img/keyboard.svg" alt="Electronics" style="width: 16px; height: 16px;"> Electronics</a>
                <a href="dashboard.php?cat=Books" class="dash-pill <?php echo ($currentCategory === 'Books') ? 'active' : ''; ?>">
                    <img src="assets/img/academics.svg" alt="Books" style="width: 16px; height: 16px;"> Books</a>
                <a href="dashboard.php?cat=Hobbies+%26+Lifestyle" class="dash-pill <?php echo ($currentCategory === 'Hobbies & Lifestyle') ? 'active' : ''; ?>">
                    <img src="assets/img/labubus.svg" alt="Hobbies" style="width: 16px; height: 16px;"> Hobbies</a>
                <a href="dashboard.php?cat=Events+%26+Tickets" class="dash-pill <?php echo ($currentCategory === 'Events & Tickets') ? 'active' : ''; ?>">
                    <img src="assets/img/tickets.svg" alt="Events" style="width: 16px; height: 16px;"> Events</a>
                <a href="dashboard.php?cat=Course-Specific" class="dash-pill <?php echo ($currentCategory === 'Course-Specific') ? 'active' : ''; ?>">
                    <img src="assets/img/electronics.svg" alt="Course-Specific" style="width: 16px; height: 16px;"> Course-Specific</a>
                <a href="dashboard.php?cat=Food" class="dash-pill <?php echo ($currentCategory === 'Food') ? 'active' : ''; ?>">
                    🍔 Food</a>
            </div>

        </div>
    </div>

    <!-- ── DASHBOARD LISTINGS ── -->
    <div class="container" style="max-width: 1200px; padding: 0 4% 60px;">
        <h3 style="font-family: 'DM Serif Display', serif; font-size: 28px; margin-bottom: 24px; color: var(--text-dark);">
            <?php echo ($currentCategory === 'all') ? 'Recent Listings' : htmlspecialchars($currentCategory) . ' Listings'; ?>
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
                <div class="dash-listing-card" style="animation-delay: <?php echo $delay; ?>s;" onclick="window.location.href='listing.php?id=<?php echo $item['LISTING_ID']; ?>'">
                    <div class="dash-listing-img-wrap">
                        <img src="<?php echo $imgSrc; ?>" class="dash-listing-img" alt="<?php echo htmlspecialchars($item['TITLE']); ?>" loading="lazy">
                        <span class="dash-listing-badge"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
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

        // Profile dropdown toggle
        var profileBtn = document.getElementById('profileBtn');
        var profileDropdown = document.getElementById('profileDropdown');

        if(profileBtn && profileDropdown){
            profileBtn.addEventListener('click', function(e){
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(){
                profileDropdown.classList.remove('show');
            });

            profileDropdown.addEventListener('click', function(e){
                e.stopPropagation();
            });
        }

        // Sticky navbar shadow on scroll
        var dashNav = document.querySelector('.dash-navbar');
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
                // When the element's top reaches 69px (navbar is 68px), it's sticking
                if (rect.top <= 69) {
                    stickyFilters.classList.add('is-sticky');
                } else {
                    stickyFilters.classList.remove('is-sticky');
                }
            });
        }
    </script>

</body>
</html>