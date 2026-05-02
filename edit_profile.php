<?php
session_start();

require_once __DIR__ . '/db.php';
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
    $cys  = $_POST['cys'];
    $sex  = $_POST['sex'];
    $un   = $_POST['username'];
    $em   = $_POST['email'];
    
    // Update basic user info
    $sqlUpd = "UPDATE USERS SET FIRST_NAME = ?, LAST_NAME = ?, CYS = ?, SEX = ?, USERNAME = ?, EMAIL = ? WHERE USER_ID = ?";
    $params = [$fn, $ln, $cys, $sex, $un, $em, $loginId];
    db_query($conn, $sqlUpd, $params);

    // Update Password if filled
    if (!empty($_POST['new_pw'])) {
        $currentPwInput = $_POST['current_pw'];
        $newPwInput     = $_POST['new_pw'];
        
        $sqlCheck = "SELECT `PASSWORD` FROM USERS WHERE USER_ID = ?";
        $resCheck = db_query($conn, $sqlCheck, [$loginId]);
        $rowCheck = db_fetch_assoc($resCheck);
        
        if ($rowCheck['PASSWORD'] === $currentPwInput) {
            $sqlPw = "UPDATE USERS SET `PASSWORD` = ? WHERE USER_ID = ?";
            db_query($conn, $sqlPw, [$newPwInput, $loginId]);
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
            $existingImage = $resExist && db_fetch_assoc($resExist);
            
            if ($existingImage) {
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
$cys        = $rowUser['CYS']         ?? '';
$sex        = $rowUser['SEX']         ?? '';
$username   = $rowUser['USERNAME']    ?? '';
$email      = $rowUser['EMAIL']       ?? '';
$file_path  = $rowImg['FILE_PATH']    ?? '';

$avatarSrc  = ($file_path && file_exists($file_path))
              ? htmlspecialchars($file_path)
              : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($firstname);

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
</head>
<body class="body">

<!-- Navbar mirroring dashboard.php -->
<div class="dash-navbar">
  <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo">
  <div class="dash-nav-right">
    <div class="dash-greeting">
      <span class="dash-hello">Hello,</span>
      <span class="dash-name"><?php echo htmlspecialchars($firstname); ?></span>
    </div>

    <div class="profile-wrapper">
      <img src="<?php echo $avatarSrc; ?>" class="img-profile" alt="Profile Picture" id="profileBtn">
      <div class="profile-dropdown" id="profileDropdown">
        <div class="dropdown-profile-header">
          <img src="<?php echo $avatarSrc; ?>" alt="Profile">
          <span class="dropdown-profile-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></span>
        </div>
        <a href="dashboard.php" class="dropdown-item-custom"><span class="item-icon">🏬</span> Browse Products</a>
        <a href="storefront.php" class="dropdown-item-custom"><span class="item-icon">🏪</span> My Storefront</a>
        <a href="edit_profile.php" class="dropdown-item-custom"><span class="item-icon">👤</span> My Profile</a>
        <div class="dropdown-divider-custom"></div>
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
          <span class="badge-cys" id="header-cys"><?php echo htmlspecialchars($cys); ?></span>
        </div>
      </div>

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
              <label class="field-label">Course-Year-Section</label>
              <input class="field-input" name="cys" id="cys" type="text" value="<?php echo htmlspecialchars($cys); ?>"/>
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
              <input class="field-input" name="email" id="email" type="email" value="<?php echo htmlspecialchars($email); ?>"/>
            </div>
          </div>
        </div>

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
      </div>

      <div class="card-footer">
        <button class="btn-cancel" type="button" onclick="window.location.href='dashboard.php'">Cancel</button>
        <button class="btn-save" name="save_profile" type="submit">Save Changes</button>
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
    const map = { 0:{w:'0%',bg:'#ccc',txt:''}, 1:{w:'25%',bg:'#e74c3c',txt:'Weak'}, 2:{w:'50%',bg:'#e67e22',txt:'Fair'}, 3:{w:'75%',bg:'#f1c40f',txt:'Good'}, 4:{w:'100%',bg:'#27ae60',txt:'Strong'} };
    const s = map[score] || map[0];
    fill.style.width = s.w; fill.style.background = s.bg;
    label.textContent = s.txt; label.style.color = s.bg;
  }

  // Header update
  document.getElementById('first-name').addEventListener('input', updateHeader);
  document.getElementById('last-name').addEventListener('input',  updateHeader);
  function updateHeader() {
    const fn = document.getElementById('first-name').value.trim();
    const ln = document.getElementById('last-name').value.trim();
    document.getElementById('header-name').textContent = (fn + ' ' + ln).trim() || '—';
  }

  <?php if($updateSuccess): ?>
  setTimeout(() => { document.getElementById('toast').classList.remove('show'); }, 3000);
  <?php endif; ?>
</script>
</body>
</html>
