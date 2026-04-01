<?php
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Debug logging
error_log("Consultation page accessed with appointment ID: " . $appointment_id);

// If no appointment ID, show error page
if ($appointment_id <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - No Appointment Selected | TeleMed Cameroon</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-container {
                max-width: 500px;
                width: 100%;
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                animation: slideIn 0.5s ease;
            }
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .error-icon {
                font-size: 80px;
                color: #dc3545;
                margin-bottom: 20px;
            }
            .error-container h1 {
                color: #1a3a4a;
                margin-bottom: 15px;
                font-size: 1.8rem;
            }
            .error-container p {
                color: #6c757d;
                margin-bottom: 25px;
                line-height: 1.6;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #2b7a8a;
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 500;
                transition: all 0.3s;
                margin: 0 5px;
            }
            .btn:hover {
                background: #1f5c6e;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #6c757d;
            }
            .btn-secondary:hover {
                background: #5a6268;
            }
            .steps {
                margin-top: 30px;
                text-align: left;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 12px;
            }
            .steps h3 {
                color: #1a3a4a;
                margin-bottom: 10px;
            }
            .steps ol {
                margin-left: 20px;
                color: #6c757d;
            }
            .steps li {
                margin: 8px 0;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>No Appointment Selected</h1>
            <p>Please select a valid appointment from your appointments list to start a consultation.</p>
            <a href="appointments.php" class="btn"><i class="fas fa-calendar-check"></i> View Appointments</a>
            <a href="doctor-dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Dashboard</a>
            
            <div class="steps">
                <h3><i class="fas fa-info-circle"></i> How to Start a Consultation:</h3>
                <ol>
                    <li>Go to <strong>Appointments</strong> page from the sidebar</li>
                    <li>Find an <strong>approved</strong> appointment</li>
                    <li>Click the <strong>Start Consultation</strong> button</li>
                    <li>You will be redirected to this consultation page</li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Fetch appointment details
$sql = "SELECT a.*, 
        p.name as patient_name, 
        p.id as patient_id,
        p.email as patient_email,
        p.phone as patient_phone
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        WHERE a.id = ? AND a.doctor_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

// If appointment not found, show error
if (!$appointment) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Appointment Not Found | TeleMed Cameroon</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-container { max-width: 500px; background: white; border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            .error-icon { font-size: 80px; color: #dc3545; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 30px; background: #2b7a8a; color: white; text-decoration: none; border-radius: 10px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-search"></i></div>
            <h1>Appointment Not Found</h1>
            <p>The appointment you're trying to access does not exist or you don't have permission to view it.</p>
            <a href="appointments.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Check if appointment can be started
if ($appointment['status'] != 'approved' && $appointment['status'] != 'ongoing') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Cannot Start Consultation | TeleMed Cameroon</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-container { max-width: 500px; background: white; border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            .error-icon { font-size: 80px; color: #ffc107; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 30px; background: #2b7a8a; color: white; text-decoration: none; border-radius: 10px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-clock"></i></div>
            <h1>Cannot Start Consultation</h1>
            <p>This appointment is <strong><?php echo ucfirst($appointment['status']); ?></strong>. Only approved appointments can be started.</p>
            <a href="appointments.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Check if consultation already exists or create new one
$consultation_sql = "SELECT * FROM consultations WHERE appointment_id = ?";
$consultation_stmt = $conn->prepare($consultation_sql);
$consultation_stmt->bind_param("i", $appointment_id);
$consultation_stmt->execute();
$consultation = $consultation_stmt->get_result()->fetch_assoc();

if (!$consultation) {
    // Create new consultation
    $create_sql = "INSERT INTO consultations (appointment_id, patient_id, doctor_id, consultation_type, status, start_time) 
                   VALUES (?, ?, ?, ?, 'active', NOW())";
    $create_stmt = $conn->prepare($create_sql);
    $create_stmt->bind_param("iiis", $appointment_id, $appointment['patient_id'], $doctor_id, $appointment['consultation_type']);
    $create_stmt->execute();
    $consultation_id = $conn->insert_id;
    
    // Update appointment status
    $update_appointment = "UPDATE appointments SET status = 'ongoing' WHERE id = ?";
    $update_stmt = $conn->prepare($update_appointment);
    $update_stmt->bind_param("i", $appointment_id);
    $update_stmt->execute();
} else {
    $consultation_id = $consultation['id'];
}

$consultation_type = $appointment['consultation_type'];

// Fetch patient health records
$health_sql = "SELECT * FROM patient_health_records WHERE patient_id = ?";
$health_stmt = $conn->prepare($health_sql);
$health_stmt->bind_param("i", $appointment['patient_id']);
$health_stmt->execute();
$health_records = $health_stmt->get_result()->fetch_assoc();

// Fetch previous prescriptions
$prescriptions_sql = "SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC LIMIT 5";
$prescriptions_stmt = $conn->prepare($prescriptions_sql);
$prescriptions_stmt->bind_param("i", $appointment['patient_id']);
$prescriptions_stmt->execute();
$previous_prescriptions = $prescriptions_stmt->get_result();

// Fetch existing consultation notes
$notes_sql = "SELECT * FROM consultation_notes WHERE consultation_id = ? ORDER BY created_at DESC LIMIT 1";
$notes_stmt = $conn->prepare($notes_sql);
$notes_stmt->bind_param("i", $consultation_id);
$notes_stmt->execute();
$existing_notes = $notes_stmt->get_result()->fetch_assoc();
$saved_notes = $existing_notes ? $existing_notes['notes'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Live Consultation | Dr. <?php echo htmlspecialchars($doctor_name); ?> | TeleMed Cameroon</title>
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

        /* Sidebar */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, #0a2b3e 0%, #123152 100%);
            color: white;
            padding: 30px 20px;
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
            background: #3498db;
            color: white;
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

        .doctor-badge {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        .consultation-container {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .video-section {
            flex: 2;
            min-width: 300px;
            background: #1a2e3b;
            border-radius: 20px;
            padding: 20px;
        }

        .video-container {
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            aspect-ratio: 16/9;
        }

        #localVideo, #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .control-btn:hover {
            transform: scale(1.1);
        }

        .control-btn.danger {
            background: #dc3545;
            color: white;
        }

        .chat-section {
            flex: 1;
            min-width: 300px;
            background: white;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .chat-header {
            padding: 20px;
            background: #2b7a8a;
            color: white;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            height: 400px;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }

        .message.sent {
            align-items: flex-end;
        }

        .message.received {
            align-items: flex-start;
        }

        .message-bubble {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            word-wrap: break-word;
        }

        .message.sent .message-bubble {
            background: #2b7a8a;
            color: white;
        }

        .message.received .message-bubble {
            background: white;
            color: #212529;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .message-time {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .chat-input-area {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            background: white;
        }

        .chat-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 24px;
            outline: none;
        }

        .info-panel {
            width: 380px;
            background: white;
            border-radius: 20px;
            overflow-y: auto;
            max-height: calc(100vh - 40px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .panel-section {
            padding: 20px;
            border-bottom: 1px solid #eef2f6;
        }

        .panel-section h3 {
            margin-bottom: 15px;
            color: #1a3a4a;
            font-size: 1.1rem;
        }

        .health-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
        }

        .health-info p {
            margin: 8px 0;
            color: #495057;
            font-size: 0.9rem;
        }

        .notes-area {
            width: 100%;
            min-height: 150px;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            resize: vertical;
            font-family: monospace;
        }

        .prescription-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #2b7a8a;
            color: white;
        }

        .btn-primary:hover {
            background: #1f5c6e;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .prescription-inputs {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }

        .prescription-inputs input {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }

        @media (max-width: 1200px) {
            .consultation-container {
                flex-direction: column;
            }
            .info-panel {
                width: 100%;
                max-height: none;
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
                padding: 20px 15px;
                padding-top: 80px;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
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
                <p>Connect</p>
            </div>
            <ul class="nav-links">
                <li onclick="window.location.href='doctor-dashboard.php'"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></li>
                <li onclick="window.location.href='appointments.php'"><i class="fa-solid fa-calendar-check"></i><span>Appointments</span></li>
                <li class="active"><i class="fa-solid fa-video"></i><span>Consultation</span></li>
                <li onclick="window.location.href='medical-reports.php'"><i class="fa-solid fa-notes-medical"></i><span>Medical Reports</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout('../index.php')"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-video"></i> Live Consultation</h1>
                    <p>Consulting with: <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                </div>
                <div class="doctor-badge">
                    <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor_name); ?>
                </div>
            </div>

            <div class="consultation-container">
                
                <div class="video-section">
                    <?php if($consultation_type == 'video'): ?>
                    <div class="video-container">
                        <video id="remoteVideo" autoplay playsinline></video>
                        <video id="localVideo" autoplay playsinline muted></video>
                    </div>
                    <div class="video-controls">
                        <button class="control-btn" id="toggleMic" onclick="toggleMicrophone()"><i class="fas fa-microphone"></i></button>
                        <button class="control-btn" id="toggleCam" onclick="toggleCamera()"><i class="fas fa-video"></i></button>
                        <button class="control-btn danger" onclick="endConsultation()"><i class="fas fa-phone-slash"></i></button>
                    </div>
                    <?php else: ?>
                    <div class="chat-section">
                        <div class="chat-header">
                            <h3><i class="fas fa-comments"></i> Live Chat Consultation</h3>
                            <p>Chatting with: <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <div class="message received"><div class="message-bubble">Consultation started. How can I help you today?</div><span class="message-time"><?php echo date('h:i A'); ?></span></div>
                        </div>
                        <div class="chat-input-area">
                            <input type="text" class="chat-input" id="chatInput" placeholder="Type your message...">
                            <button class="btn btn-primary" onclick="sendMessage()"><i class="fas fa-paper-plane"></i> Send</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-panel">
                    <div class="panel-section">
                        <h3><i class="fas fa-user-md"></i> Consultation Info</h3>
                        <div class="health-info">
                            <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                            <?php if(!empty($appointment['patient_phone'])): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Type:</strong> <?php echo ucfirst($consultation_type); ?> Consultation</p>
                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?></p>
                        </div>
                    </div>

                    <div class="panel-section">
                        <h3><i class="fas fa-heartbeat"></i> Patient Health Records</h3>
                        <?php if($health_records && !empty($health_records)): ?>
                        <div class="health-info">
                            <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($health_records['blood_group'] ?? 'Not recorded'); ?></p>
                            <p><strong>Allergies:</strong> <?php echo htmlspecialchars($health_records['allergies'] ?? 'None'); ?></p>
                            <p><strong>Chronic Conditions:</strong> <?php echo htmlspecialchars($health_records['chronic_conditions'] ?? 'None'); ?></p>
                            <p><strong>Current Medications:</strong> <?php echo htmlspecialchars($health_records['current_medications'] ?? 'None'); ?></p>
                        </div>
                        <?php else: ?>
                        <p class="health-info">No health records found.</p>
                        <?php endif; ?>
                    </div>

                    <?php if($previous_prescriptions && $previous_prescriptions->num_rows > 0): ?>
                    <div class="panel-section">
                        <h3><i class="fas fa-history"></i> Previous Prescriptions</h3>
                        <?php while($pres = $previous_prescriptions->fetch_assoc()): ?>
                        <div class="prescription-item">
                            <strong><?php echo htmlspecialchars($pres['medication_name']); ?></strong><br>
                            <small>Dosage: <?php echo htmlspecialchars($pres['dosage']); ?> | Duration: <?php echo htmlspecialchars($pres['duration']); ?></small>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>

                    <div class="panel-section">
                        <h3><i class="fas fa-notes-medical"></i> Consultation Notes</h3>
                        <textarea id="consultationNotes" class="notes-area" placeholder="Enter your diagnosis, notes, and recommendations here..."><?php echo htmlspecialchars($saved_notes); ?></textarea>
                        <small style="color: #6c757d; margin-top: 5px; display: block;"><i class="fas fa-info-circle"></i> Notes are auto-saved every 30 seconds</small>
                    </div>

                    <div class="panel-section">
                        <h3><i class="fas fa-prescription-bottle"></i> Prescriptions</h3>
                        <div id="prescriptionsList"></div>
                        <div class="prescription-inputs">
                            <input type="text" id="medName" placeholder="Medication name *">
                            <input type="text" id="dosage" placeholder="Dosage (e.g., 500mg twice daily)">
                            <input type="text" id="duration" placeholder="Duration (e.g., 7 days)">
                            <input type="text" id="instructions" placeholder="Instructions (optional)">
                        </div>
                        <button class="btn btn-primary" onclick="addPrescription()" style="width: 100%;"><i class="fas fa-plus"></i> Add Prescription</button>
                    </div>

                    <div class="panel-section">
                        <button class="btn btn-success" onclick="endConsultation()" style="width: 100%;"><i class="fas fa-check-circle"></i> End Consultation & Generate Report</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        const consultationId = <?php echo $consultation_id; ?>;
        const appointmentId = <?php echo $appointment_id; ?>;
        const patientId = <?php echo $appointment['patient_id']; ?>;
        const doctorId = <?php echo $doctor_id; ?>;
        const consultationType = '<?php echo $consultation_type; ?>';
        
        let prescriptions = [];
        let peerConnection;
        let localStream;
        let isAudioEnabled = true;
        let isVideoEnabled = true;
        
        <?php if($consultation_type == 'video'): ?>
        const configuration = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }] };
        
        async function initWebRTC() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('localVideo').srcObject = localStream;
                peerConnection = new RTCPeerConnection(configuration);
                localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
                peerConnection.ontrack = (event) => { document.getElementById('remoteVideo').srcObject = event.streams[0]; };
                peerConnection.onicecandidate = (event) => { if (event.candidate) sendIceCandidate(event.candidate); };
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                await fetch('../ws-server.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=send_offer&room=consultation_${consultationId}&offer=${encodeURIComponent(JSON.stringify(offer))}` });
                startSignalingPolling();
            } catch (error) { alert('Unable to access camera/microphone. Please check permissions.'); }
        }
        
        async function sendIceCandidate(candidate) {
            await fetch('../ws-server.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=send_ice&room=consultation_${consultationId}&candidate=${encodeURIComponent(JSON.stringify(candidate))}` });
        }
        
        function startSignalingPolling() {
            setInterval(async () => {
                const resp = await fetch('../ws-server.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_answer&room=consultation_${consultationId}` });
                const data = await resp.json();
                if (data.success && data.answer) { await peerConnection.setRemoteDescription(JSON.parse(data.answer)); }
            }, 2000);
            setInterval(async () => {
                const resp = await fetch('../ws-server.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_ice&room=consultation_${consultationId}` });
                const data = await resp.json();
                if (data.success && data.candidates) {
                    for (const c of data.candidates) { try { await peerConnection.addIceCandidate(JSON.parse(c)); } catch(e) {} }
                }
            }, 2000);
        }
        
        function toggleMicrophone() {
            if (localStream) { localStream.getAudioTracks().forEach(t => t.enabled = !t.enabled); isAudioEnabled = !isAudioEnabled; document.getElementById('toggleMic').innerHTML = isAudioEnabled ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>'; }
        }
        
        function toggleCamera() {
            if (localStream) { localStream.getVideoTracks().forEach(t => t.enabled = !t.enabled); isVideoEnabled = !isVideoEnabled; document.getElementById('toggleCam').innerHTML = isVideoEnabled ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>'; }
        }
        
        initWebRTC();
        <?php endif; ?>
        
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;
            addMessage(message, 'sent');
            input.value = '';
            await fetch('../api/consultation.php?action=message', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ consultation_id: consultationId, sender_id: doctorId, sender_type: 'doctor', message: message }) });
        }
        
        function addMessage(message, type) {
            const chatDiv = document.getElementById('chatMessages');
            if (!chatDiv) return;
            const div = document.createElement('div');
            div.className = `message ${type}`;
            div.innerHTML = `<div class="message-bubble">${escapeHtml(message)}</div><span class="message-time">${new Date().toLocaleTimeString()}</span>`;
            chatDiv.appendChild(div);
            chatDiv.scrollTop = chatDiv.scrollHeight;
        }
        
        function addPrescription() {
            const medName = document.getElementById('medName').value;
            if (!medName) { alert('Please enter medication name'); return; }
            prescriptions.push({ medName, dosage: document.getElementById('dosage').value, duration: document.getElementById('duration').value, instructions: document.getElementById('instructions').value });
            updatePrescriptionsList();
            document.getElementById('medName').value = ''; document.getElementById('dosage').value = ''; document.getElementById('duration').value = ''; document.getElementById('instructions').value = '';
        }
        
        function updatePrescriptionsList() {
            const listDiv = document.getElementById('prescriptionsList');
            listDiv.innerHTML = '';
            prescriptions.forEach((pres, idx) => {
                const div = document.createElement('div');
                div.className = 'prescription-item';
                div.innerHTML = `<strong>${escapeHtml(pres.medName)}</strong><br><small>Dosage: ${escapeHtml(pres.dosage)} | Duration: ${escapeHtml(pres.duration)}</small>${pres.instructions ? `<br><small>Instructions: ${escapeHtml(pres.instructions)}</small>` : ''}<button onclick="removePrescription(${idx})" style="float:right; background:#dc3545; color:white; border:none; padding:2px 8px; border-radius:4px; cursor:pointer;">✖</button>`;
                listDiv.appendChild(div);
            });
        }
        
        function removePrescription(index) { prescriptions.splice(index, 1); updatePrescriptionsList(); }
        
        async function loadMessages() {
            try {
                const resp = await fetch(`../api/consultation.php?action=messages&id=${consultationId}`);
                const messages = await resp.json();
                if (messages && messages.length > 0) {
                    const chatDiv = document.getElementById('chatMessages');
                    if (chatDiv) { chatDiv.innerHTML = ''; messages.forEach(msg => addMessage(msg.message, msg.sender_type === 'doctor' ? 'sent' : 'received')); }
                }
            } catch(e) {}
        }
        
        async function endConsultation() {
            if (confirm('End consultation? A medical report will be generated.')) {
                const notes = document.getElementById('consultationNotes').value;
                await fetch('../api/consultation.php?action=complete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ consultation_id: consultationId, appointment_id: appointmentId, patient_id: patientId, doctor_id: doctorId, notes: notes, prescriptions: prescriptions }) });
                if (peerConnection) peerConnection.close();
                if (localStream) localStream.getTracks().forEach(t => t.stop());
                window.location.href = 'appointments.php';
            }
        }
        
        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebarOverlay').classList.toggle('active'); }
        function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('active'); }
        function logout() { if(confirm('Logout?')) window.location.href = '../index.php'; }
        
        <?php if($consultation_type == 'chat'): ?>
        loadMessages();
        setInterval(loadMessages, 3000);
        document.getElementById('chatInput')?.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
        <?php endif; ?>
        
        setInterval(async () => {
            const notes = document.getElementById('consultationNotes').value;
            if (notes && notes.trim() !== '') {
                await fetch('../api/consultation.php?action=save_notes', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ consultation_id: consultationId, notes: notes, doctor_id: doctorId }) });
            }
        }, 30000);
        
        document.querySelectorAll('.nav-links li').forEach(link => { link.addEventListener('click', () => { if (window.innerWidth <= 768) setTimeout(closeSidebar, 150); }); });
        let resizeTimer;
        window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => { if (window.innerWidth > 768 && document.getElementById('sidebar').classList.contains('open')) closeSidebar(); }, 250); });
    </script>
</body>
</html>