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

// Fetch pending doctors (doctors awaiting approval)
$pending_sql = "SELECT * FROM users WHERE role = 'doctor' AND (status = 'pending' OR status IS NULL) ORDER BY created_at DESC";
$pending_result = $conn->query($pending_sql);

// Fetch approved doctors for reference
$approved_sql = "SELECT * FROM users WHERE role = 'doctor' AND status = 'approved' ORDER BY created_at DESC LIMIT 5";
$approved_result = $conn->query($approved_sql);

// Get statistics
$total_pending = $pending_result ? $pending_result->num_rows : 0;
$total_approved = $approved_result ? $approved_result->num_rows : 0;

// Get total doctors count
$total_doctors_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
$total_doctors_result = $conn->query($total_doctors_sql);
$total_doctors = $total_doctors_result ? $total_doctors_result->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Approve Doctors | Admin Dashboard | TeleMed Cameroon</title>
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

        .stat-card.pending i { color: #f39c12; }
        .stat-card.approved i { color: #2ecc71; }
        .stat-card.total i { color: #3498db; }

        /* Doctor Cards */
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

        .doctor-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
        }

        .doctor-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .doctor-card.pending {
            border-left-color: #f39c12;
        }

        .doctor-card.approved {
            border-left-color: #2ecc71;
        }

        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .doctor-info h3 {
            color: #1a3a4a;
            font-size: 1.3rem;
            margin-bottom: 8px;
        }

        .doctor-info p {
            color: #6c757d;
            margin: 5px 0;
            font-size: 0.9rem;
        }

        .doctor-info i {
            width: 20px;
            margin-right: 8px;
            color: #2b7a8a;
        }

        .doctor-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #495057;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: #2b7a8a;
            width: 20px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 24px;
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

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
            transform: translateY(-2px);
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

            .doctor-details {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
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

            .page-header h1 {
                font-size: 1.5rem;
            }

            .doctor-info h3 {
                font-size: 1.1rem;
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
                <h2><i class="fas fa-shield-alt"></i> TeleMed</h2>
                <p>Admin Panel</p>
            </div>
            
            <ul class="nav-links">
                <li onclick="window.location.href='admin-dashboard.php'"> 
                   <i class="fa-solid fa-gauge"></i> 
                   <span>Dashboard</span>
                </li>
                <li onclick="window.location.href='approve-doctors.php'" class="active">
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

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-check"></i> Approve Doctors</h1>
                    <p>Review and manage doctor registration requests</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card pending" onclick="document.getElementById('pending-section').scrollIntoView({behavior: 'smooth'})">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $total_pending; ?></h3>
                    <p>Pending Approvals</p>
                </div>
                <div class="stat-card approved" onclick="document.getElementById('approved-section').scrollIntoView({behavior: 'smooth'})">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $total_approved; ?></h3>
                    <p>Approved Doctors</p>
                </div>
                <div class="stat-card total">
                    <i class="fas fa-user-md"></i>
                    <h3><?php echo $total_doctors; ?></h3>
                    <p>Total Doctors</p>
                </div>
            </div>

            <!-- Pending Doctors Section -->
            <div id="pending-section" class="section-title">
                <h2><i class="fas fa-hourglass-half"></i> Pending Approval</h2>
                <span class="badge badge-pending"><?php echo $total_pending; ?> waiting</span>
            </div>

            <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                <?php while($doctor = $pending_result->fetch_assoc()): ?>
                    <div class="doctor-card pending">
                        <div class="doctor-header">
                            <div class="doctor-info">
                                <h3><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <?php if(isset($doctor['phone']) && !empty($doctor['phone'])): ?>
                                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-pending">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        </div>
                        
                        <div class="doctor-details">
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span>Registered: <?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></span>
                            </div>
                            <?php if(isset($doctor['specialty']) && !empty($doctor['specialty'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-stethoscope"></i>
                                <span>Specialty: <?php echo htmlspecialchars($doctor['specialty']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(isset($doctor['location']) && !empty($doctor['location'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Location: <?php echo htmlspecialchars($doctor['location']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(isset($doctor['experience']) && !empty($doctor['experience'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-briefcase"></i>
                                <span>Experience: <?php echo htmlspecialchars($doctor['experience']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="process-doctor-approval.php?id=<?php echo $doctor['id']; ?>&action=approve" class="btn btn-approve" onclick="return confirmApprove('<?php echo addslashes($doctor['name']); ?>')">
                                <i class="fas fa-check"></i> Approve Doctor
                            </a>
                            <a href="process-doctor-approval.php?id=<?php echo $doctor['id']; ?>&action=reject" class="btn btn-reject" onclick="return confirmReject('<?php echo addslashes($doctor['name']); ?>')">
                                <i class="fas fa-times"></i> Reject Application
                            </a>
                            <a href="view-doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                    <h3>No Pending Approvals</h3>
                    <p>All doctor registrations have been processed.</p>
                    <small>New doctor registrations will appear here for review.</small>
                </div>
            <?php endif; ?>

            <!-- Approved Doctors Section -->
            <?php if ($approved_result && $approved_result->num_rows > 0): ?>
            <div id="approved-section" class="section-title" style="margin-top: 40px;">
                <h2><i class="fas fa-check-circle"></i> Recently Approved</h2>
                <a href="manage-users.php?role=doctor" style="color: #e74c3c; text-decoration: none;">View All →</a>
            </div>

            <?php while($doctor = $approved_result->fetch_assoc()): ?>
                <div class="doctor-card approved">
                    <div class="doctor-header">
                        <div class="doctor-info">
                            <h3><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                        </div>
                        <span class="badge badge-approved">
                            <i class="fas fa-check-circle"></i> Approved
                        </span>
                    </div>
                    
                    <div class="doctor-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span>Approved: <?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="view-doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-view">
                            <i class="fas fa-eye"></i> View Profile
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
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
        
        function confirmApprove(doctorName) {
            return confirm(`Approve Dr. ${doctorName}?\n\nThis will allow the doctor to:\n✓ Log in to their account\n✓ Accept patient appointments\n✓ Conduct video consultations\n\nDo you want to proceed?`);
        }
        
        function confirmReject(doctorName) {
            return confirm(`Reject Dr. ${doctorName}'s application?\n\nThis action will permanently remove the doctor's registration and cannot be undone.\n\nAre you sure?`);
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
        
        // Auto-dismiss alerts
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
    </script>
</body>
</html>