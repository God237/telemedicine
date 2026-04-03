<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];

// Get date range for reports (default: last 30 days)
$end_date = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Get statistics for reports

// 1. Total users
$total_users_sql = "SELECT COUNT(*) as total FROM users";
$total_users_result = $conn->query($total_users_sql);
$total_users = $total_users_result ? $total_users_result->fetch_assoc()['total'] : 0;

// 2. Users by role
$patients_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'patient'";
$patients_result = $conn->query($patients_sql);
$total_patients = $patients_result ? $patients_result->fetch_assoc()['total'] : 0;

$doctors_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
$doctors_result = $conn->query($doctors_sql);
$total_doctors = $doctors_result ? $doctors_result->fetch_assoc()['total'] : 0;

$pending_doctors_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'pending'";
$pending_doctors_result = $conn->query($pending_doctors_sql);
$pending_doctors = $pending_doctors_result ? $pending_doctors_result->fetch_assoc()['total'] : 0;

// 3. Appointments statistics
$total_appointments_sql = "SELECT COUNT(*) as total FROM appointments";
$total_appointments_result = $conn->query($total_appointments_sql);
$total_appointments = $total_appointments_result ? $total_appointments_result->fetch_assoc()['total'] : 0;

$pending_appointments_sql = "SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'";
$pending_appointments_result = $conn->query($pending_appointments_sql);
$pending_appointments = $pending_appointments_result ? $pending_appointments_result->fetch_assoc()['total'] : 0;

$completed_appointments_sql = "SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'";
$completed_appointments_result = $conn->query($completed_appointments_sql);
$completed_appointments = $completed_appointments_result ? $completed_appointments_result->fetch_assoc()['total'] : 0;

$cancelled_appointments_sql = "SELECT COUNT(*) as total FROM appointments WHERE status = 'cancelled'";
$cancelled_appointments_result = $conn->query($cancelled_appointments_sql);
$cancelled_appointments = $cancelled_appointments_result ? $cancelled_appointments_result->fetch_assoc()['total'] : 0;

// 4. Monthly appointments data for chart
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $sql = "SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN '$month_start' AND '$month_end'";
    $result = $conn->query($sql);
    $count = $result ? $result->fetch_assoc()['total'] : 0;
    
    $monthly_data[] = [
        'month' => $month_name,
        'count' => $count
    ];
}

// 5. Consultation type distribution
$video_sql = "SELECT COUNT(*) as total FROM appointments WHERE consultation_type = 'video'";
$video_result = $conn->query($video_sql);
$video_consultations = $video_result ? $video_result->fetch_assoc()['total'] : 0;

$chat_sql = "SELECT COUNT(*) as total FROM appointments WHERE consultation_type = 'chat'";
$chat_result = $conn->query($chat_sql);
$chat_consultations = $chat_result ? $chat_result->fetch_assoc()['total'] : 0;

// 6. Recent appointments (last 10)
$recent_appointments_sql = "SELECT a.*, 
                            p.name as patient_name, 
                            d.name as doctor_name 
                            FROM appointments a
                            LEFT JOIN users p ON a.patient_id = p.id
                            LEFT JOIN users d ON a.doctor_id = d.id
                            ORDER BY a.created_at DESC LIMIT 10";
$recent_appointments = $conn->query($recent_appointments_sql);

// 7. Top doctors by appointments
$top_doctors_sql = "SELECT d.name, COUNT(a.id) as appointment_count 
                    FROM users d 
                    LEFT JOIN appointments a ON d.id = a.doctor_id 
                    WHERE d.role = 'doctor' 
                    GROUP BY d.id 
                    ORDER BY appointment_count DESC 
                    LIMIT 5";
$top_doctors = $conn->query($top_doctors_sql);

// 8. User registrations over time
$user_growth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $sql = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN '$month_start' AND '$month_end'";
    $result = $conn->query($sql);
    $count = $result ? $result->fetch_assoc()['total'] : 0;
    
    $user_growth[] = [
        'month' => $month_name,
        'count' => $count
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Reports | Admin Dashboard | TeleMed Cameroon</title>
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
            background: linear-gradient(135deg, #1a2a3a 0%, #0f1a24 100%);
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
            background: #e74c3c;
            color: white;
            box-shadow: 0 4px 12px rgba(231,76,60,0.3);
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
            background: #e74c3c;
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
            background: #c0392b;
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

        /* Header */
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

        .admin-badge {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        /* Stats Cards */
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

        .stat-card.users i { color: #3498db; }
        .stat-card.appointments i { color: #9b59b6; }
        .stat-card.doctors i { color: #2ecc71; }
        .stat-card.patients i { color: #e74c3c; }

        /* Report Filters */
        .report-filters {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filter-form {
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

        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .btn-filter {
            padding: 10px 25px;
            background: #2b7a8a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-filter:hover {
            background: #1f5c6e;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .chart-card h3 {
            color: #1a3a4a;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        canvas {
            max-height: 100%;
            width: 100%;
        }

        /* Tables */
        .data-table {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .data-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1a3a4a;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        /* Export Button */
        .export-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .btn-export {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover {
            background: #218838;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .chart-card {
                padding: 15px;
            }

            .chart-container {
                height: 250px;
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
    
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <section class="dashboard">

        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <h2><i class="fas fa-shield-alt"></i> TeleMed Connect</h2>
                <p>Admin Panel</p>
            </div>
            
            <ul class="nav-links">
                <li onclick="window.location.href='admin-dashboard.php'"> 
                   <i class="fa-solid fa-gauge"></i> 
                   <span>Dashboard</span>
                </li>
                <li onclick="window.location.href='approve-doctors.php'">
                   <i class="fa-solid fa-user-md"></i> 
                   <span>Approve Doctors</span>
                </li>
                <li onclick="window.location.href='manage-users.php'">
                    <i class="fa-solid fa-users"></i> 
                    <span>Manage Users</span>
                </li>
                <li onclick="window.location.href='view-reports.php'" class="active">
                   <i class="fa-solid fa-chart-line"></i> 
                   <span>View Reports</span>
                </li>
                <li onclick="window.location.href='remove-accounts.php'">
                    <i class="fa-solid fa-user-slash"></i> 
                    <span>Remove Accounts</span>
                </li>
                <li onclick="logout('../index.php')">
                  <i class="fa-solid fa-right-from-bracket"></i>
                  <span>Logout</span>
                </li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content" id="mainContent">

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-chart-line"></i> Analytics & Reports</h1>
                    <p>Comprehensive platform analytics and insights</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid">
                <div class="stat-card users">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card appointments">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card doctors">
                    <i class="fas fa-user-md"></i>
                    <h3><?php echo $total_doctors; ?></h3>
                    <p>Doctors</p>
                </div>
                <div class="stat-card patients">
                    <i class="fas fa-user"></i>
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Patients</p>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="report-filters">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-chart-pie"></i> Report Type</label>
                        <select name="report_type">
                            <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                            <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Activity</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-filter"><i class="fas fa-chart-line"></i> Generate Report</button>
                    </div>
                </form>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Monthly Appointments Chart -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-bar"></i> Monthly Appointments Trend</h3>
                    <div class="chart-container">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>

                <!-- Consultation Type Distribution -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-pie"></i> Consultation Types</h3>
                    <div class="chart-container">
                        <canvas id="consultationTypeChart"></canvas>
                    </div>
                </div>

                <!-- Appointment Status Distribution -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-donut"></i> Appointment Status</h3>
                    <div class="chart-container">
                        <canvas id="appointmentStatusChart"></canvas>
                    </div>
                </div>

                <!-- User Growth Chart -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-line"></i> User Registrations Trend</h3>
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Doctors Section -->
            <div class="data-table">
                <div style="padding: 20px; border-bottom: 1px solid #dee2e6;">
                    <h3><i class="fas fa-trophy"></i> Top Performing Doctors</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Doctor Name</th>
                            <th>Total Appointments</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        if ($top_doctors && $top_doctors->num_rows > 0): 
                            while($doctor = $top_doctors->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><strong>Dr. <?php echo htmlspecialchars($doctor['name']); ?></strong></td>
                            <td><?php echo $doctor['appointment_count']; ?> consultations</td>
                            <td><span class="badge badge-completed">Active</span></td>
                        </tr>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No data available</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Appointments Table -->
            <div class="data-table">
                <div style="padding: 20px; border-bottom: 1px solid #dee2e6;">
                    <h3><i class="fas fa-history"></i> Recent Appointments</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_appointments && $recent_appointments->num_rows > 0): ?>
                            <?php while($appointment = $recent_appointments->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $appointment['id']; ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo ucfirst($appointment['consultation_type']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No recent appointments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Export Section -->
            <div class="export-section">
                <button class="btn-export" onclick="exportReport()">
                    <i class="fas fa-download"></i> Export Report as CSV
                </button>
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
        
        // Chart Data
        const monthlyLabels = <?php echo json_encode(array_column($monthly_data, 'month')); ?>;
        const monthlyCounts = <?php echo json_encode(array_column($monthly_data, 'count')); ?>;
        const userGrowthLabels = <?php echo json_encode(array_column($user_growth, 'month')); ?>;
        const userGrowthCounts = <?php echo json_encode(array_column($user_growth, 'count')); ?>;
        
        // Monthly Appointments Chart
        const ctx1 = document.getElementById('appointmentsChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Appointments',
                    data: monthlyCounts,
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
                            text: 'Number of Appointments'
                        }
                    }
                }
            }
        });
        
        // Consultation Type Chart
        const ctx2 = document.getElementById('consultationTypeChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Video Consultation', 'Chat Consultation'],
                datasets: [{
                    data: [<?php echo $video_consultations; ?>, <?php echo $chat_consultations; ?>],
                    backgroundColor: ['#3498db', '#2ecc71'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // Appointment Status Chart
        const ctx3 = document.getElementById('appointmentStatusChart').getContext('2d');
        new Chart(ctx3, {
            type: 'pie',
            data: {
                labels: ['Pending', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [<?php echo $pending_appointments; ?>, <?php echo $completed_appointments; ?>, <?php echo $cancelled_appointments; ?>],
                    backgroundColor: ['#f39c12', '#2ecc71', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // User Growth Chart
        const ctx4 = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(ctx4, {
            type: 'bar',
            data: {
                labels: userGrowthLabels,
                datasets: [{
                    label: 'New Users',
                    data: userGrowthCounts,
                    backgroundColor: '#2b7a8a',
                    borderRadius: 8
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
                            text: 'Number of New Users'
                        }
                    }
                }
            }
        });
        
        // Export Report Function
        function exportReport() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            window.location.href = `export-report.php?start_date=${startDate}&end_date=${endDate}`;
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
    </script>
</body>
</html>