<?php
session_start();
require_once __DIR__ . '/db.php';
$conn = db_connect();

// Fetch recent available listings for the carousel
$sqlCarousel = "SELECT L.*, I.FILE_PATH, U.FIRST_NAME, U.LAST_NAME 
                FROM LISTINGS L
                LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
                JOIN USERS U ON L.USER_ID = U.USER_ID
                WHERE (L.STATUS = 'Available' OR L.STATUS IS NULL)
                ORDER BY L.DATE_POSTED DESC LIMIT 8";
$stmtCarousel = db_query($conn, $sqlCarousel);
$carouselItems = [];
if ($stmtCarousel) {
    while($row = db_fetch_assoc($stmtCarousel)){
        $carouselItems[] = $row;
    }
}

// Fetch recent available listings for the showcase
$currentCategory = isset($_GET['cat']) ? $_GET['cat'] : 'all';

$sqlShowcase = "SELECT L.*, I.FILE_PATH, U.FIRST_NAME, U.LAST_NAME 
                FROM LISTINGS L
                LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
                JOIN USERS U ON L.USER_ID = U.USER_ID
                WHERE (L.STATUS = 'Available' OR L.STATUS IS NULL)";

$paramsShowcase = [];
if ($currentCategory !== 'all') {
    $sqlShowcase .= " AND L.CATEGORY = ?";
    $paramsShowcase[] = $currentCategory;
}

$sqlShowcase .= " ORDER BY L.DATE_POSTED DESC LIMIT 12";
$stmtShowcase = db_query($conn, $sqlShowcase, $paramsShowcase);
$showcaseItems = [];
if ($stmtShowcase) {
    while($row = db_fetch_assoc($stmtShowcase)){
        $showcaseItems[] = $row;
    }
}

// Helper function to map categories to gradients and emojis
function getCategoryStyle($category) {
    if (stripos($category, 'Books') !== false) return ['📚', 'linear-gradient(135deg,#d4f0e0,#86c9a0)'];
    if (stripos($category, 'Clothing') !== false) return ['👗', 'linear-gradient(135deg,#e8d5f5,#c9a8e8)'];
    if (stripos($category, 'Electronics') !== false) return ['💻', 'linear-gradient(135deg,#fde8c8,#f5c98a)'];
    if (stripos($category, 'Hobbies') !== false) return ['🎸', 'linear-gradient(135deg,#d0f0e8,#8fd5c0)'];
    if (stripos($category, 'Events') !== false) return ['🎟️', 'linear-gradient(135deg,#ffd6d6,#ffadad)'];
    if (stripos($category, 'Course') !== false) return ['🧪', 'linear-gradient(135deg,#dce8ff,#a8c4f5)'];
    return ['🛍️', 'linear-gradient(135deg,#eee,#ccc)'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline — The Campus Marketplace</title>
    <meta name="description"
        content="Pipeline is the official student marketplace for De La Salle University Dasmariñas. Buy, sell, and connect with fellow Lasallians.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
</head>

<body>

    <!-- ── NAVBAR ── -->
    <header class="top-nav" id="topNav">
        <div class="nav-left">
            <a href="index.php" class="nav-logo">
                <img src="assets/img/pipeline_wireframe-removebg.png" alt="Pipeline Logo">
            </a>
            <nav class="nav-links">
                <a href="browse.php" class="nav-link">Browse</a>
                <a href="browse.php?cat=Clothing+%26+Apparel" class="nav-link">Clothing</a>
                <a href="browse.php?cat=Hobbies+%26+Lifestyle" class="nav-link">Hobbies</a>
                <a href="browse.php?cat=Electronics" class="nav-link">Electronics</a>
                <a href="#about" class="nav-link">About</a>
            </nav>
        </div>
        <div class="nav-right">
            <a href="login.html" class="nav-login">Log In</a>
            <a href="regis.html" class="nav-signup">Sign Up</a>
        </div>
    </header>

    <main>

        <!-- ── HERO ── -->
        <section class="hero">
            <div class="hero-inner">

                <!-- LEFT: text content -->
                <div class="hero-left">
                    <p class="hero-eyebrow">✦ The campus exchange for Lasallians</p>
                    <h1 class="hero-headline">Discover, Buy &amp; Sell<br>Inside La Salle</h1>
                    <p class="hero-sub">Pipeline is the official student marketplace of DLSU-D. Find textbooks,
                        clothing, gadgets, and more from your campus community.</p>
                    <div class="hero-ctas">
                        <a href="login.html" class="cta-primary">Get Started — It's Free</a>
                        <a href="#" class="cta-ghost js-auth-trigger">Browse Listings ↗</a>
                    </div>
                    <!-- Category pills -->
                    <div class="hero-pills">
                        <a href="index.php?cat=all#showcase" class="pill <?php echo ($currentCategory === 'all') ? 'pill-active' : ''; ?>">All</a>
                        <a href="index.php?cat=Clothing+%26+Apparel#showcase" class="pill <?php echo ($currentCategory === 'Clothing & Apparel') ? 'pill-active' : ''; ?>">Clothing &amp; Apparel</a>
                        <a href="index.php?cat=Electronics#showcase" class="pill <?php echo ($currentCategory === 'Electronics and Tech') ? 'pill-active' : ''; ?>">Electronics</a>
                        <a href="index.php?cat=Books#showcase" class="pill <?php echo ($currentCategory === 'Books') ? 'pill-active' : ''; ?>">Books</a>
                        <a href="index.php?cat=Hobbies+%26+Lifestyle#showcase" class="pill <?php echo ($currentCategory === 'Hobbies & Lifestyle') ? 'pill-active' : ''; ?>">Hobbies</a>
                        <a href="index.php?cat=Events+%26+Tickets#showcase" class="pill <?php echo ($currentCategory === 'Events & Tickets') ? 'pill-active' : ''; ?>">Events</a>
                        <a href="index.php?cat=Course-Specific#showcase" class="pill <?php echo ($currentCategory === 'Course-Specific') ? 'pill-active' : ''; ?>">Course-Specific</a>
                    </div>
                </div>

                <!-- RIGHT: carousel -->
                <div class="hero-right">
                    <div class="hero-carousel-wrap">
                        <!-- Up arrow -->
                        <button class="hc-arrow hc-arrow--prev" id="hcPrev" aria-label="Previous">&#8593;</button>

                        <div class="hc-track-outer">
                            <div class="hc-track" id="hcTrack">

                                <?php if (!empty($carouselItems)): ?>
                                    <?php foreach($carouselItems as $item): ?>
                                        <?php 
                                        $imgSrc = !empty($item['FILE_PATH']) ? htmlspecialchars($item['FILE_PATH']) : '';
                                        $price = '₱' . number_format($item['PRICE'], 2);
                                        list($emoji, $bg) = getCategoryStyle($item['CATEGORY']);
                                        ?>
                                        <div class="hc-card js-auth-trigger">
                                            <div class="hc-card-img" style="background:<?php echo $bg; ?>; position: relative;">
                                                <?php if ($imgSrc): ?>
                                                    <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($item['TITLE']); ?>" style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0;">
                                                <?php else: ?>
                                                    <span class="hc-emoji"><?php echo $emoji; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="hc-card-body">
                                                <div class="hc-status-row">
                                                    <span class="hc-status-tag <?php echo (strtolower($item['STATUS']) === 'sold') ? 'sold' : ''; ?>">
                                                        <?php echo htmlspecialchars($item['STATUS'] ?? 'Available'); ?>
                                                    </span>
                                                </div>
                                                <span class="hc-tag"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                                                <p class="hc-title"><?php echo htmlspecialchars($item['TITLE']); ?></p>
                                                <p class="hc-price"><?php echo $price; ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="hc-card">
                                        <div class="hc-card-img" style="background:linear-gradient(135deg,#d4f0e0,#86c9a0)">
                                            <span class="hc-emoji">📚</span>
                                        </div>
                                        <div class="hc-card-body">
                                            <span class="hc-tag">Books</span>
                                            <p class="hc-title">Pre-loved Textbooks</p>
                                            <p class="hc-price">From ₱50</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <!-- Down arrow -->
                        <button class="hc-arrow hc-arrow--next" id="hcNext" aria-label="Next">&#8595;</button>
                    </div>

                    <!-- Dot indicators -->
                    <div class="hc-dots" id="hcDots"></div>
                </div>

            </div>
        </section>

        <!-- ── LISTING SHOWCASE GRID ── -->

        <section class="showcase" id="showcase">
            <div class="container">
                <div class="section-header" style="margin-bottom: 40px; text-align: left;">
                    <span class="section-eyebrow">✦ Browse Listings</span>
                    <h2 style="font-family: 'DM Serif Display', serif; font-size: 42px;">
                        <?php echo ($currentCategory === 'all') ? 'Recently Listed' : htmlspecialchars($currentCategory) . ' Listings'; ?>
                    </h2>
                </div>
                
                <?php if (empty($showcaseItems)): ?>
                    <div style="text-align: center; padding: 60px 0; color: var(--text-soft); background: var(--white); border-radius: var(--radius-md); border: 1px solid var(--border); animation: fadeInUp 0.6s ease forwards;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🛍️</div>
                        <h3>No listings found in this category</h3>
                        <p>Check back later or explore other categories!</p>
                    </div>
                <?php else: ?>
                    <div class="showcase-grid">
                        <?php foreach($showcaseItems as $index => $item): ?>
                            <?php 
                            $imgSrc = !empty($item['FILE_PATH']) ? htmlspecialchars($item['FILE_PATH']) : '';
                            list($emoji, $bg) = getCategoryStyle($item['CATEGORY']);
                            $tallClass = ($index === 1 || $index === 4) ? ' shot-card--tall' : '';
                            $delay = $index * 0.08; // 80ms stagger
                            ?>
                            <article class="shot-card<?php echo $tallClass; ?> js-auth-trigger" style="animation-delay: <?php echo $delay; ?>s;">
                                <div class="shot-img" style="background: <?php echo $bg; ?>; position: relative;">
                                    <div class="listing-status-badge <?php echo (strtolower($item['STATUS']) === 'sold') ? 'sold' : ''; ?>">
                                        <?php echo htmlspecialchars($item['STATUS'] ?? 'Available'); ?>
                                    </div>
                                    <?php if ($imgSrc): ?>
                                        <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($item['TITLE']); ?>" style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0;">
                                    <?php else: ?>
                                        <span class="shot-emoji"><?php echo $emoji; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="shot-meta">
                                    <span class="shot-category"><?php echo htmlspecialchars($item['CATEGORY']); ?></span>
                                    <h3 class="shot-title"><?php echo htmlspecialchars($item['TITLE']); ?></h3>
                                    <p class="shot-desc">₱<?php echo number_format($item['PRICE'], 2); ?> • <?php echo htmlspecialchars($item['CONDITION']); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                </div>

                <div class="showcase-cta">
                    <a href="login.html" class="cta-primary">View All Listings</a>
                </div>
            </div>
        </section>

        <!-- ── COMMUNITY STRIP ── -->
        <section class="community" id="about">
            <div class="container community-inner">
                <div class="community-text">
                    <span class="section-eyebrow">Our Community</span>
                    <h2>From the Rotunda<br>to the Lake</h2>
                    <p>The official community exchange for Lasallians. Skip the long commutes and find exactly what you
                        need right here on campus. Sustainable, local, and 100% Green &amp; White.</p>
                    <a href="regis.html" class="cta-primary" style="margin-top:28px; display:inline-flex;">Join Pipeline
                        Today</a>
                </div>
                <div class="community-media">
                    <img src="assets/img/rotundatolake.jpg" alt="DLSU-D Campus">
                </div>
            </div>
        </section>

        <!-- ── STATS STRIP ── -->
        <section class="stats-strip">
            <div class="container stats-grid">
                <div class="stat-item">
                    <span class="stat-num">100%</span>
                    <span class="stat-label">Lasallian Sellers</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-num">7</span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-num">Free</span>
                    <span class="stat-label">To Join &amp; List</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-num">On-Campus</span>
                    <span class="stat-label">Meetup Only</span>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS ── -->
        <section class="how-it-works">
            <div class="container">
                <div class="section-header">
                    <span class="section-eyebrow">Simple Process</span>
                    <h2>How Pipeline Works</h2>
                </div>
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">01</div>
                        <h3>Create an Account</h3>
                        <p>Sign up using your DLSU-D school email. Verification keeps the community safe and
                            Lasallian-only.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-number">02</div>
                        <h3>Browse or List</h3>
                        <p>Find what you need across 7 categories, or post your own item for sale in under a minute.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-number">03</div>
                        <h3>Meet Up on Campus</h3>
                        <p>Arrange a safe meetup spot inside DLSU-D. No shipping, no hassle — just campus-to-campus.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CALLOUT BANNER ── -->
        <section class="callout-banner">
            <div class="container callout-content">
                <h2>Ready to start your<br>Pipeline journey?</h2>
                <p>Join thousands of Lasallians already buying and selling on campus.</p>
                <div class="callout-actions">
                    <a href="regis.html" class="cta-primary">Sign Up for Free</a>
                    <a href="#" class="cta-ghost cta-ghost--light js-auth-trigger">Browse Listings</a>
                </div>
            </div>
        </section>

        <!-- ── AUTH MODAL ── -->
        <div class="auth-modal-overlay" id="authModal">
            <div class="auth-modal">
                <button class="auth-modal-close" id="authModalClose" aria-label="Close">&times;</button>
                <div class="auth-modal-icon">🔒</div>
                <h3 class="auth-modal-title">Join Pipeline</h3>
                <p class="auth-modal-desc">You need to log in or create an account with your DLSU-D email to view full listings and connect with sellers.</p>
                <div class="auth-modal-actions">
                    <a href="login.html" class="cta-primary">Log In</a>
                    <a href="regis.html" class="cta-ghost">Create an Account</a>
                </div>
            </div>
        </div>

    </main>

    <!-- ── FOOTER ── -->
    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-top">
                <div class="footer-brand">
                    <img src="assets/img/pipeline_logo_light.png" alt="Pipeline">
                    <p>The official student marketplace of<br>De La Salle University – Dasmariñas.</p>
                    <p class="footer-legal">E-Commerce trademark application submitted and pending approval (Ref No:
                        25/1099-13741)</p>
                </div>
                <div class="footer-nav-group">
                    <span class="footer-nav-title">Marketplace</span>
                    <a href="browse.php">Browse All</a>
                    <a href="browse.php?cat=Clothing+%26+Apparel">Clothing</a>
                    <a href="browse.php?cat=Electronics">Electronics</a>
                    <a href="browse.php?cat=Books">Books</a>
                </div>
                <div class="footer-nav-group">
                    <span class="footer-nav-title">Account</span>
                    <a href="login.html">Log In</a>
                    <a href="regis.html">Sign Up</a>
                    <a href="login_admin.html">Admin</a>
                </div>
                <div class="footer-nav-group">
                    <span class="footer-nav-title">Company</span>
                    <a href="#">Help Centre</a>
                    <a href="#">Contact Us</a>
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2026 Pipeline Marketplace. All rights reserved.</span>
                <div class="footer-bottom-links">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                    <a href="#">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        /* ── Sticky nav ── */
        const nav = document.getElementById('topNav');
        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 10);
        });

        /* ── Hero Carousel ── */
        (function () {
            const track = document.getElementById('hcTrack');
            const prevBtn = document.getElementById('hcPrev');
            const nextBtn = document.getElementById('hcNext');
            const dotsWrap = document.getElementById('hcDots');

            const cards = Array.from(track.children);
            const CARD_H = 240;  
            const STEP = CARD_H;
            let current = 0;
            let autoTimer;

            /* Build dots */
            cards.forEach((_, i) => {
                const d = document.createElement('button');
                d.className = 'hc-dot' + (i === 0 ? ' hc-dot--active' : '');
                d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
                d.addEventListener('click', () => goTo(i));
                dotsWrap.appendChild(d);
            });
            const dots = Array.from(dotsWrap.children);

            function goTo(idx) {
                current = Math.max(0, Math.min(idx, cards.length - 1));
                track.style.transform = `translateY(-${current * STEP}px)`;
                dots.forEach((d, i) => d.classList.toggle('hc-dot--active', i === current));
                prevBtn.disabled = current === 0;
                nextBtn.disabled = current === cards.length - 1;
            }

            prevBtn.addEventListener('click', () => { resetAuto(); goTo(current - 1); });
            nextBtn.addEventListener('click', () => { resetAuto(); goTo(current + 1); });

            function autoPlay() {
                goTo(current < cards.length - 1 ? current + 1 : 0);
            }

            function resetAuto() {
                clearInterval(autoTimer);
                autoTimer = setInterval(autoPlay, 3000);
            }

            goTo(0);
            autoTimer = setInterval(autoPlay, 3000);

            /* Pause on hover */
            track.closest('.hc-track-outer').addEventListener('mouseenter', () => clearInterval(autoTimer));
            track.closest('.hc-track-outer').addEventListener('mouseleave', () => resetAuto());
        })();

        /* ── Auth Modal ── */
        (function() {
            const triggers = document.querySelectorAll('.js-auth-trigger');
            const modal = document.getElementById('authModal');
            const closeBtn = document.getElementById('authModalClose');

            if (!modal) return;

            triggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    modal.classList.add('active');
                });
            });

            closeBtn.addEventListener('click', () => {
                modal.classList.remove('active');
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        })();
    </script>

</body>

</html>