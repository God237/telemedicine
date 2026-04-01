<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once dirname(__DIR__) . '/config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

// Validate required fields
$fullname = trim($data['fullname'] ?? '');
$email = trim($data['email'] ?? '');
$subject = trim($data['subject'] ?? '');
$message = trim($data['message'] ?? '');

if (empty($fullname) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit();
}

// Save to database
$sql = "INSERT INTO contact_messages (fullname, email, subject, message, status) VALUES (?, ?, ?, ?, 'unread')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $fullname, $email, $subject, $message);

if ($stmt->execute()) {
    $message_id = $conn->insert_id;
    
    // Optional: Send email notification to admin
    $admin_email = "admin@telemed.cm"; // Change to your admin email
    $email_subject = "New Contact Form Message from " . $fullname;
    $email_body = "
        <html>
        <head>
            <title>New Contact Form Message</title>
        </head>
        <body>
            <h2>New Message from TeleMed Cameroon Contact Form</h2>
            <p><strong>Name:</strong> " . htmlspecialchars($fullname) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
            <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            <hr>
            <p><small>Message ID: " . $message_id . " | Sent: " . date('Y-m-d H:i:s') . "</small></p>
        </body>
        </html>
    ";
    
    // Uncomment to enable email notifications (requires mail configuration)
    /*
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    
    mail($admin_email, $email_subject, $email_body, $headers);
    */
    
    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent successfully! We will get back to you soon.',
        'message_id' => $message_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again later.']);
}

$stmt->close();
$conn->close();
?>