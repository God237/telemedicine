<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>About Us | TeleMed Cameroon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f8fafc;
            overflow-x: hidden;
        }

        /* Header/Navbar Styles */
        .header {
            position: fixed;
            top: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            padding: 15px 5%;
            height: 70px;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header.hide {
            transform: translateY(-100%);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo img {
            height: 50px;
            width: auto;
            border-radius: 50%;
        }

        .logo span {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1a73e8;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 40px;
            margin: 0;
            padding: 0;
            transition: all 0.3s ease;
        }

        .nav-links li a {
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 5px 0;
        }

        .nav-links li a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, #1a73e8, #34a853);
            transition: width 0.3s ease;
        }

        .nav-links li a:hover::after {
            width: 100%;
        }

        .nav-links li a:hover {
            color: #1a73e8;
        }

        .btn-login, .btn-signup {
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-login {
            color: #1a73e8;
            border: 1px solid #1a73e8;
            background: transparent;
        }

        .btn-login:hover {
            background: #1a73e8;
            color: white;
            transform: translateY(-2px);
        }

        .btn-signup {
            background: #1a73e8;
            color: white;
            border: 1px solid #1a73e8;
        }

        .btn-signup:hover {
            background: #0d5bbf;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26,115,232,0.3);
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #1a73e8;
            z-index: 1001;
        }

        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        .mobile-overlay.active {
            display: block;
        }

        /* Page Header */
        .page-header {
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/1.jpeg');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            height: 400px;
            text-align: center;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 70px;
            position: relative;
        }

        .page-header-content {
            animation: fadeInUp 0.8s ease;
        }

        .page-header h1 {
            font-size: 3.5rem;
            margin-bottom: 15px;
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* About Sections */
        .about-section {
            max-width: 900px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .about-section:hover {
            transform: translateY(-5px);
        }

        .about-section h2 {
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .about-section h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #1a73e8;
            border-radius: 2px;
        }

        .about-section p {
            color: #6c757d;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        .about-section ul {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .about-section ul li {
            padding: 10px 0;
            color: #495057;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .about-section ul li i {
            color: #28a745;
            font-size: 1.2rem;
        }

        /* Mission Vision Section */
        .mission-vision {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .mv-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .mv-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .mv-card i {
            font-size: 3rem;
            color: #1a73e8;
            margin-bottom: 20px;
        }

        .mv-card h3 {
            font-size: 1.5rem;
            color: #1a3a4a;
            margin-bottom: 15px;
        }

        .mv-card p {
            color: #6c757d;
            line-height: 1.6;
        }

        /* Team Section */
        .team-section {
            background: #f8f9fa;
            padding: 60px 20px;
            text-align: center;
        }

        .team-section h2 {
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 40px;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .team-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #1a73e8, #34a853);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            color: white;
            font-weight: bold;
        }

        .team-card h3 {
            font-size: 1.2rem;
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .team-card p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #1a73e8, #0d5bbf);
            color: white;
            text-align: center;
            padding: 60px 20px;
            margin: 40px 0 0;
        }

        .cta-section h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }

        .btn-primary {
            display: inline-block;
            padding: 12px 30px;
            background: white;
            color: #1a73e8;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Footer */
        footer {
            background: #1a2a3a;
            color: white;
            text-align: center;
            padding: 30px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .nav-links {
                position: fixed;
                top: 70px;
                left: -100%;
                width: 80%;
                height: calc(100vh - 70px);
                background: white;
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
                padding: 40px 20px;
                gap: 25px;
                transition: left 0.3s ease;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                z-index: 999;
            }

            .nav-links.active {
                left: 0;
            }

            .auth-buttons {
                display: none;
            }

            .auth-buttons.mobile {
                display: flex;
                flex-direction: column;
                width: 100%;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .page-header p {
                font-size: 1rem;
            }

            .about-section {
                margin: 30px 20px;
                padding: 30px 20px;
            }

            .about-section h2 {
                font-size: 1.5rem;
            }

            .mission-vision {
                grid-template-columns: 1fr;
                padding: 0 20px;
            }

            .team-grid {
                grid-template-columns: 1fr;
                padding: 0 20px;
            }
        }
    </style>
</head>
<body>

    <header class="header" id="header">
        <div class="logo">
            <img src="images/logo 3.5.jpeg" alt="TeleMed Logo">
        </div>

        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php" class="active">About</a></li>
            <li><a href="works.php">How It Works</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>

        <div class="auth-buttons">
            <a href="login.php" class="btn-login">Login</a>
            <a href="signup.php" class="btn-signup">Sign Up</a>
        </div>
    </header>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    <section class="page-header">
        <div class="page-header-content">
            <h1>About TeleMed Connect</h1>
            <p>Connecting patients with trusted doctors anytime, anywhere</p>
        </div>
    </section>

    <div class="mission-vision">
        <div class="mv-card">
            <i class="fas fa-bullseye"></i>
            <h3>Our Mission</h3>
            <p>To make healthcare accessible by connecting patients with qualified doctors in their local areas through secure online consultations.</p>
        </div>
        <div class="mv-card">
            <i class="fas fa-eye"></i>
            <h3>Our Vision</h3>
            <p>To become Cameroon's leading telemedicine platform, revolutionizing healthcare delivery across the nation.</p>
        </div>
    </div>

    <section class="about-section">
        <h2>How It Works</h2>
        <ul>
            <li><i class="fas fa-check-circle"></i> Search for doctors by location and specialty</li>
            <li><i class="fas fa-check-circle"></i> Book appointments easily</li>
            <li><i class="fas fa-check-circle"></i> Consult online or visit nearby clinics</li>
        </ul>
    </section>

    <section class="about-section">
        <h2>Why Choose TeleMed Cameroon?</h2>
        <ul>
            <li><i class="fas fa-check-circle"></i> Verified and qualified doctors</li>
            <li><i class="fas fa-check-circle"></i> Location-based doctor matching</li>
            <li><i class="fas fa-check-circle"></i> Secure and reliable platform</li>
            <li><i class="fas fa-check-circle"></i> 24/7 access to medical records</li>
            <li><i class="fas fa-check-circle"></i> Affordable healthcare solutions</li>
        </ul>
    </section>

    <section class="team-section">
        <h2>Our Commitment to You</h2>
        <div class="team-grid">
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-shield-alt"></i></div>
                <h3>Secure & Private</h3>
                <p>Your health data is encrypted and protected</p>
            </div>
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-clock"></i></div>
                <h3>24/7 Availability</h3>
                <p>Access healthcare whenever you need it</p>
            </div>
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-hand-holding-heart"></i></div>
                <h3>Patient First</h3>
                <p>Your health and comfort are our priority</p>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <h2>Ready to get started?</h2>
        <a href="index.php" class="btn-primary">Find a Doctor</a>
    </section>

    <footer>
        <p>&copy; 2026 TeleMed Connect. All Rights Reserved</p>
    </footer>

    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        const mobileOverlay = document.getElementById('mobileOverlay');
        let lastScrollTop = 0;
        const header = document.getElementById('header');

        function toggleMenu() {
            navLinks.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            const icon = menuToggle.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
                document.body.style.overflow = 'hidden';
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                document.body.style.overflow = '';
            }
        }

        function closeMenu() {
            navLinks.classList.remove('active');
            mobileOverlay.classList.remove('active');
            const icon = menuToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
            document.body.style.overflow = '';
        }

        menuToggle.addEventListener('click', toggleMenu);
        mobileOverlay.addEventListener('click', closeMenu);

        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeMenu();
            });
        });

        // Hide/show header on scroll
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                header.classList.add('hide');
            } else {
                header.classList.remove('hide');
            }
            lastScrollTop = scrollTop;
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768 && navLinks.classList.contains('active')) closeMenu();
            }, 250);
        });
    </script>
</body>
</html>