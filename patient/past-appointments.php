<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$doctor_filter = isset($_GET['doctor']) ? intval($_GET['doctor']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query
$sql = "SELECT a.*, 
        d.name as doctor_name,
        d.specialty as doctor_specialty,
        d.location as doctor_location,
        d.phone as doctor_phone,
        d.email as doctor_email,
        c.id as consultation_id,
        c.status as consultation_status,
        c.start_time,
        c.end_time,
        (SELECT COUNT(*) FROM consultation_messages WHERE consultation_id = c.id) as message_count,
        (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescription_count
        FROM appointments a
        JOIN users d ON a.doctor_id = d.id
        LEFT JOIN consultations c ON a.id = c.appointment_id
        WHERE a.patient_id = ?";

if ($status_filter != 'all') {
    $sql .= " AND a.status = '$status_filter'";
}

if (!empty($date_filter)) {
    $sql .= " AND a.appointment_date = '$date_filter'";
}

if ($doctor_filter > 0) {
    $sql .= " AND a.doctor_id = $doctor_filter";
}

if (!empty($search_query)) {
    $sql .= " AND (d.name LIKE '%$search_query%' OR d.specialty LIKE '%$search_query%' OR a.reason LIKE '%$search_query%')";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Fetch doctors list for filter
$doctors_sql = "SELECT DISTINCT d.id, d.name 
                FROM appointments a
                JOIN users d ON a.doctor_id = d.id
                WHERE a.patient_id = ?
                ORDER BY d.name";
$doctors_stmt = $conn->prepare($doctors_sql);
$doctors_stmt->bind_param("i", $patient_id);
$doctors_stmt->execute();
$doctors = $doctors_stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
              COUNT(CASE WHEN status = 'approved' AND appointment_date >= CURDATE() THEN 1 END) as upcoming_count,
              COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
              COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
              FROM appointments 
              WHERE patient_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get next appointment
$next_sql = "SELECT a.*, d.name as doctor_name, d.specialty, d.location 
             FROM appointments a
             JOIN users d ON a.doctor_id = d.id
             WHERE a.patient_id = ? AND a.status = 'approved' 
             AND a.appointment_date >= CURDATE()
             ORDER BY a.appointment_date ASC, a.appointment_time ASC
             LIMIT 1";
$next_stmt = $conn->prepare($next_sql);
$next_stmt->bind_param("i", $patient_id);
$next_stmt->execute();
$next_appointment = $next_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Past Appointments | Patient Dashboard | TeleMed Cameroon</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            text-align: center;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            color: #1a3a4a;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .stat-card.completed i { color: #2ecc71; }
        .stat-card.upcoming i { color: #3498db; }
        .stat-card.pending i { color: #f39c12; }
        .stat-card.cancelled i { color: #e74c3c; }

        .next-appointment-card {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .next-appointment-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .next-appointment-info p {
            opacity: 0.9;
            margin: 5px 0;
        }

        .join-btn {
            background: white;
            color: #2b7a8a;
            border: none;
            padding: 10px 25px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .join-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .search-btn, .reset-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .search-btn {
            background: #2b7a8a;
            color: white;
        }

        .reset-btn {
            background: #6c757d;
            color: white;
        }

        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 25px;
            overflow-x: auto;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab:hover {
            color: #2b7a8a;
        }

        .tab.active {
            color: #2b7a8a;
            border-bottom-color: #2b7a8a;
        }

        .appointments-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .appointment-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid;
        }

        .appointment-card.completed {
            border-left-color: #2ecc71;
        }

        .appointment-card.upcoming {
            border-left-color: #3498db;
        }

        .appointment-card.pending {
            border-left-color: #f39c12;
        }

        .appointment-card.cancelled {
            border-left-color: #e74c3c;
        }

        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .appointment-header {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .doctor-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a3a4a;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-upcoming {
            background: #cce5ff;
            color: #004085;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .appointment-body {
            padding: 20px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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

        .reason {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            color: #495057;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-join {
            background: #28a745;
            color: white;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-report {
            background: #6c757d;
            color: white;
        }

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

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            text-align: center;
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
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
            .tabs {
                overflow-x: auto;
            }
            .details-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn-icon {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
            .next-appointment-card {
                flex-direction: column;
                text-align: center;
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
                <li onclick="window.location.href='find-doctor.php'"><i class="fa-solid fa-stethoscope"></i><span>Find Doctor</span></li>
                <li onclick="window.location.href='book-appointment.php'"><i class="fa-solid fa-calendar-plus"></i><span>Book Appointment</span></li>
                <li onclick="window.location.href='consultation.php'"><i class="fa-solid fa-video"></i><span>Consultation</span></li>
                <li onclick="window.location.href='medical-reports.php'"><i class="fa-solid fa-notes-medical"></i><span>Medical Reports</span></li>
                <li class="active"><i class="fa-solid fa-calendar-days"></i><span>Past Appointments</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
                    <p>View and manage all your appointments</p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card completed" onclick="applyFilter('completed')">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['completed_count'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card upcoming" onclick="applyFilter('approved')">
                    <i class="fas fa-calendar-day"></i>
                    <h3><?php echo $stats['upcoming_count'] ?? 0; ?></h3>
                    <p>Upcoming</p>
                </div>
                <div class="stat-card pending" onclick="applyFilter('pending')">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card cancelled" onclick="applyFilter('cancelled')">
                    <i class="fas fa-times-circle"></i>
                    <h3><?php echo $stats['cancelled_count'] ?? 0; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>

            <?php if($next_appointment): ?>
            <div class="next-appointment-card">
                <div class="next-appointment-info">
                    <h3><i class="fas fa-calendar-check"></i> Next Appointment</h3>
                    <p><strong>Dr. <?php echo htmlspecialchars($next_appointment['doctor_name']); ?></strong> - <?php echo htmlspecialchars($next_appointment['specialty']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($next_appointment['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($next_appointment['location']); ?></p>
                </div>
                <button class="join-btn" onclick="window.location.href='consultation.php?id=<?php echo $next_appointment['id']; ?>'">
                    <i class="fas fa-video"></i> Join Consultation
                </button>
            </div>
            <?php endif; ?>

            <div class="filters-section">
                <form method="GET" action="" id="filterForm">
                    <div class="filters">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Date</label>
                            <input type="date" name="date" value="<?php echo $date_filter; ?>">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-user-md"></i> Doctor</label>
                            <select name="doctor">
                                <option value="0">All Doctors</option>
                                <?php while($doc = $doctors->fetch_assoc()): ?>
                                    <option value="<?php echo $doc['id']; ?>" <?php echo $doctor_filter == $doc['id'] ? 'selected' : ''; ?>>
                                        Dr. <?php echo htmlspecialchars($doc['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Search</label>
                            <input type="text" name="search" placeholder="Doctor or reason" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i> Apply</button>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="button" class="reset-btn" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="tabs">
                <button class="tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>" onclick="applyFilter('all')">All</button>
                <button class="tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>" onclick="applyFilter('approved')">Upcoming</button>
                <button class="tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" onclick="applyFilter('pending')">Pending</button>
                <button class="tab <?php echo $status_filter == 'completed' ? 'active' : ''; ?>" onclick="applyFilter('completed')">Completed</button>
                <button class="tab <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>" onclick="applyFilter('cancelled')">Cancelled</button>
            </div>

            <div class="appointments-container">
                <?php if ($appointments && $appointments->num_rows > 0): ?>
                    <?php while($apt = $appointments->fetch_assoc()): ?>
                        <div class="appointment-card <?php echo $apt['status']; ?>">
                            <div class="appointment-header">
                                <div>
                                    <div class="doctor-name">Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></div>
                                    <div class="specialty"><?php echo htmlspecialchars($apt['doctor_specialty']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $apt['status']; ?>">
                                    <i class="fas <?php 
                                        echo $apt['status'] == 'completed' ? 'fa-check-circle' : 
                                            ($apt['status'] == 'approved' ? 'fa-calendar-check' : 
                                            ($apt['status'] == 'pending' ? 'fa-clock' : 'fa-times-circle')); 
                                    ?>"></i>
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                            <div class="appointment-body">
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('F j, Y', strtotime($apt['appointment_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-comments"></i>
                                        <span><?php echo ucfirst($apt['consultation_type']); ?> Consultation</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($apt['doctor_location']); ?></span>
                                    </div>
                                </div>
                                
                                <?php if(!empty($apt['reason'])): ?>
                                <div class="reason">
                                    <i class="fas fa-notes-medical"></i> <strong>Reason:</strong> <?php echo htmlspecialchars($apt['reason']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <?php if($apt['status'] == 'approved'): ?>
                                        <button onclick="window.location.href='consultation.php?id=<?php echo $apt['id']; ?>'" class="btn-icon btn-join">
                                            <i class="fas fa-video"></i> Join Consultation
                                        </button>
                                        <button onclick="cancelAppointment(<?php echo $apt['id']; ?>)" class="btn-icon btn-cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if($apt['status'] == 'completed' && $apt['consultation_id']): ?>
                                        <button onclick="viewReport(<?php echo $apt['consultation_id']; ?>)" class="btn-icon btn-report">
                                            <i class="fas fa-file-medical"></i> View Medical Report
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button onclick="viewDetails(<?php echo $apt['id']; ?>)" class="btn-icon btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Found</h3>
                        <p>You have no appointments in this category.</p>
                        <a href="find-doctor.php" class="btn-icon btn-view" style="margin-top: 15px; background: #2b7a8a;">
                            <i class="fas fa-search"></i> Find a Doctor
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <i class="fas fa-exclamation-triangle" style="font-size: 60px; color: #dc3545; margin-bottom: 20px;"></i>
            <h3>Cancel Appointment</h3>
            <p>Are you sure you want to cancel this appointment? This action cannot be undone.</p>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <button class="btn-icon btn-cancel" onclick="confirmCancel()">Yes, Cancel</button>
                <button class="btn-icon btn-view" onclick="closeModal()" style="background: #6c757d;">No, Go Back</button>
            </div>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content" id="detailsContent"></div>
    </div>

    <script>
        let cancelAppointmentId = null;
        
        function applyFilter(status) {
            const url = new URL(window.location.href);
            url.searchParams.set('status', status);
            url.searchParams.delete('date');
            url.searchParams.delete('doctor');
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
        
        function resetFilters() {
            window.location.href = 'past-appointments.php';
        }
        
        function cancelAppointment(appointmentId) {
            cancelAppointmentId = appointmentId;
            document.getElementById('cancelModal').style.display = 'flex';
        }
        
        async function confirmCancel() {
            if (cancelAppointmentId) {
                const response = await fetch('../api/cancel-appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ appointment_id: cancelAppointmentId })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Appointment cancelled successfully');
                    location.reload();
                } else {
                    alert('Failed to cancel appointment');
                }
            }
            closeModal();
        }
        
        function closeModal() {
            document.getElementById('cancelModal').style.display = 'none';
            document.getElementById('detailsModal').style.display = 'none';
            cancelAppointmentId = null;
        }
        
        async function viewDetails(appointmentId) {
            const response = await fetch(`../api/get-appointment-details.php?id=${appointmentId}`);
            const apt = await response.json();
            
            const modalContent = document.getElementById('detailsContent');
            modalContent.innerHTML = `
                <div style="text-align: center; border-bottom: 1px solid #dee2e6; padding-bottom: 15px; margin-bottom: 20px;">
                    <h2><i class="fas fa-calendar-check"></i> Appointment Details</h2>
                </div>
                <div style="margin-bottom: 15px;">
                    <p><strong><i class="fas fa-user-md"></i> Doctor:</strong> Dr. ${escapeHtml(apt.doctor_name)}</p>
                    <p><strong><i class="fas fa-stethoscope"></i> Specialty:</strong> ${escapeHtml(apt.doctor_specialty)}</p>
                    <p><strong><i class="fas fa-calendar"></i> Date:</strong> ${new Date(apt.appointment_date).toLocaleDateString()}</p>
                    <p><strong><i class="fas fa-clock"></i> Time:</strong> ${apt.appointment_time}</p>
                    <p><strong><i class="fas fa-comments"></i> Type:</strong> ${apt.consultation_type}</p>
                    <p><strong><i class="fas fa-notes-medical"></i> Reason:</strong> ${escapeHtml(apt.reason)}</p>
                    <p><strong><i class="fas fa-flag-checkered"></i> Status:</strong> ${apt.status}</p>
                </div>
                <button onclick="closeModal()" class="btn-icon btn-view" style="background: #2b7a8a; width: 100%;">
                    Close
                </button>
            `;
            document.getElementById('detailsModal').style.display = 'flex';
        }
        
        async function viewReport(consultationId) {
            window.location.href = `medical-reports.php`;
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
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }
        
        document.querySelectorAll('.nav-links li').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) setTimeout(closeSidebar, 150);
            });
        });
        
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth > 768 && document.getElementById('sidebar').classList.contains('open')) closeSidebar();
            }, 250);
        });
        
        window.onclick = function(event) {
            const cancelModal = document.getElementById('cancelModal');
            const detailsModal = document.getElementById('detailsModal');
            if (event.target == cancelModal) closeModal();
            if (event.target == detailsModal) closeModal();
        }
    </script>
</body>
</html>