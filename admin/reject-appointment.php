<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

if (isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
    
    // First, get appointment details for logging
    $get_sql = "SELECT a.*, d.name as doctor_name, p.name as patient_name 
                FROM appointments a
                JOIN users d ON a.doctor_id = d.id
                JOIN users p ON a.patient_id = p.id
                WHERE a.id = ?";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("i", $appointment_id);
    $get_stmt->execute();
    $appointment = $get_stmt->get_result()->fetch_assoc();
    
    if ($appointment) {
        // Update appointment status to cancelled
        $sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment #" . $appointment_id . " has been rejected/cancelled.";
        } else {
            $_SESSION['error'] = "Failed to reject appointment: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Appointment not found.";
    }
}

// Redirect back to the referring page or admin dashboard
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin-dashboard.php';
header("Location: " . $redirect_url);
exit();
?>