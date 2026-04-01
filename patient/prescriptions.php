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

// Fetch all prescriptions for this patient
$sql = "SELECT p.*, 
        d.name as doctor_name,
        d.specialty as doctor_specialty,
        c.consultation_type,
        c.end_time as consultation_date,
        a.appointment_date
        FROM prescriptions p
        JOIN consultations c ON p.consultation_id = c.id
        JOIN users d ON p.doctor_id = d.id
        JOIN appointments a ON c.appointment_id = a.id
        WHERE p.patient_id = ?";

if ($status_filter != 'all') {
    $sql .= " AND p.status = '$status_filter'";
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(p.created_at) = '$date_filter'";
}

if ($doctor_filter > 0) {
    $sql .= " AND p.doctor_id = $doctor_filter";
}

if (!empty($search_query)) {
    $sql .= " AND (p.medication_name LIKE '%$search_query%' OR d.name LIKE '%$search_query%')";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$prescriptions = $stmt->get_result();

// Fetch doctors list for filter
$doctors_sql = "SELECT DISTINCT d.id, d.name 
                FROM prescriptions p
                JOIN users d ON p.doctor_id = d.id
                WHERE p.patient_id = ?
                ORDER BY d.name";
$doctors_stmt = $conn->prepare($doctors_sql);
$doctors_stmt->bind_param("i", $patient_id);
$doctors_stmt->execute();
$doctors = $doctors_stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_prescriptions,
              COUNT(DISTINCT doctor_id) as total_doctors,
              COUNT(CASE WHEN status = 'active' THEN 1 END) as active_prescriptions,
              DATE_FORMAT(MAX(created_at), '%M %d, %Y') as last_prescription_date
              FROM prescriptions 
              WHERE patient_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Set default stats
if (!$stats) {
    $stats = [
        'total_prescriptions' => 0,
        'total_doctors' => 0,
        'active_prescriptions' => 0,
        'last_prescription_date' => 'No prescriptions'
    ];
}

// Get most common medications
$common_meds_sql = "SELECT medication_name, COUNT(*) as count 
                    FROM prescriptions 
                    WHERE patient_id = ? 
                    GROUP BY medication_name 
                    ORDER BY count DESC 
                    LIMIT 5";
$common_stmt = $conn->prepare($common_meds_sql);
$common_stmt->bind_param("i", $patient_id);
$common_stmt->execute();
$common_medications = $common_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Prescriptions | Patient Dashboard | TeleMed Cameroon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .stat-card.total i { color: #3498db; }
        .stat-card.doctors i { color: #2ecc71; }
        .stat-card.active i { color: #28a745; }
        .stat-card.recent i { color: #f39c12; }

        .charts-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .charts-section h3 {
            color: #1a3a4a;
            margin-bottom: 20px;
        }

        .chart-container {
            height: 300px;
            position: relative;
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

        .prescriptions-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .prescription-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid;
        }

        .prescription-card.active {
            border-left-color: #28a745;
        }

        .prescription-card.completed {
            border-left-color: #6c757d;
        }

        .prescription-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .prescription-header {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .medication-name {
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

        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-completed {
            background: #e2e3e5;
            color: #383d41;
        }

        .prescription-body {
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

        .instructions {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            color: #495057;
            font-size: 0.9rem;
            border-left: 3px solid #2b7a8a;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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

        .btn-download {
            background: #28a745;
            color: white;
        }

        .btn-print {
            background: #6c757d;
            color: white;
        }

        .btn-view {
            background: #17a2b8;
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
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reminder-card {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .reminder-card i {
            font-size: 2rem;
            color: #856404;
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
                <li onclick="window.location.href='past-appointments.php'"><i class="fa-solid fa-calendar-days"></i><span>Past Appointments</span></li>
                <li class="active"><i class="fa-solid fa-prescription-bottle"></i><span>Prescriptions</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-prescription-bottle"></i> My Prescriptions</h1>
                    <p>View and manage all your medication prescriptions</p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card total">
                    <i class="fas fa-file-prescription"></i>
                    <h3><?php echo $stats['total_prescriptions']; ?></h3>
                    <p>Total Prescriptions</p>
                </div>
                <div class="stat-card doctors">
                    <i class="fas fa-user-md"></i>
                    <h3><?php echo $stats['total_doctors']; ?></h3>
                    <p>Doctors</p>
                </div>
                <div class="stat-card active">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['active_prescriptions']; ?></h3>
                    <p>Active Prescriptions</p>
                </div>
                <div class="stat-card recent">
                    <i class="fas fa-calendar"></i>
                    <h3><?php echo $stats['last_prescription_date']; ?></h3>
                    <p>Last Prescription</p>
                </div>
            </div>

            <!-- Medication Reminder -->
            <?php if($stats['active_prescriptions'] > 0): ?>
            <div class="reminder-card">
                <i class="fas fa-bell"></i>
                <div>
                    <strong>Medication Reminder</strong><br>
                    You have <?php echo $stats['active_prescriptions']; ?> active prescription(s). Remember to take your medications as prescribed.
                </div>
            </div>
            <?php endif; ?>

            <!-- Most Common Medications Chart -->
            <?php if($common_medications && $common_medications->num_rows > 0): ?>
            <div class="charts-section">
                <h3><i class="fas fa-chart-pie"></i> Most Common Medications</h3>
                <div class="chart-container">
                    <canvas id="medicationsChart"></canvas>
                </div>
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
                            <input type="text" name="search" placeholder="Medication or doctor name" value="<?php echo htmlspecialchars($search_query); ?>">
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

            <div class="prescriptions-container">
                <?php if ($prescriptions && $prescriptions->num_rows > 0): ?>
                    <?php while($pres = $prescriptions->fetch_assoc()): ?>
                        <div class="prescription-card <?php echo $pres['status'] ?? 'active'; ?>">
                            <div class="prescription-header">
                                <div>
                                    <div class="medication-name"><?php echo htmlspecialchars($pres['medication_name']); ?></div>
                                    <div>Prescribed by: Dr. <?php echo htmlspecialchars($pres['doctor_name']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $pres['status'] ?? 'active'; ?>">
                                    <i class="fas <?php echo ($pres['status'] ?? 'active') == 'active' ? 'fa-check-circle' : 'fa-check-double'; ?>"></i>
                                    <?php echo ucfirst($pres['status'] ?? 'Active'); ?>
                                </span>
                            </div>
                            <div class="prescription-body">
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Date: <?php echo date('F j, Y', strtotime($pres['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Time: <?php echo date('g:i A', strtotime($pres['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-stethoscope"></i>
                                        <span>Specialty: <?php echo htmlspecialchars($pres['doctor_specialty']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-comments"></i>
                                        <span>Consultation: <?php echo ucfirst($pres['consultation_type']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-item" style="margin-bottom: 10px;">
                                    <i class="fas fa-flask"></i>
                                    <span><strong>Dosage:</strong> <?php echo htmlspecialchars($pres['dosage']); ?></span>
                                </div>
                                
                                <div class="detail-item" style="margin-bottom: 10px;">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span><strong>Duration:</strong> <?php echo htmlspecialchars($pres['duration']); ?></span>
                                </div>
                                
                                <?php if(!empty($pres['instructions'])): ?>
                                <div class="instructions">
                                    <i class="fas fa-info-circle"></i> <strong>Instructions:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($pres['instructions'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <button onclick="viewPrescription(<?php echo $pres['id']; ?>)" class="btn-icon btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <button onclick="downloadPrescription(<?php echo $pres['id']; ?>)" class="btn-icon btn-download">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                    <button onclick="printPrescription(<?php echo $pres['id']; ?>)" class="btn-icon btn-print">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription-bottle"></i>
                        <h3>No Prescriptions Found</h3>
                        <p>You don't have any prescriptions yet. When doctors prescribe medications, they will appear here.</p>
                        <a href="find-doctor.php" class="btn-icon btn-view" style="margin-top: 15px; background: #2b7a8a;">
                            <i class="fas fa-search"></i> Find a Doctor
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div id="prescriptionModal" class="modal">
        <div class="modal-content" id="prescriptionContent"></div>
    </div>

    <script>
        // Chart data for medications
        const medNames = [];
        const medCounts = [];
        
        <?php while($med = $common_medications->fetch_assoc()): ?>
            medNames.push('<?php echo addslashes($med['medication_name']); ?>');
            medCounts.push(<?php echo $med['count']; ?>);
        <?php endwhile; ?>
        
        if (medNames.length > 0) {
            const ctx = document.getElementById('medicationsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: medNames,
                    datasets: [{
                        label: 'Number of Prescriptions',
                        data: medCounts,
                        backgroundColor: '#2b7a8a',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Prescriptions' }
                        },
                        x: {
                            title: { display: true, text: 'Medication' }
                        }
                    }
                }
            });
        }
        
        async function viewPrescription(prescriptionId) {
            const response = await fetch(`../api/get-prescription.php?id=${prescriptionId}`);
            const pres = await response.json();
            
            const modalContent = document.getElementById('prescriptionContent');
            modalContent.innerHTML = `
                <div style="text-align: center; border-bottom: 2px solid #2b7a8a; padding-bottom: 15px; margin-bottom: 20px;">
                    <h2><i class="fas fa-prescription-bottle"></i> Prescription Details</h2>
                    <p>Generated on: ${new Date(pres.created_at).toLocaleString()}</p>
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-user-md"></i> Prescribed By</h3>
                    <p><strong>Doctor:</strong> Dr. ${escapeHtml(pres.doctor_name)}</p>
                    <p><strong>Specialty:</strong> ${escapeHtml(pres.doctor_specialty)}</p>
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-capsules"></i> Medication Details</h3>
                    <p><strong>Medication:</strong> ${escapeHtml(pres.medication_name)}</p>
                    <p><strong>Dosage:</strong> ${escapeHtml(pres.dosage)}</p>
                    <p><strong>Duration:</strong> ${escapeHtml(pres.duration)}</p>
                    ${pres.instructions ? `<p><strong>Instructions:</strong><br>${escapeHtml(pres.instructions)}</p>` : ''}
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-calendar"></i> Consultation Information</h3>
                    <p><strong>Date:</strong> ${new Date(pres.consultation_date).toLocaleDateString()}</p>
                    <p><strong>Type:</strong> ${pres.consultation_type}</p>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="downloadPrescriptionPDF(${prescriptionId})" class="btn-icon btn-download" style="margin-right: 10px;">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <button onclick="window.print()" class="btn-icon btn-print">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="closeModal()" class="btn-icon btn-view" style="background: #6c757d;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            document.getElementById('prescriptionModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('prescriptionModal').style.display = 'none';
        }
        
        function downloadPrescription(prescriptionId) {
            window.location.href = `../api/download-prescription.php?id=${prescriptionId}`;
        }
        
        function downloadPrescriptionPDF(prescriptionId) {
            window.location.href = `../api/download-prescription.php?id=${prescriptionId}&format=pdf`;
        }
        
        function printPrescription(prescriptionId) {
            viewPrescription(prescriptionId);
            setTimeout(() => window.print(), 500);
        }
        
        function resetFilters() {
            window.location.href = 'prescriptions.php';
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
            const modal = document.getElementById('prescriptionModal');
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>