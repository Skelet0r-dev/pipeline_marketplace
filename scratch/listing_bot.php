<?php
// ============================================================
// listing_bot.php  –  Automatic Sample Listing Generator
// ============================================================
require_once __DIR__ . '/../db.php';

$conn = db_connect();
if(!$conn) die("Connection failed");

// Settings (Initial defaults)
$targetUserId = 1;
$count = 5;

// Sample Data
$samples = [
    [
        'title' => 'Business Law Textbook (De Leon)',
        'desc' => 'Latest edition, no highlights or pen marks. Perfect for CBAA students.',
        'price' => 450.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'Like New',
        'img' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&q=80&w=400'
    ],
    [
        'title' => 'Drawing Board A3',
        'desc' => 'Rotring A3 Drawing Board with parallel motion and drafting head. Great for CEAT.',
        'price' => 1200.00,
        'cat' => 'Academics',
        'cond' => 'Used',
        'img' => 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&q=80&w=400'
    ],
    [
        'title' => 'Wireless Mouse (Logitech)',
        'desc' => 'Silent clicks, compact design. Battery lasts for months.',
        'price' => 300.00,
        'cat' => 'Electronics & Tech',
        'cond' => 'New',
        'img' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?auto=format&fit=crop&q=80&w=400'
    ],
    [
        'title' => 'DLSU-D Green Lanyard',
        'desc' => 'Official university lanyard, never worn.',
        'price' => 50.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'New',
        'img' => 'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?auto=format&fit=crop&q=80&w=400'
    ],
    [
        'title' => 'Noise Cancelling Headphones',
        'desc' => 'Sony WH-1000XM4. Best for focusing in the library.',
        'price' => 12000.00,
        'cat' => 'Electronics and Tech',
        'cond' => 'Like New',
        'img' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&q=80&w=400'
    ],
    [
        'title' => 'Mechanic Keyboard (Hot-swappable)',
        'desc' => 'Blue switches, RGB lighting. High performance for coding.',
        'price' => 1800.00,
        'cat' => 'Electronics and Tech',
        'cond' => 'Used',
        'img' => 'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?auto=format&fit=crop&q=80&w=400'
    ],
    [
        'title' => 'C Programming Absolute Beginner Guide',
        'desc' => 'Must-have for CICS students starting their coding journey.',
        'price' => 350.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'Like New',
        'img' => 'https://images.unsplash.com/photo-1516116216624-53e697fedbea?auto=format&fit=crop&q=80&w=400'
    ],
    // --- Local Assets from /listings/ ---
    [
        'title' => 'Vintage Gaming Console',
        'desc' => 'Classic console with two controllers. Perfect for nostalgia trips.',
        'price' => 1500.00,
        'cat' => 'Electronics and Tech',
        'cond' => 'Used',
        'img' => 'listings/listing_10_1777974523.jpg'
    ],
    [
        'title' => 'Study Desk Organizer',
        'desc' => 'Keep your pens, notes, and phone in one place. Very sleek.',
        'price' => 120.00,
        'cat' => 'Academics',
        'cond' => 'New',
        'img' => 'listings/listing_11_1777974582.jpg'
    ],
    [
        'title' => 'Dorm-sized Electric Kettle',
        'desc' => 'Boils water in minutes. Essential for cup noodles and coffee.',
        'price' => 450.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'Like New',
        'img' => 'listings/listing_12_1777992791.jpg'
    ],
    [
        'title' => 'Comfy Fleece Jacket',
        'desc' => 'Perfect for the cold aircon in the classrooms.',
        'price' => 600.00,
        'cat' => 'Clothing & Apparel',
        'cond' => 'Used',
        'img' => 'listings/listing_13_1777992914.jpg'
    ],
    [
        'title' => 'Scientific Calculator (FX-991ES Plus)',
        'desc' => 'Essential for engineering students. Barely used.',
        'price' => 850.00,
        'cat' => 'Electronics and Tech',
        'cond' => 'Like New',
        'img' => 'listings/listing_14_1777997065.jpg'
    ],
    [
        'title' => 'Ergonomic Study Lamp',
        'desc' => 'Adjustable brightness, perfect for late night review.',
        'price' => 350.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'New',
        'img' => 'listings/listing_15_1777997083.jpg'
    ],
    [
        'title' => 'Portable Power Bank (20k mAh)',
        'desc' => 'Keeps your phone charged during long lecture days.',
        'price' => 1200.00,
        'cat' => 'Electronics and Tech',
        'cond' => 'Like New',
        'img' => 'listings/listing_16_1777997112.jpeg'
    ],
    [
        'title' => 'Gaming Mouse Pad (Extra Large)',
        'desc' => 'Smooth surface, non-slip base. Great for CICS students.',
        'price' => 400.00,
        'cat' => 'Electronics & Tech',
        'cond' => 'New',
        'img' => 'listings/listing_17_1777997182.jpg'
    ],
    [
        'title' => 'Heavy Duty Stapler',
        'desc' => 'Can staple up to 50 pages. Perfect for thesis season.',
        'price' => 250.00,
        'cat' => 'Academics',
        'cond' => 'Used',
        'img' => 'listings/listing_18_1778005163.jpg'
    ],
    [
        'title' => 'Bluetooth Speaker (Mini)',
        'desc' => 'Small but loud. Perfect for dorm hangouts.',
        'price' => 500.00,
        'cat' => 'Electronics & Tech',
        'cond' => 'Used',
        'img' => 'listings/listing_19_1778083713.jpg'
    ],
    [
        'title' => 'Correction Tape (Pack of 3)',
        'desc' => 'Brand new, essential for exam season.',
        'price' => 100.00,
        'cat' => 'Academics',
        'cond' => 'New',
        'img' => 'listings/listing_20_1778083776.jpg'
    ],
    [
        'title' => 'Whiteboard Markers (Set)',
        'desc' => 'Assorted colors. Used for group study sessions.',
        'price' => 150.00,
        'cat' => 'Academics',
        'cond' => 'Like New',
        'img' => 'listings/listing_21_1778084766.jpg'
    ],
    [
        'title' => 'Compact Desk Fan',
        'desc' => 'Keep cool in the dorm. USB powered and very quiet.',
        'price' => 280.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'New',
        'img' => 'listings/listing_22_1778419203.jpg'
    ],
    [
        'title' => 'Mechanical Pencil (0.5mm)',
        'desc' => 'Premium drafting pencil, perfect for architectural sketches.',
        'price' => 200.00,
        'cat' => 'Academics',
        'cond' => 'Like New',
        'img' => 'listings/listing_23_1778419234.png'
    ],
    [
        'title' => 'Laptop Sleeve (13-14 inch)',
        'desc' => 'Padded interior to protect your device from scratches.',
        'price' => 350.00,
        'cat' => 'Electronics & Tech',
        'cond' => 'New',
        'img' => 'listings/listing_24_1778419390.jpg'
    ],
    [
        'title' => 'Stainless Steel Food Container',
        'desc' => 'Eco-friendly way to bring your baon to campus.',
        'price' => 450.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'Like New',
        'img' => 'listings/listing_25_1778419411.jpg'
    ],
    [
        'title' => 'Canvas Backpack (Retro)',
        'desc' => 'Stylish and spacious. Fits all your daily school essentials.',
        'price' => 1200.00,
        'cat' => 'Clothing & Apparel',
        'cond' => 'Used',
        'img' => 'listings/listing_26_1778419450.jpg'
    ],
    [
        'title' => 'Notebook (Dotted/Journal)',
        'desc' => 'Great for bullet journaling or taking clean lecture notes.',
        'price' => 180.00,
        'cat' => 'Academics',
        'cond' => 'New',
        'img' => 'listings/listing_27_1778419484.jpg'
    ],
    [
        'title' => 'Foldable Laptop Stand',
        'desc' => 'Improve your posture during long study hours. Lightweight.',
        'price' => 550.00,
        'cat' => 'Electronics & Tech',
        'cond' => 'Like New',
        'img' => 'listings/listing_28_1778419506.jpg'
    ],
    [
        'title' => 'University Green Hoodie',
        'desc' => 'Stay warm and show your school spirit. Very soft fabric.',
        'price' => 850.00,
        'cat' => 'Clothing & Apparel',
        'cond' => 'Used',
        'img' => 'listings/listing_29_1778419533.jpg'
    ],
    [
        'title' => 'Mini Table Humidifier',
        'desc' => 'Great for dry dorm rooms. Changes colors with LED lights.',
        'price' => 300.00,
        'cat' => 'Hobbies & Lifestyle',
        'cond' => 'New',
        'img' => 'listings/listing_30_1778419552.jpg'
    ]
];

// --- Dynamically Add EVERYTHING in /listings/ ---
$localImages = glob(__DIR__ . '/../listings/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
foreach ($localImages as $fullPath) {
    $filename = basename($fullPath);
    // Avoid re-adding those already hardcoded above if they were picked up
    if (strpos($filename, 'listing_10_') !== false || strpos($filename, 'listing_11_') !== false || 
        strpos($filename, 'listing_12_') !== false || strpos($filename, 'listing_13_') !== false) continue;

    $samples[] = [
        'title' => 'Mystery Student Find',
        'desc' => 'A unique item discovered in the campus marketplace. Check it out!',
        'price' => (float)rand(50, 2000),
        'cat' => ['Electronics and Tech', 'Academics', 'Clothing & Apparel', 'Hobbies & Lifestyle', 'Food', 'Events & Tickets', 'Course-Specific'][array_rand([0,1,2,3,4,5,6])],
        'cond' => ['New', 'Like New', 'Used'][array_rand([0,1,2])],
        'img' => 'listings/' . $filename
    ];
}

echo "<!DOCTYPE html><html><head><title>Listing Bot</title><style>body{font-family:sans-serif; padding:40px; background:#f8fafc;} .success{color:#087832; font-weight:bold;} .card{background:white; padding:20px; border-radius:12px; box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1); max-width:600px; margin:auto;}</style></head><body>";
echo "<div class='card'><h1>Listing Bot 🤖</h1>";

if(isset($_POST['run'])){
    $targetUserId = (int)$_POST['user_id'];
    $count = (int)$_POST['count'];
    $created = 0;

    // Check if user exists
    $check = db_query($conn, "SELECT USER_ID FROM USERS WHERE USER_ID = ?", [$targetUserId]);
    if(!db_fetch($check)){
        echo "<p style='color:red;'>Error: User ID $targetUserId does not exist. Please select a valid user.</p>";
        echo "<a href='listing_bot.php'>Try Again</a>";
    } else {
        for($i=0; $i<$count; $i++){
            $item = $samples[array_rand($samples)];
            $title = $item['title'] . " #" . rand(1000, 9999);
            
            $sql = "INSERT INTO LISTINGS (USER_ID, TITLE, DESCRIPTION, PRICE, CATEGORY, `CONDITION`, STATUS, MEETUP_SPOT, PAYMENT_METHOD) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Available', 'Library', 'Cash/GCash')";
            $res = db_query($conn, $sql, [$targetUserId, $title, $item['desc'], $item['price'], $item['cat'], $item['cond']]);
            
            if($res){
                $listingId = db_last_insert_id($conn);
                
                // Explicitly check image insert
                $resImg = db_query($conn, "INSERT INTO LISTING_IMG (LISTING_ID, FILE_PATH, IS_PRIMARY) VALUES (?, ?, 1)", [$listingId, $item['img']]);
                
                if ($resImg) {
                    $created++;
                } else {
                    echo "<p style='color:orange;'>Listing created but IMAGE failed: " . db_last_error() . "</p>";
                    $created++; // Still count the listing
                }
            } else {
                echo "<p style='color:red;'>Error creating listing: " . db_last_error() . "</p>";
            }
        }
        echo "<p class='success'>Successfully created $created listings for User ID $targetUserId!</p>";
        echo "<a href='listing_bot.php'>Back to Bot</a> | <a href='../dashboard.php'>Go to Dashboard</a>";
    }
} else {
    // Fetch users for dropdown
    $userRes = db_query($conn, "SELECT USER_ID, USERNAME, FIRST_NAME, LAST_NAME FROM USERS ORDER BY USERNAME ASC");
    $users = [];
    while($u = db_fetch_assoc($userRes)) $users[] = $u;

    echo "<p>Select a user and choose how many random listings to generate.</p>";
    echo "<form method='POST'>
            <label>Select Target User: </label><br>
            <select name='user_id' style='padding:8px; margin:10px 0; border:1px solid #ddd; border-radius:4px; width:100%; max-width:300px;'>";
    foreach($users as $user){
        echo "<option value='{$user['USER_ID']}'>{$user['USERNAME']} ({$user['FIRST_NAME']} {$user['LAST_NAME']})</option>";
    }
    echo "  </select><br>
            <label>Number of listings: </label><br>
            <input type='number' name='count' value='5' style='padding:8px; margin:10px 0; border:1px solid #ddd; border-radius:4px; width:100px;'><br>
            <button type='submit' name='run' style='padding:10px 20px; background:#087832; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;'>RUN BOT 🚀</button>
          </form>";
    
    if(empty($users)){
        echo "<p style='color:red; margin-top:20px;'>⚠️ No users found in database. Please register a user first.</p>";
    }
}

echo "</div></body></html>";
db_close($conn);
?>
