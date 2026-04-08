
<!-- 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Telemed Connect</title>
    <link rel="stylesheet" href="login-signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
   
    <div class="login-card">

        <h2>Login</h2>

        <form action="login.php" method="POST">

            <div class="input-group">
                <input type="email" name="email" id="email" placeholder="Email" required>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="fa-solid fa-eye" id="togglePassword"  style="cursor:pointer;"></i>
            </div>

            <div class="input-group">
                <select name="role" id="role" required>
                    <option value="" disabled selected>select role</option>
                    <option value="patient">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Admin</option>
                </select>
            </div> 
            
            <button type="submit" class="login-btn">Login</button>
            <p class="login-link">Don't have an account yet ? <a href="signup.php">Signup</a></p>
        </form>
    </div>
    

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

setupPasswordToggle("password", "togglePassword");
</script>

</body>
</html> --> 


<?php
session_start();
include "config.php";

$error_message = '';
$email = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize and validate inputs
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    // Validate email format
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } 
    // Check if all fields are provided
    elseif (empty($email) || empty($password) || empty($role)) {
        $error_message = "Please fill in all fields";
    } else {

        // Use prepared statement to prevent SQL injection
        $sql = "SELECT id, name, email, password, role FROM users WHERE email = ? AND role = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ss", $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Successful login
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Role-based redirection
                    switch ($role) {
                        case "patient":
                            header("Location: patient/patient-dashboard.php");
                            break;
                        case "doctor":
                            header("Location: doctor/doctor-dashboard.php");
                            break;
                        case "admin":
                            header("Location: admin/admin-dashboard.php");
                            break;
                        default:
                            header("Location: login.php");
                    }
                    exit();
                    
                } else {
                    // Generic error message for incorrect password
                    $error_message = "Invalid email or password";
                }
            } else {
                // Generic error message for user not found
                $error_message = "Invalid email or password";
            }
            $stmt->close();
        } else {
            $error_message = "An error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Telemed Connect</title>
    <link rel="stylesheet" href="./login-signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </head>
<body>
   
    <div class="login-card">
        <h2>Login</h2>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm">
            
            <div class="input-group">
                <input type="email" name="email" id="email" placeholder="Email" 
                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" 
                       required>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="fa-solid fa-eye" id="togglePassword" style="cursor:pointer;"></i>
            </div>

            <div class="input-group">
                <select name="role" id="role" required>
                    <option value="" disabled selected>select role</option>
                    <option value="patient">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Admin</option>
                </select>
            </div> 
            
            <button type="submit" class="login-btn">Login</button>
            <p class="login-link">Don't have an account yet ? <a href="signup.php">Signup</a></p>
        </form>
    </div>

<script>
    // Password visibility toggle
    function setupPasswordToggle(inputId, toggleId) {
        const input = document.getElementById(inputId);
        const toggle = document.getElementById(toggleId);

        if (input && toggle) {
            toggle.addEventListener("click", () => {
                const isPassword = input.type === "password";
                input.type = isPassword ? "text" : "password";
                toggle.classList.toggle("fa-eye");
                toggle.classList.toggle("fa-eye-slash");
            });
        }
    }

    setupPasswordToggle("password", "togglePassword");
    
    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const role = document.getElementById('role').value;
        const loginBtn = document.querySelector('.login-btn');
        
        if (!email || !password || !role) {
            e.preventDefault();
            alert('Please fill in all fields');
            return false;
        }
        
        // Email format validation
        const emailPattern = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
        if (!emailPattern.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
        
        // Disable button to prevent double submission
        loginBtn.disabled = true;
        loginBtn.textContent = 'Logging in...';
    });
</script>

</body>
</html>