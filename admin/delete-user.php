<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'permanent';
    
    // Get user details for message
    $name_sql = "SELECT name, role FROM users WHERE id = ?";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->bind_param("i", $user_id);
    $name_stmt->execute();
    $user = $name_stmt->get_result()->fetch_assoc();
    
    if ($user) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete related data based on user role
            if ($user['role'] == 'doctor') {
                // Delete doctor's appointments
                $delete_appointments = "DELETE FROM appointments WHERE doctor_id = ?";
                $stmt = $conn->prepare($delete_appointments);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            } elseif ($user['role'] == 'patient') {
                // Delete patient's appointments
                $delete_appointments = "DELETE FROM appointments WHERE patient_id = ?";
                $stmt = $conn->prepare($delete_appointments);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }
            
            // Delete consultations related to user
            $delete_consultations = "DELETE FROM consultations WHERE patient_id = ? OR doctor_id = ?";
            $stmt = $conn->prepare($delete_consultations);
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            
            // Delete consultation messages
            $delete_messages = "DELETE FROM consultation_messages WHERE sender_id = ?";
            $stmt = $conn->prepare($delete_messages);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Delete consultation medical data
            $delete_medical = "DELETE FROM consultation_medical_data WHERE consultation_id IN (SELECT id FROM consultations WHERE patient_id = ? OR doctor_id = ?)";
            $stmt = $conn->prepare($delete_medical);
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            
            // Finally delete the user
            $delete_user = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($delete_user);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $prefix = ($user['role'] == 'doctor') ? 'Dr. ' : '';
            $_SESSION['success'] = $prefix . $user['name'] . " has been permanently deleted from the system.";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "User not found.";
    }
}

header("Location: remove-accounts.php");
exit();
?>