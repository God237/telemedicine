<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "doctor") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration with correct path
require_once dirname(__DIR__) . '/config.php';

// Get doctor information
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'];

// Fetch pending appointments for this specific doctor
$sql = "SELECT a.*, 
        u.name as patient_name,
        u.email as patient_email
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? AND a.status = 'pending'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch upcoming approved appointments
$upcoming_sql = "SELECT a.*, 
                 u.name as patient_name,
                 u.email as patient_email
                 FROM appointments a
                 JOIN users u ON a.patient_id = u.id
                 WHERE a.doctor_id = ? AND a.status = 'approved' 
                 AND a.appointment_date >= CURDATE()
                 ORDER BY a.appointment_date ASC, a.appointment_time ASC
                 LIMIT 5";

$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param("i", $doctor_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
              COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
              COUNT(CASE WHEN status = 'approved' AND appointment_date >= CURDATE() THEN 1 END) as today_count,
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
              FROM appointments 
              WHERE doctor_id = ?";
              
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Doctor Dashboard | Telemed Connect Cameroon</title>
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
            background: linear-gradient(135deg, #0a2b3e 0%, #123152 100%);
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
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: calc(100% - 280px);
        }

        /* When sidebar is closed, main content expands */
        .main-content.expanded {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .welcome h1 {
            font-size: 1.8rem;
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .welcome p {
            color: #6c757d;
        }

        .doctor-badge {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        /* Stats Cards - Responsive Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .stat-card i {
            font-size: 2.5rem;
            color: #2b7a8a;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Appointments Section */
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-title h2 {
            color: #1a3a4a;
            font-size: 1.5rem;
        }

        .view-all {
            color: #2b7a8a;
            text-decoration: none;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .appointment-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid #ffc107;
        }

        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .patient-info h3 {
            color: #1a3a4a;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .patient-info p {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 3px;
        }

        .appointment-details {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: #2b7a8a;
            width: 20px;
        }

        .symptoms {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
            color: #495057;
            font-size: 0.9rem;
            border-left: 3px solid #2b7a8a;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-consult {
            background: #2b7a8a;
            color: white;
        }

        .btn-consult:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        /* Upcoming Appointments */
        .upcoming-card {
            border-left-color: #28a745;
        }

        /* Badge Styles */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background: #ffc107;
            color: #000;
        }

        .badge-approved {
            background: #28a745;
            color: white;
        }

        .badge-completed {
            background: #17a2b8;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #dee2e6;
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

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
                padding-top: 80px;
            }

            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }

            .welcome h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-card h3 {
                font-size: 1.8rem;
            }

            .section-title h2 {
                font-size: 1.2rem;
            }

            .appointment-card {
                padding: 15px;
            }

            .appointment-details {
                gap: 10px;
            }

            .detail-item {
                font-size: 0.85rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
                padding-top: 70px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }

            .welcome h1 {
                font-size: 1.2rem;
            }

            .welcome p {
                font-size: 0.85rem;
            }

            .doctor-badge {
                font-size: 0.85rem;
                padding: 6px 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card i {
                font-size: 2rem;
            }

            .stat-card h3 {
                font-size: 1.5rem;
            }

            .section-title h2 {
                font-size: 1rem;
            }

            .patient-info h3 {
                font-size: 1rem;
            }

            .badge {
                font-size: 0.7rem;
                padding: 3px 8px;
            }

            .symptoms {
                font-size: 0.85rem;
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
                <h2><i class="fas fa-stethoscope"></i> TeleMed</h2>
                <p>Cameroon</p>
            </div>
            
            <ul class="nav-links">
                <li onclick="window.location.href='doctor-dashboard.php'" class="active"> 
                   <i class="fa-solid fa-gauge"></i> 
                   <span>Dashboard</span>
                </li>
                <li onclick="window.location.href='appointments.php'">
                   <i class="fa-solid fa-calendar-check"></i> 
                   <span>All Appointments</span>
                </li>
                <li onclick="window.location.href='consultation.php'">
                    <i class="fa-solid fa-video"></i> 
                    <span>Live Consultation</span>
                </li>
                <li onclick="window.location.href='medical-reports.php'">
                   <i class="fa-solid fa-notes-medical"></i> 
                   <span>Medical Reports</span>
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
        <div class="main-content" id="mainContent">

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
                <div class="welcome">
                    <h1>Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?> 👋</h1>
                    <p>Here's what's happening with your practice today</p>
                </div>
                <div class="doctor-badge">
                    <i class="fas fa-user-md"></i> Verified Doctor
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='appointments.php?status=pending'">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                    <p>Pending Appointments</p>
                </div>
                <div class="stat-card" onclick="window.location.href='appointments.php?status=today'">
                    <i class="fas fa-calendar-day"></i>
                    <h3><?php echo $stats['today_count'] ?? 0; ?></h3>
                    <p>Today's Appointments</p>
                </div>
                <div class="stat-card" onclick="window.location.href='appointments.php?status=completed'">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['completed_count'] ?? 0; ?></h3>
                    <p>Completed Consultations</p>
                </div>
            </div>

            <!-- Pending Appointments Section -->
            <div class="section-title">
                <h2><i class="fas fa-hourglass-half"></i> Pending Appointments</h2>
                <a href="appointments.php?status=pending" class="view-all">View All →</a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="appointment-card">
                        <div class="appointment-header">
                            <div class="patient-info">
                                <h3><?php echo htmlspecialchars($row['patient_name']); ?></h3>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['patient_email']); ?></p>
                            </div>
                            <span class="badge badge-pending">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        </div>
                        
                        <div class="appointment-details">
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('F j, Y', strtotime($row['appointment_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('g:i A', strtotime($row['appointment_time'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-comments"></i>
                                <span><?php echo ucfirst($row['consultation_type']); ?> Consultation</span>
                            </div>
                        </div>
                        
                        <?php if(!empty($row['reason'])): ?>
                        <div class="symptoms">
                            <i class="fas fa-notes-medical"></i> <strong>Reason for consultation:</strong><br>
                            <?php echo htmlspecialchars($row['reason']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <a href="approve-appointment.php?id=<?php echo $row['id']; ?>" class="btn btn-approve" onclick="return confirm('Approve this appointment?')">
                                <i class="fas fa-check"></i> Approve
                            </a>
                            <a href="reject-appointment.php?id=<?php echo $row['id']; ?>" class="btn btn-reject" onclick="return confirm('Reject this appointment?')">
                                <i class="fas fa-times"></i> Reject
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>No pending appointments at the moment</p>
                    <small style="color: #6c757d;">When patients book appointments, they will appear here</small>
                </div>
            <?php endif; ?>

            <!-- Upcoming Appointments Section -->
            <div class="section-title" style="margin-top: 40px;">
                <h2><i class="fas fa-calendar-week"></i> Upcoming Appointments</h2>
                <a href="appointments.php?status=approved" class="view-all">View All →</a>
            </div>

            <?php if ($upcoming_result && $upcoming_result->num_rows > 0): ?>
                <?php while($row = $upcoming_result->fetch_assoc()): ?>
                    <div class="appointment-card upcoming-card">
                        <div class="appointment-header">
                            <div class="patient-info">
                                <h3><?php echo htmlspecialchars($row['patient_name']); ?></h3>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['patient_email']); ?></p>
                            </div>
                            <span class="badge badge-approved">
                                <i class="fas fa-check-circle"></i> Approved
                            </span>
                        </div>
                        
                        <div class="appointment-details">
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('F j, Y', strtotime($row['appointment_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('g:i A', strtotime($row['appointment_time'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-comments"></i>
                                <span><?php echo ucfirst($row['consultation_type']); ?> Consultation</span>
                            </div>
                        </div>
                        
                        <?php if(!empty($row['reason'])): ?>
                        <div class="symptoms">
                            <i class="fas fa-notes-medical"></i> <strong>Reason:</strong> <?php echo htmlspecialchars($row['reason']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <a href="start-consultation.php?id=<?php echo $row['id']; ?>" class="btn btn-consult">
                                <i class="fas fa-video"></i> Start Consultation
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-day"></i>
                    <p>No upcoming appointments scheduled</p>
                    <small style="color: #6c757d;">Approved appointments will appear here</small>
                </div>
            <?php endif; ?>

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
        
        // Optional: Auto-refresh pending appointments every 60 seconds
        // Uncomment if you want automatic refresh
        /*
        setTimeout(function() {
            location.reload();
        }, 60000);
        */
    </script>
</body>
</html>