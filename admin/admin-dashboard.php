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

// Get statistics for dashboard
// Total users count (patients)
$users_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'patient'";
$users_result = $conn->query($users_sql);
$total_patients = $users_result ? $users_result->fetch_assoc()['total'] : 0;

// Total doctors count
$doctors_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
$doctors_result = $conn->query($doctors_sql);
$total_doctors = $doctors_result ? $doctors_result->fetch_assoc()['total'] : 0;

// Check if status column exists, if not, set pending_doctors to 0
$pending_doctors = 0;
$status_column_exists = false;

// Check if status column exists in users table
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($check_column && $check_column->num_rows > 0) {
    $status_column_exists = true;
    $pending_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'pending'";
    $pending_result = $conn->query($pending_sql);
    $pending_doctors = $pending_result ? $pending_result->fetch_assoc()['total'] : 0;
}

// Total appointments
$appointments_sql = "SELECT COUNT(*) as total FROM appointments";
$appointments_result = $conn->query($appointments_sql);
$total_appointments = $appointments_result ? $appointments_result->fetch_assoc()['total'] : 0;

// Completed consultations
$completed_sql = "SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'";
$completed_result = $conn->query($completed_sql);
$completed_consultations = $completed_result ? $completed_result->fetch_assoc()['total'] : 0;

// Recent activities (last 5 registrations)
$recent_users_sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($recent_users_sql);

// Recent appointments
$recent_appointments_sql = "SELECT a.*, 
                            p.name as patient_name, 
                            d.name as doctor_name 
                            FROM appointments a
                            LEFT JOIN users p ON a.patient_id = p.id
                            LEFT JOIN users d ON a.doctor_id = d.id
                            ORDER BY a.created_at DESC LIMIT 5";
$recent_appointments = $conn->query($recent_appointments_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Dashboard | Telemed Connect Cameroon</title>
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
            background: linear-gradient(135deg, #1a2a3a 0%, #0f1a24 100%);
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
            background: #e74c3c;
            color: white;
            box-shadow: 0 4px 12px rgba(231,76,60,0.3);
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

        .admin-badge {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
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

        .stat-card i {
            font-size: 2.5rem;
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

        .stat-card.patients i { color: #3498db; }
        .stat-card.doctors i { color: #2ecc71; }
        .stat-card.pending i { color: #f39c12; }
        .stat-card.appointments i { color: #9b59b6; }
        .stat-card.completed i { color: #1abc9c; }

        /* Section Styles */
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
            color: #e74c3c;
            text-decoration: none;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Tables - Responsive */
        .data-table {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            overflow-y: hidden;
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

        /* Badge Styles */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-patient {
            background: #cce5ff;
            color: #004085;
        }

        .badge-doctor {
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
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
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

            .data-table {
                font-size: 0.85rem;
            }

            .data-table th,
            .data-table td {
                padding: 10px 12px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .btn-icon {
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

            .admin-badge {
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

            .badge {
                font-size: 0.7rem;
                padding: 3px 8px;
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
                <h2><i class="fas fa-shield-alt"></i> TeleMed Connect</h2>
                <p>Admin Panel</p>
            </div>
            
            <ul class="nav-links">
                <li onclick="window.location.href='admin-dashboard.php'" class="active"> 
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
                <li onclick="window.location.href='view-reports.php'">
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
                    <h1>Welcome, <?php echo htmlspecialchars($admin_name); ?></h1>
                    <p>TeleMed Connect Administration</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i> Administrator
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card patients" onclick="window.location.href='manage-users.php?role=patient'">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Total Patients</p>
                </div>
                <div class="stat-card doctors" onclick="window.location.href='manage-users.php?role=doctor'">
                    <i class="fas fa-user-md"></i>
                    <h3><?php echo $total_doctors; ?></h3>
                    <p>Total Doctors</p>
                </div>
                <div class="stat-card pending" onclick="window.location.href='approve-doctors.php'">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $pending_doctors; ?></h3>
                    <p>Pending Approvals</p>
                </div>
                <div class="stat-card appointments" onclick="window.location.href='view-reports.php'">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card completed" onclick="window.location.href='view-reports.php'">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $completed_consultations; ?></h3>
                    <p>Completed Consultations</p>
                </div>
            </div>

            <!-- Recent User Registrations -->
            <div class="section-title">
                <h2><i class="fas fa-user-plus"></i> Recent Registrations</h2>
                <a href="manage-users.php" class="view-all">View All →</a>
            </div>

            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'doctor' ? 'badge-doctor' : 'badge-patient'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn-icon btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="delete-user.php?id=<?php echo $user['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this user?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No recent registrations</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Appointments -->
            <div class="section-title">
                <h2><i class="fas fa-calendar-alt"></i> Recent Appointments</h2>
                <a href="view-reports.php" class="view-all">View All →</a>
            </div>

            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_appointments && $recent_appointments->num_rows > 0): ?>
                            <?php while($appointment = $recent_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                    <td><?php echo ucfirst($appointment['consultation_type']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn-icon btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No appointments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>