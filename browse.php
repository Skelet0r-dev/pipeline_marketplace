<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: dashboard.php"); exit; }

$serverName=".\SQLEXPRESS";
$connectionOptions=["Database"=>"pipeline_db", "Uid"=>"", "PWD"=>""];
$conn=sqlsrv_connect($serverName,$connectionOptions);

$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$loginId = $_SESSION['user_id'];

// Fetch current user info for Navbar
$sqlUser = "SELECT FIRST_NAME FROM dbo.[USERS] WHERE USER_ID='$loginId'";
$resUser = sqlsrv_query($conn, $sqlUser);
$userRow = sqlsrv_fetch_array($resUser, SQLSRV_FETCH_ASSOC);

// Fetch Profile Image for Navbar
$sqlImgNavbar = "SELECT FILE_PATH FROM dbo.[USER_IMG] WHERE USER_ID='$loginId'";
$resImgNav = sqlsrv_query($conn, $sqlImgNavbar);
$navImgRow = sqlsrv_fetch_array($resImgNav, SQLSRV_FETCH_ASSOC);
$nav_file_path = $navImgRow['FILE_PATH'] ?? 'assets/img/default_avatar.png';

// BUILD THE MAIN QUERY (Filtering by Category)
// We join with USERS to show who the seller is
$sql = "SELECT L.*, I.FILE_PATH, U.FIRST_NAME, U.LAST_NAME 
        FROM dbo.[LISTINGS] L
        LEFT JOIN dbo.[LISTING_IMG] I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
        JOIN dbo.[USERS] U ON L.USER_ID = U.USER_ID
        WHERE L.STATUS = 'Available'";

if($category !== 'all'){
    // Handle "Course-Specific" which might have sub-tags like "(CEAT)"
    if($category == 'Course-Specific'){
        $sql .= " AND L.CATEGORY LIKE 'Course-Specific%'";
    } else {
        $sql .= " AND L.CATEGORY = ?";
        $params = [$category];
    }
}

$stmt = ($category !== 'all' && $category !== 'Course-Specific') 
        ? sqlsrv_query($conn, $sql, $params) 
        : sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse <?php echo htmlspecialchars($category); ?> – Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/browse.css"> <!-- New CSS file -->
</head>
<body class="body">

    <!-- NAVBAR (Same as dashboard) -->
    <div class="dash-navbar">
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Logo">
        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo htmlspecialchars($userRow['FIRST_NAME']); ?></span>
            </div>
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($nav_file_path); ?>" class="img-profile" id="profileBtn">
                <!-- Dropdown content here (copy from storefront.php) -->
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-success text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Browse</li>
                  </ol>
                </nav>
                <h2 class="fw-bold browse-title">
                    <?php echo $category == 'all' ? 'All Campus Listings' : htmlspecialchars($category); ?>
                </h2>
            </div>
            <span class="text-muted">Discover items from fellow Lasallians</span>
        </div>

        <div class="row g-4">
            <?php while($item = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
            <div class="col-md-3">
                <div class="sf-card browse-card">
                    <div class="sf-card-img-wrap">
                        <img src="<?php echo $item['FILE_PATH'] ? htmlspecialchars($item['FILE_PATH']) : 'assets/img/no_image.png'; ?>" class="sf-card-img-real">
                        <span class="sf-card-cat"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                    </div>
                    <div class="sf-card-body">
                        <p class="sf-card-title"><?php echo htmlspecialchars($item['TITLE']); ?></p>
                        <p class="seller-name">👤 <?php echo htmlspecialchars($item['FIRST_NAME'] . ' ' . $item['LAST_NAME']); ?></p>
                        <div class="sf-card-footer">
                            <span class="sf-card-price">₱<?php echo number_format($item['PRICE'], 2); ?></span>
                            <span class="sf-card-cond cond-<?php echo strtolower(str_replace(' ', '', $item['CONDITION'])); ?>">
                                <?php echo $item['CONDITION']; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>