<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$serverName=".\SQLEXPRESS";
$connectionOptions=[
    "Database"=>"pipeline_db",
    "Uid"=>"",
    "PWD"=>""
];
$conn=sqlsrv_connect($serverName,$connectionOptions);
if($conn==false)
    die(print_r(sqlsrv_errors(),true));

$loginId=$_SESSION['user_id'];

// Get user info
$sql="SELECT * FROM dbo.[USERS] WHERE USER_ID='$loginId'";
$result=sqlsrv_query($conn,$sql);
$user=sqlsrv_fetch_array($result,SQLSRV_FETCH_ASSOC);

$firstname=$user['FIRST_NAME'];
$lastname=$user['LAST_NAME'];
$fullname=$firstname.' '.$lastname;
$cys=$user['CYS'];


// Get profile image
$sqlimg="SELECT FILE_PATH FROM dbo.[USER_IMG] WHERE USER_ID='$loginId'";
$resultimg=sqlsrv_query($conn,$sqlimg);
$rowimg=sqlsrv_fetch_array($resultimg,SQLSRV_FETCH_ASSOC);
$file_path=$rowimg['FILE_PATH'];

// Get listing count
$sqlcount="SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE USER_ID='$loginId' AND STATUS='Available'";
$resultcount=sqlsrv_query($conn,$sqlcount);
$rowcount=sqlsrv_fetch_array($resultcount,SQLSRV_FETCH_ASSOC);
$listing_count=$rowcount['CNT'];

// Get sold count
$sqlsold="SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE USER_ID='$loginId' AND STATUS='Sold'";
$resultsold=sqlsrv_query($conn,$sqlsold);
$rowsold=sqlsrv_fetch_array($resultsold,SQLSRV_FETCH_ASSOC);
$sold_count=$rowsold['CNT'];

// Handle Add Listing POST
$modal_error='';
// Pick up flash success from session (set after redirect)
$modal_success=isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
unset($_SESSION['flash_success']);
if(isset($_POST['add_listing'])){
    $title=trim($_POST['title']);
    $description=trim($_POST['description']);
    $price=trim($_POST['price']);
    $category=$_POST['category'];
    $condition=$_POST['condition'];
    $status='Available';
    $college=isset($_POST['college']) ? trim($_POST['college']) : '';
    $meetup=trim($_POST['meetup_spot']);
    $payment=trim($_POST['payment_method']);

    // If course-specific, append college to category
    $categoryval=$category;
    if($category=='Course-Specific' && $college!=''){
        $categoryval='Course-Specific ('.$college.')';
    }

    // Insert listing
    $sqladd="INSERT INTO dbo.[LISTINGS] (USER_ID,TITLE,DESCRIPTION,PRICE,CATEGORY,CONDITION,STATUS,MEETUP_SPOT,PAYMENT_METHOD)
             VALUES ('$loginId','$title','$description','$price','$categoryval','$condition','$status','$meetup','$payment')";
    $resultadd=sqlsrv_query($conn,$sqladd);

    if($resultadd){
        // Get the new listing ID
        $sqllastid="SELECT TOP 1 LISTING_ID FROM dbo.[LISTINGS] WHERE USER_ID='$loginId' ORDER BY LISTING_ID DESC";
        $resultlastid=sqlsrv_query($conn,$sqllastid);
        $rowlastid=sqlsrv_fetch_array($resultlastid,SQLSRV_FETCH_ASSOC);
        $newlistingid=$rowlastid['LISTING_ID'];

        // Handle image upload
        if(isset($_FILES['listing_img']) && $_FILES['listing_img']['error']==0){
            $imgname=$_FILES['listing_img']['name'];
            $imgtmp=$_FILES['listing_img']['tmp_name'];
            $imgext=strtolower(pathinfo($imgname,PATHINFO_EXTENSION));
            $allowed=['jpg','jpeg','png','webp'];

            if(in_array($imgext,$allowed)){
                $newname='listing_'.$newlistingid.'_'.time().'.'.$imgext;
                $uploadpath='listings/'.$newname;
                if(move_uploaded_file($imgtmp,$uploadpath)){
                    $sqlimginsert="INSERT INTO dbo.[LISTING_IMG] (LISTING_ID,FILE_PATH,IS_PRIMARY) VALUES ('$newlistingid','$uploadpath','1')";
                    sqlsrv_query($conn,$sqlimginsert);
                }
            }
        }

        sqlsrv_close($conn);
        $_SESSION['flash_success']='Listing added successfully!';
        header("Location: storefront.php");
        exit;

    } else {
        $modal_error='Failed to add listing. Please try again.';
    }
}

// Handle Delete Listing POST
if(isset($_POST['delete_listing'])){
    $deleteid=trim($_POST['edit_listing_id']);

    // Only owner can delete
    $sqldelete="DELETE FROM dbo.[LISTING_IMG] WHERE LISTING_ID='$deleteid'";
    sqlsrv_query($conn,$sqldelete);

    $sqldeletelisting="DELETE FROM dbo.[LISTINGS] WHERE LISTING_ID='$deleteid' AND USER_ID='$loginId'";
    $resultdelete=sqlsrv_query($conn,$sqldeletelisting);

    if($resultdelete){
        sqlsrv_close($conn);
        $_SESSION['flash_success']='Listing deleted successfully.';
        header("Location: storefront.php");
        exit;
    } else {
        $edit_error='Failed to delete listing. Please try again.';
    }
}
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

    // If course-specific, append college
    $categoryval=$category;
    if($category=='Course-Specific' && $college!=''){
        $categoryval='Course-Specific ('.$college.')';
    }

    // Update listing — only owner can edit
    $sqlupdate="UPDATE dbo.[LISTINGS]
                SET TITLE='$title', DESCRIPTION='$description', PRICE='$price',
                    CATEGORY='$categoryval', CONDITION='$condition', STATUS='$status',
                    MEETUP_SPOT='$meetup', PAYMENT_METHOD='$payment'
                WHERE LISTING_ID='$editid' AND USER_ID='$loginId'";
    $resultupdate=sqlsrv_query($conn,$sqlupdate);

    if($resultupdate){
        // Handle new image if uploaded
        if(isset($_FILES['edit_listing_img']) && $_FILES['edit_listing_img']['error']==0){
            $imgname=$_FILES['edit_listing_img']['name'];
            $imgtmp=$_FILES['edit_listing_img']['tmp_name'];
            $imgext=strtolower(pathinfo($imgname,PATHINFO_EXTENSION));
            $allowed=['jpg','jpeg','png','webp'];

            if(in_array($imgext,$allowed)){
                $newname='listing_'.$editid.'_'.time().'.'.$imgext;
                $uploadpath='listings/'.$newname;
                if(move_uploaded_file($imgtmp,$uploadpath)){
                    // Delete old image record and insert new
                    sqlsrv_query($conn,"DELETE FROM dbo.[LISTING_IMG] WHERE LISTING_ID='$editid'");
                    $sqlimgupdate="INSERT INTO dbo.[LISTING_IMG] (LISTING_ID,FILE_PATH,IS_PRIMARY) VALUES ('$editid','$uploadpath','1')";
                    sqlsrv_query($conn,$sqlimgupdate);
                }
            }
        }

        sqlsrv_close($conn);
        $_SESSION['flash_success']='Listing updated successfully!';
        header("Location: storefront.php");
        exit;

    } else {
        $edit_error='Failed to update listing. Please try again.';
    }
}

// Get active listings
$sqllistings="SELECT L.*, I.FILE_PATH AS IMG
              FROM dbo.[LISTINGS] L
              LEFT JOIN dbo.[LISTING_IMG] I ON L.LISTING_ID=I.LISTING_ID AND I.IS_PRIMARY=1
              WHERE L.USER_ID='$loginId' AND L.STATUS='Available'
              ORDER BY L.DATE_POSTED DESC";
$resultlistings=sqlsrv_query($conn,$sqllistings);

// Get sold listings
$sqlsoldlist="SELECT L.*, I.FILE_PATH AS IMG
              FROM dbo.[LISTINGS] L
              LEFT JOIN dbo.[LISTING_IMG] I ON L.LISTING_ID=I.LISTING_ID AND I.IS_PRIMARY=1
              WHERE L.USER_ID='$loginId' AND L.STATUS='Sold'
              ORDER BY L.DATE_POSTED DESC";
$resultsoldlist=sqlsrv_query($conn,$sqlsoldlist);
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
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo">
        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo htmlspecialchars($firstname); ?></span>
            </div>
            <div class="profile-wrapper">
                <img src="<?php echo htmlspecialchars($file_path); ?>" class="img-profile" alt="Profile Picture" id="profileBtn">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-profile-header">
                        <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Profile">
                        <span class="dropdown-profile-name"><?php echo htmlspecialchars($firstname); ?></span>
                    </div>
                    <a href="dashboard.php" class="dropdown-item-custom"><span class="item-icon">🏬</span> Browse Products</a>
                    <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon">🏪</span> My Storefront</a>
                    <a href="profile.php" class="dropdown-item-custom"><span class="item-icon">👤</span> My Profile</a>
                    <a href="purchases.php" class="dropdown-item-custom"><span class="item-icon">🛍️</span> Purchases</a>
                    <a href="settings.php" class="dropdown-item-custom"><span class="item-icon">⚙️</span> Settings</a>
                    <div class="dropdown-divider-custom"></div>
                    <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon">🚪</span> Log Out</a>
                </div>
            </div>
        </div>
    </div>
    <div class="dash-header-bar"></div>

    <!-- Profile Section -->
    <div class="sf-profile-section">
        <div class="sf-profile-row">

            <!-- Avatar -->
            <div class="sf-avatar-wrap">
                <img src="<?php echo htmlspecialchars($file_path); ?>" class="sf-avatar" alt="Storefront Avatar">
                <div class="sf-verified">✓</div>
            </div>

            <!-- Info -->
            <div class="sf-info">
                <div class="sf-name-row">
                    <h2 class="sf-name"><?php echo htmlspecialchars($fullname); ?></h2>
                    <span class="sf-badge">🎓 <?php echo htmlspecialchars($cys); ?></span>
                </div>
                <p class="sf-handle">@<?php echo strtolower(str_replace(' ','.',htmlspecialchars($fullname))); ?> </p>

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
                    <div class="sf-stat-div"></div>
                    <div class="sf-stat">
                        <span class="sf-stat-num">— ⭐</span>
                        <span class="sf-stat-label">Rating</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
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
            <button class="sf-tab" data-tab="reviews">⭐ Reviews</button>
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
        while($item=sqlsrv_fetch_array($resultlistings, SQLSRV_FETCH_ASSOC)){
            $haslistings=true;
            $imgpath=$item['IMG'] ? $item['IMG'] : 'assets/img/no_image.png';
            $condclass=$item['CONDITION']=='New' ? 'cond-new' : ($item['CONDITION']=='Like New' ? 'cond-great' : 'cond-good');
            $meetup=isset($item['MEETUP_SPOT']) ? htmlspecialchars($item['MEETUP_SPOT']) : '—';
            $payment=isset($item['PAYMENT_METHOD']) && $item['PAYMENT_METHOD']!='' ? htmlspecialchars($item['PAYMENT_METHOD']) : '—';
            $desc=htmlspecialchars($item['DESCRIPTION'] ? $item['DESCRIPTION'] : '');
            $dateposted=$item['DATE_POSTED'] ? ($item['DATE_POSTED'] instanceof DateTime ? $item['DATE_POSTED']->format('M d, Y') : date('M d, Y', strtotime($item['DATE_POSTED']))) : '—';
            echo '<div class="sf-card" ';
            echo 'data-id="'.$item['LISTING_ID'].'" ';
            echo 'data-title="'.htmlspecialchars($item['TITLE']).'" ';
            echo 'data-price="'.number_format($item['PRICE'],2).'" ';
            echo 'data-category="'.htmlspecialchars($item['CATEGORY']).'" ';
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
            echo '<span class="sf-card-cat">'.htmlspecialchars($item['CATEGORY']).'</span>';
            echo '<div class="sf-card-hover">';
            echo '<button class="sf-card-view">View Item</button>';
            echo '<button class="sf-card-edit-btn" onclick="event.stopPropagation(); openEditModal(this.closest(\'.sf-card\'))">✏️ Edit</button>';
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
        while($item=sqlsrv_fetch_array($resultsoldlist, SQLSRV_FETCH_ASSOC)){
            $hassold=true;
            $imgpath=$item['IMG'] ? $item['IMG'] : 'assets/img/no_image.png';
            $condclass=$item['CONDITION']=='New' ? 'cond-new' : ($item['CONDITION']=='Like New' ? 'cond-great' : 'cond-good');
            $meetup=isset($item['MEETUP_SPOT']) ? htmlspecialchars($item['MEETUP_SPOT']) : '—';
            $payment=isset($item['PAYMENT_METHOD']) && $item['PAYMENT_METHOD']!='' ? htmlspecialchars($item['PAYMENT_METHOD']) : '—';
            $desc=htmlspecialchars($item['DESCRIPTION'] ? $item['DESCRIPTION'] : '');
            $dateposted=$item['DATE_POSTED'] ? ($item['DATE_POSTED'] instanceof DateTime ? $item['DATE_POSTED']->format('M d, Y') : date('M d, Y', strtotime($item['DATE_POSTED']))) : '—';
            echo '<div class="sf-card" ';
            echo 'data-id="'.$item['LISTING_ID'].'" ';
            echo 'data-title="'.htmlspecialchars($item['TITLE']).'" ';
            echo 'data-price="'.number_format($item['PRICE'],2).'" ';
            echo 'data-category="'.htmlspecialchars($item['CATEGORY']).'" ';
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
            echo '<span class="sf-card-cat">'.htmlspecialchars($item['CATEGORY']).'</span>';
            echo '<div class="sf-card-hover">';
            echo '<button class="sf-card-edit-btn" onclick="event.stopPropagation(); openEditModal(this.closest(\'.sf-card\'))">✏️ Edit</button>';
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

    <!-- ── REVIEWS TAB ── -->
    <div class="sf-content d-none" id="tab-reviews">
        <div class="sf-reviews">
            <div class="sf-empty">
                <div class="sf-empty-icon">⭐</div>
                <p class="sf-empty-text">Reviews will appear here once enabled.</p>
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

                        <!-- Left column -->
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
                                        <option value="Clothing and Apparel">👕 Clothing & Apparel</option>
                                        <option value="Hobbies and Lifestyle">🐇 Hobbies & Lifestyle</option>
                                        <option value="Food">🍪 Food</option>
                                        <option value="Events and Tickets">🎟️ Events & Tickets</option>
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

                            <!-- College dropdown for Course-Specific -->
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

                            <!-- Meetup Spot -->
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

                            <!-- Status -->
                            <div class="mt-2">
                                <label class="listing-label">Status</label>
                                <select name="edit_status" id="editStatus" class="listing-select" required>
                                    <option value="Available">Available</option>
                                    <option value="Sold">Sold</option>
                                </select>
                            </div>

                            <!-- Payment Method -->
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

                        <!-- Right column: image -->
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
                            <!-- Current image preview -->
                            <div class="edit-current-img-wrap">
                                <p class="listing-label mt-2">Current Photo</p>
                                <img src="" id="editCurrentImg" class="edit-current-img" alt="Current">
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="delete_listing" class="btn-delete-listing" onclick="return confirmDelete()">🗑️ Delete Listing</button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn-cancel-listing" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_listing" class="btn-add-listing">Save Changes</button>
                    </div>
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
                    <h5 class="modal-title" id="addListingModalLabel">＋ Add New Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="storefront.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                    <?php if($modal_error!=''): ?>
                    <div class="modal-alert-error"><?php echo htmlspecialchars($modal_error); ?></div>
                    <?php endif; ?>

                    <div class="row g-3">

                        <!-- Left column -->
                        <div class="col-md-7">

                            <div class="mb-3">
                                <label class="listing-label">Item Title</label>
                                <input type="text" name="title" class="listing-input" placeholder="e.g. Calculus Textbook 2nd Ed." maxlength="100" required>
                            </div>

                            <div class="mb-3">
                                <label class="listing-label">Description</label>
                                <textarea name="description" class="listing-textarea" placeholder="Describe your item — condition details, inclusions, etc."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="listing-label">Price (₱)</label>
                                <input type="number" name="price" class="listing-input" placeholder="0.00" min="0" step="0.01" required>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="listing-label">Category</label>
                                    <select name="category" class="listing-select" id="categorySelect" required>
                                        <option value="" disabled selected>Select</option>
                                        <option value="Academics">📚 Academics</option>
                                        <option value="Electronics and Tech">💻 Electronics & Tech</option>
                                        <option value="Clothing and Apparel">👕 Clothing & Apparel</option>
                                        <option value="Hobbies and Lifestyle">🐇 Hobbies & Lifestyle</option>
                                        <option value="Food">🍪 Food</option>
                                        <option value="Events and Tickets">🎟️ Events & Tickets</option>
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

                            <!-- College dropdown, shown only for Course-Specific -->
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

                            <!-- Preferred Meetup Spot -->
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

                            <!-- Payment Method -->
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

                            <!-- Hidden default status -->
                            <input type="hidden" name="status" value="Available">

                        </div>

                        <!-- Right column: image upload -->
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
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-listing" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_listing" class="btn-add-listing">Post Listing</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile dropdown
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
            profileDropdown.addEventListener('click', function(e){ e.stopPropagation(); });
        }

        // Tab switching
        var tabs = document.querySelectorAll('.sf-tab');
        tabs.forEach(function(tab){
            tab.addEventListener('click', function(){
                tabs.forEach(function(t){ t.classList.remove('active'); });
                this.classList.add('active');
                var target = this.dataset.tab;
                document.querySelectorAll('.sf-content').forEach(function(c){ c.classList.add('d-none'); });
                document.getElementById('tab-' + target).classList.remove('d-none');
            });
        });

        // Image preview
        var listingImgInput = document.getElementById('listingImgInput');
        var imgPreview = document.getElementById('imgPreview');
        var uploadBox = document.getElementById('uploadBox');
        var uploadPrompt = document.getElementById('uploadPrompt');

        listingImgInput.addEventListener('change', function(){
            var file = this.files[0];
            if(file){
                var reader = new FileReader();
                reader.onload = function(e){
                    imgPreview.src = e.target.result;
                    imgPreview.style.display = 'block';
                    uploadPrompt.style.display = 'none';
                    uploadBox.style.borderColor = '#606c38';
                };
                reader.readAsDataURL(file);
            }
        });

        // Open item detail modal
        function openItemModal(card) {
            var title    = card.getAttribute('data-title');
            var price    = card.getAttribute('data-price');
            var category = card.getAttribute('data-category');
            var condition= card.getAttribute('data-condition');
            var condclass= card.getAttribute('data-condclass');
            var meetup   = card.getAttribute('data-meetup');
            var payment  = card.getAttribute('data-payment');
            var desc     = card.getAttribute('data-desc');
            var date     = card.getAttribute('data-date');
            var img      = card.getAttribute('data-img');
            var status   = card.getAttribute('data-status');

            document.getElementById('detailModalTitle').textContent = title;
            document.getElementById('detailTitle').textContent = title;
            document.getElementById('detailPrice').textContent = '₱' + price;
            document.getElementById('detailCategory').textContent = category;
            document.getElementById('detailMeetup').textContent = meetup;
            document.getElementById('detailPayment').textContent = payment ? payment : '—';
            document.getElementById('detailDate').textContent = date;
            document.getElementById('detailImg').src = img;
            document.getElementById('detailDesc').textContent = desc !== '' ? desc : 'No description provided.';

            var condEl = document.getElementById('detailCond');
            condEl.textContent = condition;
            condEl.className = 'sf-card-cond ' + condclass;

            var statusEl = document.getElementById('detailStatus');
            if(status === 'Sold'){
                statusEl.textContent = 'SOLD';
                statusEl.className = 'detail-status-badge detail-status-sold';
            } else {
                statusEl.textContent = 'Available';
                statusEl.className = 'detail-status-badge detail-status-available';
            }

            var modal = new bootstrap.Modal(document.getElementById('itemDetailModal'));
            modal.show();
        }
        var categorySelect = document.getElementById('categorySelect');
        var collegeRow = document.getElementById('collegeRow');
        var collegeSelect = document.getElementById('collegeSelect');

        categorySelect.addEventListener('change', function(){
            if(this.value == 'Course-Specific'){
                collegeRow.classList.remove('college-row-hidden');
                collegeSelect.setAttribute('required', 'required');
            } else {
                collegeRow.classList.add('college-row-hidden');
                collegeSelect.removeAttribute('required');
                collegeSelect.value = '';
            }
        });

        function confirmDelete() {
            return confirm('Are you sure you want to delete this listing? This cannot be undone.');
        }

        // Open edit modal and pre-fill fields
        function openEditModal(card) {
            var id       = card.getAttribute('data-id');
            var title    = card.getAttribute('data-title');
            var price    = card.getAttribute('data-price').replace(/,/g, '');
            var category = card.getAttribute('data-category');
            var condition= card.getAttribute('data-condition');
            var meetup   = card.getAttribute('data-meetup');
            var payment  = card.getAttribute('data-payment');
            var desc     = card.getAttribute('data-desc');
            var img      = card.getAttribute('data-img');
            var status   = card.getAttribute('data-status');

            document.getElementById('editListingId').value   = id;
            document.getElementById('editTitle').value       = title;
            document.getElementById('editDescription').value = desc;
            document.getElementById('editPrice').value       = price;
            document.getElementById('editCurrentImg').src    = img;

            // Set category - handle Course-Specific (COLLEGE) format
            var catSelect = document.getElementById('editCategory');
            var catVal = category;
            if(category.indexOf('Course-Specific') === 0){
                catVal = 'Course-Specific';
                var collegeMatch = category.match(/\(([^)]+)\)/);
                if(collegeMatch){
                    document.getElementById('editCollegeRow').classList.remove('college-row-hidden');
                    document.getElementById('editCollege').value = collegeMatch[1];
                }
            } else {
                document.getElementById('editCollegeRow').classList.add('college-row-hidden');
            }
            setSelectValue(catSelect, catVal);
            setSelectValue(document.getElementById('editCondition'), condition);
            setSelectValue(document.getElementById('editMeetup'), meetup);
            setSelectValue(document.getElementById('editStatus'), status);
            setSelectValue(document.getElementById('editPayment'), payment);

            // Reset upload box
            document.getElementById('editListingImgInput').value = '';
            document.getElementById('editImgPreview').style.display = 'none';
            document.getElementById('editUploadPrompt').style.display = 'block';

            var modal = new bootstrap.Modal(document.getElementById('editListingModal'));
            modal.show();
        }

        function setSelectValue(selectEl, val) {
            for(var i = 0; i < selectEl.options.length; i++){
                if(selectEl.options[i].value === val){
                    selectEl.selectedIndex = i;
                    break;
                }
            }
        }

        // Edit modal category → college toggle
        var editCategorySelect = document.getElementById('editCategory');
        var editCollegeRow = document.getElementById('editCollegeRow');
        var editCollegeSelect = document.getElementById('editCollege');

        editCategorySelect.addEventListener('change', function(){
            if(this.value == 'Course-Specific'){
                editCollegeRow.classList.remove('college-row-hidden');
                editCollegeSelect.setAttribute('required', 'required');
            } else {
                editCollegeRow.classList.add('college-row-hidden');
                editCollegeSelect.removeAttribute('required');
                editCollegeSelect.value = '';
            }
        });

        // Edit image preview
        var editImgInput = document.getElementById('editListingImgInput');
        var editImgPreview = document.getElementById('editImgPreview');
        var editUploadPrompt = document.getElementById('editUploadPrompt');

        editImgInput.addEventListener('change', function(){
            var file = this.files[0];
            if(file){
                var reader = new FileReader();
                reader.onload = function(e){
                    editImgPreview.src = e.target.result;
                    editImgPreview.style.display = 'block';
                    editUploadPrompt.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

    </script>

</body>
</html>
<?php sqlsrv_close($conn); ?>