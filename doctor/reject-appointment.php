<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "doctor") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

if (isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    $doctor_id = $_SESSION['user_id'];
    
    // First verify this appointment belongs to this doctor
    $check_sql = "SELECT id FROM appointments WHERE id = ? AND doctor_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $appointment_id, $doctor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update appointment status to cancelled
        $sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment rejected and cancelled successfully.";
        } else {
            $_SESSION['error'] = "Failed to reject appointment: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "You do not have permission to reject this appointment.";
    }
}

header("Location: doctor-dashboard.php");
exit();
?>