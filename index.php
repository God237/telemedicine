<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title> Home  | Telemed Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            overflow-x: hidden;
            background: #f8fafc;
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
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header.hide {
            transform: translateY(-100%);
        }

        /* Logo Styles */
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1001;
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

        /* Navigation Links */
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

        /* Auth Buttons */
        .auth-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            z-index: 1001;
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

        /* Mobile Menu Toggle Button */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #1a73e8;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            transform: scale(1.1);
        }

        /* Mobile Menu Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            transition: all 0.3s ease;
        }

        .mobile-overlay.active {
            display: block;
        }

        /* Hero Section */
        .hero {
            background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url(images/4.jpeg);
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            height: 500px;
            text-align: center;
            padding: 20px 30px;
            color: white;
            position: relative;
            margin-top: 70px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            top: 50%;
            transform: translateY(-50%);
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .hero-buttons {
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .hero-buttons a {
            display: inline-block;
            margin: 10px;
            padding: 12px 30px;
            font-size: 1rem;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #1a73e8;
            color: white;
            border: 2px solid #1a73e8;
        }

        .btn-primary:hover {
            background: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(26,115,232,0.4);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: #1a73e8;
            transform: translateY(-3px);
        }

        /* Search Section */
        .search {
            background: white;
            padding: 40px 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .search h2 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            max-width: 800px;
            margin: 0 auto;
        }

        .search-form select, .search-form button {
            padding: 12px 20px;
            font-size: 1rem;
            border-radius: 50px;
            border: 1px solid #ddd;
            background: white;
        }

        .search-form select {
            min-width: 180px;
            cursor: pointer;
        }

        .search-form button {
            background: #1a73e8;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-form button:hover {
            background: #0d5bbf;
            transform: translateY(-2px);
        }

        /* Platform Features */
        .platform-container {
            display: flex;
            align-items: center;
            gap: 50px;
            max-width: 1200px;
            margin: 80px auto;
            padding: 0 20px;
        }

        .platform-image {
            flex: 1;
        }

        .platform-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .platform-text-block {
            flex: 1;
        }

        .platform-text-block h2 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .feature-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .check-circle {
            min-width: 32px;
            height: 32px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .feature-text {
            font-size: 1rem;
            color: #555;
            line-height: 1.5;
        }

        .highlight-point {
            color: #1a73e8;
            font-weight: 600;
        }

        /* Steps Section */
        .steps-section {
            background: #f8fafc;
            padding: 80px 20px;
            text-align: center;
        }

        .steps-section h2 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 50px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .step-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .step-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }

        .step-card i {
            font-size: 3rem;
            color: #1a73e8;
            margin-bottom: 20px;
        }

        .step-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .step-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Footer */
        footer {
            background: #1a2a3a;
            color: white;
            text-align: center;
            padding: 30px;
        }

        /* Animations */
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .platform-container {
                flex-direction: column;
                text-align: center;
            }

            .feature-row {
                justify-content: center;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }
        }

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

            .nav-links li a {
                font-size: 1.1rem;
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

            .nav-links .auth-buttons.mobile a {
                width: 100%;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .hero-buttons a {
                padding: 10px 20px;
                font-size: 0.9rem;
            }

            .search-form select, .search-form button {
                width: 100%;
                max-width: 300px;
            }

            .steps {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .step-card {
                padding: 30px 20px;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 1.5rem;
            }

            .hero-content p {
                font-size: 0.9rem;
            }

            .platform-text-block h2 {
                font-size: 1.5rem;
            }

            .steps-section h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Header/Navbar -->
    <header class="header" id="header">
        <div class="logo">
            <img src="images/logo 3.5.jpeg" alt="TeleMed Logo">
        </div>

        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="works.php">How It Works</a></li>
            <li><a href="contact.php">Contact</a></li>
            <!-- <li class="auth-buttons mobile">
                <a href="login.php" class="btn-login">Login</a>
                <a href="register.php" class="btn-signup">Sign Up</a>
            </li> -->
        </ul>

        <div class="auth-buttons">
            <a href="login.php" class="btn-login">Login</a>
            <a href="signup.php" class="btn-signup">Sign Up</a>
        </div>
    </header>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Your Health, Our Priority</h1>
            <p>Connect with qualified doctors across Cameroon from the comfort of your home</p>
            <div class="hero-buttons">
                <a href="signup.php" class="btn-primary">Get Started</a>
                <a href="works.php" class="btn-secondary">Learn More</a>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search">
        <h2>Find a Doctor Near You</h2>
        <div class="search-form">
            <select id="city">
                <option value="">Select Location</option>
                <option value="yaounde">Bonaberi</option>
                <option value="douala">Akwa</option>
                <option value="bafoussam">Bonassama</option>
                <option value="garoua">Bonamoussadi</option>
                <option value="bamenda">Bepanda</option>
            </select>
            <select id="specialty">
                <option value="">Select Specialty</option>
                <option value="general">General Practitioner</option>
                <option value="cardiology">Cardiology</option>
                <option value="pediatrics">Pediatrics</option>
                <option value="dermatology">Dermatology</option>
            </select>
            <button onclick="searchDoctors()">Search Doctors</button>
        </div>
    </section>

    <!-- Platform Features -->
    <div class="platform-container">
        <div class="platform-image">
            <img src="images/doctor2.jpg" alt="Telemedicine Platform">
        </div>
        <div class="platform-text-block">
            <h2>Why Choose TeleMed Connect?</h2>
            <div class="feature-row">
                <div class="check-circle"><i class="fas fa-check"></i></div>
                <div class="feature-text"><span class="highlight-point">Location-based</span> doctor matching</div>
            </div>
            <div class="feature-row">
                <div class="check-circle"><i class="fas fa-check"></i></div>
                <div class="feature-text">Secure <span class="highlight-point">video consultations</span> with certified doctors</div>
            </div>
            <div class="feature-row">
                <div class="check-circle"><i class="fas fa-check"></i></div>
                <div class="feature-text"><span class="highlight-point">24/7 access</span> to medical records and prescriptions</div>
            </div>
            <!-- <div class="feature-row">
                <div class="check-circle"><i class="fas fa-check"></i></div>
                <div class="feature-text">Affordable healthcare <span class="highlight-point">starting from 5000 FCFA</span> per consultation</div>
            </div> -->
        </div>
    </div>

    <!-- How It Works Steps -->
    <section class="steps-section">
        <h2>How TeleMed Works</h2>
        <div class="steps">
            <div class="step-card">
                <i class="fas fa-user-plus"></i>
                <h3>1. Create Account</h3>
                <p>Sign up as a patient and complete your health profile</p>
            </div>
            <div class="step-card">
                <i class="fas fa-search"></i>
                <h3>2. Find a Doctor</h3>
                <p>Search for doctors near your location by specialty</p>
            </div>
            <div class="step-card">
                <i class="fas fa-calendar-check"></i>
                <h3>3. Book Appointment</h3>
                <p>Choose a convenient time for your consultation</p>
            </div>
            <div class="step-card">
                <i class="fas fa-video"></i>
                <h3>4. Start Consultation</h3>
                <p>Connect with your doctor via video or chat</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2026 TeleMed Cameroon. All rights reserved. | Your Health, Our Priority</p>
    </footer>

    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        const mobileOverlay = document.getElementById('mobileOverlay');
        let lastScrollTop = 0;
        const header = document.getElementById('header');

        // Toggle menu function
        function toggleMenu() {
            navLinks.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            
            // Change icon
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

        // Close menu function
        function closeMenu() {
            navLinks.classList.remove('active');
            mobileOverlay.classList.remove('active');
            const icon = menuToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
            document.body.style.overflow = '';
        }

        // Event listeners
        menuToggle.addEventListener('click', toggleMenu);
        mobileOverlay.addEventListener('click', closeMenu);

        // Close menu when clicking on a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeMenu();
                }
            });
        });

        // Hide/show header on scroll
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                header.classList.add('hide');
            } else {
                // Scrolling up
                header.classList.remove('hide');
            }
            lastScrollTop = scrollTop;
        });

        // Search function
        function searchDoctors() {
            const city = document.getElementById('city').value;
            const specialty = document.getElementById('specialty').value;
            
            if (!city && !specialty) {
                alert('Please select a city or specialty to search for doctors');
                return;
            }
            
            // Redirect to find doctor page with filters
            window.location.href = `signup.php?city=${city}&specialty=${specialty}`;
        }

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768 && navLinks.classList.contains('active')) {
                    closeMenu();
                }
            }, 250);
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.step-card, .feature-row, .platform-container').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>