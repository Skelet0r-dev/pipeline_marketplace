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
require_once __DIR__ . '/listing_reactions.php';
$conn = db_connect();
listing_reactions_ensure_schema($conn);
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
        $section_post_key = isset($_POST['add_listing']) ? 'section' : 'edit_section';
        $section=isset($_POST[$section_post_key]) ? trim($_POST[$section_post_key]) : '';
        $categoryval='Course-Specific ('.$college . ($section ? ' - '.$section : '') . ')';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($file_path); ?>" class="img-profile" alt="Profile Picture" id="profileBtn">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-mobile-nav">
                        <div class="dropdown-profile-header">
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Profile">
                            <div>
                                <div class="dropdown-profile-name"><?php echo htmlspecialchars($firstname); ?></div>
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

    <!-- Profile Section -->
    <div class="sf-profile-section">
        <div class="sf-profile-row">
            <div class="sf-avatar-wrap">
                <img src="<?php echo htmlspecialchars($file_path); ?>" class="sf-avatar" alt="Storefront Avatar">
                <div class="sf-verified"><i class="bi bi-check"></i></div>
            </div>
            <div class="sf-info">
                <div class="sf-name-row">
                    <h2 class="sf-name"><?php echo htmlspecialchars($fullname); ?></h2>
                    <span class="sf-badge"><i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($department . ' - ' . $section); ?></span>
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
            <button class="sf-tab active" data-tab="listings"><i class="bi bi-tag"></i> Listings</button>
            <button class="sf-tab" data-tab="sold"><i class="bi bi-check-circle-fill"></i> Sold</button>
        </div>
    </div>

    <!-- ── Flash Toast ── -->
    <?php if($modal_success!=''): ?>
    <div class="sf-toast" id="sfToast">
        <span><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($modal_success); ?></span>
        <button class="sf-toast-close" onclick="document.getElementById('sfToast').style.display='none'"><i class="bi bi-x"></i></button>
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
            echo '<button class="sf-card-edit-btn" onclick="event.stopPropagation(); openEditModal(this.closest(\'.sf-card\'))"><i class="bi bi-pencil"></i> Edit</button>';
            
            // ── SOLD Button ──
            echo '<form method="POST" action="storefront.php" style="display:inline;" onclick="event.stopPropagation()">';
            echo '<input type="hidden" name="mark_listing_id" value="'.$item['LISTING_ID'].'">';
            echo '<button type="submit" name="mark_sold" class="sf-card-sold-btn" onclick="return confirm(\'Mark this item as SOLD?\')"><i class="bi bi-check-circle-fill"></i> Sold</button>';
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
            echo '<div class="sf-empty-icon"><i class="bi bi-tag"></i></div>';
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
            echo '<button class="sf-card-edit-btn" onclick="event.stopPropagation(); openEditModal(this.closest(\'.sf-card\'))"><i class="bi bi-pencil"></i> Edit</button>';
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
            echo '<div class="sf-empty-icon"><i class="bi bi-box"></i></div>';
            echo '<p class="sf-empty-text">Sold items will appear here.</p>';
            echo '</div>';
        }
        ?>
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
                                        <span class="detail-meta-label"><i class="bi bi-geo-alt"></i> Meet-up Spot</span>
                                        <span class="detail-meta-val" id="detailMeetup"></span>
                                    </div>
                                    <div class="detail-meta-row">
                                        <span class="detail-meta-label"><i class="bi bi-credit-card"></i> Preferred Payment</span>
                                        <span class="detail-meta-val" id="detailPayment"></span>
                                    </div>
                                    <div class="detail-meta-row">
                                        <span class="detail-meta-label"><i class="bi bi-calendar"></i> Date Posted</span>
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
                    <h5 class="modal-title" id="editListingModalLabel"><i class="bi bi-pencil"></i> Edit Listing</h5>
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
                                        <option value="Academics"><i class="bi bi-book"></i> Academics</option>
                                        <option value="Electronics and Tech"><i class="bi bi-laptop"></i> Electronics & Tech</option>
                                        <option value="Clothing & Apparel"><i class="bi bi-tag"></i> Clothing & Apparel</option>
                                        <option value="Hobbies & Lifestyle"><i class="bi bi-bicycle"></i> Hobbies & Lifestyle</option>
                                        <option value="Food"><i class="bi bi-basket"></i> Food</option>
                                        <option value="Events & Tickets"><i class="bi bi-ticket-perforated"></i> Events & Tickets</option>
                                        <option value="Course-Specific"><i class="bi bi-journal-text"></i> Course-Specific</option>
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
                                    <option value="CEAT">CEAT</option>
                                    <option value="CLAC">CLAC</option>
                                    <option value="CBAA">CBAA</option>
                                    <option value="COS">COS</option>
                                    <option value="CICS">CICS</option>
                                    <option value="COED">COED</option>
                                    <option value="CCJE">CCJE</option>
                                    <option value="CTHM">CTHM</option>
                                    <option value="COL">COL</option>
                                </select>
                            </div>

                            <div class="mt-2 section-row-hidden" id="editSectionRow">
                                <label class="listing-label">Section</label>
                                <input type="text" name="edit_section" id="editSectionInput" class="listing-input" placeholder="e.g. CS31">
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
                                    <div class="img-upload-icon"><i class="bi bi-image"></i></div>
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
                        <button type="submit" name="delete_listing" class="btn-delete-listing"><i class="bi bi-trash"></i> Delete</button>
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
                                        <option value="Academics"><i class="bi bi-book"></i> Academics</option>
                                        <option value="Electronics and Tech"><i class="bi bi-laptop"></i> Electronics & Tech</option>
                                        <option value="Clothing & Apparel"><i class="bi bi-tag"></i> Clothing & Apparel</option>
                                        <option value="Hobbies & Lifestyle"><i class="bi bi-bicycle"></i> Hobbies & Lifestyle</option>
                                        <option value="Food"><i class="bi bi-basket"></i> Food</option>
                                        <option value="Events & Tickets"><i class="bi bi-ticket-perforated"></i> Events & Tickets</option>
                                        <option value="Course-Specific"><i class="bi bi-journal-text"></i> Course-Specific</option>
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
                                <select name="college" id="collegeSelect" class="listing-select">
                                    <option value="" disabled selected>Select College</option>
                                    <option value="CEAT">CEAT</option>
                                    <option value="CLAC">CLAC</option>
                                    <option value="CBAA">CBAA</option>
                                    <option value="COS">COS</option>
                                    <option value="CICS">CICS</option>
                                    <option value="COED">COED</option>
                                    <option value="CCJE">CCJE</option>
                                    <option value="CTHM">CTHM</option>
                                    <option value="COL">COL</option>
                                </select>
                            </div>

                            <div class="mt-2 section-row-hidden" id="sectionRow">
                                <label class="listing-label">Section</label>
                                <input type="text" name="section" id="sectionInput" class="listing-input" placeholder="e.g. CS31">
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
                                    <div class="img-upload-icon"><i class="bi bi-image"></i></div>
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
    <script>
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
            toast.style.cssText = `position:fixed; top:24px; right:24px; width:320px; background:white; border-left:5px solid #087832; box-shadow:0 15px 35px rgba(0,0,0,0.15); border-radius:12px; padding:16px; display:flex; gap:12px; z-index:10001; font-family:'DM Sans', sans-serif; transition: all 0.5s ease;`;
            toast.innerHTML = `<img src="${notif.avatar}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:700;color:#087832;font-size:14px;">${notif.sender}</div><div style="color:#666;font-size:13px;">${notif.message}</div></div>`;
            document.body.appendChild(toast);
            
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(0)';
            }, 10);

            setTimeout(() => { 
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(50px)';
                setTimeout(() => toast.remove(), 500);
            }, 6000);
        }

        if ("Notification" in window && Notification.permission !== "denied" && Notification.permission !== "granted") {
            Notification.requestPermission();
        }
        checkNotifications();
        setInterval(checkNotifications, 10000);
    </script>
</body>
</html>
<?php db_close($conn); ?>
