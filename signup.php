<?php
require_once 'config.php';

$error_message = '';
$success_message = '';

// Define Douala area coordinates
$area_coordinates = [
    'Akwa' => ['lat' => 4.0469, 'lng' => 9.7043],
    'Bonanjo' => ['lat' => 4.0500, 'lng' => 9.7042],
    'Bonaberi' => ['lat' => 4.0833, 'lng' => 9.6833],
    'Bonamoussadi' => ['lat' => 4.0833, 'lng' => 9.7167],
    'New Bell' => ['lat' => 4.0417, 'lng' => 9.6958],
    'Deido' => ['lat' => 4.0458, 'lng' => 9.6896],
    'Makepe' => ['lat' => 4.0708, 'lng' => 9.6833],
    'Tsinga' => ['lat' => 4.0542, 'lng' => 9.7125],
    'Damas' => ['lat' => 4.0479, 'lng' => 9.7083],
    'Logbessou' => ['lat' => 4.0972, 'lng' => 9.7167]
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmpassword'] ?? '';
    $role = $_POST['role'] ?? '';
    $area = $_POST['area'] ?? '';

    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword) || empty($role)) {
        $error_message = "Please fill in all required fields";
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    }
    elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long";
    }
    elseif ($password !== $confirmPassword) {
        $error_message = "Passwords do not match";
    } 
    else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Email already exists. Please use a different email or login.";
        } else {
            // Set status based on role
            $status = ($role == 'doctor') ? 'pending' : 'approved';
            
            // Get coordinates for the area
            $latitude = null;
            $longitude = null;
            if ($role == 'doctor' && !empty($area) && isset($area_coordinates[$area])) {
                $latitude = $area_coordinates[$area]['lat'];
                $longitude = $area_coordinates[$area]['lng'];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into database
            $sql = "INSERT INTO users (name, email, password, role, area, status, latitude, longitude, city) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Douala')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssdd", $name, $email, $hashedPassword, $role, $area, $status, $latitude, $longitude);

            if ($stmt->execute()) {
                if ($role == 'doctor') {
                    $success_message = "Registration successful! Your account is pending admin approval. You will be notified once approved.";
                } else {
                    $success_message = "Registration successful! Please login to continue.";
                    header("refresh:2;url=login.php");
                }
                // Clear form data
                $name = $email = $area = '';
            } else {
                $error_message = "Error: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup | Telemed Connect</title>
    <link rel="stylesheet" href="login-signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="signup-container">
    <div class="signup-card">
        <h2>Create Account</h2>
        <p>Join Telemed Connect and start your virtual healthcare journey.</p>

        <form method="POST" onsubmit="return validatePassword()">

            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="name" id="fullname" required>
            </div>

            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" id="email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password"  required>
                    <i class="fa-solid fa-eye" id="togglePassword"  style="cursor:pointer;"></i>
                </div>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirmpassword" name="confirmpassword"  required>
                    <i class="fa-solid fa-eye" id="toggleConfirmPassword"  style="cursor:pointer;"></i>
                </div>
            </div>

            <div class="input-group">
                <label>Register As</label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="patient">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="input-group">
                <label>Area (Douala)</label>
                <select name="area" required>
                    <option value="">Select Location</option>
                    <option value="Bonamoussadi">Bonamoussadi</option>
                    <option value="Akwa">Akwa</option>
                    <option value="Bonaberi">Bonaberi</option>
                    <option value="New Bell">New Bell</option>
                    <option value="Deido">Deido</option>
                    <option value="Makepe">Makepe</option>
                </select>
            </div>

            <button type="submit" class="signup-btn">Sign Up</button>

            <p class="login-link">
                Already have an account? <a href="login.php">Login</a>
            </p>

        </form>
    </div>
</div>

<script>
function validatePassword() {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmpassword").value;

    if (password !== confirmPassword) {
        alert("Passwords do not match");
        return false;
    }

    return true;
}
</script>

<script>
function setupPasswordToggle(inputId, toggleId) {
    const input = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);

    toggle.addEventListener("click", () => {
        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";

        toggle.classList.toggle("fa-eye");
        toggle.classList.toggle("fa-eye-slash");
    });
}

// Initialize both fields
setupPasswordToggle("password", "togglePassword");
setupPasswordToggle("confirmpassword", "toggleConfirmPassword");
</script>

</body>
</html>
