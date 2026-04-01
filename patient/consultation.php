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

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Debug logging
error_log("Patient consultation page accessed with appointment ID: " . $appointment_id);

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
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-container { max-width: 500px; background: white; border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            .error-icon { font-size: 80px; color: #dc3545; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 30px; background: #2b7a8a; color: white; text-decoration: none; border-radius: 10px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h1>No Appointment Selected</h1>
            <p>Please select an appointment from your appointments list to start a consultation.</p>
            <a href="my-appointments.php" class="btn"><i class="fas fa-calendar-check"></i> View Appointments</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Fetch appointment details
$sql = "SELECT a.*, 
        d.name as doctor_name, 
        d.id as doctor_id,
        d.email as doctor_email,
        d.phone as doctor_phone,
        d.specialty as doctor_specialty,
        d.location as doctor_location,
        d.experience as doctor_experience
        FROM appointments a
        JOIN users d ON a.doctor_id = d.id
        WHERE a.id = ? AND a.patient_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);
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
            .btn { display: inline-block; padding: 12px 30px; background: #2b7a8a; color: white; text-decoration: none; border-radius: 10px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-search"></i></div>
            <h1>Appointment Not Found</h1>
            <p>The appointment you're trying to access does not exist.</p>
            <a href="my-appointments.php" class="btn"><i class="fas fa-arrow-left"></i> My Appointments</a>
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
            .btn { display: inline-block; padding: 12px 30px; background: #2b7a8a; color: white; text-decoration: none; border-radius: 10px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><i class="fas fa-clock"></i></div>
            <h1>Cannot Start Consultation</h1>
            <p>This appointment is <strong><?php echo ucfirst($appointment['status']); ?></strong>. Only approved appointments can be started.</p>
            <a href="my-appointments.php" class="btn"><i class="fas fa-arrow-left"></i> My Appointments</a>
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
    $create_stmt->bind_param("iiis", $appointment_id, $patient_id, $appointment['doctor_id'], $appointment['consultation_type']);
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
$health_stmt->bind_param("i", $patient_id);
$health_stmt->execute();
$health_records = $health_stmt->get_result()->fetch_assoc();

// Fetch consultation notes
$notes_sql = "SELECT * FROM consultation_notes WHERE consultation_id = ? ORDER BY created_at DESC LIMIT 1";
$notes_stmt = $conn->prepare($notes_sql);
$notes_stmt->bind_param("i", $consultation_id);
$notes_stmt->execute();
$notes = $notes_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Live Consultation | Patient Dashboard | TeleMed Cameroon</title>
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
            width: 350px;
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

        .doctor-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
        }

        .doctor-info p {
            margin: 8px 0;
            color: #495057;
        }

        .health-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
        }

        .health-info p {
            margin: 8px 0;
            color: #495057;
        }

        .notes-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 200px;
            overflow-y: auto;
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

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .waiting-message {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2b7a8a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1024px) {
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
            .video-controls {
                gap: 10px;
            }
            .control-btn {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }
            .control-btn {
                width: 40px;
                height: 40px;
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
                <li class="active"><i class="fa-solid fa-video"></i><span>Consultation</span></li>
                <li onclick="window.location.href='medical-reports.php'"><i class="fa-solid fa-notes-medical"></i><span>Medical Reports</span></li>
                <li onclick="window.location.href='past-appointments.php'"><i class="fa-solid fa-calendar-days"></i><span>Past Appointments</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-video"></i> Live Consultation</h1>
                    <p>Consulting with: Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
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
                        <button class="control-btn" id="toggleMic" onclick="toggleMicrophone()" title="Mute/Unmute">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <button class="control-btn" id="toggleCam" onclick="toggleCamera()" title="Turn Camera On/Off">
                            <i class="fas fa-video"></i>
                        </button>
                        <button class="control-btn danger" onclick="endConsultation()" title="End Consultation">
                            <i class="fas fa-phone-slash"></i>
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="chat-section">
                        <div class="chat-header">
                            <h3><i class="fas fa-comments"></i> Live Chat Consultation</h3>
                            <p>Chatting with: Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <div class="message received">
                                <div class="message-bubble">Consultation started. How can we help you today?</div>
                                <span class="message-time"><?php echo date('h:i A'); ?></span>
                            </div>
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
                        <h3><i class="fas fa-user-md"></i> Doctor Information</h3>
                        <div class="doctor-info">
                            <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                            <p><strong>Specialty:</strong> <?php echo htmlspecialchars($appointment['doctor_specialty']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($appointment['doctor_location']); ?></p>
                            <p><strong>Experience:</strong> <?php echo htmlspecialchars($appointment['doctor_experience'] ?? 'N/A'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($appointment['doctor_email']); ?></p>
                        </div>
                    </div>

                    <div class="panel-section">
                        <h3><i class="fas fa-heartbeat"></i> Your Health Records</h3>
                        <?php if($health_records && !empty($health_records)): ?>
                        <div class="health-info">
                            <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($health_records['blood_group'] ?? 'Not recorded'); ?></p>
                            <p><strong>Allergies:</strong> <?php echo htmlspecialchars($health_records['allergies'] ?? 'None'); ?></p>
                            <p><strong>Chronic Conditions:</strong> <?php echo htmlspecialchars($health_records['chronic_conditions'] ?? 'None'); ?></p>
                            <p><strong>Current Medications:</strong> <?php echo htmlspecialchars($health_records['current_medications'] ?? 'None'); ?></p>
                        </div>
                        <?php else: ?>
                        <div class="waiting-message">
                            <i class="fas fa-notes-medical"></i>
                            <p>No health records found. Your doctor may ask for this information.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="panel-section">
                        <h3><i class="fas fa-notes-medical"></i> Doctor's Notes</h3>
                        <div class="notes-content" id="consultationNotes">
                            <?php if($notes && !empty($notes['notes'])): ?>
                                <?php echo nl2br(htmlspecialchars($notes['notes'])); ?>
                            <?php else: ?>
                                <em>No notes yet. Your doctor will add notes during the consultation.</em>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel-section">
                        <button class="btn btn-success" onclick="endConsultation()" style="width: 100%;">
                            <i class="fas fa-check-circle"></i> End Consultation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Consultation variables
        const consultationId = <?php echo $consultation_id; ?>;
        const appointmentId = <?php echo $appointment_id; ?>;
        const doctorId = <?php echo $appointment['doctor_id']; ?>;
        const patientId = <?php echo $patient_id; ?>;
        const consultationType = '<?php echo $consultation_type; ?>';
        const patientName = '<?php echo addslashes($patient_name); ?>';
        
        // WebRTC variables
        let peerConnection;
        let localStream;
        let isAudioEnabled = true;
        let isVideoEnabled = true;
        
        <?php if($consultation_type == 'video'): ?>
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ]
        };
        
        async function initWebRTC() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('localVideo').srcObject = localStream;
                
                peerConnection = new RTCPeerConnection(configuration);
                localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
                
                peerConnection.ontrack = (event) => {
                    document.getElementById('remoteVideo').srcObject = event.streams[0];
                };
                
                peerConnection.onicecandidate = (event) => {
                    if (event.candidate) sendIceCandidate(event.candidate);
                };
                
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                sendOffer(offer);
                startSignalingPolling();
            } catch (error) {
                console.error('Error accessing media devices:', error);
                alert('Unable to access camera/microphone. Please check permissions.');
            }
        }
        
        async function sendOffer(offer) {
            await fetch('../ws-server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_offer&room=consultation_${consultationId}&offer=${encodeURIComponent(JSON.stringify(offer))}`
            });
        }
        
        async function sendIceCandidate(candidate) {
            await fetch('../ws-server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_ice&room=consultation_${consultationId}&candidate=${encodeURIComponent(JSON.stringify(candidate))}`
            });
        }
        
        function startSignalingPolling() {
            setInterval(async () => {
                const resp = await fetch('../ws-server.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=get_answer&room=consultation_${consultationId}`
                });
                const data = await resp.json();
                if (data.success && data.answer) {
                    await peerConnection.setRemoteDescription(JSON.parse(data.answer));
                }
            }, 2000);
            
            setInterval(async () => {
                const resp = await fetch('../ws-server.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=get_ice&room=consultation_${consultationId}`
                });
                const data = await resp.json();
                if (data.success && data.candidates) {
                    for (const c of data.candidates) {
                        try { await peerConnection.addIceCandidate(JSON.parse(c)); } catch(e) {}
                    }
                }
            }, 2000);
        }
        
        function toggleMicrophone() {
            if (localStream) {
                localStream.getAudioTracks().forEach(t => t.enabled = !t.enabled);
                isAudioEnabled = !isAudioEnabled;
                document.getElementById('toggleMic').innerHTML = isAudioEnabled ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';
            }
        }
        
        function toggleCamera() {
            if (localStream) {
                localStream.getVideoTracks().forEach(t => t.enabled = !t.enabled);
                isVideoEnabled = !isVideoEnabled;
                document.getElementById('toggleCam').innerHTML = isVideoEnabled ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';
            }
        }
        
        initWebRTC();
        <?php endif; ?>
        
        // Chat functions
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;
            
            addMessage(message, 'sent');
            input.value = '';
            
            await fetch('../api/consultation.php?action=message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    consultation_id: consultationId,
                    sender_id: patientId,
                    sender_type: 'patient',
                    message: message
                })
            });
        }
        
        function addMessage(message, type) {
            const chatDiv = document.getElementById('chatMessages');
            if (!chatDiv) return;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `
                <div class="message-bubble">${escapeHtml(message)}</div>
                <span class="message-time">${new Date().toLocaleTimeString()}</span>
            `;
            chatDiv.appendChild(messageDiv);
            chatDiv.scrollTop = chatDiv.scrollHeight;
        }
        
        async function loadMessages() {
            try {
                const resp = await fetch(`../api/consultation.php?action=messages&id=${consultationId}`);
                const messages = await resp.json();
                if (messages && messages.length > 0) {
                    const chatDiv = document.getElementById('chatMessages');
                    if (chatDiv && chatDiv.children.length <= 1) {
                        chatDiv.innerHTML = '';
                        messages.forEach(msg => {
                            addMessage(msg.message, msg.sender_type === 'patient' ? 'sent' : 'received');
                        });
                    }
                }
            } catch(e) {}
        }
        
        async function loadNotes() {
            try {
                const resp = await fetch(`../api/consultation.php?action=get_notes&id=${consultationId}`);
                const data = await resp.json();
                if (data && data.notes) {
                    document.getElementById('consultationNotes').innerHTML = escapeHtml(data.notes).replace(/\n/g, '<br>');
                }
            } catch(e) {}
        }
        
        async function endConsultation() {
            if (confirm('Are you sure you want to end this consultation?')) {
                await fetch('../api/consultation.php?action=patient_end', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        consultation_id: consultationId,
                        appointment_id: appointmentId
                    })
                });
                
                if (peerConnection) peerConnection.close();
                if (localStream) localStream.getTracks().forEach(t => t.stop());
                
                window.location.href = 'my-appointments.php';
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
        
        function logout() {
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }
        
        <?php if($consultation_type == 'chat'): ?>
        loadMessages();
        setInterval(loadMessages, 3000);
        setInterval(loadNotes, 5000);
        
        document.getElementById('chatInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
        <?php endif; ?>
        
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
                if (window.innerWidth <= 1024) {
                    // Adjust map if needed
                }
            }, 250);
        });
    </script>
</body>
</html>