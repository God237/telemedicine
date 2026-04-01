<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Contact Us | TeleMed Cameroon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Keep all your existing CSS styles here */
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
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/3.jpeg');
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

        /* Contact Info Cards */
        .contact-info {
            max-width: 1200px;
            margin: 80px auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 0 20px;
        }

        .contact-card {
            background: white;
            padding: 40px 30px;
            text-align: center;
            border-radius: 20px;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .contact-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1a73e8, #34a853);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .contact-icon i {
            font-size: 2rem;
            color: white;
        }

        .contact-card h3 {
            font-size: 1.3rem;
            color: #1a3a4a;
            margin-bottom: 10px;
        }

        .contact-card p {
            color: #6c757d;
            font-size: 1rem;
        }

        /* Contact Form Section */
        .contact-section {
            background: white;
            padding: 60px 20px;
        }

        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .contact-form h2 {
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 20px;
        }

        .contact-form p {
            color: #6c757d;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26,115,232,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn-submit {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            position: relative;
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-submit .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-submit:hover:not(:disabled) {
            background: #0d5bbf;
            transform: translateY(-2px);
        }

        .contact-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        /* Map Section */
        .map-section {
            padding: 60px 20px;
            background: #f8fafc;
        }

        .map-section h2 {
            text-align: center;
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 30px;
        }

        .map-container {
            max-width: 1200px;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .map-container iframe {
            width: 100%;
            height: 400px;
            border: 0;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

            .contact-info {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .contact-container {
                grid-template-columns: 1fr;
                gap: 40px;
                text-align: center;
            }

            .contact-image img {
                max-width: 100%;
                height: auto;
            }

            .map-container iframe {
                height: 300px;
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
            <li><a href="about.php">About</a></li>
            <li><a href="works.php">How It Works</a></li>
            <li><a href="contact.php" class="active">Contact</a></li>
        </ul>

        <div class="auth-buttons">
            <a href="login.php" class="btn-login">Login</a>
            <a href="signup.php" class="btn-signup">Sign Up</a>
        </div>
    </header>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    <section class="page-header">
        <div class="page-header-content">
            <h1>Contact Us</h1>
            <p>We are here to help. Reach out anytime</p>
        </div>
    </section>

    <div class="contact-info">
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <h3>Email Us</h3>
            <p>info@telemed.com</p>
        </div>

        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h3>Call Us</h3>
            <p>+237 673 036 597</p>
        </div>

        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3>Visit Us</h3>
            <p>Douala, Cameroon</p>
        </div>
    </div>

    <section class="contact-section">
        <div class="contact-container">
            <div class="contact-form">
                <h2>Send Us a Message</h2>
                <p>Have questions or feedback? We'd love to hear from you!</p>
                
                <div id="alertMessage" style="display: none;"></div>

                <form id="contactForm">
                    <div class="form-group">
                        <input type="text" id="fullname" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" id="email" placeholder="Email Address" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="subject" placeholder="Subject">
                    </div>
                    <div class="form-group">
                        <textarea id="message" placeholder="Your Message" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <div class="contact-image">
                <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Customer Support">
            </div>
        </div>
    </section>

    <section class="map-section">
        <h2>Our Location</h2>
        <div class="map-container">
            <iframe 
                src="https://maps.google.com/maps?q=Douala,Cameroon&z=12&output=embed"
                allowfullscreen=""
                loading="lazy">
            </iframe>
        </div>
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

        // Handle form submission
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertMessage = document.getElementById('alertMessage');

        function showAlert(message, type) {
            alertMessage.style.display = 'flex';
            alertMessage.className = `alert alert-${type}`;
            alertMessage.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            
            setTimeout(() => {
                alertMessage.style.display = 'none';
            }, 5000);
        }

        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = {
                fullname: document.getElementById('fullname').value.trim(),
                email: document.getElementById('email').value.trim(),
                subject: document.getElementById('subject').value.trim(),
                message: document.getElementById('message').value.trim()
            };
            
            // Validate form
            if (!formData.fullname || !formData.email || !formData.message) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }
            
            // Validate email
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (!emailRegex.test(formData.email)) {
                showAlert('Please enter a valid email address', 'error');
                return;
            }
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Sending...';
            
            try {
                const response = await fetch('../api/contact.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    contactForm.reset();
                } else {
                    showAlert(result.message || 'Failed to send message. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Network error. Please check your connection and try again.', 'error');
            } finally {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Message';
            }
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