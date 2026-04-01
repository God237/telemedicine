<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>How It Works | TeleMed Cameroon</title>
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
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
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

        /* Steps Section */
        .steps-section {
            padding: 80px 20px;
            background: #f8fafc;
        }

        .steps-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .step-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .step-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1a73e8, #34a853);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }

        .step-card h3 {
            font-size: 1.3rem;
            color: #1a3a4a;
            margin-bottom: 15px;
        }

        .step-card p {
            color: #6c757d;
            line-height: 1.6;
        }

        .step-icon {
            font-size: 3rem;
            color: #1a73e8;
            margin-bottom: 20px;
        }

        /* Benefits Section */
        .benefits-section {
            background: white;
            padding: 80px 20px;
        }

        .benefits-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .benefits-container h2 {
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 15px;
        }

        .benefits-container > p {
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .benefit-card {
            background: #f8fafc;
            padding: 30px;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .benefit-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1a73e8, #34a853);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .benefit-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .benefit-card h3 {
            font-size: 1.2rem;
            color: #1a3a4a;
            margin-bottom: 10px;
        }

        .benefit-card p {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* FAQ Section */
        .faq-section {
            background: #f8fafc;
            padding: 80px 20px;
        }

        .faq-section h2 {
            text-align: center;
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 40px;
        }

        .faq-grid {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .faq-question {
            padding: 20px;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #1a3a4a;
            transition: all 0.3s;
        }

        .faq-question:hover {
            background: #f8f9fa;
        }

        .faq-question i {
            transition: transform 0.3s;
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            color: #6c757d;
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            padding: 0 20px 20px;
            max-height: 300px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #1a73e8, #0d5bbf);
            color: white;
            text-align: center;
            padding: 60px 20px;
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

            .steps-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .step-card {
                padding: 30px 20px;
            }

            .benefit-card {
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .step-number {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }

            .step-icon {
                font-size: 2.5rem;
            }

            .step-card h3 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

    <header class="header" id="header">
        <div class="logo">
            <img src="images/logo.jpeg" alt="TeleMed Logo" onerror="this.src='https://via.placeholder.com/50x50?text=TM'">
            <span>TeleMed Cameroon</span>
        </div>

        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="works.php" class="active">How It Works</a></li>
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
            <h1>How It Works</h1>
            <p>Get medical help in just a few simple steps</p>
        </div>
    </section>

    <section class="steps-section">
        <div class="steps-container">
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <i class="fas fa-search step-icon"></i>
                    <h3>Find a Doctor</h3>
                    <p>Search for qualified doctors based on your location and medical needs. Filter by specialty, availability, and ratings to find the perfect match for your healthcare needs.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <i class="fas fa-calendar-check step-icon"></i>
                    <h3>Book Appointment</h3>
                    <p>Select a convenient date and time that works for you. Choose between video or chat consultation based on your preference and medical requirements.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <i class="fas fa-video step-icon"></i>
                    <h3>Consult Online</h3>
                    <p>Connect with your doctor through our secure video or chat platform. Get professional medical advice, diagnosis, and treatment from the comfort of your home.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <i class="fas fa-prescription-bottle step-icon"></i>
                    <h3>Get Treatment</h3>
                    <p>Receive prescriptions, medical reports, and follow-up care instructions directly through the platform. Download your medical records anytime, anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="benefits-section">
        <div class="benefits-container">
            <h2>Why Choose TeleMed Cameroon?</h2>
            <p>Experience healthcare reimagined with our innovative platform</p>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>24/7 Availability</h3>
                    <p>Access healthcare services anytime, anywhere. No more waiting rooms or appointment delays.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure & Private</h3>
                    <p>Your medical information is protected with bank-grade encryption. Complete confidentiality guaranteed.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h3>Quality Care</h3>
                    <p>Connect with verified, experienced doctors who are committed to providing the best healthcare.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Affordable Rates</h3>
                    <p>Quality healthcare at competitive prices. No hidden fees, transparent pricing for every consultation.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="faq-section">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <div class="faq-question">
                    <span>Is TeleMed Cameroon available nationwide?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Yes! Our platform connects patients with doctors across all major cities in Cameroon including Yaoundé, Douala, Bafoussam, Garoua, and Bamenda. We're continuously expanding our network to reach more areas.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>How do I choose between video and chat consultation?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Choose video consultation for face-to-face interaction, physical examination, and more personal connection. Choose chat consultation for quick follow-ups, prescription refills, non-urgent questions, or when you prefer text-based communication.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>Is my medical information secure?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Absolutely! We use bank-grade encryption to protect your personal and medical information. All consultations are confidential and comply with international healthcare data protection standards.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>What if I need a prescription refill?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    You can request a prescription refill during a follow-up consultation. Your doctor will review your case and provide an updated prescription if appropriate. All prescriptions are available for download in your medical records.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>How do I pay for consultations?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    We accept multiple payment methods including mobile money (MTN Mobile Money, Orange Money), credit/debit cards, and bank transfers. All payments are processed securely through our platform.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>Can I get a refund if I cancel my appointment?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Yes, you can cancel your appointment up to 24 hours before the scheduled time for a full refund. Cancellations within 24 hours may be subject to a small processing fee. Please check our cancellation policy for details.
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <h2>Ready to get started?</h2>
        <a href="index.php" class="btn-primary">Find a Doctor</a>
    </section>

    <footer>
        <p>&copy; 2025 TeleMed Cameroon. All Rights Reserved</p>
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

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                faqItem.classList.toggle('active');
            });
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