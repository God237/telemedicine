<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$appointment_id = $data['appointment_id'] ?? 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

$patient_id = $_SESSION['user_id'];

$sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Unable to cancel appointment']);
}
?>