<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: dashboard.php"); exit; }

require_once __DIR__ . '/db.php';
$conn = db_connect();
if($conn === false) die(db_last_error());

$loginId = (int)$_SESSION['user_id'];
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($profileId <= 0){ header("Location: dashboard.php"); exit; }
if($profileId === $loginId){ header("Location: storefront.php"); exit; }

function normalizeCategoryLabel($category){
    if($category === null){ return ''; }
    if(stripos($category, 'Course-Specific') === 0){ return $category; }
    $map = [
        'Clothing and Apparel'  => 'Clothing & Apparel',
        'Hobbies and Lifestyle' => 'Hobbies & Lifestyle',
        'Events and Tickets'    => 'Events & Tickets'
    ];
    return $map[$category] ?? $category;
}

$meStmt = db_query($conn, "SELECT FIRST_NAME FROM USERS WHERE USER_ID=?", [$loginId]);
$me = db_fetch_assoc($meStmt);
$meImgStmt = db_query($conn, "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID=?", [$loginId]);
$meImg = db_fetch_assoc($meImgStmt);
$meAvatar = $meImg['FILE_PATH'] ?? 'assets/img/default_avatar.png';

$profileStmt = db_query(
    $conn,
    "SELECT U.USER_ID, U.FIRST_NAME, U.LAST_NAME, U.USERNAME, U.CYS, U.EMAIL, UI.FILE_PATH AS AVATAR
     FROM USERS U
     LEFT JOIN USER_IMG UI ON U.USER_ID = UI.USER_ID
     WHERE U.USER_ID=?",
    [$profileId]
);
$profile = $profileStmt ? db_fetch_assoc($profileStmt) : null;
if(!$profile){ header("Location: dashboard.php"); exit; }

$listingCountStmt = db_query($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE USER_ID=? AND `STATUS`='Available'", [$profileId]);
$listingCountRow = db_fetch_assoc($listingCountStmt);
$listingCount = (int)$listingCountRow['CNT'];

$soldCountStmt = db_query($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE USER_ID=? AND `STATUS`='Sold'", [$profileId]);
$soldCountRow = db_fetch_assoc($soldCountStmt);
$soldCount = (int)$soldCountRow['CNT'];

$listingsStmt = db_query(
    $conn,
    "SELECT L.*, I.FILE_PATH AS IMG
     FROM LISTINGS L
     LEFT JOIN LISTING_IMG I ON L.LISTING_ID=I.LISTING_ID AND I.IS_PRIMARY=1
     WHERE L.USER_ID=? AND L.`STATUS`='Available'
     ORDER BY L.DATE_POSTED DESC",
    [$profileId]
);

$fullname = $profile['FIRST_NAME'] . ' ' . $profile['LAST_NAME'];
$avatar = $profile['AVATAR'] ?? 'assets/img/default_avatar.png';
$messageLink = 'mailto:' . rawurlencode($profile['EMAIL']) . '?subject=' . rawurlencode('Pipeline Marketplace Inquiry');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fullname); ?> - Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/storefront.css">
</head>
<body class="body">
    <div class="dash-navbar">
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo">
        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo htmlspecialchars($me['FIRST_NAME'] ?? ''); ?></span>
            </div>
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($meAvatar); ?>" class="img-profile" alt="Profile Picture" id="profileBtn">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-profile-header">
                        <img src="<?php echo htmlspecialchars($meAvatar); ?>" alt="Profile">
                        <span class="dropdown-profile-name"><?php echo htmlspecialchars($me['FIRST_NAME'] ?? ''); ?></span>
                    </div>
                    <a href="dashboard.php" class="dropdown-item-custom"><span class="item-icon">B</span> Browse Products</a>
                    <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon">S</span> My Storefront</a>
                    <a href="edit_profile.php" class="dropdown-item-custom"><span class="item-icon">P</span> My Profile</a>
                    <div class="dropdown-divider-custom"></div>
                    <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon">X</span> Log Out</a>
                </div>
            </div>
        </div>
    </div>
    <div class="dash-header-bar"></div>

    <div class="sf-profile-section public-profile-section">
        <div class="sf-profile-row">
            <div class="sf-avatar-wrap">
                <img src="<?php echo htmlspecialchars($avatar); ?>" class="sf-avatar" alt="Profile Avatar">
                <div class="sf-verified">✓</div>
            </div>
            <div class="sf-info">
                <div class="sf-name-row">
                    <h2 class="sf-name"><?php echo htmlspecialchars($fullname); ?></h2>
                    <span class="sf-badge"><?php echo htmlspecialchars($profile['CYS']); ?></span>
                </div>
                <p class="sf-handle">@<?php echo htmlspecialchars($profile['USERNAME']); ?></p>
                <div class="sf-stats">
                    <div class="sf-stat">
                        <span class="sf-stat-num"><?php echo $listingCount; ?></span>
                        <span class="sf-stat-label">Listings</span>
                    </div>
                    <div class="sf-stat-div"></div>
                    <div class="sf-stat">
                        <span class="sf-stat-num"><?php echo $soldCount; ?></span>
                        <span class="sf-stat-label">Sold</span>
                    </div>
                </div>
            </div>
            <div class="sf-actions public-profile-actions">
                <a class="sf-btn-add public-message-btn" href="<?php echo htmlspecialchars($messageLink); ?>">Message</a>
                <button type="button" class="public-report-toggle" id="toggleUserReport">Report Profile</button>
            </div>
        </div>
    </div>

    <div class="public-report-modal" id="userReportPanel" hidden>
        <div class="public-report-backdrop" id="userReportBackdrop"></div>
        <form class="public-report-dialog" id="userReportForm" enctype="multipart/form-data">
            <div class="public-report-head">
                <h3>Report this profile</h3>
                <button type="button" id="closeUserReport" aria-label="Close report form">×</button>
            </div>
            <input type="hidden" name="reported_user_id" value="<?php echo $profileId; ?>">
            <label for="userReportReason">Reason</label>
            <select id="userReportReason" name="report_reason" required>
                <option value="" disabled selected>Select a reason</option>
                <option value="Suspicious account">Suspicious account</option>
                <option value="Harassment or abuse">Harassment or abuse</option>
                <option value="Scam or fraud concern">Scam or fraud concern</option>
                <option value="Impersonation">Impersonation</option>
                <option value="Other">Other</option>
            </select>
            <label for="userReportDetails">Justification</label>
            <textarea id="userReportDetails" name="report_details" maxlength="1000" placeholder="Explain why this profile should be reviewed." required></textarea>
            <label for="proofPhoto">Proof photo (optional)</label>
            <input id="proofPhoto" name="proof_photo" type="file" accept=".jpg,.jpeg,.png,.webp">
            <div class="public-report-actions">
                <button type="button" class="public-report-cancel" id="cancelUserReport">Cancel</button>
                <button type="submit" class="public-report-submit" id="submitUserReport">Submit Report</button>
            </div>
            <div class="public-report-feedback" id="userReportFeedback" hidden></div>
        </form>
    </div>

    <div class="sf-content">
        <div class="public-section-title">
            <h3>Active Listings</h3>
        </div>
        <div class="sf-grid">
            <?php
            $hasListings = false;
            while($item = db_fetch_assoc($listingsStmt)){
                $hasListings = true;
                $imgpath = $item['IMG'] ? $item['IMG'] : 'assets/img/no_image.png';
                $condclass = $item['CONDITION']=='New' ? 'cond-new' : ($item['CONDITION']=='Like New' ? 'cond-great' : 'cond-good');
                $categoryLabel = normalizeCategoryLabel($item['CATEGORY']);
                echo '<a class="sf-card public-listing-card" href="listing.php?id='.(int)$item['LISTING_ID'].'">';
                echo '<div class="sf-card-img-wrap">';
                echo '<img src="'.htmlspecialchars($imgpath).'" class="sf-card-img-real" alt="'.htmlspecialchars($item['TITLE']).'">';
                echo '<span class="sf-card-cat">'.htmlspecialchars($categoryLabel).'</span>';
                echo '</div>';
                echo '<div class="sf-card-body">';
                echo '<p class="sf-card-title">'.htmlspecialchars($item['TITLE']).'</p>';
                echo '<div class="sf-card-footer">';
                echo '<span class="sf-card-price">PHP '.number_format($item['PRICE'],2).'</span>';
                echo '<span class="sf-card-cond '.$condclass.'">'.htmlspecialchars($item['CONDITION']).'</span>';
                echo '</div>';
                echo '</div>';
                echo '</a>';
            }
            if(!$hasListings){
                echo '<div class="sf-empty sf-empty-fullrow">';
                echo '<div class="sf-empty-icon">+</div>';
                echo '<p class="sf-empty-text">This user has no active listings right now.</p>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <script>
        const profileBtn = document.getElementById('profileBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if(profileBtn && profileDropdown){
            profileBtn.addEventListener('click', function(e){
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
            document.addEventListener('click', function(){
                profileDropdown.classList.remove('show');
            });
            profileDropdown.addEventListener('click', function(e){ e.stopPropagation(); });
        }

        const reportPanel = document.getElementById('userReportPanel');
        const reportBackdrop = document.getElementById('userReportBackdrop');
        const toggleReport = document.getElementById('toggleUserReport');
        const closeReport = document.getElementById('closeUserReport');
        const cancelReport = document.getElementById('cancelUserReport');
        const reportForm = document.getElementById('userReportForm');
        const reportFeedback = document.getElementById('userReportFeedback');
        const submitReport = document.getElementById('submitUserReport');

        function setReportVisible(visible){
            reportPanel.hidden = !visible;
            document.body.classList.toggle('public-report-open', visible);
        }

        function showReportFeedback(message, isError){
            reportFeedback.hidden = false;
            reportFeedback.textContent = message;
            reportFeedback.className = 'public-report-feedback ' + (isError ? 'is-error' : 'is-success');
        }

        toggleReport.addEventListener('click', function(){ setReportVisible(true); });
        reportBackdrop.addEventListener('click', function(){ setReportVisible(false); });
        closeReport.addEventListener('click', function(){ setReportVisible(false); });
        cancelReport.addEventListener('click', function(){ setReportVisible(false); });

        reportForm.addEventListener('submit', function(e){
            e.preventDefault();
            submitReport.disabled = true;
            submitReport.textContent = 'Submitting...';
            reportFeedback.hidden = true;

            fetch('report_user.php', { method: 'POST', body: new FormData(reportForm) })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if(data.error){
                        showReportFeedback(data.error, true);
                        return;
                    }
                    showReportFeedback(data.message || 'Profile report submitted.', false);
                    reportForm.reset();
                })
                .catch(function(){
                    showReportFeedback('Could not submit your report right now. Please try again.', true);
                })
                .finally(function(){
                    submitReport.disabled = false;
                    submitReport.textContent = 'Submit Report';
                });
        });
    </script>
</body>
</html>
<?php db_close($conn); ?>
