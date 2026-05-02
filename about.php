<?php
require_once 'includes/db.php';

function aboutCount(PDO $pdo, string $query): int
{
    try {
        $value = $pdo->query($query)->fetchColumn();
        return $value !== false ? (int) $value : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

$pageTitle = 'About | CampusMarketplace';
$metaDescription = 'Learn about CampusMarketplace — the #1 platform for student-to-student commerce.';

$browseUrl = getSpaUrl();
$sellUrl = isLoggedIn() ? $baseUrl . 'add_product.php' : $baseUrl . 'register.php';

$approvedProducts = aboutCount($pdo, "SELECT COUNT(*) FROM products WHERE status = 'approved'");
$activeSellers = aboutCount($pdo, "SELECT COUNT(DISTINCT user_id) FROM products WHERE status = 'approved'");
$liveCategories = aboutCount($pdo, "SELECT COUNT(DISTINCT category) FROM products WHERE status = 'approved'");
$completedOrders = aboutCount($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'completed'");

$tiers = [];
try {
    $tiers = $pdo->query("SELECT * FROM account_tiers ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tiers = [];
}

$categories = [
    [
        'name' => 'Phones & Accessories',
        'description' => 'Smartphones, chargers, cases, earpods, and everyday mobile gear.',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><line x1="11" y1="18" x2="13" y2="18"/></svg>'
    ],
    [
        'name' => 'Computers & Tech',
        'description' => 'Laptops, monitors, keyboards, and accessories for class and creative work.',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/></svg>'
    ],
    [
        'name' => 'Fashion & Personal Style',
        'description' => 'Clothing, shoes, bags, and accessories that move fast around campus.',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3a4 4 0 0 1-8 0L4 5l1 5h2v10h10V10h2l1-5-4-2z"/></svg>'
    ],
    [
        'name' => 'Books & Study Materials',
        'description' => 'Textbooks, notebooks, handouts, and useful study extras for students.',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>'
    ],
    [
        'name' => 'Food & Daily Needs',
        'description' => 'Snacks, groceries, and practical daily supplies that save students time.',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2v12"/><path d="M10 2v12"/><path d="M8 2v20"/><path d="M15 2v8c0 1.1.9 2 2 2h1v10"/><path d="M19 2v20"/></svg>'
    ],
    [
        'name' => 'Hostels & Spaces',
        'description' => 'Rooms, shared spaces, and housing leads tailored to student life.',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/></svg>'
    ]
];

require_once 'includes/header.php';
?>

<style>
    .about-shell {
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.92), rgba(255, 255, 255, 1));
    }

    .about-hero {
        position: relative;
        min-height: 72vh;
        display: flex;
        align-items: flex-end;
        overflow: hidden;
        color: #fff;
        background: #0f172a;
    }

    .about-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(135deg, rgba(15, 23, 42, 0.86), rgba(30, 41, 59, 0.38)),
            linear-gradient(180deg, rgba(15, 23, 42, 0.08), rgba(15, 23, 42, 0.9)),
            url('<?= getAssetUrl('low-angle-photography-red-concrete-building.jpg') ?>') center/cover no-repeat;
    }

    .about-hero-content {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 6rem 5% 4rem;
        display: grid;
        gap: 2rem;
    }

    .about-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        width: fit-content;
        padding: 0.45rem 0.9rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.16);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .about-hero h1 {
        max-width: 820px;
        margin: 0;
        font-size: clamp(2.6rem, 4vw, 4.8rem);
        line-height: 1.02;
        font-weight: 800;
    }

    .about-hero p {
        max-width: 720px;
        margin: 0;
        font-size: 1.08rem;
        line-height: 1.75;
        color: rgba(255, 255, 255, 0.86);
    }

    .about-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.9rem;
    }

    .about-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 50px;
        padding: 0.85rem 1.4rem;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
    }

    .about-btn:hover {
        transform: translateY(-1px);
    }

    .about-btn-primary {
        background: #7c3aed;
        color: #fff;
    }

    .about-btn-primary:hover {
        background: #6d28d9;
        color: #fff;
    }

    .about-btn-secondary {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .about-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
    }

    .about-hero-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
        margin-top: 1rem;
    }

    .about-strip-card {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.98);
    }

    .about-strip-card strong,
    .about-strip-card span {
        display: block;
    }

    .about-strip-card strong {
        font-size: 1rem;
        font-weight: 800;
        color: #fff;
    }

    .about-strip-card span {
        margin-top: 0.3rem;
        font-size: 0.9rem;
        line-height: 1.5;
        color: rgba(255, 255, 255, 0.8);
    }

    .about-section {
        padding: 4rem 0;
    }

    .about-band {
        padding: 2rem 0 0;
    }

    .about-grid-2 {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        gap: 2rem;
        align-items: center;
    }

    .about-kicker {
        margin: 0 0 0.9rem;
        color: #0f766e;
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .about-section h2 {
        margin: 0 0 1rem;
        font-size: clamp(2rem, 3vw, 3rem);
        line-height: 1.08;
        letter-spacing: 0;
    }

    .about-section p {
        color: var(--text-muted);
        line-height: 1.8;
        font-size: 1rem;
    }

    .about-list {
        margin: 1.25rem 0 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.9rem;
    }

    .about-list li {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 0.85rem;
        align-items: start;
    }

    .about-list-badge {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(124, 58, 237, 0.12);
        color: #7c3aed;
        font-weight: 800;
        font-size: 0.85rem;
    }

    .about-image-panel {
        min-height: 420px;
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 18px 60px rgba(15, 23, 42, 0.12);
        background:
            linear-gradient(180deg, rgba(15, 23, 42, 0.06), rgba(15, 23, 42, 0.32)),
            url('<?= getAssetUrl('IMG_5834.webp') ?>') center/cover no-repeat;
    }

    .about-card-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .about-card {
        padding: 1.3rem;
        border-radius: 18px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        min-height: 100%;
    }

    .about-card h3,
    .about-card h4 {
        margin: 0 0 0.65rem;
        font-size: 1.05rem;
    }

    .about-card p,
    .about-card li {
        font-size: 0.95rem;
        line-height: 1.7;
        color: var(--text-muted);
    }

    .about-step-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        margin-bottom: 0.85rem;
        background: rgba(14, 165, 233, 0.12);
        color: #0369a1;
        font-weight: 800;
    }

    .about-category-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.6rem;
        color: var(--text-main);
    }

    .about-category-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(99, 102, 241, 0.1);
        color: #4f46e5;
        flex-shrink: 0;
    }

    .about-tier-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .about-tier-card {
        position: relative;
        padding: 2.5rem 2rem;
        border-radius: 24px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }

    .about-tier-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 32px 64px rgba(0,0,0,0.1);
    }

    .about-tier-card[data-tier="premium"] {
        border-color: rgba(245, 158, 11, 0.28);
        box-shadow: 0 18px 50px rgba(245, 158, 11, 0.12);
    }

    .about-tier-card[data-tier="pro"] {
        border-color: rgba(107, 114, 128, 0.24);
    }

    .about-tier-name {
        font-size: 1.2rem;
        font-weight: 800;
        text-transform: capitalize;
        margin: 0;
    }

    .about-tier-price {
        margin: 0.4rem 0 0.25rem;
        font-size: 1.7rem;
        font-weight: 800;
        color: var(--text-main);
    }

    .about-tier-meta {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .about-tier-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.55rem;
    }

    .about-tier-list li {
        display: grid;
        grid-template-columns: 20px minmax(0, 1fr);
        gap: 0.6rem;
        align-items: start;
        color: var(--text-muted);
    }

    .about-tier-list li::before {
        content: "•";
        color: #7c3aed;
        font-size: 1.1rem;
        line-height: 1;
        margin-top: 0.15rem;
    }

    .about-accent-band {
        padding: 2rem;
        border-radius: 24px;
        background:
            linear-gradient(135deg, rgba(14, 165, 233, 0.08), rgba(249, 115, 22, 0.08)),
            var(--card-bg);
        border: 1px solid var(--border);
    }

    .about-faq {
        display: grid;
        gap: 1rem;
    }

    .about-faq-item {
        padding: 1.35rem 1.4rem;
        border-radius: 18px;
        background: var(--card-bg);
        border: 1px solid var(--border);
    }

    .about-faq-item h3 {
        margin: 0 0 0.45rem;
        font-size: 1rem;
    }

    .about-cta {
        border-radius: 28px;
        padding: 2.25rem;
        background:
            linear-gradient(135deg, rgba(124, 58, 237, 0.96), rgba(167, 139, 250, 0.88)),
            #7c3aed;
        color: #fff;
    }

    .about-cta p {
        color: rgba(255, 255, 255, 0.84);
        margin-bottom: 0;
    }

    .about-cta-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.25rem;
        flex-wrap: wrap;
    }

    .about-cta .about-btn-secondary {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.18);
    }

    @media (max-width: 1024px) {
        .about-hero-strip,
        .about-card-grid,
        .about-tier-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .about-grid-2 {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 720px) {
        .about-hero {
            min-height: 68vh;
        }

        .about-hero-content {
            padding-top: 5rem;
            padding-bottom: 3rem;
        }

        .about-hero-strip,
        .about-card-grid,
        .about-tier-grid {
            grid-template-columns: 1fr;
        }

        .about-tier-card {
            padding: 2rem 1.5rem;
        }

        .about-cta {
            padding: 1.6rem;
        }
    }
</style>

</div>

<main class="about-shell">
    <section class="about-hero">
        <div class="about-hero-content">
            <div class="about-eyebrow">About CampusMarketplace</div>
            <div>
                <h1>The campus-first marketplace built for real student life.</h1>
            </div>
            <p>
                CampusMarketplace is a focused buying and selling platform for students and campus communities.
                It brings listings, direct messaging, order flow, seller storefronts, and trusted discovery into one place
                so people can move items faster without relying on scattered group chats and noisy social feeds.
            </p>
            <div class="about-hero-actions">
                <a class="about-btn about-btn-primary" href="<?= htmlspecialchars($browseUrl, ENT_QUOTES, 'UTF-8') ?>">Browse Listings</a>
                <a class="about-btn about-btn-secondary" href="<?= htmlspecialchars($sellUrl, ENT_QUOTES, 'UTF-8') ?>">Start Selling</a>
            </div>
            <div class="about-hero-strip">
                <div class="about-strip-card">
                    <strong><?= $approvedProducts > 0 ? number_format($approvedProducts) : 'Campus-first' ?></strong>
                    <span><?= $approvedProducts > 0 ? 'live listings already available to browse' : 'built around campus needs instead of generic classifieds' ?></span>
                </div>
                <div class="about-strip-card">
                    <strong><?= $activeSellers > 0 ? number_format($activeSellers) : 'Direct chat' ?></strong>
                    <span><?= $activeSellers > 0 ? 'sellers actively listing products on the platform' : 'buyers and sellers can speak clearly before any meetup' ?></span>
                </div>
                <div class="about-strip-card">
                    <strong><?= $liveCategories > 0 ? number_format($liveCategories) : 'Shareable shops' ?></strong>
                    <span><?= $liveCategories > 0 ? 'categories helping shoppers find what matters faster' : 'every seller can build a catalog people can share outside the app' ?></span>
                </div>
                <div class="about-strip-card">
                    <strong><?= $completedOrders > 0 ? number_format($completedOrders) : 'Flexible tiers' ?></strong>
                    <span><?= $completedOrders > 0 ? 'completed orders showing the platform in motion' : 'basic, pro, and premium setups for different seller stages' ?></span>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <section class="about-section about-band">
            <div class="about-grid-2">
                <div>
                    <p class="about-kicker">What The Site Is About</p>
                    <h2>A single place for students to discover, compare, and move fast on campus deals.</h2>
                    <p>
                        The site is designed around the kinds of things students actually trade every week:
                        phones, laptops, fashion, textbooks, groceries, room spaces, and practical daily essentials.
                        Instead of jumping between multiple social apps, buyers can browse by category, compare listings,
                        message sellers directly, and track the progress of an order in one experience.
                    </p>
                    <p>
                        For sellers, it is more than a listing board. It is a small storefront system with product uploads,
                        images, descriptions, order tracking, visibility options, shop sharing, and tier-based growth tools that help
                        serious student sellers look more organized and more trustworthy.
                    </p>
                    <ul class="about-list">
                        <li>
                            <span class="about-list-badge">1</span>
                            <div>
                                <strong>Campus-focused discovery</strong><br>
                                Products are organized for the kinds of needs people have on and around campus.
                            </div>
                        </li>
                        <li>
                            <span class="about-list-badge">2</span>
                            <div>
                                <strong>Direct buyer-seller flow</strong><br>
                                Chat, order updates, and follow-through live inside the same platform.
                            </div>
                        </li>
                        <li>
                            <span class="about-list-badge">3</span>
                            <div>
                                <strong>Seller growth tools</strong><br>
                                Tiers, shop links, and listing visibility help sellers reach more people cleanly.
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="about-image-panel" aria-hidden="true"></div>
            </div>
        </section>

        <section class="about-section" id="how-it-works">
            <p class="about-kicker">How It Works</p>
            <h2>Simple for buyers, structured for sellers, and easy to understand from the first visit.</h2>
            <div class="about-card-grid">
                <article class="about-card">
                    <div class="about-step-label">1</div>
                    <h3>Browse what matters</h3>
                    <p>
                        Visitors can explore listings by category, search by keyword, compare pricing, and open product pages
                        to get a better sense of condition, photos, and seller information.
                    </p>
                </article>
                <article class="about-card">
                    <div class="about-step-label">2</div>
                    <h3>Message before you move</h3>
                    <p>
                        Buyers can open a conversation with the seller to confirm availability, negotiate details,
                        ask questions, and agree on where and when a meetup should happen.
                    </p>
                </article>
                <article class="about-card">
                    <div class="about-step-label">3</div>
                    <h3>Place and complete the order</h3>
                    <p>
                        Once both sides are ready, the order is created on the platform. Seller and buyer confirmations
                        help mark the journey from requested to sold to completed.
                    </p>
                </article>
                <article class="about-card">
                    <div class="about-step-label">4</div>
                    <h3>Sell with a cleaner catalog</h3>
                    <p>
                        Sellers add products with images, descriptions, prices, and categories, which makes the shop easier
                        to trust and much easier for buyers to scan.
                    </p>
                </article>
                <article class="about-card">
                    <div class="about-step-label">5</div>
                    <h3>Grow with your own storefront</h3>
                    <p>
                        Each seller can build a catalog and use a shareable shop link to bring in traffic from WhatsApp,
                        Instagram, or anywhere else they already reach people.
                    </p>
                </article>
                <article class="about-card">
                    <div class="about-step-label">6</div>
                    <h3>Build reputation over time</h3>
                    <p>
                        Reviews, completed orders, listing quality, and consistent communication all help a seller look more
                        dependable to the next buyer who lands on the page.
                    </p>
                </article>
            </div>
        </section>

        <section class="about-section">
            <p class="about-kicker">What People Can Find Here</p>
            <h2>Built around the categories students actually search for.</h2>
            <div class="about-card-grid">
                <?php foreach ($categories as $category): ?>
                    <article class="about-card">
                        <div class="about-category-head">
                            <span class="about-category-icon"><?= $category['icon'] ?></span>
                            <h3><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                        <p><?= htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!empty($tiers)): ?>
            <section class="about-section">
                <p class="about-kicker">Seller Tiers</p>
                <h2>Choose the setup that matches how seriously you want to sell.</h2>
                <p>
                    CampusMarketplace supports different seller levels so casual users and more active sellers can both
                    operate comfortably. The core idea is simple: start small, grow when you need more reach, and keep your shop
                    looking organized as your catalog expands.
                </p>
                <div class="about-tier-grid">
                    <?php foreach ($tiers as $tier): ?>
                        <?php
                        $tierName = strtolower((string) ($tier['tier_name'] ?? 'basic'));
                        $tierBenefits = json_decode($tier['benefits'] ?? '[]', true);
                        if (!is_array($tierBenefits)) {
                            $tierBenefits = [];
                        }

                        $tierItems = [];
                        if (!empty($tier['product_limit'])) {
                            $tierItems[] = 'Up to ' . (int) $tier['product_limit'] . ' active listings';
                        }
                        if (!empty($tier['image_limit'])) {
                            $tierItems[] = 'Up to ' . (int) $tier['image_limit'] . ' image' . ((int) $tier['image_limit'] === 1 ? '' : 's') . ' per listing';
                        }
                        if (!empty($tier['ads_boost'])) {
                            $tierItems[] = 'Boost support for better product visibility';
                        }
                        foreach ($tierBenefits as $benefit) {
                            if (is_string($benefit) && $benefit !== '') {
                                $tierItems[] = $benefit;
                            }
                        }
                        $tierItems = array_values(array_unique($tierItems));

                        $price = isset($tier['price']) ? (float) $tier['price'] : 0.0;
                        $originalPrice = isset($tier['original_price']) ? (float) $tier['original_price'] : 0.0;
                        $isDiscounted = ($originalPrice > $price);

                        $duration = isset($tier['duration']) ? (int) $tier['duration'] : 0;
                        $priceLabel = $price <= 0 ? 'Free' : '₵' . number_format($price, $price == floor($price) ? 0 : 2);
                        $originalPriceLabel = '₵' . number_format($originalPrice, 0);
                        $durationLabel = $duration > 0 ? 'Valid for ' . $duration . ' month' . ($duration === 1 ? '' : 's') : 'Flexible duration';
                        
                        // Contrast & Visibility Logic
                        $isPremium = ($tierName === 'premium');
                        $cardStyle = $isPremium ? "background:var(--gold); border-color:rgba(0,0,0,0.1); color:#000;" : "";
                        $priceColor = $isPremium ? "#000" : "var(--primary)";
                        $oldPriceColor = $isPremium ? "rgba(0,0,0,0.35)" : "rgba(255,255,255,0.4)";
                        $nameColor = $isPremium ? "#000" : "var(--text-main)";
                        $badgeStyle = $isPremium 
                            ? "background:#000; color:#fff; font-size:0.45em; padding:4px 10px; border-radius:99px; vertical-align:middle; margin-left:8px; font-weight:900; text-transform:uppercase;"
                            : "background:var(--gold); color:#000; font-size:0.45em; padding:4px 10px; border-radius:99px; vertical-align:middle; margin-left:8px; font-weight:900; text-transform:uppercase;";
                        ?>
                        <article class="about-tier-card" data-tier="<?= htmlspecialchars($tierName, ENT_QUOTES, 'UTF-8') ?>" style="<?= $cardStyle ?>">
                            <p class="about-tier-name" style="color:<?= $nameColor ?>; font-weight:850;"><?= htmlspecialchars(ucfirst($tierName), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="about-tier-price-wrap" style="display:flex; flex-direction:column; gap:2px; margin-bottom:1.5rem;">
                                <?php if ($isDiscounted): ?>
                                    <span style="text-decoration: line-through; color: <?= $oldPriceColor ?>; font-size: 1rem; font-weight: 700;"><?= htmlspecialchars($originalPriceLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <div style="display:flex; align-items:baseline; gap:4px;">
                                    <span class="about-tier-price" style="font-size:2.8rem; font-weight:900; line-height:1; letter-spacing:-0.05em; color:<?= $priceColor ?>;">
                                        <?= htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if ($isDiscounted): ?>
                                        <span class="sale-badge" style="<?= $badgeStyle ?>">Save <?= round((1 - ($price/$originalPrice)) * 100) ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="about-tier-meta"><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></div>
                            <ul class="about-tier-list">
                                <?php foreach ($tierItems as $tierItem): ?>
                                    <li><span><?= htmlspecialchars($tierItem, ENT_QUOTES, 'UTF-8') ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="about-section">
            <div class="about-accent-band">
                <p class="about-kicker">Why Choose CampusMarketplace</p>
                <h2>Because students need something faster, cleaner, and more relevant than random online marketplaces.</h2>
                <div class="about-card-grid">
                    <article class="about-card">
                        <h3>Closer to your real audience</h3>
                        <p>
                            The people browsing are more likely to understand the campus context, delivery realities,
                            preferred meetup points, and student price expectations.
                        </p>
                    </article>
                    <article class="about-card">
                        <h3>Less noise, better intent</h3>
                        <p>
                            Categories, order flow, and listing structure help buyers arrive with more clarity than they do in
                            crowded timelines and repost-heavy group chats.
                        </p>
                    </article>
                    <article class="about-card">
                        <h3>Built-in seller growth</h3>
                        <p>
                            Sellers do not just post once and hope. They can build a recognizable store, share a catalog link,
                            improve visibility, and grow repeat trust.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-section" id="safe-trading">
            <p class="about-kicker">Safe Trading Habits</p>
            <h2>Good marketplace habits make every deal smoother.</h2>
            <div class="about-grid-2">
                <div>
                    <p>
                        The platform is designed to help people communicate clearly, but good judgment still matters.
                        Buyers and sellers get the best results when they confirm details early, inspect items carefully,
                        and keep meetups straightforward.
                    </p>
                    <ul class="about-list">
                        <li>
                            <span class="about-list-badge">A</span>
                            <div>Use product photos, descriptions, and chat to confirm condition and availability before meeting.</div>
                        </li>
                        <li>
                            <span class="about-list-badge">B</span>
                            <div>Agree on a safe, public campus location and a clear time before leaving for the meetup.</div>
                        </li>
                        <li>
                            <span class="about-list-badge">C</span>
                            <div>Inspect the product properly before making payment, especially for electronics and high-value items.</div>
                        </li>
                        <li>
                            <span class="about-list-badge">D</span>
                            <div>Leave honest reviews after successful orders so the next buyer or seller has better context.</div>
                        </li>
                    </ul>
                </div>
                <div class="about-card">
                    <h3>What makes this platform easier to trust?</h3>
                    <p>
                        Listings are structured, sellers build identifiable profiles, orders can move through clear status updates,
                        and product discovery is tied to real categories rather than endless reposts. That combination makes the
                        experience feel more intentional and easier to follow.
                    </p>
                    <p>
                        Most deals are still direct between buyer and seller, which keeps communication flexible while letting
                        both sides decide what works best for pickup and payment.
                    </p>
                </div>
            </div>
        </section>

        <section class="about-section">
            <p class="about-kicker">Common Questions</p>
            <h2>Quick answers for new visitors.</h2>
            <div class="about-faq">
                <article class="about-faq-item">
                    <h3>Do I need an account before I can use the site?</h3>
                    <p>
                        You can explore public listings first. An account becomes important when you want to message sellers,
                        place orders, manage a cart, or start selling your own products.
                    </p>
                </article>
                <article class="about-faq-item">
                    <h3>How do payments usually work?</h3>
                    <p>
                        Most marketplace deals are arranged directly between the buyer and seller, usually around meetup or
                        delivery. The platform helps with discovery, communication, and order tracking so both sides stay aligned.
                    </p>
                </article>
                <article class="about-faq-item">
                    <h3>Can sellers share their shop outside the platform?</h3>
                    <p>
                        Yes. Sellers can build a catalog and use a shareable shop link to bring in traffic from WhatsApp,
                        Instagram, or any other place they already reach people.
                    </p>
                </article>
                <article class="about-faq-item">
                    <h3>Why should someone choose this site instead of a general marketplace?</h3>
                    <p>
                        Because it is shaped around the speed, categories, and trust signals that matter in campus life.
                        It feels more relevant, more focused, and easier to use for student buying and selling.
                    </p>
                </article>
            </div>
        </section>

        <section class="about-section">
            <div class="about-cta">
                <div class="about-cta-row">
                    <div>
                        <p class="about-kicker" style="color: rgba(255,255,255,0.82);">Ready To Explore?</p>
                        <h2 style="margin-bottom:0.65rem; color:#fff;">Find your next deal or open your shop on CampusMarketplace.</h2>
                        <p>Browse what is already live, or create an account and start listing in a way that feels organized from day one.</p>
                    </div>
                    <div class="about-hero-actions">
                        <a class="about-btn about-btn-secondary" href="<?= htmlspecialchars($browseUrl, ENT_QUOTES, 'UTF-8') ?>">Explore Now</a>
                        <a class="about-btn about-btn-secondary" href="<?= htmlspecialchars($sellUrl, ENT_QUOTES, 'UTF-8') ?>">Create Seller Account</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
