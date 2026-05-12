<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/square_config.php';
$conn = db_connect();
if ($conn == false) die(db_last_error());

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$loginId = $_SESSION['user_id'];
$updateSuccess = false;

// ─── 1. HANDLE PROFILE UPDATES (POST) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $fn   = $_POST['first_name'];
    $ln   = $_POST['last_name'];
    $college = $_POST['college'];
    $dept = $_POST['department'];
    $sec  = $_POST['section'];
    $sex  = $_POST['sex'];
    $un   = $_POST['username'];
    
    // Update basic user info
    $sqlUpd = "UPDATE USERS SET FIRST_NAME = ?, LAST_NAME = ?, COLLEGE = ?, DEPARTMENT = ?, SECTION = ?, SEX = ?, USERNAME = ? WHERE USER_ID = ?";
    $params = [$fn, $ln, $college, $dept, $sec, $sex, $un, $loginId];
    db_query($conn, $sqlUpd, $params);

    // Update Password if filled
    if (!empty($_POST['new_pw'])) {
        $currentPwInput = $_POST['current_pw'];
        $newPwInput     = $_POST['new_pw'];
        
        $sqlCheck = "SELECT `PASSWORD` FROM USERS WHERE USER_ID = ?";
        $resCheck = db_query($conn, $sqlCheck, [$loginId]);
        $rowCheck = db_fetch_assoc($resCheck);
        
        if (password_verify($currentPwInput, $rowCheck['PASSWORD'])) {
            $hashedNew = password_hash($newPwInput, PASSWORD_DEFAULT);
            $sqlPw = "UPDATE USERS SET `PASSWORD` = ? WHERE USER_ID = ?";
            db_query($conn, $sqlPw, [$hashedNew, $loginId]);
        }
    }

    // Handle File Upload for Profile Picture
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName    = "image_" . date("Y-m-d_His") . "_" . basename($_FILES['avatar']['name']);
        $destPath    = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Check if record exists in USER_IMG
            $sqlExist = "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID = ?";
            $resExist = db_query($conn, $sqlExist, [$loginId]);
            $imageRow = $resExist ? db_fetch_assoc($resExist) : null;
            $imageRecordExists = !empty($imageRow);
            
            if ($imageRecordExists) {
                $sqlImgUpd = "UPDATE USER_IMG SET IMG_NAME = ?, FILE_PATH = ? WHERE USER_ID = ?";
                db_query($conn, $sqlImgUpd, [$fileName, $destPath, $loginId]);
            } else {
                $sqlImgIns = "INSERT INTO USER_IMG (IMG_NAME, FILE_PATH, USER_ID) VALUES (?, ?, ?)";
                db_query($conn, $sqlImgIns, [$fileName, $destPath, $loginId]);
            }
        }
    }
    $updateSuccess = true;
}

// ─── 2. FETCH REFRESHED DATA ───
$sqlUser = "SELECT * FROM USERS WHERE USER_ID = ?";
$resUser = db_query($conn, $sqlUser, [$loginId]);
$rowUser = db_fetch_assoc($resUser);

$sqlImg  = "SELECT * FROM USER_IMG WHERE USER_ID = ?";
$resImg  = db_query($conn, $sqlImg, [$loginId]);
$rowImg  = db_fetch_assoc($resImg);

$firstname  = $rowUser['FIRST_NAME']  ?? '';
$lastname   = $rowUser['LAST_NAME']   ?? '';
$std_num    = $rowUser['STD_NUM']     ?? '';
$college    = $rowUser['COLLEGE']     ?? '';
$department = $rowUser['DEPARTMENT']  ?? '';
$section    = $rowUser['SECTION']     ?? '';
$sex        = $rowUser['SEX']         ?? '';
$username   = $rowUser['USERNAME']    ?? '';
$email      = $rowUser['EMAIL']       ?? '';
$file_path  = $rowImg['FILE_PATH']    ?? '';

$avatarSrc  = ($file_path && file_exists($file_path))
              ? htmlspecialchars($file_path)
              : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($firstname);

$colleges = ['CEAT', 'CLAC', 'CBAA', 'COS', 'CICS', 'COED', 'CCJE', 'CTHM'];

db_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Profile – Pipeline</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="assets/css/edit_profile.css"/>
  <!-- Square Web Payments SDK -->
  <script src="https://sandbox.web.squarecdn.com/v1/square.js" onerror="console.error('Square SDK failed to load'); document.getElementById('card-container').innerHTML = '<span style="color:red;">Square SDK blocked by browser or network.</span>';"></script>
</head>
<body class="body">

<!-- Navbar mirroring dashboard.php -->
  <div class="dash-navbar">
    <a href="dashboard.php"><img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo"></a>
    
    <!-- Center Nav Links -->
    <div class="dash-nav-links">
      <a href="dashboard.php" class="dash-nav-link">Browse Products</a>
      <a href="storefront.php" class="dash-nav-link">My Storefront</a>
      <a href="edit_profile.php" class="dash-nav-link active">My Profile</a>
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
        <img src="<?php echo $avatarSrc; ?>" class="img-profile" alt="Profile Picture" id="profileBtn">
        <div class="profile-dropdown" id="profileDropdown">
          <a href="edit_profile.php?tab=support" class="dropdown-item-custom"><span class="item-icon">💖</span> Support Us</a>
          <a href="logout.php" class="dropdown-item-custom logout"><span class="item-icon">🚪</span> Log Out</a>
        </div>
      </div>
    </div>
  </div>

<div class="dash-header-bar"></div>

<div class="page-wrapper">
  <form action="edit_profile.php" method="POST" enctype="multipart/form-data" id="profileForm">
    <div class="profile-card">
      
      <div class="card-header">
        <div class="avatar-wrap">
          <img id="header-avatar" src="<?php echo $avatarSrc; ?>" alt="Avatar"/>
          <div class="avatar-overlay" onclick="document.getElementById('avatar-file-input').click()">
            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            <span>Change</span>
          </div>
          <input type="file" id="avatar-file-input" name="avatar" accept="image/*" onchange="previewAvatar(event)" style="display:none"/>
        </div>
        <div class="header-info">
          <h1 id="header-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></h1>
          <p id="header-username">@<?php echo htmlspecialchars($username); ?></p>
          <div class="d-flex gap-2 flex-wrap">
            <span class="badge-cys" id="header-college"><?php echo htmlspecialchars($college); ?></span>
            <span class="badge-cys" id="header-department"><?php echo htmlspecialchars($department); ?></span>
            <span class="badge-cys" id="header-section"><?php echo htmlspecialchars($section); ?></span>
          </div>
        </div>
      </div>

      <div class="profile-tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('edit')">Settings</button>
        <button type="button" class="tab-btn" onclick="switchTab('support')">Support Us</button>
      </div>

      <div class="tab-content" id="tab-edit">
        <div class="card-body">
        <div class="section">
          <div class="section-label">
            <h3>Identity</h3>
            <p>Your basic name information visible on your profile.</p>
          </div>
          <div class="section-fields">
            <div class="field-group">
              <label class="field-label">First Name</label>
              <input class="field-input" name="first_name" id="first-name" type="text" value="<?php echo htmlspecialchars($firstname); ?>"/>
            </div>
            <div class="field-group">
              <label class="field-label">Last Name</label>
              <input class="field-input" name="last_name" id="last-name" type="text" value="<?php echo htmlspecialchars($lastname); ?>"/>
            </div>
          </div>
        </div>

        <div class="section">
          <div class="section-label">
            <h3>Account Details</h3>
            <p>Your login credentials and course year-section.</p>
          </div>
          <div class="section-fields three">
            <div class="field-group">
              <label class="field-label">Student Number</label>
              <div class="readonly-chip">
                <input class="field-input" type="text" value="<?php echo htmlspecialchars($std_num); ?>" disabled/>
                <div class="lock-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
              </div>
              <span class="field-hint">Cannot be changed.</span>
            </div>
            <div class="field-group">
              <label class="field-label">College</label>
              <select class="field-select" name="college" id="college" onchange="updateHeaderBadge('header-college', this.value)">
                <option value="" disabled <?php echo $college === '' ? 'selected' : ''; ?>>Select college…</option>
                <?php foreach ($colleges as $c): ?>
                  <option value="<?php echo $c; ?>" <?php echo $college === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field-group">
              <label class="field-label">Department</label>
              <input class="field-input" name="department" id="department" type="text" value="<?php echo htmlspecialchars($department); ?>" oninput="updateHeaderBadge('header-department', this.value)"/>
            </div>
            <div class="field-group">
              <label class="field-label">Section</label>
              <input class="field-input" name="section" id="section" type="text" value="<?php echo htmlspecialchars($section); ?>" oninput="updateHeaderBadge('header-section', this.value)"/>
            </div>
            <div class="field-group">
              <label class="field-label">Sex</label>
              <select class="field-select" name="sex">
                <option value="Female" <?php echo $sex==='Female'?'selected':''; ?>>Female</option>
                <option value="Male" <?php echo $sex==='Male'?'selected':''; ?>>Male</option>
                <option value="Prefer not to say" <?php echo $sex==='Prefer not to say'?'selected':''; ?>>Prefer not to say</option>
              </select>
            </div>
          </div>
        </div>

        <div class="section">
          <div class="section-label">
            <h3>Contact & Username</h3>
            <p>How other students find and reach you.</p>
          </div>
          <div class="section-fields">
            <div class="field-group">
              <label class="field-label">Username</label>
              <input class="field-input" name="username" id="username" type="text" value="<?php echo htmlspecialchars($username); ?>"/>
            </div>
            <div class="field-group">
              <label class="field-label">Email Address</label>
              <div class="readonly-chip">
                <input class="field-input" type="email" value="<?php echo htmlspecialchars($email); ?>" disabled/>
                <div class="lock-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
              </div>
              <span class="field-hint">Cannot be changed.</span>
            </div>
          </div>
        </div>

        <?php if (!empty($rowUser['PASSWORD'])): ?>
        <div class="section">
          <div class="section-label">
            <h3>Change Password</h3>
            <p>Leave blank to keep your current password.</p>
          </div>
          <div class="section-fields full">
            <div class="field-group">
              <label class="field-label">Current Password</label>
              <input class="field-input" name="current_pw" id="current-pw" type="password" placeholder="Enter current password"/>
            </div>
            <div class="field-group">
              <label class="field-label">New Password</label>
              <input class="field-input" name="new_pw" id="new-pw" type="password" placeholder="Enter new password" oninput="checkStrength(this.value)"/>
              <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-strength-fill"></div></div>
              <span class="pw-strength-label" id="pw-strength-label"></span>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="section">
          <div class="section-label">
            <h3>Security</h3>
            <p>Your account is managed via Microsoft SSO.</p>
          </div>
          <div class="section-fields">
             <div class="info-banner-green">
                <span class="info-icon"><i class="bi bi-info-circle"></i></span>
                <span style="font-weight: normal; font-size: 12px; font-family: 'DM Sans', sans-serif;">You are signed in with Microsoft. To change your password, please visit your DLSUD account settings.</span>
             </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="card-footer">
        <button class="btn-cancel" type="button" onclick="window.location.href='dashboard.php'">Cancel</button>
        <button class="btn-save" name="save_profile" type="submit">Save Changes</button>
      </div>
    </div>    <div class="tab-content" id="tab-support" style="display:none;">
      <div class="card-body" style="padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px;">
        
        <div class="checkout-container" style="width: 100%; max-width: 420px; text-align: center;">
          <div class="support-icon-big">💖</div>
          <h2 style="font-size: 24px; font-weight: 800; color: #087832; margin-bottom: 8px;">Support Pipeline</h2>
          <p style="font-size: 14px; color: #666; margin-bottom: 32px;">Help keep the campus marketplace alive and free.</p>

          <div class="payment-form-box" style="background: #fff; padding: 24px; border: 1.5px solid #eee; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
            <div class="field-group mb-4" style="text-align: left;">
              <label class="field-label">Select Amount (PHP)</label>
              <div class="d-flex gap-2 mt-2 mb-3">
                <button type="button" class="btn-amount" onclick="setAmount(50)">₱50</button>
                <button type="button" class="btn-amount" onclick="setAmount(100)">₱100</button>
                <button type="button" class="btn-amount" onclick="setAmount(500)">₱500</button>
              </div>
              <input type="number" id="donation-amount" class="field-input" placeholder="Custom amount" value="100" style="text-align: center; font-size: 18px; font-weight: 700; color: #087832; border-color: #087832; background: #f0fdf4;">
            </div>

            <div id="card-container" style="min-height: 100px; display: flex; align-items: center; justify-content: center; background: #f9f9f9; border-radius: 10px; margin-bottom: 20px; color: #888; font-size: 13px;">
               Loading Secure Payment Field...
            </div>

            <div class="donation-footer" style="margin-top:24px;">
              <button type="button" id="card-button" class="btn-square-pay" style="width:100%; background:#000; color:white; border:none; padding:14px; border-radius:12px; font-weight:800; font-size:14px; display:flex; align-items:center; justify-content:center; gap:10px; cursor:pointer; transition: all 0.3s ease;">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/33/Square_Inc._logo.svg/1024px-Square_Inc._logo.svg.png" style="height:14px; filter:brightness(0) invert(1);"> Complete Donation
              </button>
            </div>
            
            <div id="payment-status-container" style="margin-top:16px; min-height: 20px; font-size:13px; font-weight:700;"></div>
          </div>

          <div style="margin-top: 24px;">
            <p style="font-size: 12px; color: #999;">🔒 Secure payment processed by Square</p>
          </div>
        </div>

      </div>
      <div class="card-footer" style="justify-content: center; background: transparent; border-top: none;">
        <button class="btn-cancel" type="button" onclick="switchTab('edit')">← Back to Profile Settings</button>
      </div>
    </div>
  </form>
</div>

<!-- Toast -->
<div class="toast <?php echo $updateSuccess ? 'show' : ''; ?>" id="toast">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  <span id="toast-msg">Profile updated successfully!</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // --- SQUARE INTEGRATION GLOBALS ---
  let sqPayments;
  let sqCard;

  // Profile dropdown logic
  const profileBtn = document.getElementById('profileBtn');
  const profileDropdown = document.getElementById('profileDropdown');
  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
    document.addEventListener('click', () => profileDropdown.classList.remove('show'));
    profileDropdown.addEventListener('click', (e) => e.stopPropagation());
  }

  // Avatar Preview
  function previewAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const src = e.target.result;
      document.getElementById('header-avatar').src = src;
      document.querySelectorAll('.img-profile, .dropdown-profile-header img').forEach(img => img.src = src);
    };
    reader.readAsDataURL(file);
  }

  // Password Strength logic
  function checkStrength(val) {
    const fill = document.getElementById('pw-strength-fill');
    const label = document.getElementById('pw-strength-label');
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const map = { 0:{w:'0%',bg:'#ccc',txt:''}, 1:{w:'25%',bg:'#e74c3c',txt:'Weak'}, 2:{w:'50%',bg:'#e67e22',txt:'Fair'}, 3:{w:'75%',bg:'#f1c40f',txt:'Good'}, 4:{w:'100%',bg:'#087832',txt:'Strong'} };
    const s = map[score] || map[0];
    fill.style.width = s.w; fill.style.background = s.bg;
    label.textContent = s.txt; label.style.color = s.bg;
  }

  // Header update for name
  document.getElementById('first-name').addEventListener('input', updateHeader);
  document.getElementById('last-name').addEventListener('input',  updateHeader);
  function updateHeader() {
    const fn = document.getElementById('first-name').value.trim();
    const ln = document.getElementById('last-name').value.trim();
    document.getElementById('header-name').textContent = (fn + ' ' + ln).trim() || '—';
  }

  // Tab switching logic
  function switchTab(tabName) {
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.remove('active');
      if(btn.textContent.toLowerCase().includes(tabName === 'edit' ? 'settings' : 'support')) {
        btn.classList.add('active');
      }
    });

    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
      content.style.display = 'none';
    });
    document.getElementById('tab-' + tabName).style.display = 'block';

    // Initialize Square if switching to support tab
    if(tabName === 'support') {
      initializeSquare();
    }

    // Update URL if possible without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
  }

  // Check for tab in URL on load
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('tab') === 'support') {
    switchTab('support');
  }

  // Real-time Notification Polling
  let shownNotifIds = new Set();
  
  function setAmount(amt) {
    document.getElementById('donation-amount').value = amt;
  }

  // --- SQUARE INTEGRATION ---
  async function initializeSquare() {
    if (sqCard) return; // Already initialized

    // Wait for Square to be available in window
    if (!window.Square) {
      setTimeout(initializeSquare, 500);
      return;
    }

    const appId = '<?php echo SQUARE_APPLICATION_ID; ?>';
    const locId = '<?php echo SQUARE_LOCATION_ID; ?>';

    console.log('Square Init: Starting with AppID:', appId, 'LocID:', locId);

    try {
      const cardContainer = document.getElementById('card-container');
      console.log('Square Init: Creating payments object...');
      sqPayments = window.Square.payments(appId, locId);
      
      console.log('Square Init: Initializing card element...');
      sqCard = await sqPayments.card();
      
      console.log('Square Init: Attaching to DOM...');
      cardContainer.innerHTML = ""; 
      await sqCard.attach('#card-container');
      console.log('Square Init: Success!');
    } catch (e) {
      console.error('Square Init: Failed at some step', e);
      document.getElementById('card-container').innerHTML = '<span style="color:red;">Failed to load payment field. ' + e.message + '</span>';
    }
  }

  async function handlePaymentSubmission(event) {
    if (!sqCard) {
      alert("Payment field is still loading. Please wait a moment.");
      return;
    }
    const statusContainer = document.getElementById('payment-status-container');
    const payButton = document.getElementById('card-button');
    const amount = document.getElementById('donation-amount').value;

    if (amount <= 0) {
      statusContainer.innerHTML = '<span style="color:red;">Please enter a valid amount.</span>';
      return;
    }

    payButton.disabled = true;
    statusContainer.innerHTML = 'Processing...';

    try {
      const result = await sqCard.tokenize();
      if (result.status === 'OK') {
        // Send token to backend
        const response = await fetch('process_square_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            sourceId: result.token,
            amount: amount
          }),
        });

        const data = await response.json();
        if (data.success) {
          statusContainer.innerHTML = '<span style="color:#087832;">💖 Thank you for your donation!</span>';
          confettiEffect();
        } else {
          statusContainer.innerHTML = '<span style="color:red;">Error: ' + data.message + '</span>';
          payButton.disabled = false;
        }
      } else {
        statusContainer.innerHTML = '<span style="color:red;">Tokenization failed: ' + result.errors[0].message + '</span>';
        payButton.disabled = false;
      }
    } catch (e) {
      console.error('Payment failed', e);
      statusContainer.innerHTML = '<span style="color:red;">Error: ' + e.message + '</span>';
      payButton.disabled = false;
    }
  }

  document.getElementById('card-button')?.addEventListener('click', handlePaymentSubmission);

  function confettiEffect() {
    // Simple visual feedback
    const colors = ['#087832', '#57b147', '#ffda44', '#ffffff'];
    for(let i=0; i<30; i++) {
      const p = document.createElement('div');
      p.style.cssText = `position:fixed; left:${Math.random()*100}vw; top:-10px; width:8px; height:8px; background:${colors[Math.floor(Math.random()*4)]}; border-radius:50%; z-index:10002; transition: all 2s ease-out;`;
      document.body.appendChild(p);
      setTimeout(() => {
        p.style.top = '110vh';
        p.style.transform = `rotate(${Math.random()*360}deg) translateX(${Math.random()*100-50}px)`;
        setTimeout(() => p.remove(), 2000);
      }, 10);
    }
  }

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

  // Generic badge updater
  function updateHeaderBadge(id, value) {
    document.getElementById(id).textContent = value || '—';
  }

  <?php if($updateSuccess): ?>
  setTimeout(() => { document.getElementById('toast').classList.remove('show'); }, 3000);
  <?php endif; ?>
</script>
</body>
</html>