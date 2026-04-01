<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Get user details
    $name_sql = "SELECT name, role FROM users WHERE id = ?";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->bind_param("i", $user_id);
    $name_stmt->execute();
    $user = $name_stmt->get_result()->fetch_assoc();
    
    if ($user) {
        // Check if status column exists
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($check_column && $check_column->num_rows > 0) {
            $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $prefix = ($user['role'] == 'doctor') ? 'Dr. ' : '';
                $_SESSION['success'] = $prefix . $user['name'] . " has been suspended. They can be reactivated later.";
            } else {
                $_SESSION['error'] = "Failed to suspend user.";
            }
        } else {
            // If no status column, add it first
            $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
            $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "User suspended successfully.";
            } else {
                $_SESSION['error'] = "Failed to suspend user.";
            }
        }
    } else {
        $_SESSION['error'] = "User not found.";
    }
}

header("Location: remove-accounts.php");
exit();
?>