<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get doctor ID from URL
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$doctor_id) {
    header("Location: approve-doctors.php");
    exit();
}

// Fetch doctor details
$sql = "SELECT * FROM users WHERE id = ? AND role = 'doctor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    header("Location: approve-doctors.php");
    exit();
}

// Fetch doctor's appointment statistics
$stats_sql = "SELECT 
              COUNT(*) as total_appointments,
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
              COUNT(CASE WHEN status = 'approved' AND appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments,
              COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_appointments
              FROM appointments 
              WHERE doctor_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Fetch recent appointments by this doctor
$recent_sql = "SELECT a.*, p.name as patient_name 
               FROM appointments a
               JOIN users p ON a.patient_id = p.id
               WHERE a.doctor_id = ?
               ORDER BY a.created_at DESC LIMIT 5";
$recent_stmt = $conn->prepare($recent_sql);
$recent_stmt->bind_param("i", $doctor_id);
$recent_stmt->execute();
$recent_appointments = $recent_stmt->get_result();

// Fetch doctor's ratings/reviews (if you have a reviews table)
$avg_rating = 4.5; // Default, you can calculate from reviews table
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Doctor | Admin Dashboard | TeleMed Cameroon</title>
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
            background: #e74c3c;
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
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s;
        }

        /* Header */
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

        .admin-badge {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        /* Doctor Profile Container */
        .profile-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        /* Doctor Info Sidebar */
        .profile-sidebar {
            flex: 1;
            min-width: 300px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 30px;
        }

        .profile-header {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .doctor-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 3rem;
            color: #2b7a8a;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .status-approved {
            background: #28a745;
            color: white;
        }

        .status-pending {
            background: #ffc107;
            color: #212529;
        }

        .profile-stats {
            padding: 20px;
            border-bottom: 1px solid #eef2f6;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f6;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #6c757d;
        }

        .stat-value {
            font-weight: 600;
            color: #1a3a4a;
        }

        .profile-info {
            padding: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1rem;
            color: #1a3a4a;
            word-break: break-word;
        }

        /* Main Content Area */
        .profile-main {
            flex: 2;
            min-width: 400px;
        }

        /* Action Cards */
        .action-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .action-card h3 {
            color: #1a3a4a;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eef2f6;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        /* Bio Section */
        .bio-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            line-height: 1.6;
            color: #495057;
        }

        /* Recent Appointments Table */
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointments-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #1a3a4a;
            border-bottom: 2px solid #dee2e6;
        }

        .appointments-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-upcoming {
            background: #cce5ff;
            color: #004085;
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
                padding: 20px 15px;
                padding-top: 80px;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-container {
                flex-direction: column;
            }

            .profile-sidebar {
                position: static;
            }

            .profile-main {
                min-width: auto;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .appointments-table {
                font-size: 0.85rem;
            }

            .appointments-table th,
            .appointments-table td {
                padding: 8px;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .doctor-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
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
                <h2><i class="fas fa-shield-alt"></i> TeleMed</h2>
                <p>Admin Panel</p>
            </div>
            <ul class="nav-links">
                <li onclick="window.location.href='admin-dashboard.php'"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></li>
                <li onclick="window.location.href='approve-doctors.php'" class="active"><i class="fa-solid fa-user-md"></i><span>Approve Doctors</span></li>
                <li onclick="window.location.href='manage-users.php'"><i class="fa-solid fa-users"></i><span>Manage Users</span></li>
                <li onclick="window.location.href='view-reports.php'"><i class="fa-solid fa-chart-line"></i><span>View Reports</span></li>
                <li onclick="window.location.href='remove-accounts.php'"><i class="fa-solid fa-user-slash"></i><span>Remove Accounts</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-md"></i> Doctor Profile</h1>
                    <p>Review doctor details before making a decision</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                </div>
            </div>

            <div class="profile-container">
                <!-- Left Sidebar - Doctor Info -->
                <div class="profile-sidebar">
                    <div class="profile-header">
                        <div class="doctor-avatar">
                            <?php echo strtoupper(substr($doctor['name'], 0, 1)); ?>
                        </div>
                        <h2>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h2>
                        <p><?php echo htmlspecialchars($doctor['specialty'] ?? 'General Practitioner'); ?></p>
                        <span class="status-badge <?php echo (isset($doctor['status']) && $doctor['status'] == 'approved') ? 'status-approved' : 'status-pending'; ?>">
                            <i class="fas <?php echo (isset($doctor['status']) && $doctor['status'] == 'approved') ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                            <?php echo (isset($doctor['status']) && $doctor['status'] == 'approved') ? 'Approved' : 'Pending Approval'; ?>
                        </span>
                    </div>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-calendar-check"></i> Total Appointments</span>
                            <span class="stat-value"><?php echo $stats['total_appointments'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-check-circle"></i> Completed Consultations</span>
                            <span class="stat-value"><?php echo $stats['completed_appointments'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-calendar-day"></i> Upcoming Appointments</span>
                            <span class="stat-value"><?php echo $stats['upcoming_appointments'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-star"></i> Average Rating</span>
                            <span class="stat-value">⭐ <?php echo $avg_rating; ?> / 5.0</span>
                        </div>
                    </div>

                    <div class="profile-info">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($doctor['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                            <div class="info-value"><?php echo !empty($doctor['phone']) ? htmlspecialchars($doctor['phone']) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> Location</div>
                            <div class="info-value"><?php echo !empty($doctor['location']) ? htmlspecialchars($doctor['location']) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-briefcase"></i> Experience</div>
                            <div class="info-value"><?php echo !empty($doctor['experience']) ? htmlspecialchars($doctor['experience']) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-calendar"></i> Registered On</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></div>
                        </div>
                        <?php if(!empty($doctor['consultation_fee'])): ?>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-money-bill-wave"></i> Consultation Fee</div>
                            <div class="info-value"><?php echo number_format($doctor['consultation_fee'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Main Content -->
                <div class="profile-main">
                    <!-- Action Buttons -->
                    <div class="action-card">
                        <h3><i class="fas fa-gavel"></i> Review Actions</h3>
                        <div class="action-buttons">
                            <?php if(!isset($doctor['status']) || $doctor['status'] != 'approved'): ?>
                                <a href="process-doctor-approval.php?id=<?php echo $doctor['id']; ?>&action=approve" class="btn btn-approve" onclick="return confirmApprove()">
                                    <i class="fas fa-check-circle"></i> Approve Doctor
                                </a>
                                <a href="process-doctor-approval.php?id=<?php echo $doctor['id']; ?>&action=reject" class="btn btn-reject" onclick="return confirmReject()">
                                    <i class="fas fa-times-circle"></i> Reject Application
                                </a>
                            <?php else: ?>
                                <a href="remove-doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-reject" onclick="return confirmRemove()">
                                    <i class="fas fa-trash"></i> Remove Doctor
                                </a>
                            <?php endif; ?>
                            <a href="approve-doctors.php" class="btn btn-back">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>

                    <!-- Professional Bio -->
                    <?php if(!empty($doctor['bio'])): ?>
                    <div class="action-card">
                        <h3><i class="fas fa-notes-medical"></i> Professional Bio</h3>
                        <div class="bio-content">
                            <?php echo nl2br(htmlspecialchars($doctor['bio'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Appointments -->
                    <div class="action-card">
                        <h3><i class="fas fa-history"></i> Recent Appointments</h3>
                        <?php if($recent_appointments && $recent_appointments->num_rows > 0): ?>
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($apt = $recent_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                                    <td><?php echo ucfirst($apt['consultation_type']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $apt['status']; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="color: #6c757d; text-align: center;">No appointments yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Qualifications & Certifications (Placeholder) -->
                    <div class="action-card">
                        <h3><i class="fas fa-graduation-cap"></i> Qualifications</h3>
                        <div class="bio-content">
                            <p><strong>Medical School:</strong> <?php echo !empty($doctor['medical_school']) ? htmlspecialchars($doctor['medical_school']) : 'Not provided'; ?></p>
                            <p><strong>Year of Graduation:</strong> <?php echo !empty($doctor['graduation_year']) ? htmlspecialchars($doctor['graduation_year']) : 'Not provided'; ?></p>
                            <p><strong>License Number:</strong> <?php echo !empty($doctor['license_number']) ? htmlspecialchars($doctor['license_number']) : 'Not provided'; ?></p>
                        </div>
                    </div>
                </div>
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
        
        function confirmApprove() {
            return confirm('Approve Dr. <?php echo addslashes($doctor['name']); ?>?\n\nThis will allow the doctor to:\n✓ Log in to their account\n✓ Accept patient appointments\n✓ Conduct video consultations\n\nDo you want to proceed?');
        }
        
        function confirmReject() {
            return confirm('Reject Dr. <?php echo addslashes($doctor['name']); ?>\'s application?\n\nThis action will permanently remove the doctor\'s registration and cannot be undone.\n\nAre you sure?');
        }
        
        function confirmRemove() {
            return confirm('Remove Dr. <?php echo addslashes($doctor['name']); ?> from the system?\n\nThis will permanently delete all associated data including appointments and prescriptions.\n\nThis action cannot be undone!');
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
    </script>
</body>
</html>