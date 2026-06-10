<?php
require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

// Fetch live stats for social proof
$total_institutes = mysqli_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM tenants"))['count'];
$total_students   = mysqli_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM students WHERE status = 'Active'"))['count'];
$total_enquiries  = mysqli_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM enquiries"))['count'];

function formatStat($n) {
    if ($n >= 1000) return number_format($n / 1000, 1) . 'k+';
    return ($n ?: '10') . '+';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstCRM — Smart Management for Institutes & Coaching Centers</title>
    <meta name="description" content="Run your institute like a pro. Manage enquiries, admissions, attendance, fees and staff with InstCRM. Start your 3-day free trial today.">
    <meta name="keywords" content="coaching center software, institute management system, coaching classes software, student management, fee management, QR attendance, CRM for coaching">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= BASE_URL ?>/">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL ?>/">
    <meta property="og:title" content="InstCRM — Smart Management for Institutes & Coaching Centers">
    <meta property="og:description" content="Run your institute like a pro. Manage enquiries, admissions, attendance, fees and staff with InstCRM. Start your 3-day free trial today.">
    <meta property="og:image" content="<?= BASE_URL ?>/assets/images/og-image.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= BASE_URL ?>/">
    <meta property="twitter:title" content="InstCRM — Smart Management for Institutes & Coaching Centers">
    <meta property="twitter:description" content="Run your institute like a pro. Manage enquiries, admissions, attendance, fees and staff with InstCRM. Start your 3-day free trial today.">
    <meta property="twitter:image" content="<?= BASE_URL ?>/assets/images/twitter-image.jpg">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "InstCRM",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "499",
        "priceCurrency": "INR"
      },
      "description": "Run your institute like a pro. Manage enquiries, admissions, attendance, fees and staff with InstCRM.",
      "url": "<?= BASE_URL ?>/"
    }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary:       #4f46e5;
            --primary-dark:  #3730a3;
            --primary-light: #eef2ff;
            --primary-glow:  rgba(79,70,229,0.15);
            --accent:        #06b6d4;
            --success:       #059669;
            --orange:        #f97316;
            --text:          #0f172a;
            --text-muted:    #64748b;
            --text-light:    #94a3b8;
            --border:        #e2e8f0;
            --bg:            #ffffff;
            --bg-subtle:     #f8fafc;
            --bg-card:       #ffffff;
            --shadow:        0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.05);
            --shadow-lg:     0 8px 30px rgba(0,0,0,.1);
            --shadow-xl:     0 20px 60px rgba(0,0,0,.12);
            --r:             12px;
            --r-lg:          20px;
        }

        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }
        h1, h2, h3, h4, .brand { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* ── NAVIGATION ── */
        nav {
            position: fixed; top: 0; width: 100%; z-index: 999;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 5%;
            height: 72px;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }
        .brand {
            font-size: 1.5rem; font-weight: 800; color: var(--primary);
            text-decoration: none; display: flex; align-items: center; gap: 8px;
        }
        .brand i { font-size: 1.4rem; }
        .brand span { color: var(--text); }
        .nav-links { display: flex; align-items: center; gap: 8px; }
        .nav-links a {
            color: var(--text-muted); text-decoration: none;
            padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 0.95rem;
            transition: all .2s;
        }
        .nav-links a:hover { color: var(--primary); background: var(--primary-light); }
        .btn-nav-login {
            background: transparent; border: 1.5px solid var(--border);
            color: var(--text) !important; border-radius: 8px !important;
        }
        .btn-nav-login:hover { border-color: var(--primary) !important; color: var(--primary) !important; background: var(--primary-light) !important; }
        .btn-nav-cta {
            background: var(--primary) !important; color: white !important;
            border-radius: 8px !important; font-weight: 600 !important;
            box-shadow: 0 2px 8px var(--primary-glow);
        }
        .btn-nav-cta:hover { background: var(--primary-dark) !important; transform: translateY(-1px); box-shadow: 0 4px 16px var(--primary-glow); }

        /* ── HERO ── */
        .hero {
            padding: 140px 5% 80px;
            max-width: 1200px; margin: 0 auto;
            text-align: center; position: relative;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--primary-light); color: var(--primary);
            border: 1px solid #c7d2fe; padding: 6px 16px; border-radius: 100px;
            font-size: 0.85rem; font-weight: 600; margin-bottom: 28px;
            animation: fadeInDown .6s ease;
        }
        .hero-badge i { font-size: 0.9rem; }
        .hero h1 {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 800; line-height: 1.15;
            color: var(--text); margin-bottom: 20px;
            animation: fadeInUp .7s ease;
        }
        .hero h1 .highlight {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 50%, var(--accent) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero-sub {
            font-size: 1.15rem; color: var(--text-muted);
            max-width: 560px; margin: 0 auto 40px;
            animation: fadeInUp .8s ease;
        }
        .hero-actions {
            display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;
            animation: fadeInUp .9s ease;
        }
        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--primary); color: white;
            padding: 14px 32px; border-radius: var(--r);
            font-size: 1rem; font-weight: 700; text-decoration: none;
            box-shadow: 0 4px 20px var(--primary-glow);
            transition: all .25s;
        }
        .btn-hero-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 8px 28px rgba(79,70,229,.3); }
        .btn-hero-secondary {
            display: inline-flex; align-items: center; gap: 8px;
            background: white; color: var(--text);
            padding: 14px 32px; border-radius: var(--r);
            font-size: 1rem; font-weight: 600; text-decoration: none;
            border: 1.5px solid var(--border);
            transition: all .25s;
        }
        .btn-hero-secondary:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
        .hero-note {
            margin-top: 20px; font-size: 0.85rem; color: var(--text-light);
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .hero-note i { color: var(--success); }

        /* ── DASHBOARD PREVIEW ── */
        .preview-wrap {
            max-width: 1100px; margin: 60px auto 0;
            position: relative; padding: 0 5%;
            animation: fadeInUp 1s ease;
        }
        .preview-frame {
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: var(--r-lg); padding: 20px; box-shadow: var(--shadow-xl);
            overflow: hidden;
        }
        .preview-bar {
            display: flex; align-items: center; gap: 6px;
            padding-bottom: 14px; border-bottom: 1px solid var(--border); margin-bottom: 16px;
        }
        .preview-dot { width: 11px; height: 11px; border-radius: 50%; }
        .preview-url {
            flex: 1; background: white; border: 1px solid var(--border);
            border-radius: 6px; padding: 4px 12px; font-size: 0.8rem;
            color: var(--text-muted); font-family: monospace;
        }
        .mini-dashboard {
            display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 12px;
        }
        .mini-card {
            background: white; border-radius: 10px; padding: 12px 14px;
            border: 1px solid var(--border);
        }
        .mini-card-val { font-size: 1.3rem; font-weight: 800; font-family: 'Plus Jakarta Sans',sans-serif; }
        .mini-card-lbl { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }
        .mini-row { display: flex; gap: 10px; }
        .mini-table { background: white; border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border); flex: 2; }
        .mini-chart { background: white; border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border); flex: 1; }
        .mini-row-header { display: flex; justify-content: space-between; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .mini-tr { display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; padding: 5px 0; border-bottom: 1px solid var(--bg-subtle); }
        .mini-badge { padding: 2px 8px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        .mini-badge.green { background: #dcfce7; color: #166534; }
        .mini-badge.blue { background: #dbeafe; color: #1e40af; }
        .mini-badge.orange { background: #ffedd5; color: #9a3412; }
        .mini-bar-wrap { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
        .mini-bar-item { font-size: 0.7rem; color: var(--text-muted); }
        .mini-bar-track { background: #f1f5f9; height: 6px; border-radius: 3px; margin-top: 2px; overflow: hidden; }
        .mini-bar-fill { height: 100%; border-radius: 3px; background: var(--primary); }

        /* ── STATS STRIP ── */
        .stats-strip {
            background: var(--bg-subtle);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 48px 5%;
            margin-top: 80px;
        }
        .stats-inner {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px; max-width: 1000px; margin: 0 auto; text-align: center;
        }
        .stat-val {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.5rem; font-weight: 800; color: var(--primary); line-height: 1;
        }
        .stat-lbl { font-size: 0.9rem; color: var(--text-muted); margin-top: 6px; font-weight: 500; }

        /* ── FEATURES ── */
        .section {
            padding: 100px 5%; max-width: 1200px; margin: 0 auto;
        }
        .section-label {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-light); color: var(--primary);
            border-radius: 100px; padding: 5px 14px; font-size: 0.8rem;
            font-weight: 700; letter-spacing: .5px; text-transform: uppercase;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: 800;
            color: var(--text); margin-bottom: 16px; line-height: 1.2;
        }
        .section-sub { font-size: 1.05rem; color: var(--text-muted); max-width: 600px; }
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px; margin-top: 56px;
        }
        .feat-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--r-lg); padding: 32px 28px;
            transition: all .3s;
        }
        .feat-card:hover {
            border-color: var(--primary); transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .feat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 20px;
        }
        .feat-icon.indigo { background: #eef2ff; color: var(--primary); }
        .feat-icon.cyan   { background: #ecfeff; color: #0891b2; }
        .feat-icon.green  { background: #f0fdf4; color: #16a34a; }
        .feat-icon.orange { background: #fff7ed; color: #ea580c; }
        .feat-icon.purple { background: #faf5ff; color: #7c3aed; }
        .feat-icon.rose   { background: #fff1f2; color: #e11d48; }
        .feat-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; }
        .feat-card p { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; }

        /* ── HOW IT WORKS ── */
        .steps-bg { background: var(--bg-subtle); padding: 100px 5%; }
        .steps-inner { max-width: 900px; margin: 0 auto; }
        .steps-grid { display: flex; flex-direction: column; gap: 32px; margin-top: 56px; position: relative; }
        .step {
            display: flex; gap: 24px; align-items: flex-start;
            background: white; border: 1px solid var(--border);
            border-radius: var(--r-lg); padding: 28px;
            box-shadow: var(--shadow);
        }
        .step-num {
            min-width: 44px; height: 44px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .step h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; }
        .step p { font-size: 0.9rem; color: var(--text-muted); }

        /* ── PRICING ── */
        .pricing-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px; margin-top: 56px;
        }
        .price-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--r-lg); padding: 36px 32px;
            position: relative; transition: all .3s;
        }
        .price-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-4px); }
        .price-card.featured {
            border-color: var(--primary); border-width: 2px;
            box-shadow: 0 0 0 4px rgba(79,70,229,.08), var(--shadow-lg);
        }
        .price-badge {
            position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white; font-size: 0.75rem; font-weight: 700;
            padding: 4px 16px; border-radius: 100px;
            white-space: nowrap; letter-spacing: .5px;
        }
        .price-name { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .price-amount {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 3rem; font-weight: 800; color: var(--text); line-height: 1;
        }
        .price-amount .currency { font-size: 1.5rem; vertical-align: super; }
        .price-amount .period { font-size: 1rem; color: var(--text-muted); font-weight: 500; }
        .price-subtitle { font-size: 0.85rem; color: var(--text-light); margin-top: 6px; margin-bottom: 28px; }
        .price-features { list-style: none; margin-bottom: 32px; }
        .price-features li {
            display: flex; align-items: center; gap: 10px;
            font-size: 0.9rem; color: var(--text-muted); padding: 7px 0;
            border-bottom: 1px solid var(--bg-subtle);
        }
        .price-features li i { color: var(--success); font-size: 1rem; }
        .btn-plan {
            display: block; text-align: center;
            padding: 13px 24px; border-radius: var(--r);
            font-size: 0.95rem; font-weight: 700; text-decoration: none;
            transition: all .25s;
        }
        .btn-plan.primary {
            background: var(--primary); color: white;
            box-shadow: 0 4px 14px var(--primary-glow);
        }
        .btn-plan.primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-plan.outline {
            background: transparent; color: var(--primary);
            border: 1.5px solid var(--primary);
        }
        .btn-plan.outline:hover { background: var(--primary-light); }
        .savings-tag {
            display: inline-block; background: #dcfce7; color: #166534;
            font-size: 0.72rem; font-weight: 700; padding: 2px 8px;
            border-radius: 100px; margin-left: 8px; vertical-align: middle;
        }

        /* ── TESTIMONIALS ── */
        .testimonials-bg { background: var(--bg-subtle); padding: 100px 5%; }
        .testi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px; max-width: 1100px; margin: 56px auto 0;
        }
        .testi-card {
            background: white; border: 1px solid var(--border);
            border-radius: var(--r-lg); padding: 28px;
        }
        .testi-stars { color: #f59e0b; margin-bottom: 14px; }
        .testi-text { font-size: 0.9rem; color: var(--text); line-height: 1.7; margin-bottom: 20px; }
        .testi-author { display: flex; align-items: center; gap: 12px; }
        .testi-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .testi-name { font-size: 0.9rem; font-weight: 700; }
        .testi-role { font-size: 0.8rem; color: var(--text-muted); }

        /* ── CTA BANNER ── */
        .cta-section {
            padding: 100px 5%; text-align: center;
            max-width: 900px; margin: 0 auto;
        }
        .cta-card {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 60%, #0ea5e9 100%);
            border-radius: 28px; padding: 72px 48px;
            box-shadow: 0 20px 60px rgba(79,70,229,.3);
            position: relative; overflow: hidden;
        }
        .cta-card::before {
            content: ''; position: absolute; top: -80px; right: -80px;
            width: 280px; height: 280px; border-radius: 50%;
            background: rgba(255,255,255,.06);
        }
        .cta-card::after {
            content: ''; position: absolute; bottom: -60px; left: -60px;
            width: 200px; height: 200px; border-radius: 50%;
            background: rgba(255,255,255,.04);
        }
        .cta-card h2 {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 800; color: white; margin-bottom: 16px;
        }
        .cta-card p { font-size: 1.05rem; color: rgba(255,255,255,.8); max-width: 500px; margin: 0 auto 36px; }
        .btn-cta {
            display: inline-flex; align-items: center; gap: 10px;
            background: white; color: var(--primary);
            padding: 15px 36px; border-radius: var(--r);
            font-size: 1rem; font-weight: 700; text-decoration: none;
            box-shadow: 0 4px 20px rgba(0,0,0,.2);
            transition: all .25s;
        }
        .btn-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,.25); }
        .cta-note { margin-top: 20px; font-size: 0.85rem; color: rgba(255,255,255,.6); }

        /* ── FOOTER ── */
        footer {
            background: #0f172a; color: #94a3b8;
            padding: 60px 5% 32px;
        }
        .footer-inner {
            max-width: 1100px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 24px;
            padding-bottom: 32px; border-bottom: 1px solid rgba(255,255,255,.08);
            margin-bottom: 24px;
        }
        .footer-brand { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.3rem; font-weight: 800; color: white; display: flex; align-items: center; gap: 8px; }
        .footer-brand i { color: #818cf8; }
        .footer-links { display: flex; gap: 24px; }
        .footer-links a { color: #64748b; text-decoration: none; font-size: 0.9rem; transition: color .2s; }
        .footer-links a:hover { color: white; }
        .footer-copy { max-width: 1100px; margin: 0 auto; font-size: 0.85rem; color: #475569; text-align: center; }

        /* ── ANIMATIONS ── */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .hero { padding: 120px 5% 60px; }
            .mini-dashboard { grid-template-columns: repeat(2,1fr); }
            .mini-row { flex-direction: column; }
            .nav-links .hide-sm { display: none; }
            .price-card { padding: 28px 22px; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav>
    <a href="<?= BASE_URL ?>/" class="brand"><i class="bi bi-mortarboard-fill"></i> Inst<span>CRM</span></a>
    <div class="nav-links">
        <a href="#features" class="hide-sm">Features</a>
        <a href="#pricing" class="hide-sm">Pricing</a>
        <a href="<?= BASE_URL ?>/auth/login.php" class="btn-nav-login" style="padding:8px 18px;border-radius:8px;font-weight:600;font-size:.9rem;">Login</a>
        <a href="#pricing" class="btn-nav-cta" style="padding:9px 20px;font-size:.9rem;">Free Demo →</a>
    </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-badge"><i class="bi bi-stars"></i> Trusted by coaching centres across India</div>
    <h1>
        Run Your Institute<br>
        <span class="highlight">Smarter, Not Harder.</span>
    </h1>
    <p class="hero-sub">
        One powerful platform to manage enquiries, admissions, attendance, fee receipts, and staff — so you can focus on teaching.
    </p>
    <div class="hero-actions">
        <a href="#pricing" class="btn-hero-primary">
            <i class="bi bi-lightning-fill"></i> Start 3-Day Free Demo
        </a>
        <a href="#features" class="btn-hero-secondary">
            <i class="bi bi-play-circle"></i> See What's Inside
        </a>
    </div>
    <div class="hero-note">
        <i class="bi bi-check-circle-fill"></i> No credit card required &nbsp;·&nbsp;
        <i class="bi bi-check-circle-fill"></i> Full access for 3 days &nbsp;·&nbsp;
        <i class="bi bi-check-circle-fill"></i> Cancel anytime
    </div>
</section>

<!-- ── DASHBOARD PREVIEW ── -->
<div class="preview-wrap">
    <div class="preview-frame">
        <div class="preview-bar">
            <div class="preview-dot" style="background:#ef4444;"></div>
            <div class="preview-dot" style="background:#f59e0b;"></div>
            <div class="preview-dot" style="background:#22c55e;"></div>
            <div class="preview-url">app.instcrm.in / dashboard</div>
        </div>
        <div class="mini-dashboard">
            <div class="mini-card"><div class="mini-card-val" style="color:#4f46e5;">128</div><div class="mini-card-lbl">Total Enquiries</div></div>
            <div class="mini-card"><div class="mini-card-val" style="color:#059669;">94</div><div class="mini-card-lbl">Active Students</div></div>
            <div class="mini-card"><div class="mini-card-val" style="color:#f59e0b;">12</div><div class="mini-card-lbl">Pending Follow-ups</div></div>
            <div class="mini-card"><div class="mini-card-val" style="color:#0ea5e9;">₹1.8L</div><div class="mini-card-lbl">Fees Collected</div></div>
        </div>
        <div class="mini-row">
            <div class="mini-table">
                <div class="mini-row-header"><span>Recent Admissions</span><span>Status</span></div>
                <div class="mini-tr"><span>Riya Sharma — Java Batch</span><span class="mini-badge green">Active</span></div>
                <div class="mini-tr"><span>Arjun Mehta — MERN Stack</span><span class="mini-badge green">Active</span></div>
                <div class="mini-tr"><span>Sneha Patel — Tally Pro</span><span class="mini-badge blue">New</span></div>
                <div class="mini-tr"><span>Dev Kumar — Python AI</span><span class="mini-badge orange">Pending</span></div>
            </div>
            <div class="mini-chart">
                <div class="mini-row-header"><span>Course Popularity</span></div>
                <div class="mini-bar-wrap">
                    <div class="mini-bar-item">Java Batch
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width:90%;"></div></div>
                    </div>
                    <div class="mini-bar-item">MERN Stack
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width:72%;background:#06b6d4;"></div></div>
                    </div>
                    <div class="mini-bar-item">Python AI
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width:55%;background:#7c3aed;"></div></div>
                    </div>
                    <div class="mini-bar-item">Tally Pro
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width:38%;background:#059669;"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── STATS ── -->
<div class="stats-strip">
    <div class="stats-inner">
        <div>
            <div class="stat-val"><?= formatStat($total_institutes) ?></div>
            <div class="stat-lbl">Institutes Active</div>
        </div>
        <div>
            <div class="stat-val"><?= formatStat($total_students) ?></div>
            <div class="stat-lbl">Students Enrolled</div>
        </div>
        <div>
            <div class="stat-val"><?= formatStat($total_enquiries) ?></div>
            <div class="stat-lbl">Leads Captured</div>
        </div>
        <div>
            <div class="stat-val">99.9%</div>
            <div class="stat-lbl">Uptime Guaranteed</div>
        </div>
    </div>
</div>

<!-- ── FEATURES ── -->
<section id="features" class="section">
    <div class="section-label"><i class="bi bi-grid-1x2-fill"></i> Features</div>
    <h2 class="section-title">Everything you need to manage<br>your institute, in one roof.</h2>
    <p class="section-sub">No more spreadsheets, missed follow-ups, or manual fee tracking. InstCRM is purpose-built for Indian coaching centers.</p>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon indigo"><i class="bi bi-qr-code-scan"></i></div>
            <h3>QR Lead Capture</h3>
            <p>Share branded QR codes on banners, pamphlets and social media. Students fill a form and their details land directly in your enquiries dashboard.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon cyan"><i class="bi bi-person-bounding-box"></i></div>
            <h3>QR Attendance</h3>
            <p>Print student ID cards with embedded QR codes. Teachers scan them through a webcam to mark attendance in seconds — no apps needed.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon green"><i class="bi bi-cash-coin"></i></div>
            <h3>Fee Management</h3>
            <p>Record payments, track dues, and auto-generate professional PDF receipts complete with your logo, signature and institute details.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon orange"><i class="bi bi-graph-up-arrow"></i></div>
            <h3>Live Analytics</h3>
            <p>Conversion rates, course popularity charts, and a recent activity feed — all on your dashboard so you are always in the loop.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon purple"><i class="bi bi-shield-lock-fill"></i></div>
            <h3>Role-Based Access</h3>
            <p>Create accounts for counsellors, teachers and cashiers with strict permissions. Admins can even impersonate staff for support.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon rose"><i class="bi bi-palette-fill"></i></div>
            <h3>Custom Branding</h3>
            <p>Upload your logo, set your brand colour, add your address and print receipts that look completely native to your institute.</p>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ── -->
<div class="steps-bg">
    <div class="steps-inner" style="text-align:center;">
        <div class="section-label" style="margin:0 auto 16px;"><i class="bi bi-arrow-right-circle-fill"></i> How It Works</div>
        <h2 class="section-title">Up and running in 3 simple steps.</h2>
    </div>
    <div class="steps-grid steps-inner">
        <div class="step">
            <div class="step-num">1</div>
            <div>
                <h3>Sign up & Customise</h3>
                <p>Create your free account, upload your logo, set your institute name and brand colour. Your private workspace is ready in under 2 minutes.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div>
                <h3>Add Courses, Batches & Staff</h3>
                <p>Set up your course catalogue and batch schedules. Invite your counsellors and teachers as staff users with the right level of access.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div>
                <h3>Start Managing — Instantly</h3>
                <p>Share your QR code, capture leads, admit students, scan attendance and collect fees. Everything in one streamlined workflow.</p>
            </div>
        </div>
    </div>
</div>

<!-- ── PRICING ── -->
<section id="pricing" class="section" style="max-width:1100px;">
    <div style="text-align:center;">
        <div class="section-label" style="margin:0 auto 16px;"><i class="bi bi-tag-fill"></i> Simple Pricing</div>
        <h2 class="section-title">Honest pricing. No hidden charges.</h2>
        <p class="section-sub" style="margin:0 auto;">Start with a 3-day free trial with <strong>full access</strong>. Upgrade only when you love it.</p>
    </div>
    <div class="pricing-grid">

        <!-- Free Trial -->
        <div class="price-card" style="border-style:dashed;">
            <div class="price-name">Free Trial</div>
            <div class="price-amount"><span class="currency">₹</span>0<span class="period">/3 days</span></div>
            <div class="price-subtitle">No credit card required</div>
            <ul class="price-features">
                <li><i class="bi bi-check-circle-fill"></i> Full access to all features</li>
                <li><i class="bi bi-check-circle-fill"></i> Unlimited enquiries & admissions</li>
                <li><i class="bi bi-check-circle-fill"></i> QR attendance & fee receipts</li>
                <li><i class="bi bi-check-circle-fill"></i> 1 Admin account</li>
                <li><i class="bi bi-check-circle-fill"></i> Custom branding</li>
            </ul>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-plan outline">Start Free Demo →</a>
        </div>

        <!-- Monthly -->
        <div class="price-card featured">
            <span class="price-badge">⚡ Most Popular</span>
            <div class="price-name">Monthly</div>
            <div class="price-amount"><span class="currency">₹</span>499<span class="period">/month</span></div>
            <div class="price-subtitle">Billed monthly • Cancel anytime</div>
            <ul class="price-features">
                <li><i class="bi bi-check-circle-fill"></i> Everything in Free Trial</li>
                <li><i class="bi bi-check-circle-fill"></i> Unlimited students & staff</li>
                <li><i class="bi bi-check-circle-fill"></i> Priority email support</li>
                <li><i class="bi bi-check-circle-fill"></i> Expense tracking</li>
                <li><i class="bi bi-check-circle-fill"></i> Advanced analytics & reports</li>
            </ul>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-plan primary">Get Started — ₹499/mo</a>
        </div>

        <!-- Yearly -->
        <div class="price-card">
            <div class="price-name">Yearly <span class="savings-tag">SAVE ₹989</span></div>
            <div class="price-amount"><span class="currency">₹</span>4,999<span class="period">/year</span></div>
            <div class="price-subtitle">Just ₹417/mo • Best value</div>
            <ul class="price-features">
                <li><i class="bi bi-check-circle-fill"></i> Everything in Monthly</li>
                <li><i class="bi bi-check-circle-fill"></i> 2 months free</li>
                <li><i class="bi bi-check-circle-fill"></i> Dedicated onboarding call</li>
                <li><i class="bi bi-check-circle-fill"></i> Custom domain support</li>
                <li><i class="bi bi-check-circle-fill"></i> WhatsApp support</li>
            </ul>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-plan outline">Get Annual Plan →</a>
        </div>

    </div>
</section>

<!-- ── TESTIMONIALS ── -->
<div class="testimonials-bg">
    <div style="text-align:center; max-width:700px; margin:0 auto;">
        <div class="section-label" style="margin:0 auto 16px;"><i class="bi bi-chat-quote-fill"></i> Testimonials</div>
        <h2 class="section-title">Loved by educators across India.</h2>
    </div>
    <div class="testi-grid">
        <div class="testi-card">
            <div class="testi-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
            <p class="testi-text">"InstCRM completely changed how we handle admissions. Our counsellors now spend 60% less time on paperwork. The QR attendance is a game changer!"</p>
            <div class="testi-author">
                <div class="testi-avatar">A</div>
                <div>
                    <div class="testi-name">Anita Kapoor</div>
                    <div class="testi-role">Director, Apex Coaching, Jaipur</div>
                </div>
            </div>
        </div>
        <div class="testi-card">
            <div class="testi-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
            <p class="testi-text">"We tried 3 other tools before InstCRM. Nothing comes close in terms of value for ₹499/month. The fee receipts with our logo look so professional now."</p>
            <div class="testi-author">
                <div class="testi-avatar" style="background:linear-gradient(135deg,#059669,#0ea5e9);">R</div>
                <div>
                    <div class="testi-name">Rajan Trivedi</div>
                    <div class="testi-role">Owner, Bright Future Institute, Surat</div>
                </div>
            </div>
        </div>
        <div class="testi-card">
            <div class="testi-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
            <p class="testi-text">"The 3-day free trial let us test everything before committing. Setup was under 10 minutes. Our follow-up rate has doubled since we started using it."</p>
            <div class="testi-author">
                <div class="testi-avatar" style="background:linear-gradient(135deg,#f97316,#7c3aed);">P</div>
                <div>
                    <div class="testi-name">Priya Nair</div>
                    <div class="testi-role">Co-Founder, SkillEdge Academy, Pune</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── CTA ── -->
<section class="cta-section">
    <div class="cta-card">
        <h2>Ready to transform your institute?</h2>
        <p>Join hundreds of coaching centers already using InstCRM. Your 3-day free demo — with full access — is just a click away.</p>
        <a href="<?= BASE_URL ?>/auth/register.php" class="btn-cta">
            <i class="bi bi-lightning-fill"></i> Start Your Free Demo Now
        </a>
        <div class="cta-note">No credit card · No setup fee · Cancel anytime</div>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer>
    <div class="footer-inner">
        <div class="footer-brand"><i class="bi bi-mortarboard-fill"></i> InstCRM</div>
        <div class="footer-links">
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="<?= BASE_URL ?>/auth/login.php">Login</a>
            <a href="<?= BASE_URL ?>/auth/register.php">Sign Up</a>
        </div>
    </div>
    <p class="footer-copy">&copy; <?= date('Y') ?> InstCRM. All rights reserved. Built for India's coaching industry.</p>
</footer>

</body>
</html>
