<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "doctor") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

if (isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    $doctor_id = $_SESSION['user_id'];
    
    // Update appointment status
    $sql = "UPDATE appointments SET status = 'approved' WHERE id = ? AND doctor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $appointment_id, $doctor_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Appointment approved successfully";
    } else {
        $_SESSION['error'] = "Failed to approve appointment";
    }
}

header("Location: doctor-dashboard.php");
exit();
?>