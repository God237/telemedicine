
<?php
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data safely
    $name = $_POST['name'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmpassword'] ?? '';
    $role = $_POST['role'] ?? '';
    $area = $_POST['area'] ?? '';

    // Validate passwords match
    if ($password !== $confirmPassword) {
        echo "<script>alert('Passwords do not match');</script>";
    } else {

        // Check if email already exists
        $check = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($check);

        if ($result->num_rows > 0) {
            echo "<script>alert('Email already exists');</script>";
        } else {

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into database
            $sql = "INSERT INTO users (name, email, password, role, area)
                    VALUES ('$name', '$email', '$hashedPassword', '$role', '$area')";

            if ($conn->query($sql) === TRUE) {

                // Redirect AFTER success (no echo before header)
                header("Location: login.php");
                exit();

            } else {
                echo "Error: " . $conn->error;
            }
        }
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
