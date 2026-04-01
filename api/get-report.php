<?php
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config.php';

$consultation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$consultation_id) {
    echo json_encode(['error' => 'Invalid consultation ID']);
    exit();
}

// Fetch consultation details
$sql = "SELECT c.*, 
        a.appointment_date,
        a.appointment_time,
        a.consultation_type,
        a.reason,
        p.name as patient_name,
        p.email as patient_email,
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
    echo json_encode(['error' => 'Report not found']);
    exit();
}

// Fetch prescriptions
$pres_sql = "SELECT * FROM prescriptions WHERE consultation_id = ?";
$pres_stmt = $conn->prepare($pres_sql);
$pres_stmt->bind_param("i", $consultation_id);
$pres_stmt->execute();
$prescriptions = $pres_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$report = [
    'id' => $consultation['id'],
    'patient_name' => $consultation['patient_name'],
    'patient_email' => $consultation['patient_email'],
    'doctor_name' => $consultation['doctor_name'],
    'consultation_date' => $consultation['end_time'],
    'consultation_type' => $consultation['consultation_type'],
    'reason' => $consultation['reason'],
    'notes' => $consultation['notes'],
    'prescriptions' => $prescriptions
];

echo json_encode($report);
?>