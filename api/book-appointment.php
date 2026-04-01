<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    echo json_encode(['success' => false, 'message' => 'Please login to book appointment']);
    exit();
}

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Validate required fields
$required = ['doctor_id', 'appointment_date', 'appointment_time', 'consultation_type', 'reason'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

// Get patient ID from session
$patient_id = $_SESSION['user_id'];
$doctor_id = $data['doctor_id'];
$appointment_date = $data['appointment_date'];
$appointment_time = $data['appointment_time'];
$consultation_type = $data['consultation_type'];
$reason = $data['reason'];
$notes = $data['notes'] ?? '';

// Check if doctor exists
$check_doctor = "SELECT id FROM users WHERE id = ? AND role = 'doctor'";
$stmt = $conn->prepare($check_doctor);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit();
}

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

// Insert appointment
$insert_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, consultation_type, reason, notes, status, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("iisssss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $consultation_type, $reason, $notes);

if ($stmt->execute()) {
    $appointment_id = $conn->insert_id;
    
    // You can add email notification here
    // mail($patient_email, "Appointment Confirmation", "Your appointment has been booked successfully");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Appointment booked successfully',
        'appointment_id' => $appointment_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $conn->error]);
}
?>