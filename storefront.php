<?php
// ============================================================
// storefront.php  –  Pipeline My Storefront
// Changes from original:
//   1. "Mark as Available" button on sold items (reversible)
//   2. New "Activity" tab showing likes & comments on your listings
// ============================================================
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';
$conn = db_connect();
if($conn==false)
    die(db_last_error());

$loginId=$_SESSION['user_id'];

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

// Get user info
$sql="SELECT * FROM USERS WHERE USER_ID=?";
$result=db_query($conn,$sql, [$loginId]);
$user=db_fetch_assoc($result);

$firstname=$user['FIRST_NAME'];
$lastname=$user['LAST_NAME'];
$fullname=$firstname.' '.$lastname;
$college=$user['COLLEGE'];
$department=$user['DEPARTMENT'];
$section=$user['SECTION'];
$username=$user['USERNAME'];

// Get profile image
$sqlimg="SELECT FILE_PATH FROM USER_IMG WHERE USER_ID=?";
$resultimg=db_query($conn,$sqlimg, [$loginId]);
$rowimg=db_fetch_assoc($resultimg);
$file_path=$rowimg['FILE_PATH'] ?? 'assets/img/avatar.png';

// Get listing count
$sqlcount="SELECT COUNT(*) AS CNT FROM LISTINGS WHERE USER_ID=? AND `STATUS`='Available'";
$resultcount=db_query($conn,$sqlcount, [$loginId]);
$rowcount=db_fetch_assoc($resultcount);
$listing_count=$rowcount['CNT'];

// Get sold count
$sqlsold="SELECT COUNT(*) AS CNT FROM LISTINGS WHERE USER_ID=? AND `STATUS`='Sold'";
$resultsold=db_query($conn,$sqlsold, [$loginId]);
$rowsold=db_fetch_assoc($resultsold);
$sold_count=$rowsold['CNT'];

// ── Flash success from session ──────────────────────────────
$modal_success=isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
unset($_SESSION['flash_success']);

// ── Handle Add Listing POST ─────────────────────────────────
$modal_error='';
if(isset($_POST['add_listing']) && $_POST['add_listing']=='1'){
    $title=trim($_POST['title']);
    $description=trim($_POST['description']);
    $price=trim($_POST['price']);
    $category=$_POST['category'];
    $condition=$_POST['condition'];
    $status='Available';
    $college=isset($_POST['college']) ? trim($_POST['college']) : '';
    $meetup=trim($_POST['meetup_spot']);
    $payment=trim($_POST['payment_method']);

    $categoryval=$category;
    if($category=='Course-Specific' && $college!=''){
        $categoryval='Course-Specific ('.$college.')';
    }

    $sqladd="INSERT INTO LISTINGS (USER_ID,TITLE,DESCRIPTION,PRICE,CATEGORY,`CONDITION`,`STATUS`,MEETUP_SPOT,PAYMENT_METHOD)
             VALUES (?,?,?,?,?,?,?,?,?)";
    $paramsadd=[$loginId,$title,$description,$price,$categoryval,$condition,$status,$meetup,$payment];
    $resultadd=db_query($conn,$sqladd,$paramsadd);

    if($resultadd){
        $sqllastid="SELECT LISTING_ID FROM LISTINGS WHERE USER_ID=? ORDER BY LISTING_ID DESC LIMIT 1";
        $resultlastid=db_query($conn,$sqllastid, [$loginId]);
        $rowlastid=db_fetch_assoc($resultlastid);
        $newlistingid=$rowlastid['LISTING_ID'];

        if(isset($_FILES['listing_img']) && $_FILES['listing_img']['error']==0){
            $imgname=$_FILES['listing_img']['name'];
            $imgtmp=$_FILES['listing_img']['tmp_name'];
            $imgext=strtolower(pathinfo($imgname,PATHINFO_EXTENSION));
            $allowed=['jpg','jpeg','png','webp'];
            if(in_array($imgext,$allowed)){
                $newname='listing_'.$newlistingid.'_'.time().'.'.$imgext;
                $uploadpath='listings/'.$newname;
                if(move_uploaded_file($imgtmp,$uploadpath)){
                    $sqlimginsert="INSERT INTO LISTING_IMG (LISTING_ID,FILE_PATH,IS_PRIMARY) VALUES (?,?,1)";
                    db_query($conn,$sqlimginsert, [$newlistingid, $uploadpath]);
                }
            }
        }

        db_close($conn);
        $_SESSION['flash_success']='Listing added successfully!';
        header("Location: storefront.php");
        exit;
    } else {
        $modal_error='SQL Error: '.(db_last_error() ?: 'Unknown');
    }
}

// ── Handle Delete Listing POST ──────────────────────────────
if(isset($_POST['delete_listing'])){
    $deleteid=trim($_POST['edit_listing_id']);
    db_query($conn,"DELETE FROM LISTING_IMG WHERE LISTING_ID=?", [$deleteid]);
    $sqldeletelisting="DELETE FROM LISTINGS WHERE LISTING_ID=? AND USER_ID=?";
    $resultdelete=db_query($conn,$sqldeletelisting, [$deleteid, $loginId]);
    if($resultdelete){
        db_close($conn);
        $_SESSION['flash_success']='Listing deleted successfully.';
        header("Location: storefront.php");
        exit;
    } else {
        $edit_error='Failed to delete listing. Please try again.';
    }
}

// ── Handle Edit Listing POST ────────────────────────────────
$edit_error='';
if(isset($_POST['edit_listing'])){
    $editid=trim($_POST['edit_listing_id']);
    $title=trim($_POST['edit_title']);
    $description=trim($_POST['edit_description']);
    $price=trim($_POST['edit_price']);
    $category=$_POST['edit_category'];
    $condition=$_POST['edit_condition'];
    $status=$_POST['edit_status'];
    $college=isset($_POST['edit_college']) ? trim($_POST['edit_college']) : '';
    $meetup=trim($_POST['edit_meetup_spot']);
    $payment=trim($_POST['edit_payment_method']);

    $categoryval=$category;
    if($category=='Course-Specific' && $college!=''){
        $categoryval='Course-Specific ('.$college.')';
    }

    $sqlupdate="UPDATE LISTINGS
                SET TITLE=?, DESCRIPTION=?, PRICE=?,
                    CATEGORY=?, `CONDITION`=?, `STATUS`=?,
                    MEETUP_SPOT=?, PAYMENT_METHOD=?
                WHERE LISTING_ID=? AND USER_ID=?";
    $paramsupdate=[$title,$description,$price,$categoryval,$condition,$status,$meetup,$payment,$editid,$loginId];
    $resultupdate=db_query($conn,$sqlupdate,$paramsupdate);

    if($resultupdate){
        if(isset($_FILES['edit_listing_img']) && $_FILES['edit_listing_img']['error']==0){
            $imgname=$_FILES['edit_listing_img']['name'];
            $imgtmp=$_FILES['edit_listing_img']['tmp_name'];
            $imgext=strtolower(pathinfo($imgname,PATHINFO_EXTENSION));
            $allowed=['jpg','jpeg','png','webp'];
            if(in_array($imgext,$allowed)){
                $newname='listing_'.$editid.'_'.time().'.'.$imgext;
                $uploadpath='listings/'.$newname;
                if(move_uploaded_file($imgtmp,$uploadpath)){
                        db_query($conn,"DELETE FROM LISTING_IMG WHERE LISTING_ID=?", [$editid]);
                        $sqlimgupdate="INSERT INTO LISTING_IMG (LISTING_ID,FILE_PATH,IS_PRIMARY) VALUES (?,?,1)";
                        db_query($conn,$sqlimgupdate, [$editid, $uploadpath]);
                }
            }
        }
        db_close($conn);
        $_SESSION['flash_success']='Listing updated successfully!';
        header("Location: storefront.php");
        exit;
    } else {
        $edit_error='Failed to update listing. Please try again.';
    }
}

// ── NEW: Handle "Mark as Available" (reverse sold) ──────────
if(isset($_POST['mark_available'])){
    $markId = (int)trim($_POST['mark_listing_id']);
    $sqlMark = "UPDATE LISTINGS SET `STATUS`='Available' WHERE LISTING_ID=? AND USER_ID=?";
    $resMark = db_query($conn, $sqlMark, [$markId, $loginId]);
    if($resMark){
        db_close($conn);
        $_SESSION['flash_success'] = 'Listing is now Available again.';
        header("Location: storefront.php");
        exit;
    }
}

// ── NEW: Handle "Mark as Sold" ──────────────────────────────
if(isset($_POST['mark_sold'])){
    $markId = (int)trim($_POST['mark_listing_id']);
    $sqlMark = "UPDATE LISTINGS SET `STATUS`='Sold' WHERE LISTING_ID=? AND USER_ID=?";
    $resMark = db_query($conn, $sqlMark, [$markId, $loginId]);
    if($resMark){
        db_close($conn);
        $_SESSION['flash_success'] = 'Listing marked as Sold!';
        header("Location: storefront.php");
        exit;
    }
}

// ── Fetch active listings ───────────────────────────────────
$sqllistings="SELECT L.*, I.FILE_PATH AS IMG
              FROM LISTINGS L
              LEFT JOIN LISTING_IMG I ON L.LISTING_ID=I.LISTING_ID AND I.IS_PRIMARY=1
              WHERE L.USER_ID=? AND L.`STATUS`='Available'
              ORDER BY L.DATE_POSTED DESC";
$resultlistings=db_query($conn,$sqllistings, [$loginId]);

// ── Fetch sold listings ─────────────────────────────────────
$sqlsoldlist="SELECT L.*, I.FILE_PATH AS IMG
              FROM LISTINGS L
              LEFT JOIN LISTING_IMG I ON L.LISTING_ID=I.LISTING_ID AND I.IS_PRIMARY=1
              WHERE L.USER_ID=? AND L.`STATUS`='Sold'
              ORDER BY L.DATE_POSTED DESC";
$resultsoldlist=db_query($conn,$sqlsoldlist, [$loginId]);

// ── Fetch activity: likes on your listings ──────────────────
// (join with LISTING_LIKES and LISTING_COMMENTS tables)
$sqlLikes = "SELECT LL.LIKE_ID, LL.CREATED_AT,
                    L.TITLE, L.LISTING_ID,
                    U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
                    UI.FILE_PATH AS AVATAR
             FROM LISTING_LIKES LL
             JOIN LISTINGS L ON LL.LISTING_ID = L.LISTING_ID
             JOIN USERS U    ON LL.USER_ID = U.USER_ID
             LEFT JOIN USER_IMG UI ON LL.USER_ID = UI.USER_ID
             WHERE L.USER_ID = ?
             ORDER BY LL.CREATED_AT DESC";
$resLikes = db_query($conn, $sqlLikes, [$loginId]);
$activityLikes = [];
if($resLikes){
    while($row = db_fetch_assoc($resLikes)){
        $row['CREATED_AT'] = $row['CREATED_AT'] instanceof DateTime
            ? $row['CREATED_AT']->format('M d, Y g:i A')
            : date('M d, Y g:i A');
        $activityLikes[] = $row;
    }
}

// ── Fetch activity: comments on your listings ───────────────
$sqlComments = "SELECT LC.COMMENT_ID, LC.COMMENT_TEXT, LC.CREATED_AT,
                       L.TITLE, L.LISTING_ID,
                       U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
                       UI.FILE_PATH AS AVATAR
                FROM LISTING_COMMENTS LC
                JOIN LISTINGS L ON LC.LISTING_ID = L.LISTING_ID
                JOIN USERS U    ON LC.USER_ID = U.USER_ID
                LEFT JOIN USER_IMG UI ON LC.USER_ID = UI.USER_ID
                WHERE L.USER_ID = ?
                ORDER BY LC.CREATED_AT DESC";
$resComments = db_query($conn, $sqlComments, [$loginId]);
$activityComments = [];
if($resComments){
    while($row = db_fetch_assoc($resComments)){
        $row['CREATED_AT'] = $row['CREATED_AT'] instanceof DateTime
            ? $row['CREATED_AT']->format('M d, Y g:i A')
            : date('M d, Y g:i A');
        $activityComments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Storefront – Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/storefront.css">
</head>
<body class="body">

    <!-- Navbar -->
    <div class="dash-navbar">
        <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo"></a>
        
        <!-- Center Nav Links -->
        <div class="dash-nav-links">
            <a href="dashboard.php" class="dash-nav-link">Browse Products</a>
            <a href="storefront.php" class="dash-nav-link active">My Storefront</a>
            <a href="edit_profile.php" class="dash-nav-link">My Profile</a>
            <a href="saved_listings.php" class="dash-nav-link" title="Saved Listings">
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
                <img src="<?php echo htmlspecialchars($file_path); ?>" class="img-profile" alt="Profile Picture" id="profileBtn">
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon">🚪</span> Log Out</a>
                </div>
            </div>
        </div>
    </div>
    <div class="dash-header-bar"></div>

    <!-- Profile Section -->
    <div class="sf-profile-section">
        <div class="sf-profile-row">
            <div class="sf-avatar-wrap">
                <img src="<?php echo htmlspecialchars($file_path); ?>" class="sf-avatar" alt="Storefront Avatar">
                <div class="sf-verified">✓</div>
            </div>
            <div class="sf-info">
                <div class="sf-name-row">
                    <h2 class="sf-name"><?php echo htmlspecialchars($fullname); ?></h2>
                    <span class="sf-badge">🎓 <?php echo htmlspecialchars($department . ' - ' . $section); ?></span>
                </div>
                <p class="sf-handle">@<?php echo htmlspecialchars($username); ?></p>
                <div class="sf-stats">
                    <div class="sf-stat">
                        <span class="sf-stat-num"><?php echo $listing_count; ?></span>
                        <span class="sf-stat-label">Listings</span>
                    </div>
                    <div class="sf-stat-div"></div>
                    <div class="sf-stat">
                        <span class="sf-stat-num"><?php echo $sold_count; ?></span>
                        <span class="sf-stat-label">Sold</span>
                    </div>
                </div>
            </div>
            <div class="sf-actions">
                <button class="sf-btn-add" data-bs-toggle="modal" data-bs-target="#addListingModal">＋ Add Listing</button>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="sf-tabs-wrap">
        <div class="sf-tabs">
            <button class="sf-tab active" data-tab="listings">🏷️ Listings</button>
            <button class="sf-tab" data-tab="sold">✅ Sold</button>
            <button class="sf-tab" data-tab="activity">Activity</button>
        </div>
    </div>

    <!-- ── Flash Toast ── -->
    <?php if($modal_success!=''): ?>
    <div class="sf-toast" id="sfToast">
        <span>✅ <?php echo htmlspecialchars($modal_success); ?></span>
        <button class="sf-toast-close" onclick="document.getElementById('sfToast').style.display='none'">✕</button>
    </div>
    <?php endif; ?>

    <!-- ── LISTINGS TAB ── -->
    <div class="sf-content" id="tab-listings">
        <div class="sf-grid">
        <?php
        $haslistings=false;
        while($item=db_fetch_assoc($resultlistings)){
            $haslistings=true;
            $imgpath=$item['IMG'] ? $item['IMG'] : 'assets/img/no_image.png';
            $condclass=$item['CONDITION']=='New' ? 'cond-new' : ($item['CONDITION']=='Like New' ? 'cond-great' : 'cond-good');
            $meetup=isset($item['MEETUP_SPOT']) ? htmlspecialchars($item['MEETUP_SPOT']) : '—';
            $payment=isset($item['PAYMENT_METHOD']) && $item['PAYMENT_METHOD']!='' ? htmlspecialchars($item['PAYMENT_METHOD']) : '—';
            $desc=htmlspecialchars($item['DESCRIPTION'] ? $item['DESCRIPTION'] : '');
            $dateposted=$item['DATE_POSTED'] ? ($item['DATE_POSTED'] instanceof DateTime ? $item['DATE_POSTED']->format('M d, Y') : date('M d, Y', strtotime($item['DATE_POSTED']))) : '—';
            $categoryLabel = normalizeCategoryLabel($item['CATEGORY']);
            echo '<div class="sf-card" ';
            echo 'data-id="'.$item['LISTING_ID'].'" ';
            echo 'data-title="'.htmlspecialchars($item['TITLE']).'" ';
            echo 'data-price="'.number_format($item['PRICE'],2).'" ';
            echo 'data-category="'.htmlspecialchars($categoryLabel).'" ';
            echo 'data-condition="'.htmlspecialchars($item['CONDITION']).'" ';
            echo 'data-condclass="'.$condclass.'" ';
            echo 'data-meetup="'.$meetup.'" ';
            echo 'data-payment="'.$payment.'" ';
            echo 'data-desc="'.$desc.'" ';
            echo 'data-date="'.$dateposted.'" ';
            echo 'data-img="'.htmlspecialchars($imgpath).'" ';
            echo 'data-status="Available" ';
            echo 'onclick="openItemModal(this)">';
            echo '<div class="sf-card-img-wrap">';
            echo '<img src="'.htmlspecialchars($imgpath).'" class="sf-card-img-real" alt="'.htmlspecialchars($item['TITLE']).'">';
            echo '<span class="sf-card-cat">'.htmlspecialchars($categoryLabel).'</span>';
            echo '<div class="sf-card-hover">';
            echo '<button class="sf-card-view">View Item</button>';
            echo '<button class="sf-card-edit-btn" onclick="event.stopPropagation(); openEditModal(this.closest(\'.sf-card\'))">✏️ Edit</button>';
            
            // ── SOLD Button ──
            echo '<form method="POST" action="storefront.php" style="display:inline;" onclick="event.stopPropagation()">';
            echo '<input type="hidden" name="mark_listing_id" value="'.$item['LISTING_ID'].'">';
            echo '<button type="submit" name="mark_sold" class="sf-card-sold-btn" onclick="return confirm(\'Mark this item as SOLD?\')">✅ Sold</button>';
            echo '</form>';
            
            echo '</div>';
            echo '</div>';
            echo '<div class="sf-card-body">';
            echo '<p class="sf-card-title">'.htmlspecialchars($item['TITLE']).'</p>';
            echo '<div class="sf-card-footer">';
            echo '<span class="sf-card-price">₱'.number_format($item['PRICE'],2).'</span>';
            echo '<span class="sf-card-cond '.$condclass.'">'.htmlspecialchars($item['CONDITION']).'</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        if(!$haslistings){
            echo '<div class="sf-empty sf-empty-fullrow">';
            echo '<div class="sf-empty-icon">🏷️</div>';
            echo '<p class="sf-empty-text">No active listings yet. Add one!</p>';
            echo '</div>';
        }
        ?>
        </div>
    </div>

    <!-- ── SOLD TAB ── -->
    <div class="sf-content d-none" id="tab-sold">
        <div class="sf-grid">
        <?php
        $hassold=false;
        while($item=db_fetch_assoc($resultsoldlist)){
            $hassold=true;
            $imgpath=$item['IMG'] ? $item['IMG'] : 'assets/img/no_image.png';
            $condclass=$item['CONDITION']=='New' ? 'cond-new' : ($item['CONDITION']=='Like New' ? 'cond-great' : 'cond-good');
            $meetup=isset($item['MEETUP_SPOT']) ? htmlspecialchars($item['MEETUP_SPOT']) : '—';
            $payment=isset($item['PAYMENT_METHOD']) && $item['PAYMENT_METHOD']!='' ? htmlspecialchars($item['PAYMENT_METHOD']) : '—';
            $desc=htmlspecialchars($item['DESCRIPTION'] ? $item['DESCRIPTION'] : '');
            $dateposted=$item['DATE_POSTED'] ? ($item['DATE_POSTED'] instanceof DateTime ? $item['DATE_POSTED']->format('M d, Y') : date('M d, Y', strtotime($item['DATE_POSTED']))) : '—';
            $listingId = $item['LISTING_ID'];
            $categoryLabel = normalizeCategoryLabel($item['CATEGORY']);
            echo '<div class="sf-card" ';
            echo 'data-id="'.$listingId.'" ';
            echo 'data-title="'.htmlspecialchars($item['TITLE']).'" ';
            echo 'data-price="'.number_format($item['PRICE'],2).'" ';
            echo 'data-category="'.htmlspecialchars($categoryLabel).'" ';
            echo 'data-condition="'.htmlspecialchars($item['CONDITION']).'" ';
            echo 'data-condclass="'.$condclass.'" ';
            echo 'data-meetup="'.$meetup.'" ';
            echo 'data-payment="'.$payment.'" ';
            echo 'data-desc="'.$desc.'" ';
            echo 'data-date="'.$dateposted.'" ';
            echo 'data-img="'.htmlspecialchars($imgpath).'" ';
            echo 'data-status="Sold" ';
            echo 'onclick="openItemModal(this)">';
            echo '<div class="sf-card-img-wrap sf-card-img-wrap-sold">';
            echo '<img src="'.htmlspecialchars($imgpath).'" class="sf-card-img-real sf-card-img-sold" alt="'.htmlspecialchars($item['TITLE']).'">';
            echo '<div class="sf-card-sold-overlay"><span class="sf-card-sold-badge">SOLD</span></div>';
            echo '<span class="sf-card-cat">'.htmlspecialchars($categoryLabel).'</span>';
            echo '<div class="sf-card-hover">';
            echo '<button class="sf-card-edit-btn" onclick="event.stopPropagation(); openEditModal(this.closest(\'.sf-card\'))">✏️ Edit</button>';
            // ── NEW: Mark as Available button ──
            echo '<form method="POST" action="storefront.php" style="display:inline;" onclick="event.stopPropagation()">';
            echo '<input type="hidden" name="mark_listing_id" value="'.$listingId.'">';
            echo '<button type="submit" name="mark_available" class="sf-card-relist-btn" onclick="return confirm(\'Mark this item as Available again?\')">↩️ Relist</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '<div class="sf-card-body">';
            echo '<p class="sf-card-title">'.htmlspecialchars($item['TITLE']).'</p>';
            echo '<div class="sf-card-footer">';
            echo '<span class="sf-card-price">₱'.number_format($item['PRICE'],2).'</span>';
            echo '<span class="sf-card-cond '.$condclass.'">'.htmlspecialchars($item['CONDITION']).'</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        if(!$hassold){
            echo '<div class="sf-empty sf-empty-fullrow">';
            echo '<div class="sf-empty-icon">📦</div>';
            echo '<p class="sf-empty-text">Sold items will appear here.</p>';
            echo '</div>';
        }
        ?>
        </div>
    </div>

    <!-- ── ACTIVITY TAB (likes + comments on your listings) ── -->
    <div class="sf-content d-none" id="tab-activity">
        <div class="sf-activity-wrap">

            <!-- Likes feed -->
            <div class="sf-activity-col">
                <h4 class="sf-activity-heading">❤️ Likes</h4>
                <?php if(empty($activityLikes)): ?>
                <div class="sf-activity-empty">No likes yet on your listings.</div>
                <?php else: ?>
                <div class="sf-activity-list">
                    <?php foreach($activityLikes as $like): ?>
                    <div class="sf-activity-item">
                        <img src="<?php echo htmlspecialchars($like['AVATAR'] ?? 'assets/img/avatar.png'); ?>"
                             class="sf-activity-avatar" alt="Avatar">
                        <div class="sf-activity-body">
                            <span class="sf-activity-user"><?php echo htmlspecialchars($like['FIRST_NAME'].' '.$like['LAST_NAME']); ?></span>
                            <span class="sf-activity-handle">@<?php echo htmlspecialchars($like['USERNAME']); ?></span>
                            liked your listing
                            <a href="listing.php?id=<?php echo $like['LISTING_ID']; ?>" class="sf-activity-listing-link">
                                <?php echo htmlspecialchars($like['TITLE']); ?>
                            </a>
                        </div>
                        <span class="sf-activity-time"><?php echo htmlspecialchars($like['CREATED_AT']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Comments feed -->
            <div class="sf-activity-col">
                <h4 class="sf-activity-heading">💬 Comments</h4>
                <?php if(empty($activityComments)): ?>
                <div class="sf-activity-empty">No comments yet on your listings.</div>
                <?php else: ?>
                <div class="sf-activity-list">
                    <?php foreach($activityComments as $comment): ?>
                    <div class="sf-activity-item sf-activity-item-comment">
                        <img src="<?php echo htmlspecialchars($comment['AVATAR'] ?? 'assets/img/avatar.png'); ?>"
                             class="sf-activity-avatar" alt="Avatar">
                        <div class="sf-activity-body">
                            <span class="sf-activity-user"><?php echo htmlspecialchars($comment['FIRST_NAME'].' '.$comment['LAST_NAME']); ?></span>
                            <span class="sf-activity-handle">@<?php echo htmlspecialchars($comment['USERNAME']); ?></span>
                            commented on
                            <a href="listing.php?id=<?php echo $comment['LISTING_ID']; ?>" class="sf-activity-listing-link">
                                <?php echo htmlspecialchars($comment['TITLE']); ?>
                            </a>
                            <p class="sf-activity-comment-text">"<?php echo htmlspecialchars(mb_substr($comment['COMMENT_TEXT'],0,120)); ?><?php echo mb_strlen($comment['COMMENT_TEXT'])>120?'…':''; ?>"</p>
                        </div>
                        <span class="sf-activity-time"><?php echo htmlspecialchars($comment['CREATED_AT']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ── ITEM DETAIL MODAL ── -->
    <div class="modal fade" id="itemDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalTitle">Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-0">
                        <div class="col-md-5">
                            <img src="" class="detail-img" id="detailImg" alt="Item Image">
                        </div>
                        <div class="col-md-7">
                            <div class="detail-info">
                                <div class="detail-cat-row">
                                    <span class="detail-category" id="detailCategory"></span>
                                    <span class="sf-card-cond" id="detailCond"></span>
                                    <span class="detail-status-badge" id="detailStatus"></span>
                                </div>
                                <h4 class="detail-title" id="detailTitle"></h4>
                                <p class="detail-price" id="detailPrice"></p>
                                <p class="detail-desc" id="detailDesc"></p>
                                <div class="detail-meta">
                                    <div class="detail-meta-row">
                                        <span class="detail-meta-label">📍 Meet-up Spot</span>
                                        <span class="detail-meta-val" id="detailMeetup"></span>
                                    </div>
                                    <div class="detail-meta-row">
                                        <span class="detail-meta-label">💳 Preferred Payment</span>
                                        <span class="detail-meta-val" id="detailPayment"></span>
                                    </div>
                                    <div class="detail-meta-row">
                                        <span class="detail-meta-label">📅 Date Posted</span>
                                        <span class="detail-meta-val" id="detailDate"></span>
                                    </div>
                                </div>
                                <!-- Quick link to full listing page -->
                                <a href="" class="detail-view-full-btn" id="detailViewFull">View Full Listing →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── EDIT LISTING MODAL ── -->
    <div class="modal fade" id="editListingModal" tabindex="-1" aria-labelledby="editListingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editListingModalLabel">✏️ Edit Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="storefront.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_listing_id" id="editListingId">
                <div class="modal-body">

                    <?php if($edit_error!=''): ?>
                    <div class="modal-alert-error"><?php echo htmlspecialchars($edit_error); ?></div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-7">

                            <div class="mb-3">
                                <label class="listing-label">Item Title</label>
                                <input type="text" name="edit_title" id="editTitle" class="listing-input" maxlength="100" required>
                            </div>

                            <div class="mb-3">
                                <label class="listing-label">Description</label>
                                <textarea name="edit_description" id="editDescription" class="listing-textarea"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="listing-label">Price (₱)</label>
                                <input type="number" name="edit_price" id="editPrice" class="listing-input" min="0" step="0.01" required>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="listing-label">Category</label>
                                    <select name="edit_category" id="editCategory" class="listing-select" required>
                                        <option value="" disabled>Select</option>
                                        <option value="Academics">📚 Academics</option>
                                        <option value="Electronics and Tech">💻 Electronics & Tech</option>
                                        <option value="Clothing & Apparel">👕 Clothing & Apparel</option>
                                        <option value="Hobbies & Lifestyle">🐇 Hobbies & Lifestyle</option>
                                        <option value="Food">🍪 Food</option>
                                        <option value="Events & Tickets">🎟️ Events & Tickets</option>
                                        <option value="Course-Specific">🔬 Course-Specific</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="listing-label">Condition</label>
                                    <select name="edit_condition" id="editCondition" class="listing-select" required>
                                        <option value="" disabled>Select</option>
                                        <option value="New">New</option>
                                        <option value="Like New">Like New</option>
                                        <option value="Used">Used</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-2 college-row-hidden" id="editCollegeRow">
                                <label class="listing-label">College</label>
                                <select name="edit_college" id="editCollege" class="listing-select">
                                    <option value="" disabled selected>Select College</option>
                                    <option value="CCJE">CCJE</option>
                                    <option value="COED">COED</option>
                                    <option value="COL">COL</option>
                                    <option value="CICS">CICS</option>
                                    <option value="COS">COS</option>
                                    <option value="CTHM">CTHM</option>
                                    <option value="CBAA">CBAA</option>
                                    <option value="CLAC">CLAC</option>
                                    <option value="CEAT">CEAT</option>
                                </select>
                            </div>

                            <div class="mt-2">
                                <label class="listing-label">Preferred Meet-up Spot</label>
                                <select name="edit_meetup_spot" id="editMeetup" class="listing-select" required>
                                    <option value="" disabled>Select location</option>
                                    <option value="Gate 1">Gate 1</option>
                                    <option value="Gate 3">Gate 3</option>
                                    <option value="ULS">ULS</option>
                                    <option value="MTH BLDG">MTH BLDG</option>
                                    <option value="CBAA BLDG">CBAA BLDG</option>
                                    <option value="CEAT BLDG">CEAT BLDG</option>
                                    <option value="GMH BLDG">GMH BLDG</option>
                                    <option value="VBH BLDG">VBH BLDG</option>
                                    <option value="LDH BLDG">LDH BLDG</option>
                                    <option value="FCH BLDG">FCH BLDG</option>
                                    <option value="CTHM/CIH BLDG">CTHM/CIH BLDG</option>
                                    <option value="COS BLDG">COS BLDG</option>
                                    <option value="PCH BLDG">PCH BLDG</option>
                                    <option value="JFH BLDG">JFH BLDG</option>
                                    <option value="Food Square">Food Square</option>
                                </select>
                            </div>

                            <div class="mt-2">
                                <label class="listing-label">Status</label>
                                <select name="edit_status" id="editStatus" class="listing-select" required>
                                    <option value="Available">Available</option>
                                    <option value="Sold">Sold</option>
                                </select>
                            </div>

                            <div class="mt-2">
                                <label class="listing-label">Preferred Payment Method</label>
                                <select name="edit_payment_method" id="editPayment" class="listing-select" required>
                                    <option value="" disabled>Select payment method</option>
                                    <option value="Cash upon Meetup"> Cash upon Meetup</option>
                                    <option value="GCash"> GCash</option>
                                    <option value="Maya"> Maya</option>
                                    <option value="Bank Transfer"> Bank Transfer</option>
                                </select>
                            </div>

                        </div>

                        <div class="col-md-5 d-flex flex-column justify-content-start">
                            <label class="listing-label">Item Photo</label>
                            <div class="img-upload-box" id="editUploadBox" onclick="document.getElementById('editListingImgInput').click()">
                                <input type="file" name="edit_listing_img" id="editListingImgInput" accept=".jpg,.jpeg,.png,.webp">
                                <div id="editUploadPrompt">
                                    <div class="img-upload-icon">🖼️</div>
                                    <div class="img-upload-text">Click to replace photo</div>
                                    <div class="img-upload-sub">Leave blank to keep current</div>
                                </div>
                                <img src="" class="img-preview" id="editImgPreview" alt="Preview">
                            </div>
                            <div class="edit-current-img-wrap">
                                <p class="listing-label mt-2">Current Photo</p>
                                <img src="" id="editCurrentImg" class="edit-current-img" alt="Current">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="storefront.php" onsubmit="return confirmDelete()" style="margin:0;">
                        <input type="hidden" name="edit_listing_id" id="deleteListingId">
                        <button type="submit" name="delete_listing" class="btn-delete-listing">🗑️ Delete</button>
                    </form>
                    <button type="button" class="btn-cancel-listing" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_listing" class="btn-add-listing">Save Changes</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── ADD LISTING MODAL ── -->
    <div class="modal fade" id="addListingModal" tabindex="-1" aria-labelledby="addListingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addListingModalLabel">＋ New Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="storefront.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                    <?php if($modal_error!=''): ?>
                    <div class="modal-alert-error"><?php echo htmlspecialchars($modal_error); ?></div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-7">

                            <div class="mb-3">
                                <label class="listing-label">Item Title</label>
                                <input type="text" name="title" class="listing-input" maxlength="100" required placeholder="e.g. Calculus Textbook 9th Ed.">
                            </div>

                            <div class="mb-3">
                                <label class="listing-label">Description</label>
                                <textarea name="description" class="listing-textarea" placeholder="Describe the item (condition details, included items, etc.)"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="listing-label">Price (₱)</label>
                                <input type="number" name="price" class="listing-input" min="0" step="0.01" required placeholder="0.00">
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="listing-label">Category</label>
                                    <select name="category" class="listing-select" id="categorySelect" required>
                                        <option value="" disabled selected>Select</option>
                                        <option value="Academics">📚 Academics</option>
                                        <option value="Electronics and Tech">💻 Electronics & Tech</option>
                                        <option value="Clothing & Apparel">👕 Clothing & Apparel</option>
                                        <option value="Hobbies & Lifestyle">🐇 Hobbies & Lifestyle</option>
                                        <option value="Food">🍪 Food</option>
                                        <option value="Events & Tickets">🎟️ Events & Tickets</option>
                                        <option value="Course-Specific">🔬 Course-Specific</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="listing-label">Condition</label>
                                    <select name="condition" class="listing-select" required>
                                        <option value="" disabled selected>Select</option>
                                        <option value="New">New</option>
                                        <option value="Like New">Like New</option>
                                        <option value="Used">Used</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-2 college-row-hidden" id="collegeRow">
                                <label class="listing-label">College</label>
                                <select name="college" class="listing-select" id="collegeSelect">
                                    <option value="" disabled selected>Select College</option>
                                    <option value="CCJE">CCJE</option>
                                    <option value="COED">COED</option>
                                    <option value="COL">COL</option>
                                    <option value="CICS">CICS</option>
                                    <option value="COS">COS</option>
                                    <option value="CTHM">CTHM</option>
                                    <option value="CBAA">CBAA</option>
                                    <option value="CLAC">CLAC</option>
                                    <option value="CEAT">CEAT</option>
                                </select>
                            </div>

                            <div class="mt-2">
                                <label class="listing-label">Preferred Meet-up Spot</label>
                                <select name="meetup_spot" class="listing-select" required>
                                    <option value="" disabled selected>Select location</option>
                                    <option value="Gate 1">Gate 1</option>
                                    <option value="Gate 3">Gate 3</option>
                                    <option value="ULS">ULS</option>
                                    <option value="MTH BLDG">MTH BLDG</option>
                                    <option value="CBAA BLDG">CBAA BLDG</option>
                                    <option value="CEAT BLDG">CEAT BLDG</option>
                                    <option value="GMH BLDG">GMH BLDG</option>
                                    <option value="VBH BLDG">VBH BLDG</option>
                                    <option value="LDH BLDG">LDH BLDG</option>
                                    <option value="FCH BLDG">FCH BLDG</option>
                                    <option value="CTHM/CIH BLDG">CTHM/CIH BLDG</option>
                                    <option value="COS BLDG">COS BLDG</option>
                                    <option value="PCH BLDG">PCH BLDG</option>
                                    <option value="JFH BLDG">JFH BLDG</option>
                                    <option value="Food Square">Food Square</option>
                                </select>
                            </div>

                            <div class="mt-2">
                                <label class="listing-label">Preferred Payment Method</label>
                                <select name="payment_method" class="listing-select" required>
                                    <option value="" disabled selected>Select payment method</option>
                                    <option value="Cash upon Meetup"> Cash upon Meetup</option>
                                    <option value="GCash"> GCash</option>
                                    <option value="Maya"> Maya</option>
                                    <option value="Bank Transfer"> Bank Transfer</option>
                                </select>
                            </div>

                            <input type="hidden" name="status" value="Available">
                        </div>

                        <div class="col-md-5 d-flex flex-column justify-content-start">
                            <label class="listing-label">Item Photo</label>
                            <div class="img-upload-box" id="uploadBox" onclick="document.getElementById('listingImgInput').click()">
                                <input type="file" name="listing_img" id="listingImgInput" accept=".jpg,.jpeg,.png,.webp">
                                <div id="uploadPrompt">
                                    <div class="img-upload-icon">🖼️</div>
                                    <div class="img-upload-text">Click to upload photo</div>
                                    <div class="img-upload-sub">JPG, PNG, WEBP · Max 5MB</div>
                                </div>
                                <img src="" class="img-preview" id="imgPreview" alt="Preview">
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="add_listing" value="1">
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-listing" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add-listing">Post Listing</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/storefront.js"></script>
</body>
</html>
<?php db_close($conn); ?>