<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$prescription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient_id = $_SESSION['user_id'];

$sql = "SELECT p.*, 
        d.name as doctor_name,
        d.specialty as doctor_specialty,
        c.consultation_type,
        c.end_time as consultation_date,
        u.name as patient_name
        FROM prescriptions p
        JOIN users d ON p.doctor_id = d.id
        JOIN consultations c ON p.consultation_id = c.id
        JOIN users u ON p.patient_id = u.id
        WHERE p.id = ? AND p.patient_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $prescription_id, $patient_id);
$stmt->execute();
$prescription = $stmt->get_result()->fetch_assoc();

if (!$prescription) {
    die('Prescription not found');
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="prescription_' . $prescription_id . '_' . date('Y-m-d') . '.html"');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prescription - TeleMed Cameroon</title>
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
        <h2>Prescription Document</h2>
        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
    </div>
    
    <div class="section">
        <h3>Patient Information</h3>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($prescription['patient_name']); ?></p>
        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($prescription['created_at'])); ?></p>
    </div>
    
    <div class="section">
        <h3>Prescribed By</h3>
        <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
        <p><strong>Specialty:</strong> <?php echo htmlspecialchars($prescription['doctor_specialty']); ?></p>
    </div>
    
    <div class="section">
        <h3>Medication Details</h3>
        <p><strong>Medication:</strong> <?php echo htmlspecialchars($prescription['medication_name']); ?></p>
        <p><strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?></p>
        <p><strong>Duration:</strong> <?php echo htmlspecialchars($prescription['duration']); ?></p>
        <?php if(!empty($prescription['instructions'])): ?>
        <p><strong>Instructions:</strong> <?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>This is an electronically generated prescription from TeleMed Cameroon.</p>
        <p>Please follow the dosage and instructions as prescribed by your doctor.</p>
    </div>
</body>
</html>
<?php
exit();
?>