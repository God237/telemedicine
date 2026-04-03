<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    header("Location: manage-users.php");
    exit();
}

// Fetch user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: manage-users.php");
    exit();
}

// Fetch appointment statistics for this user
if ($user['role'] == 'patient') {
    $stats_sql = "SELECT 
                  COUNT(*) as total_appointments,
                  COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
                  COUNT(CASE WHEN status = 'approved' AND appointment_date >= CURDATE() THEN 1 END) as upcoming_appointments
                  FROM appointments WHERE patient_id = ?";
} elseif ($user['role'] == 'doctor') {
    $stats_sql = "SELECT 
                  COUNT(*) as total_appointments,
                  COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
                  COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_appointments
                  FROM appointments WHERE doctor_id = ?";
} else {
    $stats = ['total_appointments' => 0, 'completed_appointments' => 0];
}

if (isset($stats_sql)) {
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
} else {
    $stats = ['total_appointments' => 0, 'completed_appointments' => 0];
}

// Helper function for safe HTML output
function safeHtml($value, $default = 'Not provided') {
    if ($value === null || $value === '') {
        return $default;
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User | Admin Dashboard | TeleMed Cameroon</title>
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
            padding: 40px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        .header {
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

        .header h1 {
            font-size: 1.8rem;
            color: #1a3a4a;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        /* User Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .profile-header {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .avatar {
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

        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 10px;
        }

        .role-patient {
            background: #cce5ff;
            color: #004085;
        }

        .role-doctor {
            background: #d4edda;
            color: #155724;
        }

        .role-admin {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 10px;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Profile Sections */
        .profile-section {
            padding: 25px;
            border-bottom: 1px solid #eef2f6;
        }

        .profile-section h3 {
            color: #1a3a4a;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eef2f6;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a3a4a;
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding: 20px;
        }

        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-circle"></i> User Details</h1>
            <a href="manage-users.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Users</a>
        </div>

        <div class="profile-card">
            <div class="profile-header">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <h2><?php echo safeHtml($user['name']); ?></h2>
                <p><?php echo safeHtml($user['email']); ?></p>
                <div>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <i class="fas <?php echo $user['role'] == 'doctor' ? 'fa-user-md' : ($user['role'] == 'patient' ? 'fa-user' : 'fa-shield-alt'); ?>"></i>
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <?php if(isset($user['status'])): ?>
                    <span class="status-badge status-<?php echo $user['status']; ?>">
                        <i class="fas <?php echo $user['status'] == 'approved' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-info-circle"></i> Personal Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo safeHtml($user['name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo safeHtml($user['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo safeHtml($user['phone'] ?? ''); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo safeHtml($user['city'] ?? ''); ?> <?php echo !empty($user['area']) ? '(' . safeHtml($user['area']) . ')' : ''; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registered On</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <?php if($user['role'] == 'doctor'): ?>
                    <div class="info-item">
                        <div class="info-label">Specialty</div>
                        <div class="info-value"><?php echo safeHtml($user['specialty'] ?? ''); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Experience</div>
                        <div class="info-value"><?php echo safeHtml($user['experience'] ?? ''); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Consultation Fee</div>
                        <div class="info-value"><?php echo !empty($user['consultation_fee']) ? number_format($user['consultation_fee'], 0, ',', ' ') . ' FCFA' : 'Not set'; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-chart-line"></i> Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-calendar-check"></i>
                        <div class="number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
                        <div class="label">Total Appointments</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <div class="number"><?php echo $stats['completed_appointments'] ?? 0; ?></div>
                        <div class="label">Completed</div>
                    </div>
                    <?php if($user['role'] == 'patient'): ?>
                    <div class="stat-card">
                        <i class="fas fa-calendar-day"></i>
                        <div class="number"><?php echo $stats['upcoming_appointments'] ?? 0; ?></div>
                        <div class="label">Upcoming</div>
                    </div>
                    <?php elseif($user['role'] == 'doctor'): ?>
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <div class="number"><?php echo $stats['pending_appointments'] ?? 0; ?></div>
                        <div class="label">Pending</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($user['role'] == 'doctor' && !empty($user['bio'])): ?>
            <div class="profile-section">
                <h3><i class="fas fa-notes-medical"></i> Professional Bio</h3>
                <div class="info-value" style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                    <?php echo nl2br(safeHtml($user['bio'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="delete-user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Delete User
                </a>
            </div>
        </div>
    </div>
</body>
</html>