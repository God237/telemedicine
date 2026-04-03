<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    echo json_encode(['success' => false, 'message' => 'Please login to book appointment']);
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit();
}

// Validate required fields
$doctor_id = isset($data['doctor_id']) ? intval($data['doctor_id']) : 0;
$appointment_date = isset($data['appointment_date']) ? $data['appointment_date'] : '';
$appointment_time = isset($data['appointment_time']) ? $data['appointment_time'] : '';
$consultation_type = isset($data['consultation_type']) ? $data['consultation_type'] : '';
$reason = isset($data['reason']) ? trim($data['reason']) : '';

if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($consultation_type) || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

// Get patient ID from session
$patient_id = $_SESSION['user_id'];

// Check if doctor exists
$check_doctor = "SELECT id, name FROM users WHERE id = ? AND role = 'doctor'";
$stmt = $conn->prepare($check_doctor);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit();
}

$doctor = $result->fetch_assoc();

// Check if appointment slot is available
$check_slot = "SELECT id FROM appointments 
               WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
               AND status NOT IN ('cancelled', 'completed')";
$stmt = $conn->prepare($check_slot);
$stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please choose another time.']);
    exit();
}

// Insert appointment with status 'pending'
$insert_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, consultation_type, reason, status, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("iissss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $consultation_type, $reason);

if ($stmt->execute()) {
    $appointment_id = $conn->insert_id;
    
    // Also store in session for debugging
    $_SESSION['last_booked_appointment'] = [
        'id' => $appointment_id,
        'doctor_id' => $doctor_id,
        'doctor_name' => $doctor['name'],
        'date' => $appointment_date,
        'time' => $appointment_time,
        'status' => 'pending'
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Appointment booked successfully! Waiting for doctor approval.',
        'appointment_id' => $appointment_id,
        'status' => 'pending'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>