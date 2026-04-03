<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "doctor") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    header("Location: appointments.php");
    exit();
}

// Get doctor information
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'];

// Fetch appointment details with patient information
$sql = "SELECT a.*, 
        p.name as patient_name,
        p.email as patient_email,
        p.phone as patient_phone,
        p.city as patient_city,
        p.area as patient_area,
        p.date_of_birth as patient_dob,
        p.gender as patient_gender
        FROM appointments a
        LEFT JOIN users p ON a.patient_id = p.id
        WHERE a.id = ? AND a.doctor_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header("Location: appointments.php");
    exit();
}

// Fetch consultation if exists
$consultation_sql = "SELECT * FROM consultations WHERE appointment_id = ?";
$consultation_stmt = $conn->prepare($consultation_sql);
$consultation_stmt->bind_param("i", $appointment_id);
$consultation_stmt->execute();
$consultation = $consultation_stmt->get_result()->fetch_assoc();

// Fetch consultation notes if any
$notes = null;
if ($consultation) {
    $notes_sql = "SELECT * FROM consultation_notes WHERE consultation_id = ? ORDER BY created_at DESC LIMIT 1";
    $notes_stmt = $conn->prepare($notes_sql);
    $notes_stmt->bind_param("i", $consultation['id']);
    $notes_stmt->execute();
    $notes = $notes_stmt->get_result()->fetch_assoc();
}

// Fetch prescriptions if any
$prescriptions = null;
if ($consultation) {
    $pres_sql = "SELECT * FROM prescriptions WHERE consultation_id = ? ORDER BY created_at DESC";
    $pres_stmt = $conn->prepare($pres_sql);
    $pres_stmt->bind_param("i", $consultation['id']);
    $pres_stmt->execute();
    $prescriptions = $pres_stmt->get_result();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Appointment | Doctor Dashboard | TeleMed Cameroon</title>
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
            max-width: 1000px;
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

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-ongoing {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Cards */
        .info-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card-header {
            background: #f8f9fa;
            padding: 15px 25px;
            border-bottom: 1px solid #dee2e6;
        }

        .card-header h3 {
            color: #1a3a4a;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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

        /* Prescription Table */
        .prescription-table {
            width: 100%;
            border-collapse: collapse;
        }

        .prescription-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #1a3a4a;
            border-bottom: 2px solid #dee2e6;
        }

        .prescription-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            color: #495057;
        }

        .notes-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #2b7a8a;
            color: white;
        }

        .btn-primary:hover {
            background: #1f5c6e;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
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

        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #dee2e6;
        }

        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .prescription-table {
                font-size: 0.85rem;
            }
            .prescription-table th,
            .prescription-table td {
                padding: 8px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-check"></i> Appointment Details</h1>
            <a href="appointments.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
        </div>

        <!-- Status Banner -->
        <div class="info-card">
            <div class="card-body" style="text-align: center;">
                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                    <i class="fas <?php 
                        echo $appointment['status'] == 'pending' ? 'fa-clock' : 
                            ($appointment['status'] == 'approved' ? 'fa-check-circle' : 
                            ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-times-circle')); 
                    ?>"></i>
                    Appointment Status: <?php echo ucfirst($appointment['status']); ?>
                </span>
                <?php if($consultation && $consultation['status']): ?>
                <span class="status-badge status-<?php echo $consultation['status']; ?>" style="margin-left: 10px;">
                    <i class="fas fa-video"></i>
                    Consultation: <?php echo ucfirst($consultation['status']); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointment Information -->
        <div class="info-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Appointment Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Appointment ID</div>
                        <div class="info-value">#<?php echo $appointment['id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Time</div>
                        <div class="info-value"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Consultation Type</div>
                        <div class="info-value">
                            <i class="fas <?php echo $appointment['consultation_type'] == 'video' ? 'fa-video' : 'fa-comments'; ?>"></i>
                            <?php echo ucfirst($appointment['consultation_type']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Reason for Consultation</div>
                        <div class="info-value"><?php echo safeHtml($appointment['reason']); ?></div>
                    </div>
                    <?php if($appointment['created_at']): ?>
                    <div class="info-item">
                        <div class="info-label">Booked On</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['created_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="info-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Patient Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo safeHtml($appointment['patient_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo safeHtml($appointment['patient_email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo safeHtml($appointment['patient_phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value">
                            <?php echo safeHtml($appointment['patient_city']); ?>
                            <?php echo !empty($appointment['patient_area']) ? ' (' . safeHtml($appointment['patient_area']) . ')' : ''; ?>
                        </div>
                    </div>
                    <?php if(!empty($appointment['patient_dob'])): ?>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($appointment['patient_dob'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($appointment['patient_gender'])): ?>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo safeHtml($appointment['patient_gender']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Consultation Details (if consultation exists) -->
        <?php if($consultation): ?>
        <div class="info-card">
            <div class="card-header">
                <h3><i class="fas fa-video"></i> Consultation Details</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Consultation ID</div>
                        <div class="info-value">#<?php echo $consultation['id']; ?></div>
                    </div>
                    <?php if($consultation['start_time']): ?>
                    <div class="info-item">
                        <div class="info-label">Started At</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($consultation['start_time'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($consultation['end_time']): ?>
                    <div class="info-item">
                        <div class="info-label">Ended At</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($consultation['end_time'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Doctor's Notes -->
                <?php if($notes && !empty($notes['notes'])): ?>
                <hr>
                <div class="info-item">
                    <div class="info-label">Doctor's Notes</div>
                    <div class="notes-content"><?php echo nl2br(safeHtml($notes['notes'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- Prescriptions -->
                <?php if($prescriptions && $prescriptions->num_rows > 0): ?>
                <hr>
                <div class="info-item">
                    <div class="info-label">Prescriptions</div>
                    <table class="prescription-table">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Duration</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($pres = $prescriptions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo safeHtml($pres['medication_name']); ?></td>
                                <td><?php echo safeHtml($pres['dosage']); ?></td>
                                <td><?php echo safeHtml($pres['duration']); ?></td>
                                <td><?php echo safeHtml($pres['instructions']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if($appointment['status'] == 'pending'): ?>
            <a href="approve-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-success" onclick="return confirm('Approve this appointment?')">
                <i class="fas fa-check"></i> Approve Appointment
            </a>
            <a href="reject-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-danger" onclick="return confirm('Reject this appointment?')">
                <i class="fas fa-times"></i> Reject Appointment
            </a>
            <?php elseif($appointment['status'] == 'approved'): ?>
            <a href="consultation.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                <i class="fas fa-video"></i> Start Consultation
            </a>
            <?php endif; ?>
            <a href="appointments.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> View All Appointments
            </a>
        </div>
    </div>
</body>
</html>