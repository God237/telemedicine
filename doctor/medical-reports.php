<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "doctor") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get doctor information
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch completed consultations with medical reports
$sql = "SELECT c.*, 
        a.id as appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.consultation_type,
        a.reason,
        p.name as patient_name,
        p.email as patient_email,
        p.phone as patient_phone,
        cn.notes as consultation_notes,
        (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescription_count
        FROM consultations c
        JOIN appointments a ON c.appointment_id = a.id
        JOIN users p ON c.patient_id = p.id
        LEFT JOIN consultation_notes cn ON c.id = cn.consultation_id
        WHERE c.doctor_id = ? AND c.status = 'completed'";

if (!empty($date_filter)) {
    $sql .= " AND DATE(c.end_time) = '$date_filter'";
}

if (!empty($search_query)) {
    $sql .= " AND (p.name LIKE '%$search_query%' OR p.email LIKE '%$search_query%')";
}

$sql .= " ORDER BY c.end_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$consultations = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_reports,
              COUNT(DISTINCT patient_id) as unique_patients,
              DATE_FORMAT(MAX(end_time), '%M %d, %Y') as last_report_date
              FROM consultations 
              WHERE doctor_id = ? AND status = 'completed'";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get monthly reports data for chart
$monthly_sql = "SELECT 
                DATE_FORMAT(end_time, '%Y-%m') as month,
                DATE_FORMAT(end_time, '%M') as month_name,
                COUNT(*) as report_count
                FROM consultations 
                WHERE doctor_id = ? AND status = 'completed' 
                AND end_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(end_time, '%Y-%m')
                ORDER BY month ASC";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->bind_param("i", $doctor_id);
$monthly_stmt->execute();
$monthly_data = $monthly_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Medical Reports | Doctor Dashboard | TeleMed Cameroon</title>
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
            background: linear-gradient(135deg, #0a2b3e 0%, #123152 100%);
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
            background: #3498db;
            color: white;
            box-shadow: 0 4px 12px rgba(52,152,219,0.3);
        }

        .nav-links li.active i {
            color: white;
        }

        /* Mobile Menu Toggle */
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

        .doctor-badge {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        /* Stats Cards */
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
        .stat-card.patients i { color: #2ecc71; }
        .stat-card.recent i { color: #f39c12; }

        /* Charts Section */
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
            font-size: 1.2rem;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Filters */
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
            font-size: 0.9rem;
        }

        .search-btn, .reset-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .search-btn {
            background: #2b7a8a;
            color: white;
        }

        .search-btn:hover {
            background: #1f5c6e;
        }

        .reset-btn {
            background: #6c757d;
            color: white;
        }

        .reset-btn:hover {
            background: #5a6268;
        }

        /* Reports Table */
        .reports-table-container {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .reports-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1a3a4a;
            border-bottom: 2px solid #dee2e6;
        }

        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
            vertical-align: middle;
        }

        .reports-table tr:hover {
            background: #f8f9fa;
        }

        /* Badge Styles */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-video {
            background: #cce5ff;
            color: #004085;
        }

        .badge-chat {
            background: #d4edda;
            color: #155724;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-download {
            background: #28a745;
            color: white;
        }

        .btn-download:hover {
            background: #218838;
        }

        .btn-print {
            background: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background: #5a6268;
        }

        /* Modal Styles */
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

        .report-header {
            border-bottom: 2px solid #2b7a8a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .report-section {
            margin-bottom: 20px;
        }

        .report-section h4 {
            color: #2b7a8a;
            margin-bottom: 10px;
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

        /* Responsive */
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

            .modal-content {
                padding: 20px;
                width: 95%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .stat-card h3 {
                font-size: 1.5rem;
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
                <h2><i class="fas fa-stethoscope"></i> TeleMed</h2>
                <p>Cameroon</p>
            </div>
            <ul class="nav-links">
                <li onclick="window.location.href='doctor-dashboard.php'"> 
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
                <li onclick="window.location.href='medical-reports.php'" class="active">
                   <i class="fa-solid fa-notes-medical"></i> 
                   <span>Medical Reports</span>
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
                    <h1><i class="fas fa-notes-medical"></i> Medical Reports</h1>
                    <p>View and manage all completed consultation reports</p>
                </div>
                <div class="doctor-badge">
                    <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor_name); ?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <i class="fas fa-file-alt"></i>
                    <h3><?php echo $stats['total_reports'] ?? 0; ?></h3>
                    <p>Total Reports Generated</p>
                </div>
                <div class="stat-card patients">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $stats['unique_patients'] ?? 0; ?></h3>
                    <p>Unique Patients</p>
                </div>
                <div class="stat-card recent">
                    <i class="fas fa-calendar"></i>
                    <h3><?php echo $stats['last_report_date'] ?? 'No reports'; ?></h3>
                    <p>Last Report</p>
                </div>
            </div>

            <!-- Monthly Reports Chart -->
            <?php if($monthly_data && $monthly_data->num_rows > 0): ?>
            <div class="charts-section">
                <h3><i class="fas fa-chart-line"></i> Reports Overview (Last 6 Months)</h3>
                <div class="chart-container">
                    <canvas id="reportsChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="" id="filterForm">
                    <div class="filters">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Date</label>
                            <input type="date" name="date" id="dateFilter" value="<?php echo $date_filter; ?>">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Search Patient</label>
                            <input type="text" name="search" placeholder="Patient name or email" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i> Apply Filters</button>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="button" class="reset-btn" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Reports Table -->
            <div class="reports-table-container">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Prescriptions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($consultations && $consultations->num_rows > 0): ?>
                            <?php while($report = $consultations->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $report['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['patient_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($report['patient_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($report['end_time'])); ?><br>
                                        <small><?php echo date('h:i A', strtotime($report['end_time'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $report['consultation_type'] == 'video' ? 'badge-video' : 'badge-chat'; ?>">
                                            <i class="fas <?php echo $report['consultation_type'] == 'video' ? 'fa-video' : 'fa-comments'; ?>"></i>
                                            <?php echo ucfirst($report['consultation_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-prescription-bottle"></i> <?php echo $report['prescription_count']; ?> prescription(s)
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewReport(<?php echo $report['id']; ?>)" class="btn-icon btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button onclick="downloadReport(<?php echo $report['id']; ?>)" class="btn-icon btn-download">
                                                <i class="fas fa-download"></i> Download
                                            </button>
                                            <button onclick="printReport(<?php echo $report['id']; ?>)" class="btn-icon btn-print">
                                                <i class="fas fa-print"></i> Print
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <h3>No Medical Reports Found</h3>
                                        <p>Complete consultations to generate medical reports.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Report View Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content" id="reportContent">
            <!-- Report content will be loaded here -->
        </div>
    </div>

    <script>
        // Chart data
        const monthlyLabels = [];
        const monthlyCounts = [];
        
        <?php while($row = $monthly_data->fetch_assoc()): ?>
            monthlyLabels.push('<?php echo $row['month_name']; ?>');
            monthlyCounts.push(<?php echo $row['report_count']; ?>);
        <?php endwhile; ?>
        
        // Create Reports Chart
        if (monthlyLabels.length > 0) {
            const ctx = document.getElementById('reportsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Reports Generated',
                        data: monthlyCounts,
                        backgroundColor: '#2b7a8a',
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Reports'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
        }
        
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
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
        
        function resetFilters() {
            window.location.href = 'medical-reports.php';
        }
        
        // View Report
        async function viewReport(consultationId) {
            const response = await fetch(`../api/get-report.php?id=${consultationId}`);
            const report = await response.json();
            
            const modalContent = document.getElementById('reportContent');
            modalContent.innerHTML = `
                <div class="report-header">
                    <h2><i class="fas fa-file-medical"></i> Medical Report</h2>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
                
                <div class="report-section">
                    <h4><i class="fas fa-user"></i> Patient Information</h4>
                    <p><strong>Name:</strong> ${escapeHtml(report.patient_name)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(report.patient_email)}</p>
                    <p><strong>Consultation Date:</strong> ${new Date(report.consultation_date).toLocaleDateString()}</p>
                </div>
                
                <div class="report-section">
                    <h4><i class="fas fa-stethoscope"></i> Consultation Details</h4>
                    <p><strong>Type:</strong> ${report.consultation_type}</p>
                    <p><strong>Reason:</strong> ${escapeHtml(report.reason)}</p>
                    <p><strong>Doctor:</strong> Dr. ${escapeHtml(report.doctor_name)}</p>
                </div>
                
                <div class="report-section">
                    <h4><i class="fas fa-notes-medical"></i> Doctor's Notes</h4>
                    <p>${escapeHtml(report.notes) || 'No notes recorded.'}</p>
                </div>
                
                <div class="report-section">
                    <h4><i class="fas fa-prescription-bottle"></i> Prescriptions</h4>
                    ${report.prescriptions.length > 0 ? `
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 8px; text-align: left;">Medication</th>
                                    <th style="padding: 8px; text-align: left;">Dosage</th>
                                    <th style="padding: 8px; text-align: left;">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${report.prescriptions.map(pres => `
                                    <tr>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">${escapeHtml(pres.medication_name)}</td>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">${escapeHtml(pres.dosage)}</td>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">${escapeHtml(pres.duration)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>No prescriptions issued.</p>'}
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button class="btn-icon btn-download" onclick="downloadReportPDF(${consultationId})" style="margin-right: 10px;">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <button class="btn-icon btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn-icon btn-view" onclick="closeModal()" style="background: #6c757d;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            
            document.getElementById('reportModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        // Download Report
        async function downloadReport(consultationId) {
            window.location.href = `../api/download-report.php?id=${consultationId}`;
        }
        
        function downloadReportPDF(consultationId) {
            window.location.href = `../api/download-report.php?id=${consultationId}&format=pdf`;
        }
        
        function printReport(consultationId) {
            viewReport(consultationId);
            setTimeout(() => {
                window.print();
            }, 500);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
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
                    const overlay = document.getElementById('sidebarOverlay');
                    if (sidebar.classList.contains('open')) {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                        document.getElementById('menuToggle').innerHTML = '<i class="fas fa-bars"></i>';
                    }
                }
            }, 250);
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>