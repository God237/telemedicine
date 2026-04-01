<?php
session_start();

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get patient information
$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['name'];

// Fetch doctors from database
$doctors_sql = "SELECT * FROM users WHERE role = 'doctor' AND (status = 'approved' OR status IS NULL)";
$doctors_result = $conn->query($doctors_sql);
$doctors = [];

if ($doctors_result && $doctors_result->num_rows > 0) {
    while($doctor = $doctors_result->fetch_assoc()) {
        $doctors[] = [
            'id' => $doctor['id'],
            'name' => $doctor['name'],
            'specialty' => $doctor['specialty'] ?? 'General Practitioner',
            'city' => $doctor['location'] ?? 'Yaoundé',
            'area' => $doctor['area'] ?? 'Central',
            'available' => true,
            'ratings' => 4.5,
            'lat' => $doctor['latitude'] ?? null,
            'lng' => $doctor['longitude'] ?? null,
            'phone' => $doctor['phone'] ?? '',
            'email' => $doctor['email'],
            'experience' => $doctor['experience'] ?? '5+ years',
            'consultation_fee' => $doctor['consultation_fee'] ?? 5000
        ];
    }
}

// If no doctors in database, use default Cameroon doctors
if (empty($doctors)) {
    $doctors = [
        ['id' => 1, 'name' => 'Dr. Marie Ndomo', 'specialty' => 'Cardiologist', 'city' => 'Yaoundé', 'area' => 'Bastos', 'available' => true, 'ratings' => 4.8, 'lat' => 3.8667, 'lng' => 11.5167, 'phone' => '655123456', 'email' => 'marie.ndomo@telemed.cm', 'experience' => '12 years', 'consultation_fee' => 8000],
        ['id' => 2, 'name' => 'Dr. Paul Tchamba', 'specialty' => 'Dermatologist', 'city' => 'Douala', 'area' => 'Bonanjo', 'available' => false, 'ratings' => 4.3, 'lat' => 4.0500, 'lng' => 9.7000, 'phone' => '677789123', 'email' => 'paul.tchamba@telemed.cm', 'experience' => '8 years', 'consultation_fee' => 6000],
        ['id' => 3, 'name' => 'Dr. Amina Moussa', 'specialty' => 'Gynecologist', 'city' => 'Yaoundé', 'area' => 'Mvog-Mbi', 'available' => true, 'ratings' => 4.9, 'lat' => 3.8725, 'lng' => 11.5185, 'phone' => '699456789', 'email' => 'amina.moussa@telemed.cm', 'experience' => '15 years', 'consultation_fee' => 10000],
        ['id' => 4, 'name' => 'Dr. Jean-Pierre Kengne', 'specialty' => 'Pediatrician', 'city' => 'Douala', 'area' => 'Akwa', 'available' => true, 'ratings' => 4.6, 'lat' => 4.0469, 'lng' => 9.7043, 'phone' => '670112233', 'email' => 'jean.kengne@telemed.cm', 'experience' => '10 years', 'consultation_fee' => 7000],
        ['id' => 5, 'name' => 'Dr. Christine Njiké', 'specialty' => 'General Practitioner', 'city' => 'Bafoussam', 'area' => 'Toungou', 'available' => true, 'ratings' => 4.5, 'lat' => 5.4778, 'lng' => 10.4178, 'phone' => '694556677', 'email' => 'christine.njike@telemed.cm', 'experience' => '7 years', 'consultation_fee' => 5000],
        ['id' => 6, 'name' => 'Dr. Alioum Bakary', 'specialty' => 'Neurologist', 'city' => 'Garoua', 'area' => 'Roumdé', 'available' => true, 'ratings' => 4.7, 'lat' => 9.3014, 'lng' => 13.3900, 'phone' => '655998877', 'email' => 'alioum.bakary@telemed.cm', 'experience' => '20 years', 'consultation_fee' => 12000],
        ['id' => 7, 'name' => 'Dr. Esther Fonyuy', 'specialty' => 'Dentist', 'city' => 'Bamenda', 'area' => 'Nkwen', 'available' => true, 'ratings' => 4.4, 'lat' => 5.9597, 'lng' => 10.1456, 'phone' => '677443322', 'email' => 'esther.fonyuy@telemed.cm', 'experience' => '6 years', 'consultation_fee' => 6000]
    ];
}

// Store doctors in session for offline access
$_SESSION['cached_doctors'] = json_encode($doctors);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Find Doctor | Patient Dashboard | TeleMed Cameroon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f4f6f9;
            overflow-x: hidden;
        }

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, #123152 0%, #0a1a2a 100%);
            color: white;
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .sidebar.closed {
            transform: translateX(-100%);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .logo {
            margin-bottom: 40px;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }

        .logo h2 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .logo p {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        .nav-links {
            list-style: none;
            padding: 0;
        }

        .nav-links li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            margin-bottom: 8px;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.3s ease;
            color: #e0e0e0;
        }

        .nav-links li i {
            font-size: 18px;
            width: 24px;
            transition: all 0.3s ease;
        }

        .nav-links li span {
            font-size: 0.95rem;
        }

        .nav-links li:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateX(5px);
            color: white;
        }

        .nav-links li.active {
            background: #2b7a8a;
            color: white;
            box-shadow: 0 4px 12px rgba(43,122,138,0.3);
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #2b7a8a;
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: #1f5c6e;
            transform: scale(1.05);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: calc(100% - 280px);
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #6c757d;
            margin-top: 5px;
        }

        .patient-badge {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        /* Search Section */
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-section input,
        .search-section select {
            flex: 1;
            min-width: 150px;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
        }

        .search-section input:focus,
        .search-section select:focus {
            border-color: #2b7a8a;
            box-shadow: 0 0 0 3px rgba(43,122,138,0.1);
        }

        .search-section button {
            padding: 12px 25px;
            background: #2b7a8a;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .search-section button:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        /* Map and Doctors Layout */
        .map-doctors-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        #map {
            height: 550px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            background: #e9ecef;
        }

        .doctors-container {
            background: white;
            border-radius: 16px;
            overflow-y: auto;
            max-height: 550px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .doctor-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            border-left: 4px solid #2b7a8a;
        }

        .doctor-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .doctor-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a3a4a;
        }

        .specialty {
            color: #2b7a8a;
            font-size: 0.85rem;
            margin: 5px 0;
        }

        .location {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 5px 0;
        }

        .distance {
            font-size: 0.85rem;
            color: #28a745;
            font-weight: 500;
        }

        .ratings {
            color: #f39c12;
            margin: 5px 0;
        }

        .fee {
            font-weight: 600;
            color: #1a3a4a;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-available {
            background: #d4edda;
            color: #155724;
        }

        .badge-unavailable {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-book {
            width: 100%;
            padding: 8px;
            background: #2b7a8a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-book:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        .btn-book:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        /* Offline Banner */
        .offline-banner {
            background: #fff3cd;
            color: #856404;
            padding: 10px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .offline-banner i {
            font-size: 1.2rem;
        }

        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2b7a8a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .map-doctors-layout {
                grid-template-columns: 1fr;
            }
            
            #map {
                height: 400px;
            }
            
            .doctors-container {
                max-height: 400px;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
                padding-top: 80px;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .search-section {
                flex-direction: column;
            }

            .search-section input,
            .search-section select,
            .search-section button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .doctor-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <section class="dashboard">
        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <h2><i class="fas fa-stethoscope"></i> TeleMed Connect</h2>
                <p>Patient panel</p>
            </div>
            <ul class="nav-links">
                <li onclick="window.location.href='patient-dashboard.php'"> 
                   <i class="fa-solid fa-gauge"></i> 
                   <span>Dashboard</span>
                </li>
                <li onclick="window.location.href='find-doctor.php'" class="active">
                   <i class="fa-solid fa-stethoscope"></i> 
                   <span>Find Doctor</span>
                </li>
                <li onclick="window.location.href='book-appointment.php'">
                   <i class="fa-solid fa-calendar-plus"></i> 
                   <span>Book Appointment</span>
                </li>
                <li onclick="window.location.href='consultation.php'">
                    <i class="fa-solid fa-video"></i> 
                    <span>Consultation</span>
                 </li>
                <li onclick="window.location.href='medical-reports.php'">
                   <i class="fa-solid fa-notes-medical"></i> 
                   <span>Medical Reports</span>
                </li>
                <li onclick="window.location.href='past-appointments.php'">
                    <i class="fa-solid fa-calendar-days"></i> 
                    <span>Past Appointments</span>
                </li>
                <li onclick="window.location.href='profile.php'">
                    <i class="fa-solid fa-user"></i> 
                    <span>Profile</span>
                 </li>
                <li onclick="logout('../index.php')">
                  <i class="fa-solid fa-right-from-bracket"></i>
                  <span>Logout</span>
                </li>
            </ul>
        </aside>

        <div class="main-content" id="mainContent">
            
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-search"></i> Find a Doctor</h1>
                    <p>Search for qualified doctors near your location in Cameroon</p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                </div>
            </div>

            <!-- Offline Banner -->
            <div id="offlineBanner" class="offline-banner">
                <i class="fas fa-wifi-slash"></i>
                <span>You are offline. Showing cached doctor data. Some features may be limited.</span>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <input type="text" id="searchInput" placeholder="Search by doctor name or specialty...">
                <select id="specialtyFilter">
                    <option value="all">All Specialties</option>
                    <option value="Cardiologist">Cardiologist</option>
                    <option value="Dermatologist">Dermatologist</option>
                    <option value="Gynecologist">Gynecologist</option>
                    <option value="Pediatrician">Pediatrician</option>
                    <option value="General Practitioner">General Practitioner</option>
                    <option value="Neurologist">Neurologist</option>
                    <option value="Dentist">Dentist</option>
                </select>
                <select id="distanceFilter">
                    <option value="10">Within 10 km</option>
                    <option value="20">Within 20 km</option>
                    <option value="50">Within 50 km</option>
                    <option value="100" selected>Within 100 km</option>
                    <option value="all">All distances</option>
                </select>
                <button onclick="filterDoctors()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <!-- Map and Doctors Layout -->
            <div class="map-doctors-layout">
                <div id="map"></div>
                <div class="doctors-container" id="doctorsContainer">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading doctors near you...</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Patient location variables
        let patientLat = null;
        let patientLng = null;
        let map = null;
        let markers = [];
        
        // Doctor data from PHP
        const doctorsData = <?php echo json_encode($doctors); ?>;
        
        // Cache doctors in localStorage for offline use
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('cachedDoctors', JSON.stringify(doctorsData));
        }
        
        let currentDoctors = [];
        let currentMarkers = [];
        
        // Check offline status
        function checkOnlineStatus() {
            if (!navigator.onLine) {
                document.getElementById('offlineBanner').style.display = 'flex';
                // Load cached doctors
                const cached = localStorage.getItem('cachedDoctors');
                if (cached) {
                    currentDoctors = JSON.parse(cached);
                    displayDoctors(currentDoctors);
                }
            } else {
                document.getElementById('offlineBanner').style.display = 'none';
                currentDoctors = doctorsData;
            }
        }
        
        // Get patient location
        function getPatientLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        patientLat = position.coords.latitude;
                        patientLng = position.coords.longitude;
                        initMap();
                        displayDoctors(currentDoctors);
                    },
                    function(error) {
                        console.error('Geolocation error:', error);
                        // Default to Yaoundé, Cameroon
                        patientLat = 3.8667;
                        patientLng = 11.5167;
                        initMap();
                        displayDoctors(currentDoctors);
                        alert('Unable to get your location. Showing doctors in Yaoundé area.');
                    }
                );
            } else {
                // Default to Yaoundé, Cameroon
                patientLat = 3.8667;
                patientLng = 11.5167;
                initMap();
                displayDoctors(currentDoctors);
                alert('Geolocation not supported. Showing doctors in Yaoundé area.');
            }
        }
        
        // Initialize map
        function initMap() {
            if (map) {
                map.remove();
            }
            
            map = L.map('map').setView([patientLat, patientLng], 12);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add patient marker
            L.marker([patientLat, patientLng], {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background-color: #2b7a8a; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 4px rgba(0,0,0,0.3);"></div>',
                    iconSize: [20, 20],
                    popupAnchor: [0, -10]
                })
            }).addTo(map).bindPopup('<strong>You are here</strong>').openPopup();
        }
        
        // Calculate distance using Haversine formula
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }
        
        // Display doctors
        function displayDoctors(doctors) {
            const container = document.getElementById('doctorsContainer');
            const distanceLimit = parseInt(document.getElementById('distanceFilter').value);
            const specialtyFilter = document.getElementById('specialtyFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            // Clear existing markers
            if (currentMarkers.length) {
                currentMarkers.forEach(marker => map.removeLayer(marker));
                currentMarkers = [];
            }
            
            // Filter doctors
            let filteredDoctors = doctors.filter(doctor => {
                if (!doctor.lat || !doctor.lng) return false;
                
                const distance = calculateDistance(patientLat, patientLng, doctor.lat, doctor.lng);
                doctor.distance = distance;
                
                // Apply distance filter
                if (distanceLimit !== 'all' && distance > distanceLimit) return false;
                
                // Apply specialty filter
                if (specialtyFilter !== 'all' && doctor.specialty !== specialtyFilter) return false;
                
                // Apply search filter
                if (searchTerm && !doctor.name.toLowerCase().includes(searchTerm) && 
                    !doctor.specialty.toLowerCase().includes(searchTerm)) return false;
                
                return true;
            });
            
            // Sort by distance
            filteredDoctors.sort((a, b) => a.distance - b.distance);
            
            // Display doctors
            if (filteredDoctors.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-md"></i>
                        <h3>No Doctors Found</h3>
                        <p>Try adjusting your filters or search criteria.</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = filteredDoctors.map(doctor => `
                <div class="doctor-card">
                    <div class="doctor-header">
                        <div>
                            <div class="doctor-name">${escapeHtml(doctor.name)}</div>
                            <div class="specialty"><i class="fas fa-stethoscope"></i> ${escapeHtml(doctor.specialty)}</div>
                            <div class="location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(doctor.city)}, ${escapeHtml(doctor.area)}</div>
                            <div class="distance"><i class="fas fa-location-dot"></i> ${doctor.distance.toFixed(1)} km away</div>
                            <div class="ratings"><i class="fas fa-star"></i> ${doctor.ratings} / 5.0</div>
                            <div class="fee"><i class="fas fa-money-bill-wave"></i> ${doctor.consultation_fee.toLocaleString()} FCFA</div>
                        </div>
                        <span class="badge ${doctor.available ? 'badge-available' : 'badge-unavailable'}">
                            ${doctor.available ? 'Available' : 'Unavailable'}
                        </span>
                    </div>
                    <button class="btn-book" onclick="bookDoctor(${doctor.id})" ${!doctor.available ? 'disabled' : ''}>
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </button>
                </div>
            `).join('');
            
            // Add doctor markers to map
            filteredDoctors.forEach(doctor => {
                const marker = L.marker([doctor.lat, doctor.lng])
                    .bindPopup(`
                        <strong>${escapeHtml(doctor.name)}</strong><br>
                        ${escapeHtml(doctor.specialty)}<br>
                        ${escapeHtml(doctor.city)}<br>
                        <strong>${doctor.distance.toFixed(1)} km away</strong><br>
                        <button onclick="bookDoctor(${doctor.id})" style="margin-top: 5px; padding: 5px 10px; background: #2b7a8a; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Book Appointment
                        </button>
                    `)
                    .addTo(map);
                currentMarkers.push(marker);
            });
            
            // Adjust map bounds to show all markers
            if (filteredDoctors.length > 0) {
                const bounds = L.latLngBounds([[patientLat, patientLng]]);
                filteredDoctors.forEach(doctor => {
                    bounds.extend([doctor.lat, doctor.lng]);
                });
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
        
        // Filter doctors
        function filterDoctors() {
            displayDoctors(currentDoctors);
        }
        
        // Book doctor
        function bookDoctor(doctorId) {
            if (!navigator.onLine) {
                alert('You are offline. Please connect to the internet to book an appointment.');
                return;
            }
            localStorage.setItem('selectedDoctorId', doctorId);
            window.location.href = 'book-appointment.php?doctor_id=' + doctorId;
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
        
        function logout() {
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }
        
        // Initialize
        window.onload = function() {
            checkOnlineStatus();
            getPatientLocation();
            
            // Listen for online/offline events
            window.addEventListener('online', function() {
                checkOnlineStatus();
                currentDoctors = doctorsData;
                displayDoctors(currentDoctors);
            });
            
            window.addEventListener('offline', function() {
                checkOnlineStatus();
            });
        };
        
        // Auto-close sidebar on mobile when clicking links
        document.querySelectorAll('.nav-links li').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    }
                }
                if (map) {
                    setTimeout(() => map.invalidateSize(), 200);
                }
            }, 250);
        });
        
        // Add event listeners for filters
        document.getElementById('specialtyFilter').addEventListener('change', filterDoctors);
        document.getElementById('distanceFilter').addEventListener('change', filterDoctors);
        document.getElementById('searchInput').addEventListener('input', filterDoctors);
    </script>
</body>
</html>