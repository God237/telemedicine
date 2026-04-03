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

// Get doctor ID from URL or session
$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 
             (isset($_SESSION['selected_doctor_id']) ? $_SESSION['selected_doctor_id'] : 0);

// If no doctor ID, redirect to find doctor page
if ($doctor_id <= 0) {
    header("Location: find-doctor.php");
    exit();
}

// Store selected doctor in session
$_SESSION['selected_doctor_id'] = $doctor_id;

// Fetch doctor details from database
$sql = "SELECT * FROM users WHERE id = ? AND role = 'doctor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

// If doctor not found, redirect
if (!$doctor) {
    header("Location: find-doctor.php");
    exit();
}

// Get doctor details
$doctor_name = $doctor['name'];
$doctor_specialty = $doctor['specialty'] ?? 'General Practitioner';
$doctor_location = $doctor['location'] ?? 'Douala';
$doctor_experience = $doctor['experience'] ?? '5+ years';
$doctor_fee = $doctor['consultation_fee'] ?? 5000;
$doctor_rating = 4.5;

// Get available time slots (30-minute intervals)
$time_slots = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '12:00', '12:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Book Appointment | Patient Dashboard | TeleMed Cameroon</title>
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

        .patient-badge {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }

        .appointment-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .doctor-info-card {
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

        .doctor-header {
            background: linear-gradient(135deg, #2b7a8a, #1a5a6a);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .doctor-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: #2b7a8a;
            font-weight: bold;
        }

        .doctor-details {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f6;
        }

        .detail-label {
            color: #6c757d;
        }

        .detail-value {
            color: #1a3a4a;
            font-weight: 600;
        }

        .booking-form-card {
            flex: 2;
            min-width: 350px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            font-size: 0.95rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-submit:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
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
            .appointment-container {
                flex-direction: column;
            }
            .doctor-info-card {
                position: static;
            }
            .form-row {
                grid-template-columns: 1fr;
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
                <li class="active"><i class="fa-solid fa-calendar-plus"></i><span>Book Appointment</span></li>
                <li onclick="window.location.href='consultation.php'"><i class="fa-solid fa-video"></i><span>Consultation</span></li>
                <li onclick="window.location.href='medical-reports.php'"><i class="fa-solid fa-notes-medical"></i><span>Medical Reports</span></li>
                <li onclick="window.location.href='past-appointments.php'"><i class="fa-solid fa-calendar-days"></i><span>Past Appointments</span></li>
                <li onclick="window.location.href='profile.php'"><i class="fa-solid fa-user"></i><span>Profile</span></li>
                <li onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-calendar-plus"></i> Book Appointment</h1>
                    <p>Schedule a consultation with Dr. <?php echo htmlspecialchars($doctor_name); ?></p>
                </div>
                <div class="patient-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_name); ?>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alertMessage" class="alert"></div>

            <div class="appointment-container">
                <div class="doctor-info-card">
                    <div class="doctor-header">
                        <div class="doctor-avatar"><?php echo strtoupper(substr($doctor_name, 0, 1)); ?></div>
                        <h2>Dr. <?php echo htmlspecialchars($doctor_name); ?></h2>
                        <p><?php echo htmlspecialchars($doctor_specialty); ?></p>
                    </div>
                    <div class="doctor-details">
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-map-marker-alt"></i> Location</span><span class="detail-value"><?php echo htmlspecialchars($doctor_location); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-briefcase"></i> Experience</span><span class="detail-value"><?php echo htmlspecialchars($doctor_experience); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-star"></i> Rating</span><span class="detail-value">⭐ <?php echo $doctor_rating; ?> / 5.0</span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-money-bill-wave"></i> Fee</span><span class="detail-value"><?php echo number_format($doctor_fee, 0, ',', ' '); ?> FCFA</span></div>
                    </div>
                </div>

                <div class="booking-form-card">
                    <div class="form-title"><i class="fas fa-calendar-check"></i> Appointment Details</div>
                    <form id="appointmentForm" onsubmit="submitAppointment(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Date</label>
                                <input type="date" id="appointmentDate" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Time</label>
                                <select id="appointmentTime" required>
                                    <option value="">Select time</option>
                                    <?php foreach($time_slots as $slot): ?>
                                        <option value="<?php echo $slot; ?>"><?php echo date('h:i A', strtotime($slot)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-comments"></i> Consultation Type</label>
                            <select id="consultationType" required>
                                <option value="video">Video Consultation</option>
                                <option value="chat">Chat Consultation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-notes-medical"></i> Reason for Consultation</label>
                            <textarea id="reason" rows="4" placeholder="Describe your symptoms or reason for consultation..." required></textarea>
                        </div>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-check-circle"></i> Confirm Appointment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script>
        const doctorData = {
            id: <?php echo $doctor_id; ?>,
            name: '<?php echo addslashes($doctor_name); ?>',
            fee: <?php echo $doctor_fee; ?>
        };
        
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('appointmentDate').min = today;
        
        // Set default date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('appointmentDate').value = tomorrow.toISOString().split('T')[0];
        
        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            alertDiv.style.display = 'flex';
            
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }
        
        async function submitAppointment(event) {
            event.preventDefault();
            
            const appointmentDate = document.getElementById('appointmentDate').value;
            const appointmentTime = document.getElementById('appointmentTime').value;
            const consultationType = document.getElementById('consultationType').value;
            const reason = document.getElementById('reason').value;
            
            if (!appointmentDate || !appointmentTime || !consultationType || !reason) {
                showAlert('Please fill in all fields', 'error');
                return false;
            }
            
            // Validate date is not in the past
            if (appointmentDate < today) {
                showAlert('Please select a future date', 'error');
                return false;
            }
            
            const appointment = {
                doctor_id: doctorData.id,
                appointment_date: appointmentDate,
                appointment_time: appointmentTime,
                consultation_type: consultationType,
                reason: reason
            };
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            
            try {
                const response = await fetch('../api/book-appointment.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(appointment)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('✅ ' + result.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'my-appointments.php';
                    }, 2000);
                } else {
                    showAlert('❌ ' + result.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Appointment';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Network error. Please check your connection and try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Appointment';
            }
            
            return false;
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