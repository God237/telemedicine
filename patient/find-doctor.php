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

// ========== CORRECTED COORDINATES FOR DOUALA AREAS ==========
$area_coordinates = [
    'Deido' => ['lat' => 4.0458, 'lng' => 9.6896, 'zoom' => 16],
    'Akwa' => ['lat' => 4.0475, 'lng' => 9.7035, 'zoom' => 16],
    'Bonanjo' => ['lat' => 4.0505, 'lng' => 9.7030, 'zoom' => 16],
    'Bonaberi' => ['lat' => 4.0820, 'lng' => 9.6820, 'zoom' => 15],
    'Bonamoussadi' => ['lat' => 4.0840, 'lng' => 9.7150, 'zoom' => 15],
    'New Bell' => ['lat' => 4.0410, 'lng' => 9.6960, 'zoom' => 16],
    'Makepe' => ['lat' => 4.0710, 'lng' => 9.6820, 'zoom' => 15],
    'Logbessou' => ['lat' => 4.0980, 'lng' => 9.7170, 'zoom' => 15],
    'Bepanda' => ['lat' => 4.0600, 'lng' => 9.7000, 'zoom' => 16],
    'Ndogbong' => ['lat' => 4.0900, 'lng' => 9.7100, 'zoom' => 15],
    'Bali' => ['lat' => 4.0550, 'lng' => 9.6950, 'zoom' => 16],
    'Bonapriso' => ['lat' => 4.0520, 'lng' => 9.7080, 'zoom' => 16],
    'Japoma' => ['lat' => 4.1050, 'lng' => 9.7200, 'zoom' => 14]
];

// Fetch patient's saved location from database
$patient_sql = "SELECT city, area, latitude, longitude FROM users WHERE id = ?";
$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient_data = $patient_stmt->get_result()->fetch_assoc();

// Set patient location
$patient_city = 'Douala';
$patient_area = $patient_data['area'] ?? 'Deido';  // Default to Deido if not set

// Get patient coordinates
if (!empty($patient_data['latitude']) && !empty($patient_data['longitude'])) {
    $patient_lat = floatval($patient_data['latitude']);
    $patient_lng = floatval($patient_data['longitude']);
} elseif (isset($area_coordinates[$patient_area])) {
    $patient_lat = $area_coordinates[$patient_area]['lat'];
    $patient_lng = $area_coordinates[$patient_area]['lng'];
} else {
    // Default to Deido
    $patient_lat = 4.0458;
    $patient_lng = 9.6896;
}

// Get zoom level
$patient_zoom = $area_coordinates[$patient_area]['zoom'] ?? 15;

// Fetch ALL approved doctors from database
$doctors_sql = "SELECT id, name, email, phone, specialty, location as city, area, 
                latitude, longitude, experience, consultation_fee, status 
                FROM users 
                WHERE role = 'doctor' AND status = 'approved'";
$doctors_result = $conn->query($doctors_sql);
$doctors = [];

if ($doctors_result && $doctors_result->num_rows > 0) {
    while($doctor = $doctors_result->fetch_assoc()) {
        // Get coordinates
        $doc_lat = null;
        $doc_lng = null;
        
        if (!empty($doctor['latitude']) && !empty($doctor['longitude'])) {
            $doc_lat = floatval($doctor['latitude']);
            $doc_lng = floatval($doctor['longitude']);
        } elseif (!empty($doctor['area']) && isset($area_coordinates[$doctor['area']])) {
            $doc_lat = $area_coordinates[$doctor['area']]['lat'];
            $doc_lng = $area_coordinates[$doctor['area']]['lng'];
        }
        
        if ($doc_lat !== null && $doc_lng !== null) {
            $doctors[] = [
                'id' => $doctor['id'],
                'name' => $doctor['name'],
                'specialty' => $doctor['specialty'] ?? 'General Practitioner',
                'city' => 'Douala',
                'area' => $doctor['area'] ?? 'Unknown',
                'available' => true,
                'ratings' => 4.5,
                'lat' => $doc_lat,
                'lng' => $doc_lng,
                'phone' => $doctor['phone'] ?? '',
                'email' => $doctor['email'],
                'experience' => $doctor['experience'] ?? '5+ years',
                'consultation_fee' => $doctor['consultation_fee'] ?? 5000
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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

        .patient-badge {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
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
            transition: all 0.3s;
        }

        .search-section button:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        .location-status {
            background: #e7f3ff;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border-left: 4px solid #2b7a8a;
        }

        .location-status i {
            color: #2b7a8a;
            font-size: 1.1rem;
        }

        .map-doctors-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        #map {
            height: 600px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: #e9ecef;
        }

        .doctors-container {
            background: white;
            border-radius: 16px;
            overflow-y: auto;
            max-height: 600px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .doctor-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            border-left: 4px solid #2b7a8a;
            cursor: pointer;
        }

        .doctor-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: #fff;
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

        .fee {
            font-weight: 600;
            color: #1a3a4a;
        }

        .badge-available {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2b7a8a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($patient_area); ?>
                </div>
            </div>

            <div class="location-status" id="locationStatus">
                <i class="fas fa-location-dot"></i>
                <span id="locationStatusText">📍 You are in <strong><?php echo htmlspecialchars($patient_area); ?></strong>, Douala</span>
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
                    <option value="Deido">Deido</option>
                    <option value="Akwa">Akwa</option>
                    <option value="Bonanjo">Bonanjo</option>
                    <option value="Bonaberi">Bonaberi</option>
                    <option value="Bonamoussadi">Bonamoussadi</option>
                    <option value="New Bell">New Bell</option>
                    <option value="Makepe">Makepe</option>
                    <option value="Logbessou">Logbessou</option>
                </select>
                <select id="distanceFilter">
                    <option value="2">Within 2 km</option>
                    <option value="5">Within 5 km</option>
                    <option value="10" selected>Within 10 km</option>
                    <option value="20">Within 20 km</option>
                    <option value="all">All distances</option>
                </select>
                <button onclick="filterDoctors()"><i class="fas fa-search"></i> Search</button>
                <button onclick="updateLiveLocation()" style="background: #28a745;"><i class="fas fa-location-dot"></i> Update My Location</button>
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
                        <div class="spinner"></div>
                        <p style="text-align: center;">Loading doctors near you...</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // ========== CORRECTED COORDINATES ==========
        // Patient location - using correct Deido coordinates
        let patientLat = <?php echo $patient_lat; ?>;
        let patientLng = <?php echo $patient_lng; ?>;
        let patientArea = '<?php echo htmlspecialchars($patient_area); ?>';
        let patientZoom = <?php echo $patient_zoom; ?>;
        
        let map = null;
        let markers = [];
        let userMarker = null;
        
        // Doctor data
        const doctorsData = <?php echo json_encode($doctors); ?>;
        let currentDoctors = doctorsData;
        let currentMarkers = [];
        
        // ========== CORRECTED AREA COORDINATES ==========
        const areaCoordinates = {
            'Deido': { lat: 4.0458, lng: 9.6896, zoom: 16 },
            'Akwa': { lat: 4.0475, lng: 9.7035, zoom: 16 },
            'Bonanjo': { lat: 4.0505, lng: 9.7030, zoom: 16 },
            'Bonaberi': { lat: 4.0820, lng: 9.6820, zoom: 15 },
            'Bonamoussadi': { lat: 4.0840, lng: 9.7150, zoom: 15 },
            'New Bell': { lat: 4.0410, lng: 9.6960, zoom: 16 },
            'Makepe': { lat: 4.0710, lng: 9.6820, zoom: 15 },
            'Logbessou': { lat: 4.0980, lng: 9.7170, zoom: 15 }
        };
        
        // Custom markers
        const patientIcon = L.divIcon({
            html: '<div style="background-color: #2b7a8a; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>',
            iconSize: [24, 24],
            className: 'patient-marker'
        });
        
        const doctorIcon = L.divIcon({
            html: '<div style="background-color: #e74c3c; width: 22px; height: 22px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>',
            iconSize: [22, 22],
            className: 'doctor-marker'
        });
        
        // Update live location
        function updateLiveLocation() {
            const statusSpan = document.getElementById('locationStatusText');
            statusSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting your location...';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        patientLat = position.coords.latitude;
                        patientLng = position.coords.longitude;
                        
                        // Update map
                        map.setView([patientLat, patientLng], 16);
                        
                        if (userMarker) {
                            map.removeLayer(userMarker);
                        }
                        userMarker = L.marker([patientLat, patientLng], { icon: patientIcon })
                            .addTo(map)
                            .bindPopup('<strong>Your Current Location</strong>')
                            .openPopup();
                        
                        // Try to get area name from coordinates
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${patientLat}&lon=${patientLng}&zoom=18&addressdetails=1`)
                            .then(response => response.json())
                            .then(data => {
                                let areaName = patientArea;
                                if (data.address && data.address.suburb) {
                                    areaName = data.address.suburb;
                                } else if (data.address && data.address.neighbourhood) {
                                    areaName = data.address.neighbourhood;
                                }
                                statusSpan.innerHTML = `📍 You are in <strong>${areaName}</strong>, Douala`;
                                document.querySelector('.patient-badge').innerHTML = `<i class="fas fa-user"></i> <?php echo $patient_name; ?> <i class="fas fa-map-marker-alt"></i> ${areaName}`;
                            })
                            .catch(() => {
                                statusSpan.innerHTML = `📍 Your location has been updated!`;
                            });
                        
                        // Refresh doctors list
                        displayDoctors(currentDoctors);
                        
                        setTimeout(() => {
                            if (statusSpan.innerHTML.includes('updated')) {
                                statusSpan.innerHTML = `📍 You are in <strong>${patientArea}</strong>, Douala`;
                            }
                        }, 3000);
                    },
                    function(error) {
                        console.error('Geolocation error:', error);
                        let errorMsg = 'Unable to get your location. ';
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMsg += 'Please allow location access in your browser.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMsg += 'Location information unavailable.';
                                break;
                            case error.TIMEOUT:
                                errorMsg += 'Location request timed out.';
                                break;
                        }
                        statusSpan.innerHTML = `⚠️ ${errorMsg} Using default location (${patientArea}).`;
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                statusSpan.innerHTML = '⚠️ Geolocation not supported by your browser.';
            }
        }
        
        // Initialize map
        function initMap() {
            if (map) map.remove();
            
            map = L.map('map').setView([patientLat, patientLng], patientZoom);
            
            // Better tile layer with street names
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 19,
                minZoom: 10
            }).addTo(map);
            
            // Add controls
            L.control.zoom({ position: 'topright' }).addTo(map);
            L.control.scale({ metric: true, imperial: false, position: 'bottomleft' }).addTo(map);
            
            // Add user marker
            userMarker = L.marker([patientLat, patientLng], { icon: patientIcon })
                .addTo(map)
                .bindPopup('<strong>Your Location</strong><br>' + patientArea + ', Douala')
                .openPopup();
        }
        
        // Calculate distance
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
        
        // Display doctors
        function displayDoctors(doctors) {
            const container = document.getElementById('doctorsContainer');
            const distanceLimit = document.getElementById('distanceFilter').value;
            const specialtyFilter = document.getElementById('specialtyFilter').value;
            const areaFilter = document.getElementById('areaFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            // Clear existing markers
            if (currentMarkers.length) {
                currentMarkers.forEach(marker => map.removeLayer(marker));
                currentMarkers = [];
            }
            
            // Filter doctors
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
                container.innerHTML = `<div class="empty-state">
                    <i class="fas fa-user-md"></i>
                    <h3>No Doctors Found</h3>
                    <p>Try adjusting your filters or search criteria.</p>
                    <p style="margin-top: 10px;">📍 You are in: ${patientArea}, Douala</p>
                    <button onclick="updateLiveLocation()" style="margin-top: 15px; padding: 8px 20px; background: #2b7a8a; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-location-dot"></i> Update My Location
                    </button>
                </div>`;
                return;
            }
            
            container.innerHTML = filtered.map(doc => `
                <div class="doctor-card" onclick="focusOnDoctor(${doc.lat}, ${doc.lng})">
                    <div class="doctor-name">${escapeHtml(doc.name)}</div>
                    <div class="specialty"><i class="fas fa-stethoscope"></i> ${escapeHtml(doc.specialty)}</div>
                    <div class="location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(doc.area)}, Douala</div>
                    <div class="distance"><i class="fas fa-location-dot"></i> ${doc.distance.toFixed(1)} km away</div>
                    <div class="fee"><i class="fas fa-money-bill-wave"></i> ${doc.consultation_fee.toLocaleString()} FCFA</div>
                    <span class="badge-available">✅ Available</span>
                    <button class="btn-book" onclick="event.stopPropagation(); bookDoctor(${doc.id})">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </button>
                </div>
            `).join('');
            
            // Add doctor markers
            filtered.forEach(doc => {
                const marker = L.marker([doc.lat, doc.lng], { icon: doctorIcon })
                    .bindPopup(`
                        <strong>${escapeHtml(doc.name)}</strong><br>
                        ${escapeHtml(doc.specialty)}<br>
                        📍 ${escapeHtml(doc.area)}, Douala<br>
                        📏 ${doc.distance.toFixed(1)} km away<br>
                        <button onclick="bookDoctor(${doc.id})" style="margin-top: 5px; padding: 5px 10px; background: #2b7a8a; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Book Appointment
                        </button>
                    `)
                    .addTo(map);
                currentMarkers.push(marker);
            });
            
            // Fit bounds
            if (filtered.length > 0) {
                const bounds = L.latLngBounds([[patientLat, patientLng]]);
                filtered.forEach(d => bounds.extend([d.lat, d.lng]));
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
        
        function focusOnDoctor(lat, lng) {
            map.setView([lat, lng], 17);
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
        
        // Initialize
        window.onload = function() {
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