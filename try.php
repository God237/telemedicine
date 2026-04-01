<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Home | Telemed Connect</title>
    
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header class="header">
            <div class="logo">
                <img src="images/logo 3.5.jpeg" alt="logo">
            </div>

            <div class="links">
                <nav class="navbar">
                    <ul class="navlinks">
                        <li><a href="index.php" class="animated-underline">Home</a></li>
                        <li><a href="about.php" class="animated-underline">About</a></li>
                        <li><a href="works.php" class="animated-underline">How it Works</a></li>
                        <li><a href="contact.php" class="animated-underline">contact</a></li>
                        <li><a href="find-doctor.php" class="btn-secondary">Find Doctor</a></li>
                        <li><a href="signup.php" class="btn-signup">Signup</a></li>
                    </ul>

                    <div class="menu-toggle">&#9776;</div>
                </nav>
            </div>
    </header>

    

    <section class="hero">
        <div class="hero-content">
            <h1>Find Trusted Doctors Near You</h1>
            <p id="first">Connect with qualified doctors in your area for online consultations</p>
        </div>
            <div class="hero-buttons">
                <a href="login.php" class="btn-login">Login</a>
                <a href="signup.php" class="btn-signup">Signup</a> 
            </div> 
    </section>


    <section class="connect">

    <div>
        <h2 class="infinite-line">Why Choose Telemed Connect ?</h2>
    </div>

    <div class="platform-container">
    <img src="images/6.jpg" class="platform-image" alt="Doctor Match">

    <div class="platform-text-block">
        
        <div class="feature-row">
            <div class="check-circle">✔</div>
            <div class="feature-text">Verified and qualified doctors</div>
        </div>

        <div class="feature-row">
            <div class="check-circle">✔</div>
            <div class="feature-text">Smart doctor matching</div>
        </div>

        <div class="feature-row">
            <div class="check-circle">✔</div>
            <div class="feature-text">Secure and reliable platform</div>
        </div>

        <div class="feature-row">
            <div class="check-circle">✔</div>
            <div class="feature-text highlight-point">
                Location-based matching: Find the nearest doctors in your area
            </div>
        </div>

        <div class="feature-row">
            <div class="check-circle">✔</div>
            <div class="feature-text">24/7 healthcare at your fingertips</div>
        </div>

    </div>
</div>

    </section>       

    <section class="steps">

        <h2 class="infinite-line">How It Works</h2>
        
        <div class="step-card">
            <h3>1. Search for a doctors</h3>
            <p>
                Find doctors based on your location and medical needs.
            </p>
        </div>
    
        <div class="step-card">
            <h3>2. Book an Appointment</h3>
            <p>
                Select a suitable time and book your consultation easily
            </p>
        </div>
    
        <div class="step-card">
            <h3>3. Consult Online</h3>
            <p>
                Connect with doctors through secure video or chat.
            </p>
        </div>
    
        <div class="step-card">
            <h3>4. Get Treatment</h3>
            <p>
                Recieve prescription sand follow-up care.
            </p>
        </div>
    </section>


        <section class="search">
        <h2 id="search" class="infinite-line">Search Doctors by Location</h2>

        <form class="search-form">
            <select required>
                <option value="" disabled selected hidden>Select City</option>
                <option>Douala</option>
                <option>Yaounde</option>
                <option>Bamenda</option>
                <option>Buea</option>
            </select>

            <select required>
                <option value="" disabled selected hidden>Select Area</option>
                <option>Akwa</option>
                <option>Bonamoussadi</option>
                <option>Obili</option>
                <option>Up Station</option>
                <option>Bounduma Gate</option>
            </select>

            <select required>
                <option value="" disabled selected hidden> Select Specialty</option>
                <option>General Pratitioner</option>
                <option>Gynecologist</option>
                <option>Cardiologist</option>
                <option>Pediatrics</option>
            </select>

            <button type="submit">Search</button>
        </form>
    </section>

    <footer>
        <p id="foot">
            &copy; 2025 Telemed Connect. All Rights Reserved
        </p>
    </footer>

    <script>
        let prevScrollPos = window.scrollY;
        const header = document.querySelector('.header');

        window.addEventListener('scroll', () => {
            const currentScrollPos = window.scrollY;
            if (prevScrollPos < currentScrollPos) {
                header.classList.add('hide');
                header.classList.remove('show');
            } else {
                header.classList.add('show');
                header.classList.remove('hide');
            }
            prevScrollPos = currentScrollPos;
        });
    </script>

<script>
    const menu = document.querySelector(".menu-toggle");
    const nav = document.querySelector(".nav-links");

    menu.addEventListener("click", () => {
        nav.classList.toggle("active");
    });
</script>

</body>
</html>