<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch data for report
// 1. Users data
$users_sql = "SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);

// 2. Appointments data
$appointments_sql = "SELECT a.*, 
                     p.name as patient_name, 
                     d.name as doctor_name 
                     FROM appointments a
                     LEFT JOIN users p ON a.patient_id = p.id
                     LEFT JOIN users d ON a.doctor_id = d.id
                     ORDER BY a.created_at DESC";
$appointments_result = $conn->query($appointments_sql);

// 3. Consultations data
$consultations_sql = "SELECT c.*, 
                      p.name as patient_name, 
                      d.name as doctor_name 
                      FROM consultations c
                      LEFT JOIN users p ON c.patient_id = p.id
                      LEFT JOIN users d ON c.doctor_id = d.id
                      ORDER BY c.created_at DESC";
$consultations_result = $conn->query($consultations_sql);

// 4. Statistics
$stats_sql = "SELECT 
              (SELECT COUNT(*) FROM users WHERE role = 'patient') as total_patients,
              (SELECT COUNT(*) FROM users WHERE role = 'doctor') as total_doctors,
              (SELECT COUNT(*) FROM users WHERE role = 'doctor' AND status = 'pending') as pending_doctors,
              (SELECT COUNT(*) FROM appointments) as total_appointments,
              (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_appointments,
              (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
              (SELECT COUNT(*) FROM consultations) as total_consultations";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

if ($format == 'pdf') {
    // Generate PDF report
    generatePDFReport($users_result, $appointments_result, $consultations_result, $stats, $start_date, $end_date);
} else {
    // Generate CSV report
    generateCSVReport($users_result, $appointments_result, $consultations_result, $stats, $start_date, $end_date);
}

function generateCSVReport($users, $appointments, $consultations, $stats, $start_date, $end_date) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="telemed_report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add report header
    fputcsv($output, ['TELEMED CAMEROON - ADMIN REPORT']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Date Range: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, []);
    
    // Add statistics section
    fputcsv($output, ['=== STATISTICS ===']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Patients', $stats['total_patients']]);
    fputcsv($output, ['Total Doctors', $stats['total_doctors']]);
    fputcsv($output, ['Pending Doctors', $stats['pending_doctors']]);
    fputcsv($output, ['Total Appointments', $stats['total_appointments']]);
    fputcsv($output, ['Completed Appointments', $stats['completed_appointments']]);
    fputcsv($output, ['Pending Appointments', $stats['pending_appointments']]);
    fputcsv($output, ['Total Consultations', $stats['total_consultations']]);
    fputcsv($output, []);
    
    // Users section
    fputcsv($output, ['=== USERS LIST ===']);
    fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Status', 'Registered Date']);
    while($row = $users->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['role'],
            $row['status'] ?? 'active',
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fputcsv($output, []);
    
    // Appointments section
    fputcsv($output, ['=== APPOINTMENTS LIST ===']);
    fputcsv($output, ['ID', 'Patient', 'Doctor', 'Date', 'Time', 'Type', 'Status', 'Reason', 'Created At']);
    
    // Reset appointment result pointer
    $appointments->data_seek(0);
    while($row = $appointments->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['patient_name'] ?? 'N/A',
            $row['doctor_name'] ?? 'N/A',
            $row['appointment_date'],
            $row['appointment_time'],
            $row['consultation_type'],
            $row['status'],
            $row['reason'],
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fputcsv($output, []);
    
    // Consultations section
    fputcsv($output, ['=== CONSULTATIONS LIST ===']);
    fputcsv($output, ['ID', 'Patient', 'Doctor', 'Type', 'Status', 'Start Time', 'End Time']);
    
    // Reset consultation result pointer
    $consultations->data_seek(0);
    while($row = $consultations->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['patient_name'] ?? 'N/A',
            $row['doctor_name'] ?? 'N/A',
            $row['consultation_type'],
            $row['status'],
            $row['start_time'],
            $row['end_time']
        ]);
    }
    
    fclose($output);
}

function generatePDFReport($users, $appointments, $consultations, $stats, $start_date, $end_date) {
    // Since PDF generation requires additional libraries (like TCPDF or FPDF),
    // we'll create an HTML report that can be printed to PDF
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>TeleMed Cameroon - Admin Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 40px;
                line-height: 1.6;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #2b7a8a;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .header h1 {
                color: #2b7a8a;
                margin-bottom: 5px;
            }
            .section {
                margin-bottom: 30px;
            }
            .section h2 {
                background: #2b7a8a;
                color: white;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background: #f4f6f9;
                font-weight: bold;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }
            .stat-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                border: 1px solid #dee2e6;
            }
            .stat-card .number {
                font-size: 24px;
                font-weight: bold;
                color: #2b7a8a;
            }
            .footer {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
                font-size: 12px;
                color: #6c757d;
            }
            @media print {
                body {
                    margin: 20px;
                }
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>TeleMed Connect</h1>
            <h2>Administrative Report</h2>
            <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
            <p>Report Period: <?php echo date('F j, Y', strtotime($start_date)); ?> - <?php echo date('F j, Y', strtotime($end_date)); ?></p>
        </div>

        <div class="section">
            <h2>Platform Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_patients']; ?></div>
                    <div>Total Patients</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_doctors']; ?></div>
                    <div>Total Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['pending_doctors']; ?></div>
                    <div>Pending Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_appointments']; ?></div>
                    <div>Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['completed_appointments']; ?></div>
                    <div>Completed Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['pending_appointments']; ?></div>
                    <div>Pending Appointments</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Users List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo ucfirst($row['role']); ?></td>
                        <td><?php echo isset($row['status']) ? ucfirst($row['status']) : 'Active'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Appointments List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $appointments->data_seek(0);
                    while($row = $appointments->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['doctor_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $row['appointment_date']; ?></td>
                        <td><?php echo $row['appointment_time']; ?></td>
                        <td><?php echo ucfirst($row['consultation_type']); ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Consultations List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $consultations->data_seek(0);
                    while($row = $consultations->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['doctor_name'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($row['consultation_type']); ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td><?php echo $row['start_time']; ?></td>
                        <td><?php echo $row['end_time']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>This is an electronically generated report from TeleMed Connect Admin Dashboard.</p>
            <p>© <?php echo date('Y'); ?> TeleMed Connect - All Rights Reserved</p>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #2b7a8a; color: white; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <button onclick="window.location.href='admin-dashboard.php'" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </button>
        </div>
    </body>
    </html>
    <?php
}
?>