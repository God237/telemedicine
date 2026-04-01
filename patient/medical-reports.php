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
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$doctor_filter = isset($_GET['doctor']) ? intval($_GET['doctor']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all medical reports for this patient
$sql = "SELECT c.*, 
        a.appointment_date,
        a.appointment_time,
        a.consultation_type,
        a.reason,
        d.name as doctor_name,
        d.specialty as doctor_specialty,
        d.location as doctor_location,
        cn.notes as consultation_notes,
        (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescription_count
        FROM consultations c
        JOIN appointments a ON c.appointment_id = a.id
        JOIN users d ON c.doctor_id = d.id
        LEFT JOIN consultation_notes cn ON c.id = cn.consultation_id
        WHERE c.patient_id = ? AND c.status = 'completed'";

if (!empty($date_filter)) {
    $sql .= " AND DATE(c.end_time) = '$date_filter'";
}

if ($doctor_filter > 0) {
    $sql .= " AND c.doctor_id = $doctor_filter";
}

if (!empty($search_query)) {
    $sql .= " AND (d.name LIKE '%$search_query%' OR d.specialty LIKE '%$search_query%' OR a.reason LIKE '%$search_query%')";
}

$sql .= " ORDER BY c.end_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$reports = $stmt->get_result();

// Fetch doctors list for filter
$doctors_sql = "SELECT DISTINCT d.id, d.name 
                FROM consultations c
                JOIN users d ON c.doctor_id = d.id
                WHERE c.patient_id = ? AND c.status = 'completed'
                ORDER BY d.name";
$doctors_stmt = $conn->prepare($doctors_sql);
$doctors_stmt->bind_param("i", $patient_id);
$doctors_stmt->execute();
$doctors = $doctors_stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_reports,
              COUNT(DISTINCT doctor_id) as total_doctors,
              DATE_FORMAT(MAX(end_time), '%M %d, %Y') as last_report_date
              FROM consultations 
              WHERE patient_id = ? AND status = 'completed'";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get monthly report data for chart
$monthly_sql = "SELECT 
                DATE_FORMAT(end_time, '%Y-%m') as month,
                DATE_FORMAT(end_time, '%M') as month_name,
                COUNT(*) as report_count
                FROM consultations 
                WHERE patient_id = ? AND status = 'completed' 
                AND end_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(end_time, '%Y-%m')
                ORDER BY month ASC";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->bind_param("i", $patient_id);
$monthly_stmt->execute();
$monthly_data = $monthly_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Medical Reports | Patient Dashboard | TeleMed Cameroon</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
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

        .reports-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .report-header {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .report-header h3 {
            font-size: 1.1rem;
        }

        .report-date {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .report-body {
            padding: 20px;
        }

        .doctor-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f6;
        }

        .doctor-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .doctor-info-item i {
            color: #2b7a8a;
            width: 20px;
        }

        .consultation-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .notes-section {
            margin-bottom: 15px;
        }

        .notes-section h4 {
            color: #1a3a4a;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .notes-content {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
        }

        .prescriptions-section h4 {
            color: #1a3a4a;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .prescription-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
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

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-download {
            background: #28a745;
            color: white;
        }

        .btn-print {
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
            max-width: 800px;
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
                grid-template-columns: 1fr;
            }
            .filters {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
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
            .page-header h1 {
                font-size: 1.5rem;
            }
            .report-header {
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
                <li class="active"><i class="fa-solid fa-notes-medical"></i><span>Medical Reports</span></li>
                <li onclick="window.location.href='past-appointments.php'"><i class="fa-solid fa-calendar-days"></i><span>Past Appointments</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-notes-medical"></i> Medical Reports</h1>
                    <p>View and download your complete medical history</p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card total">
                    <i class="fas fa-file-alt"></i>
                    <h3><?php echo $stats['total_reports'] ?? 0; ?></h3>
                    <p>Total Reports</p>
                </div>
                <div class="stat-card doctors">
                    <i class="fas fa-user-md"></i>
                    <h3><?php echo $stats['total_doctors'] ?? 0; ?></h3>
                    <p>Doctors Consulted</p>
                </div>
                <div class="stat-card recent">
                    <i class="fas fa-calendar"></i>
                    <h3><?php echo $stats['last_report_date'] ?? 'No reports'; ?></h3>
                    <p>Last Report</p>
                </div>
            </div>

            <?php if($monthly_data && $monthly_data->num_rows > 0): ?>
            <div class="charts-section">
                <h3><i class="fas fa-chart-line"></i> Consultation History (Last 6 Months)</h3>
                <div class="chart-container">
                    <canvas id="reportsChart"></canvas>
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
                            <input type="text" name="search" placeholder="Doctor, specialty or reason" value="<?php echo htmlspecialchars($search_query); ?>">
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

            <div class="reports-container">
                <?php if ($reports && $reports->num_rows > 0): ?>
                    <?php while($report = $reports->fetch_assoc()): 
                        // Fetch prescriptions for this consultation
                        $pres_sql = "SELECT * FROM prescriptions WHERE consultation_id = ?";
                        $pres_stmt = $conn->prepare($pres_sql);
                        $pres_stmt->bind_param("i", $report['id']);
                        $pres_stmt->execute();
                        $prescriptions = $pres_stmt->get_result();
                    ?>
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-file-medical"></i> Consultation Report</h3>
                            <span class="report-date"><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($report['end_time'])); ?></span>
                        </div>
                        <div class="report-body">
                            <div class="doctor-info">
                                <div class="doctor-info-item">
                                    <i class="fas fa-user-md"></i>
                                    <span><strong>Dr. <?php echo htmlspecialchars($report['doctor_name']); ?></strong></span>
                                </div>
                                <div class="doctor-info-item">
                                    <i class="fas fa-stethoscope"></i>
                                    <span><?php echo htmlspecialchars($report['doctor_specialty']); ?></span>
                                </div>
                                <div class="doctor-info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($report['doctor_location']); ?></span>
                                </div>
                            </div>

                            <div class="consultation-details">
                                <div class="doctor-info-item" style="margin-bottom: 5px;">
                                    <i class="fas fa-calendar"></i>
                                    <span><strong>Date:</strong> <?php echo date('F j, Y', strtotime($report['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($report['appointment_time'])); ?></span>
                                </div>
                                <div class="doctor-info-item" style="margin-bottom: 5px;">
                                    <i class="fas fa-comments"></i>
                                    <span><strong>Type:</strong> <?php echo ucfirst($report['consultation_type']); ?> Consultation</span>
                                </div>
                                <div class="doctor-info-item">
                                    <i class="fas fa-notes-medical"></i>
                                    <span><strong>Reason:</strong> <?php echo htmlspecialchars($report['reason']); ?></span>
                                </div>
                            </div>

                            <?php if($report['consultation_notes']): ?>
                            <div class="notes-section">
                                <h4><i class="fas fa-stethoscope"></i> Doctor's Notes & Diagnosis</h4>
                                <div class="notes-content">
                                    <?php echo nl2br(htmlspecialchars($report['consultation_notes'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if($prescriptions && $prescriptions->num_rows > 0): ?>
                            <div class="prescriptions-section">
                                <h4><i class="fas fa-prescription-bottle"></i> Prescriptions</h4>
                                <?php while($pres = $prescriptions->fetch_assoc()): ?>
                                <div class="prescription-item">
                                    <strong><?php echo htmlspecialchars($pres['medication_name']); ?></strong><br>
                                    <small>Dosage: <?php echo htmlspecialchars($pres['dosage']); ?></small><br>
                                    <small>Duration: <?php echo htmlspecialchars($pres['duration']); ?></small>
                                    <?php if(!empty($pres['instructions'])): ?>
                                    <br><small>Instructions: <?php echo htmlspecialchars($pres['instructions']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <button onclick="viewReport(<?php echo $report['id']; ?>)" class="btn-icon btn-view">
                                    <i class="fas fa-eye"></i> View Full Report
                                </button>
                                <button onclick="downloadReport(<?php echo $report['id']; ?>)" class="btn-icon btn-download">
                                    <i class="fas fa-download"></i> Download PDF
                                </button>
                                <button onclick="printReport(<?php echo $report['id']; ?>)" class="btn-icon btn-print">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No Medical Reports Found</h3>
                        <p>Complete consultations with doctors to generate medical reports.</p>
                        <a href="find-doctor.php" class="btn-icon btn-view" style="margin-top: 15px; background: #2b7a8a;">
                            <i class="fas fa-search"></i> Find a Doctor
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div id="reportModal" class="modal">
        <div class="modal-content" id="reportContent"></div>
    </div>

    <script>
        // Chart data
        const monthlyLabels = [];
        const monthlyCounts = [];
        
        <?php while($row = $monthly_data->fetch_assoc()): ?>
            monthlyLabels.push('<?php echo $row['month_name']; ?>');
            monthlyCounts.push(<?php echo $row['report_count']; ?>);
        <?php endwhile; ?>
        
        if (monthlyLabels.length > 0) {
            const ctx = document.getElementById('reportsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Consultations',
                        data: monthlyCounts,
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
                            title: { display: true, text: 'Number of Consultations' }
                        },
                        x: {
                            title: { display: true, text: 'Month' }
                        }
                    }
                }
            });
        }
        
        async function viewReport(reportId) {
            const response = await fetch(`../api/get-patient-report.php?id=${reportId}`);
            const report = await response.json();
            
            const modalContent = document.getElementById('reportContent');
            modalContent.innerHTML = `
                <div style="text-align: center; border-bottom: 2px solid #2b7a8a; padding-bottom: 15px; margin-bottom: 20px;">
                    <h2><i class="fas fa-file-medical"></i> Medical Consultation Report</h2>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-user-md"></i> Doctor Information</h3>
                    <p><strong>Name:</strong> Dr. ${escapeHtml(report.doctor_name)}</p>
                    <p><strong>Specialty:</strong> ${escapeHtml(report.doctor_specialty)}</p>
                    <p><strong>Location:</strong> ${escapeHtml(report.doctor_location)}</p>
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-calendar"></i> Consultation Details</h3>
                    <p><strong>Date:</strong> ${new Date(report.appointment_date).toLocaleDateString()} at ${report.appointment_time}</p>
                    <p><strong>Type:</strong> ${report.consultation_type}</p>
                    <p><strong>Reason:</strong> ${escapeHtml(report.reason)}</p>
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-stethoscope"></i> Doctor's Notes</h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">${escapeHtml(report.notes) || 'No notes recorded.'}</div>
                </div>
                <div style="margin-bottom: 20px;">
                    <h3><i class="fas fa-prescription-bottle"></i> Prescriptions</h3>
                    ${report.prescriptions && report.prescriptions.length > 0 ? 
                        report.prescriptions.map(p => `
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px;">
                                <strong>${escapeHtml(p.medication_name)}</strong><br>
                                Dosage: ${escapeHtml(p.dosage)} | Duration: ${escapeHtml(p.duration)}
                                ${p.instructions ? `<br>Instructions: ${escapeHtml(p.instructions)}` : ''}
                            </div>
                        `).join('') : 
                        '<p>No prescriptions issued.</p>'
                    }
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="downloadReportPDF(${reportId})" class="btn-icon btn-download" style="margin-right: 10px;">
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
            document.getElementById('reportModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        function downloadReport(reportId) {
            window.location.href = `../api/download-patient-report.php?id=${reportId}`;
        }
        
        function downloadReportPDF(reportId) {
            window.location.href = `../api/download-patient-report.php?id=${reportId}&format=pdf`;
        }
        
        function printReport(reportId) {
            viewReport(reportId);
            setTimeout(() => window.print(), 500);
        }
        
        function resetFilters() {
            window.location.href = 'medical-reports.php';
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
            const modal = document.getElementById('reportModal');
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>