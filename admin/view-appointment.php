<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    header("Location: view-reports.php");
    exit();
}

// Fetch appointment details with patient and doctor information
$sql = "SELECT a.*, 
        p.name as patient_name,
        p.email as patient_email,
        p.phone as patient_phone,
        p.city as patient_city,
        p.area as patient_area,
        d.name as doctor_name,
        d.email as doctor_email,
        d.phone as doctor_phone,
        d.specialty as doctor_specialty,
        d.location as doctor_location,
        c.id as consultation_id,
        c.status as consultation_status,
        c.start_time,
        c.end_time,
        c.consultation_type
        FROM appointments a
        LEFT JOIN users p ON a.patient_id = p.id
        LEFT JOIN users d ON a.doctor_id = d.id
        LEFT JOIN consultations c ON a.id = c.appointment_id
        WHERE a.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header("Location: view-reports.php");
    exit();
}

// Fetch prescriptions if any
$pres_sql = "SELECT * FROM prescriptions WHERE consultation_id = ?";
$pres_stmt = $conn->prepare($pres_sql);
$pres_stmt->bind_param("i", $appointment['consultation_id']);
$pres_stmt->execute();
$prescriptions = $pres_stmt->get_result();

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
    <title>View Appointment | Admin Dashboard | TeleMed Cameroon</title>
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

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-check"></i> Appointment Details</h1>
            <a href="view-reports.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Reports</a>
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
                <?php if($appointment['consultation_status']): ?>
                <span class="status-badge status-<?php echo $appointment['consultation_status']; ?>" style="margin-left: 10px;">
                    <i class="fas fa-video"></i>
                    Consultation: <?php echo ucfirst($appointment['consultation_status']); ?>
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
                </div>
            </div>
        </div>

        <!-- Doctor Information -->
        <div class="info-card">
            <div class="card-header">
                <h3><i class="fas fa-user-md"></i> Doctor Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">Dr. <?php echo safeHtml($appointment['doctor_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Specialty</div>
                        <div class="info-value"><?php echo safeHtml($appointment['doctor_specialty']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo safeHtml($appointment['doctor_email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo safeHtml($appointment['doctor_phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo safeHtml($appointment['doctor_location']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultation Details (if consultation exists) -->
        <?php if($appointment['consultation_id']): ?>
        <div class="info-card">
            <div class="card-header">
                <h3><i class="fas fa-video"></i> Consultation Details</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Consultation ID</div>
                        <div class="info-value">#<?php echo $appointment['consultation_id']; ?></div>
                    </div>
                    <?php if($appointment['start_time']): ?>
                    <div class="info-item">
                        <div class="info-label">Started At</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['start_time'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($appointment['end_time']): ?>
                    <div class="info-item">
                        <div class="info-label">Ended At</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['end_time'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Doctor's Notes -->
                <?php
                $notes_sql = "SELECT notes FROM consultation_notes WHERE consultation_id = ? ORDER BY created_at DESC LIMIT 1";
                $notes_stmt = $conn->prepare($notes_sql);
                $notes_stmt->bind_param("i", $appointment['consultation_id']);
                $notes_stmt->execute();
                $notes_result = $notes_stmt->get_result();
                $notes = $notes_result->fetch_assoc();
                ?>
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
            <a href="view-reports.php" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> View All Appointments
            </a>
            <?php if($appointment['status'] == 'pending'): ?>
            <a href="approve-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary" onclick="return confirm('Approve this appointment?')">
                <i class="fas fa-check"></i> Approve Appointment
            </a>
            <a href="reject-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-danger" onclick="return confirm('Reject this appointment?')">
                <i class="fas fa-times"></i> Reject Appointment
            </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>