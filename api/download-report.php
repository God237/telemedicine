<?php
session_start();

require_once dirname(__DIR__) . '/config.php';

$consultation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$consultation_id) {
    die('Invalid consultation ID');
}

// Fetch consultation details
$sql = "SELECT c.*, 
        a.appointment_date,
        a.appointment_time,
        a.consultation_type,
        a.reason,
        p.name as patient_name,
        p.email as patient_email,
        p.phone as patient_phone,
        d.name as doctor_name,
        cn.notes
        FROM consultations c
        JOIN appointments a ON c.appointment_id = a.id
        JOIN users p ON c.patient_id = p.id
        JOIN users d ON c.doctor_id = d.id
        LEFT JOIN consultation_notes cn ON c.id = cn.consultation_id
        WHERE c.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $consultation_id);
$stmt->execute();
$consultation = $stmt->get_result()->fetch_assoc();

if (!$consultation) {
    die('Report not found');
}

// Fetch prescriptions
$pres_sql = "SELECT * FROM prescriptions WHERE consultation_id = ?";
$pres_stmt = $conn->prepare($pres_sql);
$pres_stmt->bind_param("i", $consultation_id);
$pres_stmt->execute();
$prescriptions = $pres_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set headers for download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="medical_report_' . $consultation_id . '_' . date('Y-m-d') . '.html"');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Medical Report - TeleMed Cameroon</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2b7a8a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h3 {
            color: #2b7a8a;
            border-left: 4px solid #2b7a8a;
            padding-left: 10px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f8f9fa;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>TeleMed Cameroon</h1>
        <h2>Medical Consultation Report</h2>
        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
    </div>
    
    <div class="section">
        <h3>Patient Information</h3>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($consultation['patient_name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($consultation['patient_email']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($consultation['patient_phone'] ?? 'Not provided'); ?></p>
        <p><strong>Consultation Date:</strong> <?php echo date('F j, Y', strtotime($consultation['end_time'])); ?></p>
    </div>
    
    <div class="section">
        <h3>Consultation Details</h3>
        <p><strong>Type:</strong> <?php echo ucfirst($consultation['consultation_type']); ?></p>
        <p><strong>Reason for Consultation:</strong> <?php echo htmlspecialchars($consultation['reason']); ?></p>
        <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($consultation['doctor_name']); ?></p>
    </div>
    
    <div class="section">
        <h3>Doctor's Notes & Diagnosis</h3>
        <p><?php echo nl2br(htmlspecialchars($consultation['notes'] ?? 'No notes recorded.')); ?></p>
    </div>
    
    <div class="section">
        <h3>Prescriptions</h3>
        <?php if(count($prescriptions) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Duration</th>
                    <th>Instructions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($prescriptions as $pres): ?>
                <tr>
                    <td><?php echo htmlspecialchars($pres['medication_name']); ?></td>
                    <td><?php echo htmlspecialchars($pres['dosage']); ?></td>
                    <td><?php echo htmlspecialchars($pres['duration']); ?></td>
                    <td><?php echo htmlspecialchars($pres['instructions']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No prescriptions issued during this consultation.</p>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>This is an electronically generated medical report from TeleMed Cameroon.</p>
        <p>For any questions, please contact your healthcare provider.</p>
    </div>
</body>
</html>
<?php
exit();
?>