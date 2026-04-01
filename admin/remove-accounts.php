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

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query based on filters
$sql = "SELECT * FROM users WHERE 1=1";

if ($role_filter != 'all') {
    $sql .= " AND role = '$role_filter'";
}

if ($status_filter != 'all') {
    $sql .= " AND status = '$status_filter'";
}

if (!empty($search_query)) {
    $sql .= " AND (name LIKE '%$search_query%' OR email LIKE '%$search_query%' OR phone LIKE '%$search_query%')";
}

$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);

// Get statistics
$total_patients_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'patient'";
$total_patients_result = $conn->query($total_patients_sql);
$total_patients = $total_patients_result ? $total_patients_result->fetch_assoc()['total'] : 0;

$total_doctors_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
$total_doctors_result = $conn->query($total_doctors_sql);
$total_doctors = $total_doctors_result ? $total_doctors_result->fetch_assoc()['total'] : 0;

$pending_doctors_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'pending'";
$pending_doctors_result = $conn->query($pending_doctors_sql);
$pending_doctors = $pending_doctors_result ? $pending_doctors_result->fetch_assoc()['total'] : 0;

$inactive_users_sql = "SELECT COUNT(*) as total FROM users WHERE status = 'inactive' OR status = 'suspended'";
$inactive_users_result = $conn->query($inactive_users_sql);
$inactive_users = $inactive_users_result ? $inactive_users_result->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Remove Accounts | Admin Dashboard | TeleMed Cameroon</title>
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

        .stat-card.patients i { color: #3498db; }
        .stat-card.doctors i { color: #2ecc71; }
        .stat-card.pending i { color: #f39c12; }
        .stat-card.inactive i { color: #e74c3c; }

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

        /* Users Table */
        .users-table-container {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1a3a4a;
            border-bottom: 2px solid #dee2e6;
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
            vertical-align: middle;
        }

        .users-table tr:hover {
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

        .badge-patient {
            background: #cce5ff;
            color: #004085;
        }

        .badge-doctor {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
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

        .btn-suspend {
            background: #f39c12;
            color: white;
        }

        .btn-suspend:hover {
            background: #e67e22;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
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
            max-width: 450px;
            width: 90%;
            text-align: center;
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

        .modal-content i {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .modal-content h3 {
            margin-bottom: 15px;
            color: #1a3a4a;
        }

        .modal-content p {
            margin-bottom: 25px;
            color: #6c757d;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .modal-btn.confirm {
            background: #dc3545;
            color: white;
        }

        .modal-btn.cancel {
            background: #6c757d;
            color: white;
        }

        /* Warning Banner */
        .warning-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-banner i {
            font-size: 1.2rem;
            color: #856404;
        }

        .warning-banner p {
            color: #856404;
            font-size: 0.9rem;
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
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .warning-banner {
                flex-direction: column;
                text-align: center;
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
                <li onclick="window.location.href='remove-accounts.php'" class="active">
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
                    <h1><i class="fas fa-user-slash"></i> Remove Accounts</h1>
                    <p>Permanently delete or suspend user accounts</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
            </div>

            <!-- Warning Banner -->
            <div class="warning-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Warning:</strong> Removing accounts is permanent and cannot be undone. All associated data including appointments, messages, and medical records will be deleted. Please proceed with caution.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card patients" onclick="applyFilter('patient', 'all')">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Total Patients</p>
                </div>
                <div class="stat-card doctors" onclick="applyFilter('doctor', 'all')">
                    <i class="fas fa-user-md"></i>
                    <h3><?php echo $total_doctors; ?></h3>
                    <p>Total Doctors</p>
                </div>
                <div class="stat-card pending" onclick="applyFilter('doctor', 'pending')">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $pending_doctors; ?></h3>
                    <p>Pending Doctors</p>
                </div>
                <div class="stat-card inactive" onclick="applyFilter('all', 'inactive')">
                    <i class="fas fa-ban"></i>
                    <h3><?php echo $inactive_users; ?></h3>
                    <p>Inactive Accounts</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="" id="filterForm">
                    <div class="filters">
                        <div class="filter-group">
                            <label><i class="fas fa-user-tag"></i> User Role</label>
                            <select name="role" id="roleFilter">
                                <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="patient" <?php echo $role_filter == 'patient' ? 'selected' : ''; ?>>Patients</option>
                                <option value="doctor" <?php echo $role_filter == 'doctor' ? 'selected' : ''; ?>>Doctors</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-flag-checkered"></i> Status</label>
                            <select name="status" id="statusFilter">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Search</label>
                            <input type="text" name="search" placeholder="Name, Email or Phone" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="button" class="reset-btn" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $counter = 1; ?>
                            <?php while($user = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                        <?php if($user['role'] == 'doctor'): ?>
                                            <br><small style="color: #2b7a8a;">Dr. <?php echo htmlspecialchars($user['name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo isset($user['phone']) && !empty($user['phone']) ? htmlspecialchars($user['phone']) : '—'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'doctor' ? 'badge-doctor' : 'badge-patient'; ?>">
                                            <i class="fas <?php echo $user['role'] == 'doctor' ? 'fa-user-md' : 'fa-user'; ?>"></i>
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if(isset($user['status']) && $user['status'] == 'pending'): ?>
                                            <span class="badge badge-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif(isset($user['status']) && ($user['status'] == 'inactive' || $user['status'] == 'suspended')): ?>
                                            <span class="badge badge-inactive">
                                                <i class="fas fa-ban"></i> Inactive
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-approved">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn-icon btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if(isset($user['status']) && $user['status'] != 'inactive'): ?>
                                                <button onclick="showSuspendModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo $user['role']; ?>')" class="btn-icon btn-suspend">
                                                    <i class="fas fa-pause-circle"></i> Suspend
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="showDeleteModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo $user['role']; ?>')" class="btn-icon btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <h3>No Users Found</h3>
                                        <p>Try adjusting your filters or search criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (Optional) -->
            <div class="pagination" style="display: flex; justify-content: center; gap: 10px; margin-top: 30px;">
                <button class="page-btn active">1</button>
                <button class="page-btn">2</button>
                <button class="page-btn">3</button>
                <button class="page-btn">Next →</button>
            </div>

        </div>

    </section>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
            <h3>⚠️ Permanent Deletion</h3>
            <p id="deleteMessage">Are you sure you want to delete this user? This action cannot be undone and will permanently remove all associated data including appointments, messages, and medical records.</p>
            <div class="modal-buttons">
                <button class="modal-btn confirm" onclick="confirmDelete()">Yes, Delete Permanently</button>
                <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Suspend Confirmation Modal -->
    <div id="suspendModal" class="modal">
        <div class="modal-content">
            <i class="fas fa-pause-circle" style="color: #f39c12;"></i>
            <h3>Suspend Account</h3>
            <p id="suspendMessage">Are you sure you want to suspend this user? They will not be able to access their account until reactivated.</p>
            <div class="modal-buttons">
                <button class="modal-btn confirm" style="background: #f39c12;" onclick="confirmSuspend()">Yes, Suspend Account</button>
                <button class="modal-btn cancel" onclick="closeSuspendModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let deleteUserId = null;
        let suspendUserId = null;
        let userName = '';
        
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
        
        // Filter functions
        function applyFilter(role, status) {
            const url = new URL(window.location.href);
            if (role) url.searchParams.set('role', role);
            if (status) url.searchParams.set('status', status);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
        
        function resetFilters() {
            window.location.href = 'remove-accounts.php';
        }
        
        // Delete modal functions
        function showDeleteModal(userId, name, role) {
            deleteUserId = userId;
            userName = name;
            const message = `Are you sure you want to permanently delete ${role === 'doctor' ? 'Dr. ' : ''}${name}?<br><br>
            <strong>This will permanently remove:</strong><br>
            • All account information<br>
            • All appointment history<br>
            • All consultation messages<br>
            • All medical records<br>
            • All associated data<br><br>
            <strong style="color: #dc3545;">This action cannot be undone!</strong>`;
            document.getElementById('deleteMessage').innerHTML = message;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteUserId = null;
        }
        
        function confirmDelete() {
            if (deleteUserId) {
                window.location.href = `delete-user.php?id=${deleteUserId}&action=permanent`;
            }
        }
        
        // Suspend modal functions
        function showSuspendModal(userId, name, role) {
            suspendUserId = userId;
            userName = name;
            const message = `Are you sure you want to suspend ${role === 'doctor' ? 'Dr. ' : ''}${name}?<br><br>
            The user will not be able to:<br>
            • Log in to their account<br>
            • Book or attend appointments<br>
            • Access medical records<br><br>
            They can be reactivated later.`;
            document.getElementById('suspendMessage').innerHTML = message;
            document.getElementById('suspendModal').style.display = 'flex';
        }
        
        function closeSuspendModal() {
            document.getElementById('suspendModal').style.display = 'none';
            suspendUserId = null;
        }
        
        function confirmSuspend() {
            if (suspendUserId) {
                window.location.href = `suspend-user.php?id=${suspendUserId}`;
            }
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
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const suspendModal = document.getElementById('suspendModal');
            if (event.target == deleteModal) {
                closeModal();
            }
            if (event.target == suspendModal) {
                closeSuspendModal();
            }
        }
    </script>
</body>
</html>