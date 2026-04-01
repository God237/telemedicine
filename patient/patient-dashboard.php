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

// Get patient location (from session or database)
$patient_location = isset($_SESSION['location']) ? $_SESSION['location'] : "Douala";

// Get statistics
// Upcoming appointments count
$upcoming_sql = "SELECT COUNT(*) as count FROM appointments 
                 WHERE patient_id = ? AND status = 'approved' 
                 AND appointment_date >= CURDATE()";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param("i", $patient_id);
$upcoming_stmt->execute();
$upcoming_count = $upcoming_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Completed consultations count
$completed_sql = "SELECT COUNT(*) as count FROM appointments 
                  WHERE patient_id = ? AND status = 'completed'";
$completed_stmt = $conn->prepare($completed_sql);
$completed_stmt->bind_param("i", $patient_id);
$completed_stmt->execute();
$completed_count = $completed_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Medical reports count (from consultations table)
$reports_sql = "SELECT COUNT(*) as count FROM consultations 
                WHERE patient_id = ? AND status = 'completed'";
$reports_stmt = $conn->prepare($reports_sql);
$reports_stmt->bind_param("i", $patient_id);
$reports_stmt->execute();
$reports_count = $reports_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Get next appointment (removed specialty column)
$next_appointment_sql = "SELECT a.*, d.name as doctor_name 
                         FROM appointments a
                         JOIN users d ON a.doctor_id = d.id
                         WHERE a.patient_id = ? AND a.status = 'approved' 
                         AND a.appointment_date >= CURDATE()
                         ORDER BY a.appointment_date ASC, a.appointment_time ASC
                         LIMIT 1";
$next_stmt = $conn->prepare($next_appointment_sql);
$next_stmt->bind_param("i", $patient_id);
$next_stmt->execute();
$next_appointment = $next_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Patient Dashboard | Telemed Connect Cameroon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

        /* Sidebar Styles - Enhanced for mobile */
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

        /* Sidebar closed state for mobile */
        .sidebar.closed {
            transform: translateX(-100%);
        }

        /* Overlay for mobile when sidebar is open */
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
            background: #3498db;
            color: white;
            box-shadow: 0 4px 12px rgba(52,152,219,0.3);
        }

        .nav-links li.active i {
            color: white;
        }

        /* Mobile Menu Toggle Button */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #3498db;
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
            background: #2980b9;
            transform: scale(1.05);
        }

        /* Main Content */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: calc(100% - 280px);
        }

        /* When sidebar is closed, main content expands */
        .main.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Header */
        .dashboard-header {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .dashboard-header h1 {
            font-size: 1.8rem;
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .dashboard-header p {
            color: #6c757d;
        }

        .patient-location {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            color: #2b7a8a;
            font-size: 0.9rem;
        }

        /* Stats Cards - Responsive Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2b7a8a;
        }

        /* Quick Actions */
        .card-actions {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card-actions h2 {
            color: #1a3a4a;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            background: #2b7a8a;
            color: white;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        /* Appointment Section */
        .appointment-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .appointment-section h2 {
            color: #1a3a4a;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .appointment-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .appointment-info h3 {
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .appointment-info p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 3px 0;
        }

        .join-btn {
            padding: 10px 24px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .join-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        /* Health Summary */
        .health-summary {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header h2 {
            font-size: 1.3rem;
            color: #1a3a4a;
        }

        .section-header i {
            color: #dc2626;
            margin-right: 8px;
        }

        .status-badge {
            background: #059669;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .health-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 16px;
            transition: all 0.3s;
            border-left: 4px solid;
        }

        .health-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .card-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .card-top i {
            font-size: 1.5rem;
        }

        .card-top span {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .health-card h3 {
            font-size: 1.3rem;
            color: #1a3a4a;
        }

        .health-card.blood { border-left-color: #dc2626; }
        .health-card.blood i { color: #dc2626; }
        .health-card.allergy { border-left-color: #f59e0b; }
        .health-card.allergy i { color: #f59e0b; }
        .health-card.condition { border-left-color: #7c3aed; }
        .health-card.condition i { color: #7c3aed; }
        .health-card.visit { border-left-color: #059669; }
        .health-card.visit i { color: #059669; }

        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 20px;
            margin-top: 15px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            width: 70%;
            background: #7c3aed;
            border-radius: 20px;
            animation: progressAnimation 1s ease;
        }

        @keyframes progressAnimation {
            from { width: 0; }
            to { width: 70%; }
        }

        .mini-chart {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .mini-chart h3 {
            margin-bottom: 15px;
            color: #1a3a4a;
            font-size: 1rem;
        }

        canvas {
            max-height: 200px;
            width: 100%;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
                padding-top: 80px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }

            .dashboard-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .health-grid {
                grid-template-columns: 1fr;
            }

            .appointment-card {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 15px;
                padding-top: 70px;
            }

            .dashboard-header h1 {
                font-size: 1.2rem;
            }

            .dashboard-header p {
                font-size: 0.85rem;
            }

            .stat-card h3 {
                font-size: 0.9rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .card-actions h2,
            .appointment-section h2,
            .section-header h2 {
                font-size: 1.1rem;
            }
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    
    <!-- Mobile Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <section class="dashboard">

        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <h2><i class="fas fa-stethoscope"></i> TeleMed Connect</h2>
                <p>Patient Panel</p>
            </div>
            
            <ul class="nav-links">
                <li onclick="window.location.href='patient-dashboard.php'" class="active"> 
                   <i class="fa-solid fa-gauge"></i> 
                   <span>Dashboard</span>
                </li>
                <li onclick="window.location.href='find-doctor.php'">
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
                <li onclick="logout()">
                  <i class="fa-solid fa-right-from-bracket"></i>
                  <span>Logout</span>
                </li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main" id="mainContent">

            <!-- Display success/error messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-header">
                <h1>Welcome, <?php echo htmlspecialchars($patient_name); ?> </h1>
                <p>Your health, simplified and accessible.</p>
                <div class="patient-location">
                    <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($patient_location); ?>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='past-appointments.php?status=upcoming'">
                    <h3>Upcoming Appointments</h3>
                    <p class="stat-number"><?php echo $upcoming_count; ?></p>
                </div>
                <div class="stat-card" onclick="window.location.href='past-appointments.php?status=completed'">
                    <h3>Completed Consultations</h3>
                    <p class="stat-number"><?php echo $completed_count; ?></p>
                </div>
                <div class="stat-card" onclick="window.location.href='medical-reports.php'">
                    <h3>Medical Reports</h3>
                    <p class="stat-number"><?php echo $reports_count; ?></p>
                </div>
                <div class="stat-card" onclick="window.location.href='prescriptions.php'">
                    <h3>Prescriptions</h3>
                    <p class="stat-number">3</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card-actions">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="action-buttons">
                    <button class="action-btn" onclick="window.location.href='find-doctor.php'">
                        <i class="fas fa-search"></i> Find Doctor
                    </button>
                    <button class="action-btn" onclick="window.location.href='book-appointment.php'">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </button>
                </div>
            </div>

            <!-- Next Appointment Section -->
            <div class="appointment-section">
                <h2><i class="fas fa-calendar-check"></i> Next Appointment</h2>
                <?php if ($next_appointment): ?>
                    <div class="appointment-card">
                        <div class="appointment-info">
                            <h3>Dr. <?php echo htmlspecialchars($next_appointment['doctor_name']); ?></h3>
                            <p><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($next_appointment['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?></p>
                            <p><i class="fas fa-comments"></i> <?php echo ucfirst($next_appointment['consultation_type']); ?> Consultation</p>
                            <?php if(!empty($next_appointment['reason'])): ?>
                                <p><i class="fas fa-notes-medical"></i> Reason: <?php echo htmlspecialchars($next_appointment['reason']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="join-btn" onclick="window.location.href='../patient/consultation.php?id=<?php echo $next_appointment['id']; ?>'">
                                <i class="fas fa-video"></i> Join Consultation
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p>No upcoming appointments</p>
                        <small>Book an appointment to get started</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Health Summary -->
            <div class="health-summary">
                <div class="section-header">
                    <h2><i class="fa-solid fa-heart-pulse"></i> Health Summary</h2>
                    <span class="status-badge"><i class="fas fa-chart-line"></i> Stable</span>
                </div>

                <div class="health-grid">
                    <div class="health-card blood">
                        <div class="card-top">
                            <i class="fa-solid fa-tint"></i>
                            <span>Blood Group</span>
                        </div>
                        <h3>O+</h3>
                    </div>

                    <div class="health-card allergy">
                        <div class="card-top">
                            <i class="fa-solid fa-allergies"></i>
                            <span>Allergies</span>
                        </div>
                        <h3>Penicillin</h3>
                    </div>

                    <div class="health-card condition">
                        <div class="card-top">
                            <i class="fa-solid fa-notes-medical"></i>
                            <span>Chronic Condition</span>
                        </div>
                        <h3>Hypertension</h3>
                        <div class="progress-bar">
                            <div class="progress"></div>
                        </div>
                    </div>
                    
                    <div class="health-card visit">
                        <div class="card-top">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span>Last Visit</span>
                        </div>
                        <h3>10 Feb 2026</h3>
                    </div>
                </div>

                <div class="mini-chart">
                    <h3><i class="fas fa-chart-line"></i> Blood Pressure Trend</h3>
                    <canvas id="bpChart"></canvas>
                </div>
            </div>

        </div>

    </section>

    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
            // Change icon based on sidebar state
            if (sidebar.classList.contains('open')) {
                menuToggle.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
        
        function logout() {
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }
        
        // Auto-close sidebar when clicking on a link (mobile)
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
                    const overlay = document.getElementById('sidebarOverlay');
                    if (sidebar.classList.contains('open')) {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                        document.getElementById('menuToggle').innerHTML = '<i class="fas fa-bars"></i>';
                    }
                }
            }, 250);
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        }, 1000);
        
        // Initialize Chart
        const ctx = document.getElementById('bpChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                datasets: [{
                    label: 'Systolic BP',
                    data: [140, 135, 138, 130, 128],
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }, {
                    label: 'Diastolic BP',
                    data: [90, 88, 85, 82, 80],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 60,
                        title: {
                            display: true,
                            text: 'mmHg',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month',
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>