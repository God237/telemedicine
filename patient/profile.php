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

// Fetch patient details from database
$sql = "SELECT * FROM users WHERE id = ? AND role = 'patient'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

// Set default values for missing fields
$default_fields = [
    'phone' => '',
    'date_of_birth' => '',
    'gender' => '',
    'address' => '',
    'city' => '',
    'area' => '',
    'emergency_contact' => '',
    'emergency_phone' => '',
    'created_at' => date('Y-m-d H:i:s')
];

// Merge with defaults
foreach ($default_fields as $key => $value) {
    if (!isset($patient[$key])) {
        $patient[$key] = $value;
    }
}

// Fetch patient health records
$health_records = [];
$health_sql = "SELECT * FROM patient_health_records WHERE patient_id = ?";
$health_stmt = $conn->prepare($health_sql);
$health_stmt->bind_param("i", $patient_id);
$health_stmt->execute();
$health_result = $health_stmt->get_result();
if ($health_result && $health_result->num_rows > 0) {
    $health_records = $health_result->fetch_assoc();
}

// Set health record defaults
$health_defaults = [
    'blood_group' => '',
    'allergies' => '',
    'chronic_conditions' => '',
    'current_medications' => ''
];

foreach ($health_defaults as $key => $value) {
    if (!isset($health_records[$key])) {
        $health_records[$key] = $value;
    }
}

// Handle profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $chronic_conditions = trim($_POST['chronic_conditions'] ?? '');
    $current_medications = trim($_POST['current_medications'] ?? '');
    
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
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update users table
            $update_sql = "UPDATE users SET 
                           name = ?,
                           email = ?,
                           phone = ?,
                           date_of_birth = ?,
                           gender = ?,
                           address = ?,
                           city = ?,
                           area = ?,
                           emergency_contact = ?,
                           emergency_phone = ?
                           $password_update
                           WHERE id = ?";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssssssssi", $name, $email, $phone, $date_of_birth, $gender, $address, $city, $area, $emergency_contact, $emergency_phone, $patient_id);
            $stmt->execute();
            
            // Update or insert health records
            if ($health_result && $health_result->num_rows > 0) {
                $health_sql = "UPDATE patient_health_records SET 
                               blood_group = ?,
                               allergies = ?,
                               chronic_conditions = ?,
                               current_medications = ?,
                               updated_at = NOW()
                               WHERE patient_id = ?";
                $health_stmt = $conn->prepare($health_sql);
                $health_stmt->bind_param("ssssi", $blood_group, $allergies, $chronic_conditions, $current_medications, $patient_id);
            } else {
                $health_sql = "INSERT INTO patient_health_records (patient_id, blood_group, allergies, chronic_conditions, current_medications) 
                               VALUES (?, ?, ?, ?, ?)";
                $health_stmt = $conn->prepare($health_sql);
                $health_stmt->bind_param("issss", $patient_id, $blood_group, $allergies, $chronic_conditions, $current_medications);
            }
            $health_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $update_success = true;
            $_SESSION['name'] = $name;
            
            // Refresh patient data
            $patient['name'] = $name;
            $patient['email'] = $email;
            $patient['phone'] = $phone;
            $patient['date_of_birth'] = $date_of_birth;
            $patient['gender'] = $gender;
            $patient['address'] = $address;
            $patient['city'] = $city;
            $patient['area'] = $area;
            $patient['emergency_contact'] = $emergency_contact;
            $patient['emergency_phone'] = $emergency_phone;
            $health_records['blood_group'] = $blood_group;
            $health_records['allergies'] = $allergies;
            $health_records['chronic_conditions'] = $chronic_conditions;
            $health_records['current_medications'] = $current_medications;
            
        } catch (Exception $e) {
            $conn->rollback();
            $update_error = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// Get statistics for profile
$stats_sql = "SELECT 
              COUNT(*) as total_appointments,
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_consultations,
              COUNT(CASE WHEN status = 'approved' AND appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments
              FROM appointments 
              WHERE patient_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Set default stats
if (!$stats) {
    $stats = [
        'total_appointments' => 0,
        'completed_consultations' => 0,
        'upcoming_appointments' => 0
    ];
}

// Get last appointment date
$last_appointment_sql = "SELECT appointment_date FROM appointments 
                         WHERE patient_id = ? AND status = 'completed' 
                         ORDER BY appointment_date DESC LIMIT 1";
$last_stmt = $conn->prepare($last_appointment_sql);
$last_stmt->bind_param("i", $patient_id);
$last_stmt->execute();
$last_appointment = $last_stmt->get_result()->fetch_assoc();
$last_visit = $last_appointment ? date('F j, Y', strtotime($last_appointment['appointment_date'])) : 'No visits yet';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Profile | Patient Dashboard | TeleMed Cameroon</title>
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

        .profile-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .profile-sidebar {
            flex: 1;
            min-width: 280px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 30px;
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
            box-shadow: 0 0 0 3px rgba(43,122,138,0.1);
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
                <li class="active"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                    <p>View and manage your personal information</p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                </div>
            </div>

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
                <div class="profile-sidebar">
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php 
                                $initial = strtoupper(substr($patient['name'], 0, 1));
                                echo $initial;
                            ?>
                        </div>
                        <h2><?php echo htmlspecialchars($patient['name']); ?></h2>
                        <p><?php echo htmlspecialchars($patient['email']); ?></p>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-calendar-check"></i> Total Appointments</span>
                            <span class="stat-value"><?php echo $stats['total_appointments']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-check-circle"></i> Completed Consultations</span>
                            <span class="stat-value"><?php echo $stats['completed_consultations']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-calendar"></i> Upcoming Appointments</span>
                            <span class="stat-value"><?php echo $stats['upcoming_appointments']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-clock"></i> Last Visit</span>
                            <span class="stat-value"><?php echo $last_visit; ?></span>
                        </div>
                    </div>
                    
                    <div class="profile-info">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                            <div class="info-value"><?php echo !empty($patient['phone']) ? htmlspecialchars($patient['phone']) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Date of Birth</div>
                            <div class="info-value"><?php echo !empty($patient['date_of_birth']) ? date('F j, Y', strtotime($patient['date_of_birth'])) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-venus-mars"></i> Gender</div>
                            <div class="info-value"><?php echo !empty($patient['gender']) ? htmlspecialchars($patient['gender']) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-tint"></i> Blood Group</div>
                            <div class="info-value"><?php echo !empty($health_records['blood_group']) ? htmlspecialchars($health_records['blood_group']) : 'Not provided'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> Location</div>
                            <div class="info-value"><?php 
                                $location = [];
                                if (!empty($patient['city'])) $location[] = htmlspecialchars($patient['city']);
                                if (!empty($patient['area'])) $location[] = '(' . htmlspecialchars($patient['area']) . ')';
                                echo !empty($location) ? implode(' ', $location) : 'Not provided';
                            ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-calendar"></i> Member Since</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($patient['created_at'])); ?></div>
                        </div>
                    </div>
                </div>

                <div class="profile-form-container">
                    <div class="form-title">
                        <i class="fas fa-edit"></i> Edit Profile Information
                    </div>
                    
                    <form method="POST" action="" id="profileForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span style="color: #dc3545;">*</span></label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address <span style="color: #dc3545;">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" placeholder="e.g., 6XXXXXXXX">
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo $patient['date_of_birth']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $patient['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $patient['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $patient['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Blood Group</label>
                                <select name="blood_group">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo ($health_records['blood_group'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($health_records['blood_group'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($health_records['blood_group'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($health_records['blood_group'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($health_records['blood_group'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($health_records['blood_group'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($health_records['blood_group'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($health_records['blood_group'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>City</label>
                                <select name="city">
                                    <option value="">Select City</option>
                                    <option value="Yaoundé" <?php echo $patient['city'] == 'Yaoundé' ? 'selected' : ''; ?>>Yaoundé</option>
                                    <option value="Douala" <?php echo $patient['city'] == 'Douala' ? 'selected' : ''; ?>>Douala</option>
                                    <option value="Bafoussam" <?php echo $patient['city'] == 'Bafoussam' ? 'selected' : ''; ?>>Bafoussam</option>
                                    <option value="Garoua" <?php echo $patient['city'] == 'Garoua' ? 'selected' : ''; ?>>Garoua</option>
                                    <option value="Bamenda" <?php echo $patient['city'] == 'Bamenda' ? 'selected' : ''; ?>>Bamenda</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Area/Neighborhood</label>
                                <input type="text" name="area" value="<?php echo htmlspecialchars($patient['area']); ?>" placeholder="e.g., Bastos, Akwa">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="2" placeholder="Your full address"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Emergency Contact Name</label>
                                <input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($patient['emergency_contact']); ?>" placeholder="Emergency contact person">
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact Phone</label>
                                <input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($patient['emergency_phone']); ?>" placeholder="Emergency phone number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Allergies</label>
                            <textarea name="allergies" rows="2" placeholder="List any allergies (medications, foods, etc.)"><?php echo htmlspecialchars($health_records['allergies'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Chronic Conditions</label>
                            <textarea name="chronic_conditions" rows="2" placeholder="List any chronic conditions (diabetes, hypertension, etc.)"><?php echo htmlspecialchars($health_records['chronic_conditions'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Current Medications</label>
                            <textarea name="current_medications" rows="2" placeholder="List medications you are currently taking"><?php echo htmlspecialchars($health_records['current_medications'] ?? ''); ?></textarea>
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
        
        function resetForm() {
            if(confirm('Reset all changes? Unsaved data will be lost.')) {
                location.reload();
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
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.style.display = 'none', 300);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>