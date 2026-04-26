<?php
session_start();

if (!isset($_SESSION['admin_username'])) {
    header("Location: login_admin.html");
    exit;
}

$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "pipeline_db",
    "Uid" => "",
    "PWD" => ""
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

function tableExists($conn, $tableName) {
    $stmt = sqlsrv_query(
        $conn,
        "SELECT 1 AS EXISTS_FLAG
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
        [$tableName]
    );

    return $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function scalarValue($conn, $sql, $params = [], $field = 'CNT', $fallback = 0) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        return $fallback;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row || !isset($row[$field])) {
        return $fallback;
    }

    return $row[$field];
}

function normalizeCategoryName($category) {
    if ($category === null) {
        return '';
    }

    if (stripos($category, 'Course-Specific') === 0) {
        return 'Course-Specific';
    }

    $map = [
        'Clothing and Apparel' => 'Clothing & Apparel',
        'Hobbies and Lifestyle' => 'Hobbies & Lifestyle',
        'Events and Tickets' => 'Events & Tickets'
    ];

    return $map[$category] ?? $category;
}

function shortCategoryName($category) {
    $map = [
        'Electronics and Tech' => 'Electronics',
        'Clothing & Apparel' => 'Clothing',
        'Hobbies & Lifestyle' => 'Hobbies',
        'Events & Tickets' => 'Events',
        'Course-Specific' => 'Course-Specific'
    ];

    return $map[$category] ?? $category;
}

function categoryBadgeClass($category) {
    $map = [
        'Academics' => 'badge-green',
        'Electronics and Tech' => 'badge-purple',
        'Clothing & Apparel' => 'badge-blue',
        'Hobbies & Lifestyle' => 'badge-purple',
        'Food' => 'badge-green',
        'Events & Tickets' => 'badge-amber',
        'Course-Specific' => 'badge-green'
    ];

    return $map[$category] ?? 'badge-green';
}

function statusBadgeClass($status) {
    $map = [
        'Available' => 'badge-blue',
        'Sold' => 'badge-green',
        'Reserved' => 'badge-amber',
        'Pending Review' => 'badge-amber',
        'Pending' => 'badge-amber',
        'Removed' => 'badge-red'
    ];

    return $map[$status] ?? 'badge-blue';
}

function formatDateShort($value) {
    if ($value instanceof DateTime) {
        return $value->format('M d');
    }

    if (!$value) {
        return '—';
    }

    return date('M d', strtotime($value));
}

function formatDateLong($value) {
    if ($value instanceof DateTime) {
        return $value->format('M d, Y');
    }

    if (!$value) {
        return '—';
    }

    return date('M d, Y', strtotime($value));
}

function initialsFromName($firstName, $lastName) {
    $first = $firstName !== '' ? strtoupper(substr($firstName, 0, 1)) : '';
    $last = $lastName !== '' ? strtoupper(substr($lastName, 0, 1)) : '';
    $initials = $first . $last;

    return $initials !== '' ? $initials : 'U';
}

function alertClassFromReason($reason) {
    $reason = strtolower((string)$reason);

    if (strpos($reason, 'harassment') !== false || strpos($reason, 'abuse') !== false) {
        return 'danger';
    }
    if (strpos($reason, 'prohibited') !== false || strpos($reason, 'suspicious') !== false) {
        return 'warn';
    }
    if (strpos($reason, 'spam') !== false || strpos($reason, 'duplicate') !== false) {
        return 'info';
    }

    return 'ok';
}

function donutGradient($segments) {
    $total = array_sum($segments);
    if ($total <= 0) {
        return 'conic-gradient(#dde5b6 0deg 360deg)';
    }

    $colors = [
        '#283618',
        '#606c38',
        '#a7b87a',
        '#c49a40',
        '#2d8a72',
        '#dde5b6'
    ];

    $parts = [];
    $start = 0;
    $index = 0;

    foreach ($segments as $value) {
        if ($value <= 0) {
            $index++;
            continue;
        }

        $degrees = ($value / $total) * 360;
        $end = $start + $degrees;
        $parts[] = $colors[$index % count($colors)] . ' ' . round($start, 2) . 'deg ' . round($end, 2) . 'deg';
        $start = $end;
        $index++;
    }

    if (empty($parts)) {
        return 'conic-gradient(#dde5b6 0deg 360deg)';
    }

    return 'conic-gradient(' . implode(', ', $parts) . ')';
}

$reportsTableExists = tableExists($conn, 'LISTING_REPORTS');

$adminName = $_SESSION['admin_username'];
$adminInitials = strtoupper(substr($adminName, 0, 2));

$todayDisplay = date('D, M j, Y');
$today = new DateTime('today');
$sevenDaysAgo = (clone $today)->modify('-6 days');
$thirtyDaysAgo = (clone $today)->modify('-29 days');
$weekStart = (clone $today)->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+7 days');

$totalStudents = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[USERS]");
$totalListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS]");
$activeListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE STATUS='Available'");
$soldListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE STATUS='Sold'");
$reservedListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE STATUS='Reserved'");
$pendingReviewListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE STATUS IN ('Pending Review','Pending')");
$removedListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE STATUS='Removed'");
$listedThisWeek = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$weekStart, $weekEnd]
);
$soldThisMonth = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM dbo.[LISTINGS] WHERE STATUS='Sold' AND DATE_POSTED >= ?",
    [$thirtyDaysAgo]
);

$pendingReports = 0;
$reportsThisWeek = 0;
$recentReports = [];
if ($reportsTableExists) {
    $pendingReports = (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_REPORTS] WHERE REPORT_STATUS='Pending'"
    );
    $reportsThisWeek = (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_REPORTS] WHERE CREATED_AT >= ? AND CREATED_AT < ?",
        [$weekStart, $weekEnd]
    );

    $reportSql = "SELECT TOP 4 R.REPORT_REASON, R.REPORT_STATUS, R.CREATED_AT,
                         L.TITLE,
                         U.USERNAME AS REPORTER_USERNAME
                  FROM dbo.[LISTING_REPORTS] R
                  JOIN dbo.[LISTINGS] L ON R.LISTING_ID = L.LISTING_ID
                  JOIN dbo.[USERS] U ON R.REPORTER_USER_ID = U.USER_ID
                  ORDER BY R.CREATED_AT DESC, R.REPORT_ID DESC";
    $reportStmt = sqlsrv_query($conn, $reportSql);
    if ($reportStmt) {
        while ($row = sqlsrv_fetch_array($reportStmt, SQLSRV_FETCH_ASSOC)) {
            $recentReports[] = $row;
        }
    }
}

$categoryOrder = [
    'Academics',
    'Electronics and Tech',
    'Clothing & Apparel',
    'Food',
    'Hobbies & Lifestyle',
    'Events & Tickets',
    'Course-Specific'
];
$categoryCounts = array_fill_keys($categoryOrder, 0);

$categorySql = "SELECT CATEGORY, COUNT(*) AS CNT FROM dbo.[LISTINGS] GROUP BY CATEGORY";
$categoryStmt = sqlsrv_query($conn, $categorySql);
if ($categoryStmt) {
    while ($row = sqlsrv_fetch_array($categoryStmt, SQLSRV_FETCH_ASSOC)) {
        $normalized = normalizeCategoryName($row['CATEGORY']);
        if (!isset($categoryCounts[$normalized])) {
            $categoryCounts[$normalized] = 0;
        }
        $categoryCounts[$normalized] += (int) $row['CNT'];
    }
}

$maxCategoryCount = !empty($categoryCounts) ? max($categoryCounts) : 0;

$statusLegend = [
    ['label' => 'Active', 'count' => $activeListings, 'color' => '#283618'],
    ['label' => 'Sold', 'count' => $soldListings, 'color' => '#606c38'],
    ['label' => 'Reserved', 'count' => $reservedListings, 'color' => '#a7b87a'],
    ['label' => 'Pending Review', 'count' => $pendingReviewListings, 'color' => '#c49a40'],
    ['label' => 'Removed', 'count' => $removedListings, 'color' => '#2d8a72']
];
$statusSegments = array_map(function ($segment) {
    return $segment['count'];
}, $statusLegend);
$donutStyle = donutGradient($statusSegments);

$listingDays = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
$listingChartCounts = array_fill(0, 7, 0);
$listingDatesStmt = sqlsrv_query(
    $conn,
    "SELECT DATE_POSTED FROM dbo.[LISTINGS] WHERE DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$weekStart, $weekEnd]
);
if ($listingDatesStmt) {
    while ($row = sqlsrv_fetch_array($listingDatesStmt, SQLSRV_FETCH_ASSOC)) {
        $dateValue = $row['DATE_POSTED'];
        if ($dateValue instanceof DateTime) {
            $dayIndex = (int) $dateValue->format('N') - 1;
        } else {
            $dayIndex = (int) date('N', strtotime($dateValue)) - 1;
        }
        if (isset($listingChartCounts[$dayIndex])) {
            $listingChartCounts[$dayIndex]++;
        }
    }
}
$maxListingDayCount = max($listingChartCounts);

$recentListings = [];
$recentListingsSql = "SELECT TOP 5 L.TITLE, L.CATEGORY, L.PRICE, L.STATUS, L.DATE_POSTED,
                             U.USERNAME
                      FROM dbo.[LISTINGS] L
                      JOIN dbo.[USERS] U ON L.USER_ID = U.USER_ID
                      ORDER BY L.DATE_POSTED DESC, L.LISTING_ID DESC";
$recentListingsStmt = sqlsrv_query($conn, $recentListingsSql);
if ($recentListingsStmt) {
    while ($row = sqlsrv_fetch_array($recentListingsStmt, SQLSRV_FETCH_ASSOC)) {
        $recentListings[] = $row;
    }
}

$recentUsers = [];
$recentUsersSql = "SELECT TOP 4 USER_ID, FIRST_NAME, LAST_NAME, STD_NUM, CYS
                   FROM dbo.[USERS]
                   ORDER BY USER_ID DESC";
$recentUsersStmt = sqlsrv_query($conn, $recentUsersSql);
if ($recentUsersStmt) {
    while ($row = sqlsrv_fetch_array($recentUsersStmt, SQLSRV_FETCH_ASSOC)) {
        $recentUsers[] = $row;
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'reports') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pipeline_reports_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report Reason', 'Status', 'Listing Title', 'Reporter Username', 'Created At']);

    if ($reportsTableExists) {
        $csvSql = "SELECT R.REPORT_REASON, R.REPORT_STATUS, L.TITLE,
                          U.USERNAME AS REPORTER_USERNAME, R.CREATED_AT
                   FROM dbo.[LISTING_REPORTS] R
                   JOIN dbo.[LISTINGS] L ON R.LISTING_ID = L.LISTING_ID
                   JOIN dbo.[USERS] U ON R.REPORTER_USER_ID = U.USER_ID
                   ORDER BY R.CREATED_AT DESC, R.REPORT_ID DESC";
        $csvStmt = sqlsrv_query($conn, $csvSql);
        if ($csvStmt) {
            while ($row = sqlsrv_fetch_array($csvStmt, SQLSRV_FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['REPORT_REASON'],
                    $row['REPORT_STATUS'],
                    $row['TITLE'],
                    $row['REPORTER_USERNAME'],
                    formatDateLong($row['CREATED_AT'])
                ]);
            }
        }
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin_dashboard.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="assets/img/pipeline_logo_light.png" class="sidebar-logo-img" alt="Pipeline Logo">
    </div>

    <div class="sidebar-admin">
        <div class="admin-avatar"><?php echo htmlspecialchars($adminInitials); ?></div>
        <div class="admin-info">
            <div class="name"><?php echo htmlspecialchars($adminName); ?></div>
            <div class="role">ADMIN</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a class="nav-item active" href="admin_dashboard.php">
            <span class="icon">⊞</span> Dashboard
        </a>
        <a class="nav-item" href="#recent-users">
            <span class="icon">👥</span> Students
            <span class="nav-badge"><?php echo number_format($totalStudents); ?></span>
        </a>
        <a class="nav-item" href="#recent-listings">
            <span class="icon">📦</span> Listings
            <span class="nav-badge"><?php echo number_format($totalListings); ?></span>
        </a>
        <a class="nav-item" href="#listing-status">
            <span class="icon">🕐</span> Pending Listings
            <span class="nav-badge"><?php echo number_format($pendingReviewListings); ?></span>
        </a>
        <a class="nav-item" href="#recent-reports">
            <span class="icon">⚠️</span> Reports
            <span class="nav-badge"><?php echo number_format($pendingReports); ?></span>
        </a>

        <div class="nav-section-label">Categories</div>
        <a class="nav-item" href="#category-breakdown"><span class="icon">📚</span> Academics</a>
        <a class="nav-item" href="#category-breakdown"><span class="icon">💻</span> Electronics &amp; Tech</a>
        <a class="nav-item" href="#category-breakdown"><span class="icon">👕</span> Clothing &amp; Apparel</a>
        <a class="nav-item" href="#category-breakdown"><span class="icon">🎮</span> Hobbies &amp; Lifestyle</a>
        <a class="nav-item" href="#category-breakdown"><span class="icon">🍪</span> Food</a>
        <a class="nav-item" href="#category-breakdown"><span class="icon">🎟️</span> Events &amp; Tickets</a>
        <a class="nav-item" href="#category-breakdown"><span class="icon">🔬</span> Course-Specific</a>

        <div class="nav-section-label">System</div>
        <a class="nav-item" href="#recent-reports"><span class="icon">⚙️</span> Review Queue</a>
        <a class="nav-item" href="#recent-reports"><span class="icon">📋</span> Audit Snapshot</a>
    </nav>

    <div class="sidebar-footer">
        <button class="btn-logout" onclick="window.location.href='admin_logout.php'">⏻ &nbsp;LOGOUT</button>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h2>Dashboard Overview</h2>
            <p>✦ DLSU-D Campus Marketplace - Admin View</p>
        </div>
        <div class="topbar-right">
            <span class="topbar-date"><?php echo htmlspecialchars($todayDisplay); ?></span>
            <button class="notif-btn" type="button">🔔<span class="notif-dot"></span></button>
        </div>
    </div>

    <div class="content">
        <div class="actions-row">
            <button class="action-btn primary" type="button" onclick="window.location.href='#recent-listings'">📢 &nbsp;View Listings</button>
            <a class="action-btn secondary" href="admin_dashboard.php?export=reports">📊 &nbsp;Export Report</a>
            <button class="action-btn danger-btn" type="button" onclick="window.location.href='#recent-reports'">🚨 &nbsp;Review Reports</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Students</div>
                <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
                <div class="stat-change">Current registered users</div>
            </div>
            <div class="stat-card olive">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Active Listings</div>
                <div class="stat-value"><?php echo number_format($activeListings); ?></div>
                <div class="stat-change"><?php echo number_format($listedThisWeek); ?> posted this week</div>
            </div>
            <div class="stat-card amber">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">Pending Reports</div>
                <div class="stat-value"><?php echo number_format($pendingReports); ?></div>
                <div class="stat-change"><?php echo number_format($reportsThisWeek); ?> filed this week</div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Marked as Sold</div>
                <div class="stat-value"><?php echo number_format($soldListings); ?></div>
                <div class="stat-change"><?php echo number_format($soldThisMonth); ?> posted in last 30 days</div>
            </div>
        </div>

        <div class="mid-grid">
            <div class="card" id="category-breakdown">
                <div class="card-title">Listings by Category <span>Live Data</span></div>
                <?php foreach ($categoryOrder as $categoryName): ?>
                <?php
                    $count = (int) ($categoryCounts[$categoryName] ?? 0);
                    $width = $maxCategoryCount > 0 ? max(10, round(($count / $maxCategoryCount) * 100)) : 0;
                ?>
                <div class="cat-row">
                    <div class="cat-name"><?php echo htmlspecialchars(shortCategoryName($categoryName)); ?></div>
                    <div class="cat-bar-bg"><div class="cat-bar" style="width:<?php echo $width; ?>%"></div></div>
                    <div class="cat-count"><?php echo $count; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card" id="listing-status">
                <div class="card-title">Listing Status <span>Live Counts</span></div>
                <div class="donut-wrap">
                    <div class="donut" style="background: <?php echo htmlspecialchars($donutStyle); ?>;">
                        <div class="donut-center">
                            <div class="num"><?php echo number_format($totalListings); ?></div>
                            <div class="lbl">TOTAL</div>
                        </div>
                    </div>
                </div>
                <div class="legend">
                    <?php foreach ($statusLegend as $statusItem): ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?php echo htmlspecialchars($statusItem['color']); ?>"></div>
                        <?php echo htmlspecialchars($statusItem['label']); ?>
                        <span><?php echo number_format($statusItem['count']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-title">New Listings <span>This Week</span></div>
                <div class="bar-chart">
                    <?php foreach ($listingDays as $index => $dayLabel): ?>
                    <?php
                        $dayCount = $listingChartCounts[$index];
                        $height = $maxListingDayCount > 0 ? max(4, round(($dayCount / $maxListingDayCount) * 88)) : 4;
                        $isActiveDay = $dayCount === $maxListingDayCount && $maxListingDayCount > 0;
                    ?>
                    <div class="bar-col<?php echo $isActiveDay ? ' active' : ''; ?>">
                        <div class="bar" style="height:<?php echo $height; ?>px"></div>
                        <div class="bar-label"><?php echo $dayLabel; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-footer">
                    <span><?php echo number_format(array_sum($listingChartCounts)); ?> new listings total</span>
                    <span style="color:var(--success)">Week of <?php echo htmlspecialchars($weekStart->format('M d')); ?></span>
                </div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="card" id="recent-listings">
                <div class="card-title">Recent Listings <span>Latest 5</span></div>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentListings)): ?>
                        <tr>
                            <td colspan="5">No listings found.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentListings as $listing): ?>
                        <?php
                            $categoryName = normalizeCategoryName($listing['CATEGORY']);
                            $statusName = $listing['STATUS'];
                        ?>
                        <tr>
                            <td>
                                <div class="item-name"><?php echo htmlspecialchars($listing['TITLE']); ?></div>
                                <div class="item-seller">by @<?php echo htmlspecialchars($listing['USERNAME']); ?></div>
                            </td>
                            <td><span class="badge <?php echo categoryBadgeClass($categoryName); ?>"><?php echo htmlspecialchars(shortCategoryName($categoryName)); ?></span></td>
                            <td style="font-weight:700">₱<?php echo number_format((float) $listing['PRICE'], 2); ?></td>
                            <td><span class="badge <?php echo statusBadgeClass($statusName); ?>"><?php echo htmlspecialchars($statusName); ?></span></td>
                            <td style="color:var(--muted); font-size:11px"><?php echo htmlspecialchars(formatDateShort($listing['DATE_POSTED'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; flex-direction:column; gap:16px;">
                <div class="card" id="recent-users">
                    <div class="card-title">Recent Users <span>Latest IDs</span></div>
                    <?php if (empty($recentUsers)): ?>
                    <div class="alert-text">
                        <div class="desc">No users found.</div>
                    </div>
                    <?php else: ?>
                    <?php
                        $userColors = ['var(--forest)', 'var(--olive)', '#2d8a72', '#c49a40'];
                    ?>
                    <?php foreach ($recentUsers as $index => $user): ?>
                    <div class="user-row">
                        <div class="user-av" style="background:<?php echo $userColors[$index % count($userColors)]; ?>">
                            <?php echo htmlspecialchars(initialsFromName((string) $user['FIRST_NAME'], (string) $user['LAST_NAME'])); ?>
                        </div>
                        <div class="user-info">
                            <div class="uname"><?php echo htmlspecialchars(trim($user['FIRST_NAME'] . ' ' . $user['LAST_NAME'])); ?></div>
                            <div class="ustd"><?php echo htmlspecialchars((string) $user['STD_NUM']); ?> · <?php echo htmlspecialchars((string) $user['CYS']); ?></div>
                        </div>
                        <div class="user-time">ID #<?php echo (int) $user['USER_ID']; ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card" id="recent-reports">
                    <div class="card-title">Recent Reports <span><?php echo $reportsTableExists ? 'Live Data' : 'Table Missing'; ?></span></div>
                    <?php if (!$reportsTableExists): ?>
                    <div class="alert-item">
                        <div class="alert-icon warn">⚠️</div>
                        <div class="alert-text">
                            <div class="title">LISTING_REPORTS not found</div>
                            <div class="desc">Run the report table script first to populate the admin review queue.</div>
                        </div>
                    </div>
                    <?php elseif (empty($recentReports)): ?>
                    <div class="alert-item">
                        <div class="alert-icon ok">✅</div>
                        <div class="alert-text">
                            <div class="title">No reports yet</div>
                            <div class="desc">The report queue is currently empty.</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($recentReports as $report): ?>
                    <?php $alertClass = alertClassFromReason($report['REPORT_REASON']); ?>
                    <div class="alert-item">
                        <div class="alert-icon <?php echo $alertClass; ?>">
                            <?php echo $alertClass === 'danger' ? '🚨' : ($alertClass === 'warn' ? '⚠️' : ($alertClass === 'info' ? 'ℹ️' : '✅')); ?>
                        </div>
                        <div class="alert-text">
                            <div class="title"><?php echo htmlspecialchars($report['REPORT_REASON']); ?></div>
                            <div class="desc"><?php echo htmlspecialchars($report['TITLE']); ?> · by @<?php echo htmlspecialchars($report['REPORTER_USERNAME']); ?></div>
                        </div>
                        <div class="alert-time"><?php echo htmlspecialchars(formatDateShort($report['CREATED_AT'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>
