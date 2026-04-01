<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

$patient_id = $_SESSION['user_id'];

$sql = "SELECT a.*, d.name as doctor_name, d.specialty as doctor_specialty, d.location as doctor_location
        FROM appointments a
        JOIN users d ON a.doctor_id = d.id
        WHERE a.id = ? AND a.patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if ($appointment) {
    echo json_encode($appointment);
} else {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
}
?>