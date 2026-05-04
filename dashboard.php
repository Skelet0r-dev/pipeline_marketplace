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
        $file_path=$rowprofile['FILE_PATH'];
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
            $file_path=$rowprofile['FILE_PATH'];
        }
    }
}

if(!isset($_POST['stdnum']) && $firstname==''){
    header("Location: dashboard.php");
    exit;
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
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo">
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

            <!-- Category pill quick-filters -->
            <div class="dash-filter-pills">
                <a href="browse.php?cat=all"                      class="dash-pill active">All Items</a>
                <a href="browse.php?cat=Clothing+%26+Apparel"     class="dash-pill">Clothing</a>
                <a href="browse.php?cat=Electronics"               class="dash-pill">Electronics</a>
                <a href="browse.php?cat=Books"                     class="dash-pill">Books</a>
                <a href="browse.php?cat=Hobbies+%26+Lifestyle"    class="dash-pill">Hobbies</a>
                <a href="browse.php?cat=Events+%26+Tickets"       class="dash-pill">Events</a>
                <a href="browse.php?cat=Course-Specific"           class="dash-pill">Course-Specific</a>
            </div>

            <!-- Category cards grid -->
            <div class="d-flex gap-3 mt-2 flex-wrap">
                <a href="browse.php?cat=Academics" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-acad">
                        <img src="assets/img/academics.svg" class="img-acad" alt="Academics Icon">
                        <p class="p-acad mb-0">Academics</p>
                    </div>
                </a>
                <a href="browse.php?cat=Electronics and Tech" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-tech">
                        <img src="assets/img/keyboard.svg" class="img-tech" alt="Keyboard Icon">
                        <p class="p-tech mb-0">Electronics</p>
                    </div>
                </a>
                <a href="browse.php?cat=Clothing%20%26%20Apparel" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-clothing">
                        <img src="assets/img/shirts.svg" class="img" alt="Shirt">
                        <p class="p-clothing mb-0">Clothing</p>
                    </div>
                </a>
                <a href="browse.php?cat=Hobbies%20%26%20Lifestyle" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-hobbies">
                        <img src="assets/img/labubus.svg" class="img" alt="Labubu">
                        <p class="p-hobbies mb-0">Hobbies</p>
                    </div>
                </a>
                <a href="browse.php?cat=Food" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-food">
                        <img src="assets/img/cookies.svg" class="img" alt="Cookies Icon">
                        <p class="p-cookies mb-0">Food</p>
                    </div>
                </a>
                <a href="browse.php?cat=Events%20%26%20Tickets" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-events">
                        <img src="assets/img/tickets.svg" class="img" alt="Tickets Icon">
                        <p class="p-events mb-0">Events</p>
                    </div>
                </a>
                <a href="browse.php?cat=Course-Specific" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-specific">
                        <img src="assets/img/electronics.svg" class="img" alt="Electronics Icon">
                        <p class="p-specific mb-0">Course-Specific</p>
                    </div>
                </a>
                <a href="browse.php?cat=all" class="category-link">
                    <div class="d-flex flex-column align-items-center justify-content-center square-allitems">
                        <img src="assets/img/cart.svg" class="img" alt="Cart Icon">
                        <p class="p-allitems mb-0">All Items</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- RIGHT: video -->
        <div class="dash-hero-right">
            <div class="video-crop">
                <video src="assets/img/dashboard-final.mp4" autoplay muted loop playsinline poster="thumbnail.jpg">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>

    </div>

<?php else: ?>

    <!-- ── LOGIN FORM WITH ERROR ── -->
    <div class="login-header">
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo-light" alt="Pipeline Logo">
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
    </script>

</body>
</html>
