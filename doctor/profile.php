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

// Fetch doctor details from database
$sql = "SELECT * FROM users WHERE id = ? AND role = 'doctor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

// If doctor not found, create default array
if (!$doctor) {
    $doctor = [
        'id' => $doctor_id,
        'name' => $doctor_name,
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
        'specialty' => '',
        'location' => '',
        'experience' => '',
        'bio' => '',
        'consultation_fee' => '',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Handle profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $consultation_fee = trim($_POST['consultation_fee'] ?? '');
    
    // Update password if provided
    $password_update = '';
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $password_update = ", password = '$hashed_password'";
        } else {
            $update_error = "New passwords do not match!";
        }
    }
    
    if (empty($update_error)) {
        $update_sql = "UPDATE users SET 
                       name = ?,
                       email = ?,
                       phone = ?,
                       specialty = ?,
                       location = ?,
                       experience = ?,
                       bio = ?,
                       consultation_fee = ?
                       $password_update
                       WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssssssi", $name, $email, $phone, $specialty, $location, $experience, $bio, $consultation_fee, $doctor_id);
        
        if ($stmt->execute()) {
            $update_success = true;
            $_SESSION['name'] = $name;
            // Refresh doctor data
            $doctor['name'] = $name;
            $doctor['email'] = $email;
            $doctor['phone'] = $phone;
            $doctor['specialty'] = $specialty;
            $doctor['location'] = $location;
            $doctor['experience'] = $experience;
            $doctor['bio'] = $bio;
            $doctor['consultation_fee'] = $consultation_fee;
        } else {
            $update_error = "Failed to update profile: " . $conn->error;
        }
    }
}

// Get statistics for profile
$stats_sql = "SELECT 
              COUNT(*) as total_patients,
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_consultations,
              COUNT(CASE WHEN status = 'approved' AND appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments
              FROM appointments 
              WHERE doctor_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Profile | Doctor Dashboard | TeleMed Cameroon</title>
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

        /* Profile Container */
        .profile-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            flex: 1;
            min-width: 280px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .profile-avatar {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            color: white;
        }

        .avatar-circle {
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

        /* Profile Form */
        .profile-form-container {
            flex: 2;
            min-width: 300px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .form-title {
            font-size: 1.3rem;
            color: #1a3a4a;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eef2f6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #1a3a4a;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2b7a8a;
            box-shadow: 0 0 0 3px rgba(43, 122, 138, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-primary {
            background: #2b7a8a;
            color: white;
        }

        .btn-primary:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
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

            .profile-container {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .avatar-circle {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .profile-form-container {
                padding: 20px;
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
                <h2><i class="fas fa-stethoscope"></i> TeleMed Connect</h2>
                <p>Doctor panel</p>
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
                <li onclick="window.location.href='medical-reports.php'">
                   <i class="fa-solid fa-notes-medical"></i> 
                   <span>Medical Reports</span>
                </li>
                <li onclick="window.location.href='profile.php'" class="active">
                    <i class="fa-solid fa-user"></i> 
                    <span>Profile</span>
                </li>
                <li onclick="logout('../index.html')">
                  <i class="fa-solid fa-right-from-bracket"></i>
                  <span>Logout</span>
                </li>
            </ul>
        </aside>

        <div class="main-content" id="mainContent">
            
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-md"></i> My Profile</h1>
                    <p>View and manage your professional information</p>
                </div>
                <div class="doctor-badge">
                    <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor_name); ?>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if($update_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Profile updated successfully!
                </div>
            <?php endif; ?>
            
            <?php if($update_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $update_error; ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php 
                                $initial = strtoupper(substr($doctor['name'], 0, 1));
                                echo $initial;
                            ?>
                        </div>
                        <h2>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h2>
                        <p><?php echo htmlspecialchars($doctor['specialty'] ?: 'General Practitioner'); ?></p>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-users"></i> Total Patients</span>
                            <span class="stat-value"><?php echo $stats['total_patients'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-check-circle"></i> Completed Consultations</span>
                            <span class="stat-value"><?php echo $stats['completed_consultations'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-calendar"></i> Upcoming Appointments</span>
                            <span class="stat-value"><?php echo $stats['upcoming_appointments'] ?? 0; ?></span>
                        </div>
                    </div>
                    
                    <div class="profile-info">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($doctor['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($doctor['phone'] ?: 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> Location</div>
                            <div class="info-value"><?php echo htmlspecialchars($doctor['location'] ?: 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Member Since</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Profile Edit Form -->
                <div class="profile-form-container">
                    <div class="form-title">
                        <i class="fas fa-edit"></i> Edit Profile Information
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Full Name <span style="color: #dc3545;">*</span></label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address <span style="color: #dc3545;">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($doctor['phone']); ?>" placeholder="e.g., 6XXXXXXXX">
                            </div>
                            <div class="form-group">
                                <label>Specialty</label>
                                <input type="text" name="specialty" value="<?php echo htmlspecialchars($doctor['specialty']); ?>" placeholder="e.g., Cardiologist">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Location</label>
                                <select name="location">
                                    <option value="">Select Area</option>
                                    <option value="Bonaberi" <?php echo $doctor['location'] == 'Bonaberi' ? 'selected' : ''; ?>>Bonaberi</option>
                                    <option value="Akwa" <?php echo $doctor['location'] == 'Akwa' ? 'selected' : ''; ?>>Akwa</option>
                                    <option value="Bonamoussadi" <?php echo $doctor['location'] == 'Bonamoussadi' ? 'selected' : ''; ?>>Bonamoussadi</option>
                                    <option value="Beseke" <?php echo $doctor['location'] == 'Beseke' ? 'selected' : ''; ?>>Beseke</option>
                                    <option value="Bonanjo" <?php echo $doctor['location'] == 'Bonanjo' ? 'selected' : ''; ?>>Bonanjo</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Years of Experience</label>
                                <input type="text" name="experience" value="<?php echo htmlspecialchars($doctor['experience']); ?>" placeholder="e.g., 10 years">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Consultation Fee (FCFA)</label>
                            <input type="number" name="consultation_fee" value="<?php echo htmlspecialchars($doctor['consultation_fee']); ?>" placeholder="e.g., 5000">
                        </div>
                        
                        <div class="form-group">
                            <label>Professional Bio</label>
                            <textarea name="bio" rows="4" placeholder="Tell patients about your experience, qualifications, and approach to care..."><?php echo htmlspecialchars($doctor['bio']); ?></textarea>
                        </div>
                        
                        <div class="form-title" style="margin-top: 20px;">
                            <i class="fas fa-lock"></i> Change Password
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
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
        
        function resetForm() {
            if(confirm('Reset all changes? Unsaved data will be lost.')) {
                location.reload();
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
        
        // Auto-dismiss alerts after 5 seconds
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