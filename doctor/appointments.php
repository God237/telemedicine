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

// Build the query
$sql = "SELECT a.*, 
        p.name as patient_name,
        p.email as patient_email,
        p.phone as patient_phone
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        WHERE a.doctor_id = ?";

if ($status_filter != 'all') {
    $sql .= " AND a.status = '$status_filter'";
}

if (!empty($date_filter)) {
    $sql .= " AND a.appointment_date = '$date_filter'";
}

if (!empty($search_query)) {
    $sql .= " AND (p.name LIKE '%$search_query%' OR p.email LIKE '%$search_query%' OR p.phone LIKE '%$search_query%')";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
              COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
              COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
              COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
              FROM appointments 
              WHERE doctor_id = ?";
              
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get today's appointments count
$today = date('Y-m-d');
$today_sql = "SELECT COUNT(*) as count FROM appointments 
              WHERE doctor_id = ? AND appointment_date = ? AND status = 'approved'";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("is", $doctor_id, $today);
$today_stmt->execute();
$today_count = $today_stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>All Appointments | Doctor Dashboard | TeleMed Cameroon</title>
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

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: calc(100% - 280px);
        }

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

        .doctor-badge {
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

        .stat-card.pending i { color: #f39c12; }
        .stat-card.approved i { color: #2ecc71; }
        .stat-card.completed i { color: #3498db; }
        .stat-card.today i { color: #e74c3c; }

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

        .appointments-table-container {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .appointments-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1a3a4a;
            border-bottom: 2px solid #dee2e6;
        }

        .appointments-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-completed { background: #cce5ff; color: #004085; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

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

        .btn-view { background: #17a2b8; color: white; }
        .btn-start { background: #28a745; color: white; }
        .btn-cancel { background: #dc3545; color: white; }

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
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters {
                flex-direction: column;
            }
            .filter-group {
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
                <h2><i class="fas fa-stethoscope"></i> TeleMed</h2>
                <p>Cameroon</p>
            </div>
            <ul class="nav-links">
                <li onclick="window.location.href='doctor-dashboard.php'"> 
                   <i class="fa-solid fa-gauge"></i> 
                   <span>Dashboard</span>
                </li>
                <li onclick="window.location.href='appointments.php'" class="active">
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
                <li onclick="logout('../index.php')">
                  <i class="fa-solid fa-right-from-bracket"></i>
                  <span>Logout</span>
                </li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-calendar-alt"></i> All Appointments</h1>
                    <p>View and manage all your patient appointments</p>
                </div>
                <div class="doctor-badge">
                    <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor_name); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card pending" onclick="applyFilter('pending')">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card approved" onclick="applyFilter('approved')">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['approved_count'] ?? 0; ?></h3>
                    <p>Approved</p>
                </div>
                <div class="stat-card completed" onclick="applyFilter('completed')">
                    <i class="fas fa-check-double"></i>
                    <h3><?php echo $stats['completed_count'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card today" onclick="applyTodayFilter()">
                    <i class="fas fa-calendar-day"></i>
                    <h3><?php echo $today_count; ?></h3>
                    <p>Today's Appointments</p>
                </div>
            </div>

            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Date</label>
                            <input type="date" name="date" value="<?php echo $date_filter; ?>">
                        </div>
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Patient name or email" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i> Apply</button>
                        </div>
                        <div class="filter-group">
                            <button type="button" class="reset-btn" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="appointments-table-container">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($appointment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $appointment['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong></td>
                                    <td>
                                        <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['patient_email']); ?></small><br>
                                        <?php if(!empty($appointment['patient_phone'])): ?>
                                            <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                    <td><?php echo ucfirst($appointment['consultation_type']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn-icon btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if($appointment['status'] == 'approved'): ?>
                                                <a href="consultation.php?id=<?php echo $appointment['id']; ?>" class="btn-icon btn-start" onclick="return confirmStart('<?php echo addslashes($appointment['patient_name']); ?>')">
                                                    <i class="fas fa-video"></i> Start Consultation
                                                </a>
                                            <?php endif; ?>
                                            <?php if($appointment['status'] == 'pending'): ?>
                                                <a href="approve-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn-icon btn-start" onclick="return confirm('Approve this appointment?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <h3>No Appointments Found</h3>
                                        <p>Try adjusting your filters or check back later.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script>
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
        
        function applyFilter(status) {
            const url = new URL(window.location.href);
            url.searchParams.set('status', status);
            window.location.href = url.toString();
        }
        
        function applyTodayFilter() {
            const today = new Date().toISOString().split('T')[0];
            const url = new URL(window.location.href);
            url.searchParams.set('date', today);
            window.location.href = url.toString();
        }
        
        function resetFilters() {
            window.location.href = 'appointments.php';
        }
        
        function confirmStart(patientName) {
            return confirm(`Start consultation with ${patientName}?`);
        }
        
        document.querySelectorAll('.nav-links li').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });
        
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
                    }
                }
            }, 250);
        });
    </script>
</body>
</html>