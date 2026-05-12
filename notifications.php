<?php
// ============================================================
// notifications.php  –  Global Pipeline Notifications
// ============================================================
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/listing_reactions.php';
$conn = db_connect();

$userId = $_SESSION['user_id'];

// Get user info for navbar
$sql="SELECT * FROM USERS WHERE USER_ID=?";
$result=db_query($conn,$sql, [$userId]);
$user=db_fetch_assoc($result);
$firstname=$user['FIRST_NAME'];

// Get profile image
$sqlimg="SELECT FILE_PATH FROM USER_IMG WHERE USER_ID=?";
$resultimg=db_query($conn,$sqlimg, [$userId]);
$rowimg=db_fetch_assoc($resultimg);
$file_path=$rowimg['FILE_PATH'] ?? 'assets/img/avatar.png';

// Fetch All Notifications (history)
// We join with LISTING_LIKES and LISTING_COMMENTS to get full context if needed, 
// but since we have a NOTIFICATIONS table now, let's use that for speed.
$sqlNotifs = "SELECT N.*, U.FIRST_NAME, U.LAST_NAME, U.USERNAME, UI.FILE_PATH AS AVATAR, L.TITLE
              FROM NOTIFICATIONS N
              JOIN USERS U ON N.SENDER_ID = U.USER_ID
              LEFT JOIN USER_IMG UI ON N.SENDER_ID = UI.USER_ID
              JOIN LISTINGS L ON N.LISTING_ID = L.LISTING_ID
              WHERE N.USER_ID = ?
              ORDER BY N.CREATED_AT DESC";
$resNotifs = db_query($conn, $sqlNotifs, [$userId]);
$notifications = [];
while($row = db_fetch_assoc($resNotifs)){
    $row['CREATED_AT_FORMATTED'] = date('M d, Y g:i A', strtotime($row['CREATED_AT']));
    $notifications[] = $row;
}

// Mark all as read when visiting this page
db_query($conn, "UPDATE NOTIFICATIONS SET IS_READ = 1 WHERE USER_ID = ?", [$userId]);

db_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .notif-container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .notif-card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .notif-header { padding: 24px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center; }
        .notif-title { font-weight: 800; font-size: 24px; color: #087832; margin: 0; }
        .notif-list { list-style: none; padding: 0; margin: 0; }
        .notif-item { padding: 20px 24px; border-bottom: 1px solid #f9f9f9; display: flex; gap: 16px; transition: background 0.2s; text-decoration: none; color: inherit; }
        .notif-item:hover { background: #f8fafc; }
        .notif-item.unread { background: #f0faf4; }
        .notif-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .notif-content { flex: 1; }
        .notif-user { font-weight: 700; color: #1e293b; margin-right: 4px; }
        .notif-handle { color: #64748b; font-size: 13px; font-weight: 400; }
        .notif-msg { color: #475569; font-size: 15px; margin: 4px 0; }
        .notif-listing { color: #087832; font-weight: 600; text-decoration: none; }
        .notif-listing:hover { text-decoration: underline; }
        .notif-time { color: #94a3b8; font-size: 12px; }
        .notif-empty { padding: 60px; text-align: center; color: #94a3b8; }
        .notif-empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="body">

    <!-- Navbar -->
    <div class="dash-navbar">
        <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo"></a>
        <div class="dash-nav-links">
            <a href="dashboard.php" class="dash-nav-link">Browse Products</a>
            <a href="storefront.php" class="dash-nav-link">My Storefront</a>
            <a href="edit_profile.php" class="dash-nav-link">My Profile</a>
            <a href="saved_listings.php" class="dash-nav-link" title="Saved Listings">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-bookmark-star-fill" viewBox="0 0 16 16" style="vertical-align: middle; margin-top: -3px;">
                    <path fill-rule="evenodd" d="M2 15.5V2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.74.439L8 13.069l-5.26 2.87A.5.5 0 0 1 2 15.5M8.16 4.1a.178.178 0 0 0-.32 0l-.634 1.285a.18.18 0 0 1-.134.098l-1.42.206a.178.178 0 0 0-.098.303L6.58 6.993c.042.041.061.1.051.158L6.39 8.565a.178.178 0 0 0 .258.187l1.27-.668a.18.18 0 0 1 .165 0l1.27.668a.178.178 0 0 0 .257-.187L9.368 7.15a.18.18 0 0 1 .05-.158l1.028-1.001a.178.178 0 0 0-.098-.303l-1.42-.206a.18.18 0 0 1-.134-.098z"/>
                </svg>
            </a>
            <!-- Notification Bell moved next to Bookmark -->
            <a href="notifications.php" class="dash-nav-link active" id="navNotifLink" title="Notifications">
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

    <div class="notif-container">
        <div class="notif-card">
            <div class="notif-header">
                <h2 class="notif-title">Notifications</h2>
                <span class="badge bg-success"><?php echo count($notifications); ?> Total</span>
            </div>
            <div class="notif-list">
                <?php if(empty($notifications)): ?>
                    <div class="notif-empty">
                        <div class="notif-empty-icon"><i class="bi bi-bell"></i></div>
                        <p>No notifications yet. Activity on your listings will appear here!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($notifications as $n): ?>
                        <a href="listing.php?id=<?php echo $n['LISTING_ID']; ?>" class="notif-item <?php echo $n['IS_READ'] ? '' : 'unread'; ?>">
                            <img src="<?php echo htmlspecialchars($n['AVATAR'] ?: 'assets/img/avatar.png'); ?>" class="notif-avatar">
                            <div class="notif-content">
                                <div>
                                    <span class="notif-user"><?php echo htmlspecialchars($n['FIRST_NAME'].' '.$n['LAST_NAME']); ?></span>
                                    <span class="notif-handle">@<?php echo htmlspecialchars($n['USERNAME']); ?></span>
                                </div>
                                <div class="notif-msg">
                                    <?php echo $n['TYPE'] === 'LIKE' ? 'reacted to' : 'commented on'; ?> your listing: 
                                    <span class="notif-listing">"<?php echo htmlspecialchars($n['TITLE']); ?>"</span>
                                </div>
                                <div class="notif-time"><?php echo $n['CREATED_AT_FORMATTED']; ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('profileBtn')?.addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('profileDropdown').classList.toggle('show');
        });
        window.addEventListener('click', function() {
            document.getElementById('profileDropdown')?.classList.remove('show');
        });
    </script>
</body>
</html>
