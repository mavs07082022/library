<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>St. Agnes Academy · Library Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet" />
    <style>
      
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f0f5;
            color: #1a1a2e;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            opacity: 0;
            animation: pageFadeIn 0.8s ease forwards;
        }

        @keyframes pageFadeIn {
            0% { opacity: 0; transform: translateY(12px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 28px;
            width: 100%;
        }

        /* ===== BUTTONS ===== */
        .btn-primary {
            background: #1a1a2e;
            color: #f0e8e8;
            padding: 12px 32px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            letter-spacing: 0.3px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .btn-primary::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
            transition: left 0.6s ease;
        }
        .btn-primary:hover::after {
            left: 100%;
        }
        .btn-primary:hover {
            background: #2a1a3a;
            box-shadow: 0 8px 24px rgba(26, 26, 46, 0.15);
            transform: translateY(-2px);
        }
        .btn-primary:active {
            transform: scale(0.97);
        }

        .btn-outline {
            background: transparent;
            color: #f0e8e8;
            padding: 12px 32px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 15px;
            border: 2px solid rgba(255, 255, 255, 0.25);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
        }
        .btn-outline:hover {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-2px);
        }

        .header-box {
            background: linear-gradient(135deg, #08080a 0%, #0a090a 30%, #d41688b0 60%, #ff0199a8 80%, #ff0199c9 100%);
            border-radius: 16px;
            padding: 0 32px 0 32px;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.06);
            animation: headerGlow 4s ease-in-out infinite alternate;
        }
        @keyframes headerGlow {
            0% { box-shadow: 0 0 30px rgba(212, 22, 136, 0.1); }
            100% { box-shadow: 0 0 60px rgba(255, 1, 153, 0.15); }
        }
        .header-box::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 200, 230, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: orbPulse 8s ease-in-out infinite alternate;
        }
        @keyframes orbPulse {
            0% { transform: scale(1) translate(0, 0); opacity: 0.4; }
            100% { transform: scale(1.3) translate(-30px, 20px); opacity: 0.8; }
        }
        .header-box .container {
            position: relative;
            z-index: 1;
        }

        .navbar {
            padding: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            animation: slideDown 0.6s ease 0.2s both;
        }
        @keyframes slideDown {
            0% { opacity: 0; transform: translateY(-20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-area img {
            height: 48px;
            width: auto;
            display: block;
        }
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #f0e8e8;
            letter-spacing: -0.3px;
        }
        .logo-text span {
            font-weight: 300;
            color: rgba(255, 255, 255, 0.5);
            font-size: 16px;
            margin-left: 4px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            font-weight: 500;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            flex-wrap: wrap;
        }
        .nav-links a {
            transition: color 0.3s ease, transform 0.3s ease;
            position: relative;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #ff0199;
            transition: width 0.3s ease;
        }
        .nav-links a:hover::after {
            width: 100%;
        }
        .nav-links a:hover {
            color: #f0e8e8;
            transform: translateY(-1px);
        }
        .nav-links .btn-primary {
            padding: 10px 28px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .nav-links .btn-primary:hover {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 20px rgba(255, 1, 153, 0.2);
        }

        /* ===== HERO ===== */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 60px 0 70px 0;
            gap: 40px;
            flex-wrap: wrap;
            animation: fadeUp 0.8s ease 0.4s both;
        }
        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .hero-content {
            flex: 1 1 480px;
        }
        .hero-content .badge {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(4px);
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            font-weight: 600;
            padding: 6px 18px;
            border-radius: 40px;
            display: inline-block;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            text-transform: uppercase;
        }
        .hero-content h1 {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 18px;
            color: #f0e8e8;
        }
        .hero-content h1 .highlight {
            background: linear-gradient(135deg, #f0e8e8, #d460b8, #ff0199);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-content p {
            font-size: 17px;
            color: rgba(255, 255, 255, 0.55);
            max-width: 480px;
            margin-bottom: 32px;
            line-height: 1.7;
        }
        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }
        .hero-buttons .btn-primary {
            padding: 14px 40px;
            background: #f0e8e8;
            color: #1a1a2e;
        }
        .hero-buttons .btn-primary:hover {
            background: #ffffff;
            box-shadow: 0 8px 30px rgba(240, 232, 232, 0.2);
        }

        .hero-image {
            flex: 1 1 380px;
            display: flex;
            justify-content: center;
        }
        .hero-image .hero-illustration {
            width: 100%;
            max-width: 400px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 30px 20px;
            text-align: center;
            backdrop-filter: blur(8px);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .hero-image .hero-illustration:hover {
            transform: scale(1.02);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        .hero-image .hero-illustration .icon-large {
            font-size: 64px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.6;
            color: #08080a;
        }
        .hero-image .hero-illustration p {
            color: #08080a;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        /* ===== FEATURES ===== */
        .features-section {
            padding: 50px 0 60px 0;
            animation: fadeUp 0.8s ease 0.6s both;
        }
        .section-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #a090a0;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .section-title {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a1a2e;
        }
        .section-sub {
            color: #6a5a6a;
            margin-bottom: 36px;
            font-size: 16px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .feature-card {
            background: #ffffff;
            padding: 28px 24px;
            border-radius: 16px;
            border: 1px solid #f0e0ee;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #d41688, #ff0199);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        .feature-card:hover {
            transform: translateY(-8px);
            border-color: #d460b8;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06);
        }
        .feature-card .icon {
            font-size: 28px;
            margin-bottom: 14px;
            display: block;
            color: #d41688;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        .feature-card:hover .icon {
            opacity: 1;
        }
        .feature-card h3 {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1a1a2e;
        }
        .feature-card p {
            color: #6a5a6a;
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
        }

        .semantic-banner {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 36px;
            border: 1px solid #f0e0ee;
            margin: 10px 0 50px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            transition: all 0.4s ease;
            animation: fadeUp 0.8s ease 0.8s both;
        }
        .semantic-banner:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
            border-color: #d460b8;
        }
        .semantic-banner .banner-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .semantic-banner .banner-icon {
            font-size: 32px;
            color: #d41688;
            opacity: 0.7;
        }
        .semantic-banner .banner-text h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 2px 0;
        }
        .semantic-banner .banner-text p {
            color: #6a5a6a;
            font-size: 14px;
            margin: 0;
        }
        .semantic-banner .banner-badge {
            background: #f0e8f0;
            color: #4a2a4a;
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .semantic-banner .banner-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34a853;
            display: inline-block;
            animation: dotPulse 2s ease-in-out infinite;
        }
        @keyframes dotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        .system-overview {
            padding: 50px 0 60px 0;
            border-top: 1px solid #f0e0ee;
            animation: fadeUp 0.8s ease 1s both;
        }
        .overview-wrap {
            display: flex;
            align-items: center;
            gap: 60px;
            flex-wrap: wrap;
        }
        .overview-text {
            flex: 1 1 400px;
        }
        .overview-text .section-title {
            margin-bottom: 16px;
        }
        .overview-text p {
            color: #5a4a5a;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 24px;
        }
        .overview-stats {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        .stat-item .number {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -0.5px;
        }
        .stat-item .label {
            font-size: 14px;
            color: #8a7a8a;
        }

        .overview-image {
            flex: 1 1 340px;
            background: linear-gradient(145deg, #f0ece8, #e4ddd6);
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a3a4a;
            font-weight: 500;
            border: 1px solid #ddd6ce;
            transition: transform 0.4s ease;
        }
        .overview-image:hover {
            transform: scale(1.02);
        }

        .footer-cta {
            padding: 40px 0 50px 0;
            border-top: 1px solid #f0e0ee;
            text-align: center;
            animation: fadeUp 0.8s ease 1.2s both;
        }
        .footer-cta h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a1a2e;
        }
        .footer-cta p {
            color: #5a4a5a;
            margin-bottom: 28px;
            font-size: 16px;
        }
        .footer-cta .btn-primary {
            padding: 14px 48px;
        }

        .footer-bottom {
            border-top: 1px solid #f0e0ee;
            padding: 24px 0 32px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            font-size: 14px;
            color: #8a7a8a;
            animation: fadeUp 0.8s ease 1.4s both;
        }
        .footer-bottom .copy {
            color: #8a7a8a;
        }
        .footer-bottom .links {
            display: flex;
            gap: 28px;
        }
        .footer-bottom .links a {
            color: #5a4a5a;
            transition: color 0.3s ease;
        }
        .footer-bottom .links a:hover {
            color: #d460b8;
        }

     
        @media (max-width: 992px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .hero-content h1 { font-size: 38px; }
        }

        @media (max-width: 720px) {
            .navbar {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            .nav-links {
                flex-wrap: wrap;
                gap: 16px;
            }
            .hero {
                padding: 30px 0 40px;
                flex-direction: column;
            }
            .hero-content h1 { font-size: 32px; }
            .features-grid { grid-template-columns: 1fr; }
            .overview-wrap { flex-direction: column; gap: 30px; }
            .footer-bottom { flex-direction: column; gap: 16px; align-items: flex-start; }
            .header-box { padding: 0 16px; border-radius: 12px; }
            .semantic-banner { flex-direction: column; align-items: flex-start; }
            .semantic-banner .banner-left { width: 100%; }
        }

        @media (max-width: 480px) {
            .container { padding: 0 16px; }
            .hero-content h1 { font-size: 28px; }
            .btn-primary, .btn-outline { padding: 10px 24px; font-size: 14px; }
            .logo-area img { height: 38px; }
            .logo-text { font-size: 17px; }
            .header-box { padding: 0 12px; border-radius: 10px; }
            .features-grid { grid-template-columns: 1fr; }
            .semantic-banner { padding: 20px; }
            .semantic-banner .banner-badge { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="header-box">
        <div class="container">

            <header>
                <div class="navbar">
                    <div class="logo-area">
                        <img src="./img/agustinnb.png" alt="St. Agnes Academy" />
                        <div class="logo-text">St. Agnes <span>Academy</span></div>
                    </div>
                    <div class="nav-links">
                        <a href="#home">Home</a>
                        <a href="#features">Features</a>
                        <a href="#overview">Overview</a>
                        <a href="#contact">Contact</a>
                        <a href="login.php" class="btn-primary">Sign In</a>
                    </div>
                </div>
            </header>

            <section class="hero" id="home">
                <div class="hero-content">
                    <div class="badge">✦ Library Management System</div>
                    <h1>Welcome to <span class="highlight">St. Agnes Academy</span><br />Library System</h1>
                    <p>Access and manage library services through one secure and connected platform designed for students, faculty, and staff of St. Agnes Academy.</p>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn-primary">Sign In</a>
                        <a href="#features" class="btn-outline">Explore Features</a>
                    </div>
                </div>
                <div class="hero-image">
                    <div class="hero-illustration">
                        <span class="icon-large">▣</span>
                        <p>Connected Library Ecosystem</p>
                        <p style="font-size:12px;opacity:0.5;margin-top:4px;">Semantic Search · Real-time Analytics</p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="container">

        <section class="features-section" id="features">
            <div class="section-label">CORE FEATURES</div>
            <div class="section-title">Everything for Modern Library Management</div>
            <div class="section-sub">Streamlined tools for students, librarians, and administrators</div>

            <div class="features-grid">
                <div class="feature-card">
                    <span class="icon">◈</span>
                    <h3>Book Catalog</h3>
                    <p>Complete inventory with cover images, categories, and location tracking</p>
                </div>
                <div class="feature-card">
                    <span class="icon">◐</span>
                    <h3>Semantic Search</h3>
                    <p>AI-powered search that understands natural language queries</p>
                </div>
                <div class="feature-card">
                    <span class="icon">◑</span>
                    <h3>Borrowing &amp; Returns</h3>
                    <p>Seamless circulation with real-time availability updates</p>
                </div>
                <div class="feature-card">
                    <span class="icon">◉</span>
                    <h3>Fine Management</h3>
                    <p>Automated overdue tracking and fine collection system</p>
                </div>
            </div>
        </section>

      
        <div class="semantic-banner">
            <div class="banner-left">
                <span class="banner-icon">◐</span>
                <div class="banner-text">
                    <h4>Semantic Search Engine</h4>
                    <p>Powered by NLP — understands context, not just keywords</p>
                </div>
            </div>
            <div class="banner-badge">
                <span class="dot"></span>
                AI Active
            </div>
        </div>

        <section class="system-overview" id="overview">
            <div class="overview-wrap">
                <div class="overview-text">
                    <div class="section-label">SYSTEM OVERVIEW</div>
                    <div class="section-title">One Platform for a Connected Library</div>
                    <p>The Library Management System integrates catalog management, borrowing workflows, fine tracking, and semantic search into a unified ecosystem for students, faculty, and library staff.</p>
                    <div class="overview-stats">
                        <div class="stat-item">
                            <span class="number">4</span>
                            <span class="label">Core Modules</span>
                        </div>
                        <div class="stat-item">
                            <span class="number">24/7</span>
                            <span class="label">Access</span>
                        </div>
                        <div class="stat-item">
                            <span class="number">◐</span>
                            <span class="label">Semantic Search</span>
                        </div>
                    </div>
                </div>
                <div class="overview-image">
                    <span style="display:flex; flex-direction:column; gap:4px;">
                        <span style="font-weight:600; color:#1a1a2e;">✦ Centralized Ecosystem</span>
                        <span style="font-size:14px; color:#5a4a5a;">Students  · Librarians · Admins</span>
                    </span>
                </div>
            </div>
        </section>


        <div class="footer-cta" id="contact">
            <h2>Ready to get started?</h2>
            <p>Join the St. Agnes Academy library community and explore our unified platform.</p>
            <a href="login.php" class="btn-primary">Sign In</a>
        </div>

  
        <div class="footer-bottom">
            <div class="copy">© 2026 St. Agnes Academy · Caloocan Inc.</div>
            <div class="links">
                <a href="#">Privacy</a>
                <a href="#">Support</a>
                <a href="#">Contact</a>
            </div>
        </div>
    </div>


    <script>
        (function() {
         
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        e.preventDefault();
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

       
            const footerYear = document.querySelector('.footer-bottom .copy');
            if (footerYear) {
                const currentYear = new Date().getFullYear();
                footerYear.textContent = footerYear.textContent.replace('2026', currentYear);
            }

         
            const cards = document.querySelectorAll('.feature-card, .semantic-banner, .overview-wrap');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            console.log('St. Agnes Academy · Library Management System');
        })();
    </script>

</body>
</html>