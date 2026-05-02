<?php
session_start();

if (!isset($_SESSION['admin_username'])) {
    header("Location: login_admin.html");
    exit;
}

require_once __DIR__ . '/db.php';
$conn = db_connect();
if ($conn === false) {
    die(db_last_error());
}

function tableExists($conn, $tableName) {
    $stmt = db_query(
        $conn,
        "SELECT 1 AS EXISTS_FLAG
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         LIMIT 1",
        [$tableName]
    );

    return $stmt && db_fetch_assoc($stmt);
}

function columnExists($conn, $tableName, $columnName) {
    $stmt = db_query(
        $conn,
        "SELECT 1 AS COL_EXISTS
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1",
        [$tableName, $columnName]
    );
    $row = $stmt ? db_fetch_assoc($stmt) : null;

    return !empty($row['COL_EXISTS']);
}

function scalarValue($conn, $sql, $params = [], $field = 'CNT', $fallback = 0) {
    $stmt = db_query($conn, $sql, $params);
    if (!$stmt) {
        return $fallback;
    }

    $row = db_fetch_assoc($stmt);
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

function categoryFilterValues($category) {
    $map = [
        'Clothing & Apparel' => ['Clothing & Apparel', 'Clothing and Apparel'],
        'Hobbies & Lifestyle' => ['Hobbies & Lifestyle', 'Hobbies and Lifestyle'],
        'Events & Tickets' => ['Events & Tickets', 'Events and Tickets']
    ];

    return $map[$category] ?? [$category];
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
        return '-';
    }

    return date('M d', strtotime($value));
}

function formatDateLong($value) {
    if ($value instanceof DateTime) {
        return $value->format('M d, Y');
    }

    if (!$value) {
        return '-';
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

function currentPage() {
    $allowedPages = ['dashboard', 'students', 'reports', 'categories', 'audit'];
    $page = $_GET['page'] ?? 'dashboard';

    return in_array($page, $allowedPages, true) ? $page : 'dashboard';
}

function safeRedirect($url) {
    header("Location: {$url}");
    exit;
}

function selectedAttr($current, $expected) {
    return (string) $current === (string) $expected ? ' selected' : '';
}

function inputValue($key) {
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : '';
}

function queryStringWith($updates) {
    $params = $_GET;
    foreach ($updates as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return http_build_query($params);
}

function navClass($targetPage, $activePage) {
    return 'nav-item' . ($targetPage === $activePage ? ' active' : '');
}

function metricRow($label, $value) {
    return ['label' => $label, 'value' => $value];
}

function auditPeriodBounds($period, DateTime $today) {
    if ($period === 'daily') {
        $start = clone $today;
        $end = (clone $start)->modify('+1 day');
        return [$start, $end, 'Daily'];
    }

    if ($period === 'monthly') {
        $start = new DateTime($today->format('Y-m-01'));
        $end = (clone $start)->modify('+1 month');
        return [$start, $end, 'Monthly'];
    }

    $start = (clone $today)->modify('monday this week');
    $end = (clone $start)->modify('+7 days');
    return [$start, $end, 'Weekly'];
}

function pdfEscape($value) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $value);
}

function outputSimplePdf($filename, $title, $lines) {
    $linesPerPage = 42;
    $pages = array_chunk($lines, $linesPerPage);
    if (empty($pages)) {
        $pages = [[]];
    }

    $objects = [];
    $pagesKids = [];
    $nextObjectId = 4;

    foreach ($pages as $pageIndex => $pageLines) {
        $pageObjectId = $nextObjectId++;
        $contentObjectId = $nextObjectId++;
        $pagesKids[] = $pageObjectId . ' 0 R';

        $contentLines = [
            'BT',
            '/F1 16 Tf',
            '50 790 Td',
            '(' . pdfEscape($title) . ') Tj',
            '/F1 10 Tf',
            '0 -20 Td',
            '(Generated ' . pdfEscape(date('M d, Y h:i A')) . ') Tj',
            '0 -18 Td'
        ];

        foreach ($pageLines as $line) {
            $contentLines[] = '(' . pdfEscape($line) . ') Tj';
            $contentLines[] = '0 -15 Td';
        }

        $contentLines[] = 'ET';
        $stream = implode("\n", $contentLines);

        $objects[$pageObjectId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 1 0 R >> >> /Contents {$contentObjectId} 0 R >>";
        $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
    }

    $objects[1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $pagesKids) . "] /Count " . count($pagesKids) . " >>";
    $objects[3] = "<< /Type /Catalog /Pages 2 0 R >>";
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 3 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$reportsTableExists = tableExists($conn, 'LISTING_REPORTS');
$listingImagesTableExists = tableExists($conn, 'LISTING_IMG');
$userReportsTableExists = tableExists($conn, 'USER_REPORTS');

$adminName = $_SESSION['admin_username'];
$adminInitials = strtoupper(substr($adminName, 0, 2));
$activePage = currentPage();
$pageTitles = [
    'dashboard' => 'Dashboard Overview',
    'students' => 'Students',
    'reports' => 'Reports',
    'categories' => 'All Categories',
    'audit' => 'Audit Snapshot'
];
$flashMessage = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

$todayDisplay = date('D, M j, Y');
$today = new DateTime('today');
$sevenDaysAgo = (clone $today)->modify('-6 days');
$thirtyDaysAgo = (clone $today)->modify('-29 days');
$weekStart = (clone $today)->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+7 days');
$auditPeriod = inputValue('audit_period');
if (!in_array($auditPeriod, ['daily', 'weekly', 'monthly'], true)) {
    $auditPeriod = 'weekly';
}
[$auditStart, $auditEnd, $auditPeriodLabel] = auditPeriodBounds($auditPeriod, $today);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_action'])) {
    if (!$reportsTableExists) {
        $_SESSION['admin_flash'] = 'Reports table was not found.';
        safeRedirect('admin_dashboard.php?page=reports');
    }

    $reportId = isset($_POST['report_id']) ? (int) $_POST['report_id'] : 0;
    $action = $_POST['report_action'];

    $reportStmt = db_query(
        $conn,
        "SELECT R.REPORT_ID, R.LISTING_ID, R.REPORT_STATUS, L.TITLE
         FROM LISTING_REPORTS R
         LEFT JOIN LISTINGS L ON R.LISTING_ID = L.LISTING_ID
         WHERE R.REPORT_ID = ?",
        [$reportId]
    );
    $report = $reportStmt ? db_fetch_assoc($reportStmt) : null;

    if (!$report) {
        $_SESSION['admin_flash'] = 'Report was not found.';
        safeRedirect('admin_dashboard.php?page=reports');
    }

    if ($action === 'deny') {
        $denyStmt = db_query(
            $conn,
            "DELETE FROM LISTING_REPORTS WHERE REPORT_ID = ?",
            [$reportId]
        );
        $_SESSION['admin_flash'] = $denyStmt ? 'Report denied and removed from the database.' : 'Unable to deny the report.';
        safeRedirect('admin_dashboard.php?page=reports');
    }

    if ($action === 'delete_listing') {
        $listingId = (int) $report['LISTING_ID'];
        db_begin_transaction($conn);

        $deleteOk = true;
        foreach (['LISTING_REPORTS', 'LISTING_COMMENTS', 'LISTING_LIKES', 'LISTING_IMG'] as $tableName) {
            if (tableExists($conn, $tableName)) {
                $deleteOk = $deleteOk && (bool) db_query(
                    $conn,
                    "DELETE FROM {$tableName} WHERE LISTING_ID = ?",
                    [$listingId]
                );
            }
        }

        $deleteOk = $deleteOk && (bool) db_query(
            $conn,
            "DELETE FROM LISTINGS WHERE LISTING_ID = ?",
            [$listingId]
        );

        if ($deleteOk) {
            db_commit($conn);
            $_SESSION['admin_flash'] = 'Reported listing deleted from the database.';
        } else {
            db_rollback($conn);
            $_SESSION['admin_flash'] = 'Unable to delete the reported listing.';
        }

        safeRedirect('admin_dashboard.php?page=reports');
    }

    $_SESSION['admin_flash'] = 'Unknown report action.';
    safeRedirect('admin_dashboard.php?page=reports');
}

$totalStudents = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM USERS");
$totalListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS");
$activeListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Available'");
$soldListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Sold'");
$reservedListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Reserved'");
$pendingReviewListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS` IN ('Pending Review','Pending')");
$removedListings = (int) scalarValue($conn, "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Removed'");
$listedThisWeek = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$weekStart, $weekEnd]
);
$soldThisMonth = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Sold' AND DATE_POSTED >= ?",
    [$thirtyDaysAgo]
);

$pendingReports = 0;
$reportsThisWeek = 0;
$recentReports = [];
if ($reportsTableExists) {
    $pendingReports = (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM LISTING_REPORTS WHERE REPORT_STATUS='Pending'"
    );
    $reportsThisWeek = (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM LISTING_REPORTS WHERE CREATED_AT >= ? AND CREATED_AT < ?",
        [$weekStart, $weekEnd]
    );

    $reportSql = "SELECT R.REPORT_REASON, R.REPORT_STATUS, R.CREATED_AT,
                         L.TITLE,
                         U.USERNAME AS REPORTER_USERNAME
                  FROM LISTING_REPORTS R
                  JOIN LISTINGS L ON R.LISTING_ID = L.LISTING_ID
                  JOIN USERS U ON R.REPORTER_USER_ID = U.USER_ID
                  ORDER BY R.CREATED_AT DESC, R.REPORT_ID DESC
                  LIMIT 4";
    $reportStmt = db_query($conn, $reportSql);
    if ($reportStmt) {
        while ($row = db_fetch_assoc($reportStmt)) {
            $recentReports[] = $row;
        }
    }
}

$studentDateColumn = null;
foreach (['CREATED_AT', 'DATE_REGISTERED', 'REG_DATE'] as $candidateColumn) {
    if (columnExists($conn, 'USERS', $candidateColumn)) {
        $studentDateColumn = $candidateColumn;
        break;
    }
}

$auditStudentsRegistered = $studentDateColumn
    ? (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM USERS WHERE {$studentDateColumn} >= ? AND {$studentDateColumn} < ?",
        [$auditStart, $auditEnd]
    )
    : $totalStudents;
$auditStudentMetricLabel = $studentDateColumn ? 'Students Registered' : 'Students Registered (Date Unavailable)';

$auditListings = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$auditStart, $auditEnd]
);
$auditSoldListings = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Sold' AND DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$auditStart, $auditEnd]
);
$auditActiveListings = (int) scalarValue(
    $conn,
    "SELECT COUNT(*) AS CNT FROM LISTINGS WHERE `STATUS`='Available' AND DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$auditStart, $auditEnd]
);

$auditReports = 0;
if ($reportsTableExists) {
    $auditReports += (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM LISTING_REPORTS WHERE CREATED_AT >= ? AND CREATED_AT < ?",
        [$auditStart, $auditEnd]
    );
}
if ($userReportsTableExists) {
    $auditReports += (int) scalarValue(
        $conn,
        "SELECT COUNT(*) AS CNT FROM USER_REPORTS WHERE CREATED_AT >= ? AND CREATED_AT < ?",
        [$auditStart, $auditEnd]
    );
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

$categorySql = "SELECT CATEGORY, COUNT(*) AS CNT FROM LISTINGS GROUP BY CATEGORY";
$categoryStmt = db_query($conn, $categorySql);
if ($categoryStmt) {
    while ($row = db_fetch_assoc($categoryStmt)) {
        $normalized = normalizeCategoryName($row['CATEGORY']);
        if (!isset($categoryCounts[$normalized])) {
            $categoryCounts[$normalized] = 0;
        }
        $categoryCounts[$normalized] += (int) $row['CNT'];
    }
}

$maxCategoryCount = !empty($categoryCounts) ? max($categoryCounts) : 0;

$auditCategoryCounts = array_fill_keys($categoryOrder, 0);
$auditCategoryStmt = db_query(
    $conn,
    "SELECT CATEGORY, COUNT(*) AS CNT
     FROM LISTINGS
     WHERE DATE_POSTED >= ? AND DATE_POSTED < ?
     GROUP BY CATEGORY",
    [$auditStart, $auditEnd]
);
if ($auditCategoryStmt) {
    while ($row = db_fetch_assoc($auditCategoryStmt)) {
        $normalized = normalizeCategoryName($row['CATEGORY']);
        if (!isset($auditCategoryCounts[$normalized])) {
            $auditCategoryCounts[$normalized] = 0;
        }
        $auditCategoryCounts[$normalized] += (int) $row['CNT'];
    }
}

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
$listingDatesStmt = db_query(
    $conn,
    "SELECT DATE_POSTED FROM LISTINGS WHERE DATE_POSTED >= ? AND DATE_POSTED < ?",
    [$weekStart, $weekEnd]
);
if ($listingDatesStmt) {
    while ($row = db_fetch_assoc($listingDatesStmt)) {
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
$recentListingsSql = "SELECT L.TITLE, L.CATEGORY, L.PRICE, L.`STATUS`, L.DATE_POSTED,
                             U.USERNAME
                       FROM LISTINGS L
                       JOIN USERS U ON L.USER_ID = U.USER_ID
                       ORDER BY L.DATE_POSTED DESC, L.LISTING_ID DESC
                       LIMIT 5";
$recentListingsStmt = db_query($conn, $recentListingsSql);
if ($recentListingsStmt) {
    while ($row = db_fetch_assoc($recentListingsStmt)) {
        $recentListings[] = $row;
    }
}

$recentUsers = [];
$recentUsersSql = "SELECT USER_ID, FIRST_NAME, LAST_NAME, STD_NUM, CYS
                   FROM USERS
                   ORDER BY USER_ID DESC
                   LIMIT 4";
$recentUsersStmt = db_query($conn, $recentUsersSql);
if ($recentUsersStmt) {
    while ($row = db_fetch_assoc($recentUsersStmt)) {
        $recentUsers[] = $row;
    }
}

$studentSearch = inputValue('student_q');
$listingSearch = inputValue('listing_q');
$listingCategory = inputValue('category');
$listingStatus = inputValue('status');
$reportFilter = inputValue('report_status');

$allUsers = [];
$allUsersSql = "SELECT USER_ID, FIRST_NAME, LAST_NAME, STD_NUM, CYS
                FROM USERS
                WHERE 1=1";
$allUsersParams = [];
if ($studentSearch !== '') {
    $allUsersSql .= " AND (FIRST_NAME LIKE ? OR LAST_NAME LIKE ? OR USERNAME LIKE ? OR STD_NUM LIKE ? OR CYS LIKE ?)";
    $like = '%' . $studentSearch . '%';
    array_push($allUsersParams, $like, $like, $like, $like, $like);
}
$allUsersSql .= " ORDER BY USER_ID DESC";
$allUsersStmt = db_query($conn, $allUsersSql, $allUsersParams);
if ($allUsersStmt) {
    while ($row = db_fetch_assoc($allUsersStmt)) {
        $allUsers[] = $row;
    }
}

$allListings = [];
$allListingsSql = "SELECT L.LISTING_ID, L.TITLE, L.CATEGORY, L.PRICE, L.`STATUS`, L.DATE_POSTED,
                          U.USERNAME
                   FROM LISTINGS L
                   JOIN USERS U ON L.USER_ID = U.USER_ID
                   WHERE 1=1";
$allListingsParams = [];
if ($listingCategory !== '') {
    if ($listingCategory === 'Course-Specific') {
        $allListingsSql .= " AND L.CATEGORY LIKE ?";
        $allListingsParams[] = 'Course-Specific%';
    } else {
        $categoryValues = categoryFilterValues($listingCategory);
        $placeholders = implode(',', array_fill(0, count($categoryValues), '?'));
        $allListingsSql .= " AND L.CATEGORY IN ({$placeholders})";
        foreach ($categoryValues as $categoryValue) {
            $allListingsParams[] = $categoryValue;
        }
    }
}
if ($listingStatus !== '') {
    $allListingsSql .= " AND L.`STATUS` = ?";
    $allListingsParams[] = $listingStatus;
}
if ($listingSearch !== '') {
    $allListingsSql .= " AND (L.TITLE LIKE ? OR L.DESCRIPTION LIKE ? OR U.USERNAME LIKE ?)";
    $like = '%' . $listingSearch . '%';
    array_push($allListingsParams, $like, $like, $like);
}
$allListingsSql .= " ORDER BY L.DATE_POSTED DESC, L.LISTING_ID DESC";
$allListingsStmt = db_query($conn, $allListingsSql, $allListingsParams);
if ($allListingsStmt) {
    while ($row = db_fetch_assoc($allListingsStmt)) {
        $allListings[] = $row;
    }
}

$allReports = [];
if ($reportsTableExists) {
    $reportImageSelect = $listingImagesTableExists ? "I.FILE_PATH AS LISTING_IMAGE," : "NULL AS LISTING_IMAGE,";
    $reportImageJoin = $listingImagesTableExists ? "LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1" : "";
    $allReportsSql = "SELECT R.REPORT_ID, R.LISTING_ID, R.REPORT_REASON, R.REPORT_DETAILS,
                              R.REPORT_STATUS, R.CREATED_AT,
                              L.TITLE, L.DESCRIPTION, L.CATEGORY, L.PRICE, L.`CONDITION`,
                              L.MEETUP_SPOT, L.PAYMENT_METHOD, L.DATE_POSTED,
                              L.`STATUS` AS LISTING_STATUS,
                              {$reportImageSelect}
                              REPORTER.USERNAME AS REPORTER_USERNAME,
                              OWNER.USERNAME AS OWNER_USERNAME
                      FROM LISTING_REPORTS R
                      LEFT JOIN LISTINGS L ON R.LISTING_ID = L.LISTING_ID
                      {$reportImageJoin}
                      LEFT JOIN USERS REPORTER ON R.REPORTER_USER_ID = REPORTER.USER_ID
                      LEFT JOIN USERS OWNER ON R.LISTING_OWNER_USER_ID = OWNER.USER_ID
                      WHERE 1=1";
    $allReportsParams = [];
    if ($reportFilter !== '') {
        $allReportsSql .= " AND R.REPORT_STATUS = ?";
        $allReportsParams[] = $reportFilter;
    }
    $allReportsSql .= " ORDER BY R.CREATED_AT DESC, R.REPORT_ID DESC";
    $allReportsStmt = db_query($conn, $allReportsSql, $allReportsParams);
    if ($allReportsStmt) {
        while ($row = db_fetch_assoc($allReportsStmt)) {
            $allReports[] = $row;
        }
    }
}

$auditMetrics = [
    metricRow($auditStudentMetricLabel, $auditStudentsRegistered),
    metricRow('Listings Posted', $auditListings),
    metricRow('Sold Listings', $auditSoldListings),
    metricRow('Active Listings', $auditActiveListings),
    metricRow('Reports Filed', $auditReports)
];

$auditLines = [$auditPeriodLabel . ' Marketplace Summary'];
$auditLines[] = 'Period: ' . $auditStart->format('M d, Y') . ' to ' . (clone $auditEnd)->modify('-1 day')->format('M d, Y');
foreach ($auditMetrics as $metric) {
    $auditLines[] = $metric['label'] . ': ' . number_format((int) $metric['value']);
}
$auditLines[] = '';
$auditLines[] = 'Listings by Category (' . $auditPeriodLabel . ')';
foreach ($auditCategoryCounts as $categoryName => $count) {
    $auditLines[] = shortCategoryName($categoryName) . ': ' . number_format((int) $count);
}

if (isset($_GET['export']) && $_GET['export'] === 'audit_pdf') {
    outputSimplePdf(
        'pipeline_audit_snapshot_' . date('Ymd_His') . '.pdf',
        'Pipeline Audit Snapshot',
        $auditLines
    );
}

if (isset($_GET['export']) && $_GET['export'] === 'audit_excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="pipeline_audit_' . $auditPeriod . '_' . date('Ymd_His') . '.xls"');
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>Pipeline ' . htmlspecialchars($auditPeriodLabel) . ' Audit</h2>';
    echo '<p>Period: ' . htmlspecialchars($auditStart->format('M d, Y')) . ' to ' . htmlspecialchars((clone $auditEnd)->modify('-1 day')->format('M d, Y')) . '</p>';
    echo '<p>Generated ' . htmlspecialchars(date('M d, Y h:i A')) . '</p>';
    echo '<h3>Marketplace Summary</h3><table border="1"><tr><th>Metric</th><th>Value</th></tr>';
    foreach ($auditMetrics as $metric) {
        echo '<tr><td>' . htmlspecialchars($metric['label']) . '</td><td>' . number_format((int) $metric['value']) . '</td></tr>';
    }
    echo '</table>';
    echo '<h3>Listings by Category</h3><table border="1"><tr><th>Category</th><th>Listings</th></tr>';
    foreach ($auditCategoryCounts as $categoryName => $count) {
        echo '<tr><td>' . htmlspecialchars($categoryName) . '</td><td>' . number_format((int) $count) . '</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'reports') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pipeline_reports_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report Reason', 'Status', 'Listing Title', 'Reporter Username', 'Created At']);

    if ($reportsTableExists) {
        $csvSql = "SELECT R.REPORT_REASON, R.REPORT_STATUS, L.TITLE,
                          U.USERNAME AS REPORTER_USERNAME, R.CREATED_AT
                   FROM LISTING_REPORTS R
                   JOIN LISTINGS L ON R.LISTING_ID = L.LISTING_ID
                   JOIN USERS U ON R.REPORTER_USER_ID = U.USER_ID
                   ORDER BY R.CREATED_AT DESC, R.REPORT_ID DESC";
        $csvStmt = db_query($conn, $csvSql);
        if ($csvStmt) {
            while ($row = db_fetch_assoc($csvStmt)) {
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
        <a class="<?php echo navClass('dashboard', $activePage); ?>" href="admin_dashboard.php?page=dashboard">
            <span class="icon">⊞</span> Dashboard
        </a>
        <a class="<?php echo navClass('students', $activePage); ?>" href="admin_dashboard.php?page=students">
            <span class="icon">👥</span> Students
        </a>
        <a class="<?php echo navClass('categories', $activePage); ?>" href="admin_dashboard.php?page=categories">
            <span class="icon">📦</span> All Categories
        </a>
        <a class="<?php echo navClass('reports', $activePage); ?>" href="admin_dashboard.php?page=reports">
            <span class="icon">⚠️</span> Reports
            <span class="nav-badge"><?php echo number_format($pendingReports); ?></span>
        </a>

        <div class="nav-section-label">System</div>
        <a class="<?php echo navClass('audit', $activePage); ?>" href="admin_dashboard.php?page=audit"><span class="icon">📋</span> Audit Snapshot</a>
    </nav>

    <div class="sidebar-footer">
        <button class="btn-logout" onclick="window.location.href='admin_logout.php'">⏻ &nbsp;LOGOUT</button>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h2><?php echo htmlspecialchars($pageTitles[$activePage] ?? 'Dashboard Overview'); ?></h2>
            <p>✦ DLSU-D Campus Marketplace - Admin View</p>
        </div>
        <div class="topbar-right">
            <span class="topbar-date"><?php echo htmlspecialchars($todayDisplay); ?></span>
        </div>
    </div>

    <div class="content">
        <?php if ($activePage === 'dashboard'): ?>


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
                            <div class="ustd"><?php echo htmlspecialchars((string) $user['STD_NUM']); ?> - <?php echo htmlspecialchars((string) $user['CYS']); ?></div>
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
                            <div class="desc">Run the report table script first to populate admin reports.</div>
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
                            <div class="desc"><?php echo htmlspecialchars($report['TITLE']); ?> - by @<?php echo htmlspecialchars($report['REPORTER_USERNAME']); ?></div>
                        </div>
                        <div class="alert-time"><?php echo htmlspecialchars(formatDateShort($report['CREATED_AT'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif ($activePage === 'students'): ?>
        <?php if ($flashMessage !== ''): ?>
        <div class="flash-message"><?php echo htmlspecialchars($flashMessage); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-title">Students <span><?php echo number_format(count($allUsers)); ?> shown</span></div>
            <form class="filter-row" method="get" action="admin_dashboard.php">
                <input type="hidden" name="page" value="students">
                <input class="filter-input" type="search" name="student_q" placeholder="Search name, username, student no., course, or section" value="<?php echo htmlspecialchars($studentSearch); ?>">
                <button class="action-btn primary" type="submit">Apply</button>
                <a class="action-btn secondary" href="admin_dashboard.php?page=students">Reset</a>
            </form>
            <table>
                <thead><tr><th>Name</th><th>Student No.</th><th>Course / Year / Section</th><th>User ID</th></tr></thead>
                <tbody>
                    <?php if (empty($allUsers)): ?>
                    <tr><td colspan="4">No users found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($allUsers as $user): ?>
                    <tr>
                        <td><div class="item-name"><?php echo htmlspecialchars(trim($user['FIRST_NAME'] . ' ' . $user['LAST_NAME'])); ?></div></td>
                        <td><?php echo htmlspecialchars((string) $user['STD_NUM']); ?></td>
                        <td><?php echo htmlspecialchars((string) $user['CYS']); ?></td>
                        <td style="color:var(--muted); font-size:11px">ID #<?php echo (int) $user['USER_ID']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($activePage === 'reports'): ?>
        <?php if ($flashMessage !== ''): ?>
        <div class="flash-message"><?php echo htmlspecialchars($flashMessage); ?></div>
        <?php endif; ?>
        <div class="actions-row">
            <a class="action-btn secondary" href="admin_dashboard.php?export=reports">📊 &nbsp;Export Reports CSV</a>
        </div>
        <div class="card">
            <div class="card-title">Reports <span><?php echo $reportsTableExists ? number_format(count($allReports)) . ' shown' : 'Table Missing'; ?></span></div>
            <form class="filter-row" method="get" action="admin_dashboard.php">
                <input type="hidden" name="page" value="reports">
                <select class="filter-select" name="report_status">
                    <option value="">All report statuses</option>
                    <option value="Pending"<?php echo selectedAttr($reportFilter, 'Pending'); ?>>Pending</option>
                    <option value="Resolved"<?php echo selectedAttr($reportFilter, 'Resolved'); ?>>Resolved</option>
                </select>
                <button class="action-btn primary" type="submit">Apply</button>
                <a class="action-btn secondary" href="admin_dashboard.php?page=reports">Reset</a>
            </form>
            <table class="reports-table">
                <thead><tr><th>Reported Listing</th><th>Reason</th><th>People</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (!$reportsTableExists): ?>
                    <tr><td colspan="5">LISTING_REPORTS table was not found.</td></tr>
                    <?php elseif (empty($allReports)): ?>
                    <tr><td colspan="5">No reports found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($allReports as $report): ?>
                    <?php
                        $listingImage = !empty($report['LISTING_IMAGE']) ? (string) $report['LISTING_IMAGE'] : 'assets/img/no_image.png';
                        $listingTitle = $report['TITLE'] ?? 'Listing deleted';
                    ?>
                    <tr>
                        <td>
                            <div class="report-listing">
                                <img class="report-thumb" src="<?php echo htmlspecialchars($listingImage); ?>" alt="<?php echo htmlspecialchars($listingTitle); ?>" onerror="this.onerror=null;this.src='assets/img/no_image.png';">
                                <div class="report-listing-info">
                                    <div class="item-name"><?php echo htmlspecialchars($listingTitle); ?></div>
                                    <?php if (!empty($report['TITLE'])): ?>
                                    <div class="item-seller"><?php echo htmlspecialchars(shortCategoryName(normalizeCategoryName($report['CATEGORY']))); ?> - ₱<?php echo number_format((float) $report['PRICE'], 2); ?></div>
                                    <?php else: ?>
                                    <div class="item-seller">The original listing is no longer available.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($report['TITLE'])): ?>
                            <details class="listing-details">
                                <summary>View item details</summary>
                                <div class="listing-detail-grid">
                                    <span>Condition</span>
                                    <strong><?php echo htmlspecialchars((string) $report['CONDITION']); ?></strong>
                                    <span>Status</span>
                                    <strong><?php echo htmlspecialchars((string) $report['LISTING_STATUS']); ?></strong>
                                    <span>Meet-up</span>
                                    <strong><?php echo htmlspecialchars((string) ($report['MEETUP_SPOT'] ?? '-')); ?></strong>
                                    <span>Payment</span>
                                    <strong><?php echo htmlspecialchars((string) ($report['PAYMENT_METHOD'] ?? '-')); ?></strong>
                                    <span>Posted</span>
                                    <strong><?php echo htmlspecialchars(formatDateLong($report['DATE_POSTED'])); ?></strong>
                                </div>
                                <div class="listing-full-description">
                                    <?php echo $report['DESCRIPTION'] ? nl2br(htmlspecialchars((string) $report['DESCRIPTION'])) : 'No description provided.'; ?>
                                </div>
                            </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="item-name"><?php echo htmlspecialchars($report['REPORT_REASON']); ?></div>
                            <details class="report-details">
                                <summary>View report details</summary>
                                <p><?php echo nl2br(htmlspecialchars((string) $report['REPORT_DETAILS'])); ?></p>
                                <span>Reported <?php echo htmlspecialchars(formatDateLong($report['CREATED_AT'])); ?></span>
                            </details>
                        </td>
                        <td>
                            <div class="report-person">Reporter <strong>@<?php echo htmlspecialchars($report['REPORTER_USERNAME'] ?? 'unknown'); ?></strong></div>
                            <div class="report-person">Owner <strong>@<?php echo htmlspecialchars($report['OWNER_USERNAME'] ?? 'unknown'); ?></strong></div>
                        </td>
                        <td><span class="badge <?php echo statusBadgeClass($report['REPORT_STATUS']); ?>"><?php echo htmlspecialchars($report['REPORT_STATUS']); ?></span></td>
                        <td>
                            <div class="table-actions">
                                <?php if ($report['REPORT_STATUS'] === 'Pending'): ?>
                                <form method="post" action="admin_dashboard.php?page=reports" onsubmit="return confirm('Deny this report?');">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['REPORT_ID']; ?>">
                                    <button class="mini-btn" type="submit" name="report_action" value="deny">Deny</button>
                                </form>
                                <?php endif; ?>
                                <?php if (!empty($report['LISTING_ID']) && !empty($report['TITLE'])): ?>
                                <form method="post" action="admin_dashboard.php?page=reports" onsubmit="return confirm('Delete this listing from the database? This cannot be undone.');">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['REPORT_ID']; ?>">
                                    <button class="mini-btn danger" type="submit" name="report_action" value="delete_listing">Delete Item</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($activePage === 'categories'): ?>
        <div class="mid-grid">
            <div class="card" style="grid-column: span 2;">
                <div class="card-title">Listings by Category <span>Live Data</span></div>
                <?php foreach ($categoryOrder as $categoryName): ?>
                <?php $count = (int) ($categoryCounts[$categoryName] ?? 0); $width = $maxCategoryCount > 0 ? max(10, round(($count / $maxCategoryCount) * 100)) : 0; ?>
                <a class="cat-row category-link" href="admin_dashboard.php?page=categories&category=<?php echo urlencode($categoryName); ?>">
                    <div class="cat-name"><?php echo htmlspecialchars(shortCategoryName($categoryName)); ?></div>
                    <div class="cat-bar-bg"><div class="cat-bar" style="width:<?php echo $width; ?>%"></div></div>
                    <div class="cat-count"><?php echo $count; ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="card">
                <div class="card-title">Category Total <span><?php echo number_format($totalListings); ?> listings</span></div>
                <div class="legend">
                    <a class="legend-item" href="admin_dashboard.php?page=categories">All Categories<span><?php echo number_format($totalListings); ?></span></a>
                    <?php foreach ($categoryCounts as $categoryName => $count): ?>
                    <a class="legend-item" href="admin_dashboard.php?page=categories&category=<?php echo urlencode($categoryName); ?>"><?php echo htmlspecialchars(shortCategoryName($categoryName)); ?><span><?php echo number_format((int) $count); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <?php echo $listingCategory !== '' ? htmlspecialchars(shortCategoryName(normalizeCategoryName($listingCategory))) : 'All Categories'; ?> Listings
                <span><?php echo number_format(count($allListings)); ?> shown</span>
            </div>
            <form class="filter-row" method="get" action="admin_dashboard.php">
                <input type="hidden" name="page" value="categories">
                <input class="filter-input" type="search" name="listing_q" placeholder="Search item title, description, or seller" value="<?php echo htmlspecialchars($listingSearch); ?>">
                <select class="filter-select" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categoryOrder as $categoryName): ?>
                    <option value="<?php echo htmlspecialchars($categoryName); ?>"<?php echo selectedAttr($listingCategory, $categoryName); ?>><?php echo htmlspecialchars($categoryName); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter-select" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['Available', 'Sold'] as $statusOption): ?>
                    <option value="<?php echo htmlspecialchars($statusOption); ?>"<?php echo selectedAttr($listingStatus, $statusOption); ?>><?php echo htmlspecialchars($statusOption); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="action-btn primary" type="submit">Apply</button>
                <a class="action-btn secondary" href="admin_dashboard.php?page=categories">Reset</a>
            </form>
            <table>
                <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (empty($allListings)): ?>
                    <tr><td colspan="5">No listings found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($allListings as $listing): ?>
                    <?php $categoryName = normalizeCategoryName($listing['CATEGORY']); $statusName = $listing['STATUS']; ?>
                    <tr>
                        <td><div class="item-name"><?php echo htmlspecialchars($listing['TITLE']); ?></div><div class="item-seller">by @<?php echo htmlspecialchars($listing['USERNAME']); ?></div></td>
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
        <?php elseif ($activePage === 'audit'): ?>
        <form class="filter-row" method="get" action="admin_dashboard.php">
            <input type="hidden" name="page" value="audit">
            <select class="filter-select" name="audit_period">
                <option value="daily"<?php echo selectedAttr($auditPeriod, 'daily'); ?>>Daily</option>
                <option value="weekly"<?php echo selectedAttr($auditPeriod, 'weekly'); ?>>Weekly</option>
                <option value="monthly"<?php echo selectedAttr($auditPeriod, 'monthly'); ?>>Monthly</option>
            </select>
            <button class="action-btn primary" type="submit">Apply</button>
        </form>
        <div class="actions-row">
            <a class="action-btn primary" href="admin_dashboard.php?export=audit_pdf&audit_period=<?php echo urlencode($auditPeriod); ?>">📄 &nbsp;Download PDF</a>
            <a class="action-btn secondary" href="admin_dashboard.php?export=audit_excel&audit_period=<?php echo urlencode($auditPeriod); ?>">📊 &nbsp;Download Excel</a>
        </div>
        <div class="stats-grid">
            <?php foreach ($auditMetrics as $index => $metric): ?>
            <?php $colors = ['green', 'olive', 'amber', 'teal']; ?>
                <div class="stat-card <?php echo $colors[$index % count($colors)]; ?>">
                <div class="stat-label"><?php echo htmlspecialchars($metric['label']); ?></div>
                <div class="stat-value"><?php echo number_format((int) $metric['value']); ?></div>
                <div class="stat-change"><?php echo htmlspecialchars($auditPeriodLabel); ?> audit period</div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bottom-grid">
            <div class="card">
                <div class="card-title">Audit Metrics <span><?php echo htmlspecialchars($auditPeriodLabel); ?></span></div>
                <table>
                    <thead><tr><th>Metric</th><th>Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($auditMetrics as $metric): ?>
                        <tr><td><?php echo htmlspecialchars($metric['label']); ?></td><td style="font-weight:700"><?php echo number_format((int) $metric['value']); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div class="card">
                    <div class="card-title">Category Snapshot <span><?php echo htmlspecialchars($auditPeriodLabel); ?></span></div>
                    <div class="legend">
                        <?php foreach ($auditCategoryCounts as $categoryName => $count): ?>
                        <div class="legend-item"><?php echo htmlspecialchars(shortCategoryName($categoryName)); ?><span><?php echo number_format((int) $count); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>
</main>

</body>
</html>
