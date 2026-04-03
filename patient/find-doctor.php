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

// Define Douala area coordinates mapping
$area_coordinates = [
    'Akwa' => ['lat' => 4.0469, 'lng' => 9.7043],
    'Bonanjo' => ['lat' => 4.0500, 'lng' => 9.7042],
    'Bonaberi' => ['lat' => 4.0833, 'lng' => 9.6833],
    'Bonamoussadi' => ['lat' => 4.0833, 'lng' => 9.7167],
    'New Bell' => ['lat' => 4.0417, 'lng' => 9.6958],
    'Deido' => ['lat' => 4.0458, 'lng' => 9.6896],
    'Makepe' => ['lat' => 4.0708, 'lng' => 9.6833],
    'Tsinga' => ['lat' => 4.0542, 'lng' => 9.7125],
    'Damas' => ['lat' => 4.0479, 'lng' => 9.7083],
    'Logbessou' => ['lat' => 4.0972, 'lng' => 9.7167]
];

// Fetch patient's saved location from database
$patient_sql = "SELECT city, area, latitude, longitude FROM users WHERE id = ?";
$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient_data = $patient_stmt->get_result()->fetch_assoc();

// Set patient location - use area coordinates if available
$patient_city = 'Douala';
$patient_area = $patient_data['area'] ?? 'Akwa';

// Get patient coordinates from area
if (isset($area_coordinates[$patient_area])) {
    $patient_lat = $area_coordinates[$patient_area]['lat'];
    $patient_lng = $area_coordinates[$patient_area]['lng'];
} else {
    $patient_lat = 4.0500;
    $patient_lng = 9.7000;
}

// Fetch ALL approved doctors from database (not just those with coordinates)
$doctors_sql = "SELECT id, name, email, phone, specialty, location as city, area, 
                latitude, longitude, experience, consultation_fee, status 
                FROM users 
                WHERE role = 'doctor' AND status = 'approved'";
$doctors_result = $conn->query($doctors_sql);
$doctors = [];

if ($doctors_result && $doctors_result->num_rows > 0) {
    while($doctor = $doctors_result->fetch_assoc()) {
        // Get coordinates - use doctor's saved coordinates or derive from area
        $doc_lat = $doctor['latitude'];
        $doc_lng = $doctor['longitude'];
        
        // If doctor has no coordinates but has an area, use area coordinates
        if (empty($doc_lat) && !empty($doctor['area']) && isset($area_coordinates[$doctor['area']])) {
            $doc_lat = $area_coordinates[$doctor['area']]['lat'];
            $doc_lng = $area_coordinates[$doctor['area']]['lng'];
        }
        
        // If still no coordinates, skip this doctor (can't show on map)
        if (empty($doc_lat) || empty($doc_lng)) {
            continue;
        }
        
        $doctors[] = [
            'id' => $doctor['id'],
            'name' => $doctor['name'],
            'specialty' => $doctor['specialty'] ?? 'General Practitioner',
            'city' => 'Douala',
            'area' => $doctor['area'] ?? 'Unknown',
            'available' => true,
            'ratings' => 4.5,
            'lat' => floatval($doc_lat),
            'lng' => floatval($doc_lng),
            'phone' => $doctor['phone'] ?? '',
            'email' => $doctor['email'],
            'experience' => $doctor['experience'] ?? '5+ years',
            'consultation_fee' => $doctor['consultation_fee'] ?? 5000
        ];
    }
}

// If no approved doctors in database, show message
if (empty($doctors)) {
    // You can keep default doctors for testing or show empty state
    $doctors = [];
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

        .dashboard {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

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
        }

        .nav-links li:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateX(5px);
            color: white;
        }

        .nav-links li.active {
            background: #2b7a8a;
            color: white;
        }

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
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s;
        }

        .page-header {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: #1a3a4a;
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
        }

        .search-section button {
            padding: 12px 25px;
            background: #2b7a8a;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
        }

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
        }

        .btn-book:hover {
            background: #1f5c6e;
        }

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

        @media (max-width: 1024px) {
            .map-doctors-layout {
                grid-template-columns: 1fr;
            }
            #map {
                height: 400px;
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
    </style>
</head>
<body>
    
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <section class="dashboard">
        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <h2><i class="fas fa-stethoscope"></i> TeleMed Connect</h2>
                <p>Patient panel</p>
            </div>
            <ul class="nav-links">
                <li onclick="window.location.href='patient-dashboard.php'"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></li>
                <li class="active"><i class="fa-solid fa-stethoscope"></i><span>Find Doctor</span></li>
                <li onclick="window.location.href='book-appointment.php'"><i class="fa-solid fa-calendar-plus"></i><span>Book Appointment</span></li>
                <li onclick="window.location.href='consultation.php'"><i class="fa-solid fa-video"></i><span>Consultation</span></li>
                <li onclick="window.location.href='medical-reports.php'"><i class="fa-solid fa-notes-medical"></i><span>Medical Reports</span></li>
                <li onclick="window.location.href='past-appointments.php'"><i class="fa-solid fa-calendar-days"></i><span>Past Appointments</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-search"></i> Find a Doctor</h1>
                    <p>Search for qualified doctors near your location in Douala, Cameroon</p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                    <!-- <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($patient_area); ?>, Douala -->
                </div>
            </div>

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
                <select id="areaFilter">
                    <option value="all">All Areas</option>
                    <option value="Akwa">Akwa</option>
                    <option value="Bonanjo">Bonanjo</option>
                    <option value="Bonaberi">Bonaberi</option>
                    <option value="Bonamoussadi">Bonamoussadi</option>
                    <option value="New Bell">New Bell</option>
                    <option value="Deido">Deido</option>
                    <option value="Makepe">Makepe</option>
                    <option value="Logbessou">Logbessou</option>
                </select>
                <select id="distanceFilter">
                    <option value="5">Within 5 km</option>
                    <option value="10" selected>Within 10 km</option>
                    <option value="20">Within 20 km</option>
                    <option value="all">All distances</option>
                </select>
                <button onclick="filterDoctors()"><i class="fas fa-search"></i> Search</button>
            </div>

            <div class="map-doctors-layout">
                <div id="map"></div>
                <div class="doctors-container" id="doctorsContainer">
                    <?php if (empty($doctors)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-md"></i>
                            <h3>No Approved Doctors Yet</h3>
                            <p>There are no approved doctors in the system yet. Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading doctors near you in Douala...</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Patient location
        let patientLat = <?php echo $patient_lat; ?>;
        let patientLng = <?php echo $patient_lng; ?>;
        let patientArea = '<?php echo htmlspecialchars($patient_area); ?>';
        let map = null;
        let markers = [];
        
        // Doctor data
        const doctorsData = <?php echo json_encode($doctors); ?>;
        let currentDoctors = [];
        let currentMarkers = [];
        
        // Area coordinates
        const areaCoordinates = {
            'Akwa': { lat: 4.0469, lng: 9.7043 },
            'Bonanjo': { lat: 4.0500, lng: 9.7042 },
            'Bonaberi': { lat: 4.0833, lng: 9.6833 },
            'Bonamoussadi': { lat: 4.0833, lng: 9.7167 },
            'New Bell': { lat: 4.0417, lng: 9.6958 },
            'Deido': { lat: 4.0458, lng: 9.6896 },
            'Makepe': { lat: 4.0708, lng: 9.6833 },
            'Logbessou': { lat: 4.0972, lng: 9.7167 }
        };
        
        function initMap() {
            if (map) map.remove();
            
            map = L.map('map').setView([patientLat, patientLng], 14);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            
            // Patient marker
            L.marker([patientLat, patientLng], {
                icon: L.divIcon({
                    html: '<div style="background-color: #2b7a8a; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white;"></div>',
                    iconSize: [20, 20]
                })
            }).addTo(map).bindPopup('<strong>Your Location</strong><br>' + patientArea + ', Douala').openPopup();
        }
        
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }
        
        function displayDoctors(doctors) {
            const container = document.getElementById('doctorsContainer');
            const distanceLimit = document.getElementById('distanceFilter').value;
            const specialtyFilter = document.getElementById('specialtyFilter').value;
            const areaFilter = document.getElementById('areaFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            if (currentMarkers.length) {
                currentMarkers.forEach(m => map.removeLayer(m));
                currentMarkers = [];
            }
            
            let filtered = doctors.filter(doc => {
                if (!doc.lat || !doc.lng) return false;
                const distance = calculateDistance(patientLat, patientLng, doc.lat, doc.lng);
                doc.distance = distance;
                if (distanceLimit !== 'all' && distance > parseFloat(distanceLimit)) return false;
                if (specialtyFilter !== 'all' && doc.specialty !== specialtyFilter) return false;
                if (areaFilter !== 'all' && doc.area !== areaFilter) return false;
                if (searchTerm && !doc.name.toLowerCase().includes(searchTerm) && 
                    !doc.specialty.toLowerCase().includes(searchTerm)) return false;
                return true;
            });
            
            filtered.sort((a, b) => a.distance - b.distance);
            
            if (filtered.length === 0) {
                container.innerHTML = `<div class="empty-state"><i class="fas fa-user-md"></i><h3>No Doctors Found</h3><p>Try adjusting your filters.</p><p>📍 You are in: ${patientArea}, Douala</p></div>`;
                return;
            }
            
            container.innerHTML = filtered.map(doc => `
                <div class="doctor-card">
                    <div class="doctor-header">
                        <div>
                            <div class="doctor-name">${escapeHtml(doc.name)}</div>
                            <div class="specialty"><i class="fas fa-stethoscope"></i> ${escapeHtml(doc.specialty)}</div>
                            <div class="location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(doc.area)}, Douala</div>
                            <div class="distance"><i class="fas fa-location-dot"></i> ${doc.distance.toFixed(1)} km away</div>
                            <div class="ratings"><i class="fas fa-star"></i> ${doc.ratings} / 5.0</div>
                            <div class="fee"><i class="fas fa-money-bill-wave"></i> ${doc.consultation_fee.toLocaleString()} FCFA</div>
                        </div>
                        <span class="badge badge-available">✅ Available</span>
                    </div>
                    <button class="btn-book" onclick="bookDoctor(${doc.id})">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </button>
                </div>
            `).join('');
            
            filtered.forEach(doc => {
                const marker = L.marker([doc.lat, doc.lng])
                    .bindPopup(`<strong>${escapeHtml(doc.name)}</strong><br>${escapeHtml(doc.specialty)}<br>📍 ${escapeHtml(doc.area)}<br>📏 ${doc.distance.toFixed(1)} km away`)
                    .addTo(map);
                currentMarkers.push(marker);
            });
            
            if (filtered.length > 0) {
                const bounds = L.latLngBounds([[patientLat, patientLng]]);
                filtered.forEach(d => bounds.extend([d.lat, d.lng]));
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
        
        function filterDoctors() {
            displayDoctors(currentDoctors);
        }
        
        function bookDoctor(doctorId) {
            localStorage.setItem('selectedDoctorId', doctorId);
            window.location.href = 'book-appointment.php?doctor_id=' + doctorId;
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
        
        function logout() {
            if(confirm('Logout?')) window.location.href = '../index.php';
        }
        
        window.onload = function() {
            currentDoctors = doctorsData;
            initMap();
            displayDoctors(currentDoctors);
            
            document.getElementById('specialtyFilter').addEventListener('change', filterDoctors);
            document.getElementById('areaFilter').addEventListener('change', filterDoctors);
            document.getElementById('distanceFilter').addEventListener('change', filterDoctors);
            document.getElementById('searchInput').addEventListener('input', filterDoctors);
        };
        
        document.querySelectorAll('.nav-links li').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) setTimeout(closeSidebar, 150);
            });
        });
    </script>
</body>
</html>