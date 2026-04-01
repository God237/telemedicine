<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT * FROM contact_messages WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND status = '$status_filter'";
}

if (!empty($search_query)) {
    $sql .= " AND (fullname LIKE '%$search_query%' OR email LIKE '%$search_query%' OR subject LIKE '%$search_query%')";
}

$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              COUNT(CASE WHEN status = 'unread' THEN 1 END) as unread,
              COUNT(CASE WHEN status = 'read' THEN 1 END) as read_count,
              COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied
              FROM contact_messages";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }
        body {
            background: #f4f6f9;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 1.8rem;
            color: #1a73e8;
        }
        .filters {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .message-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        .message-card.unread {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .message-subject {
            font-weight: bold;
            font-size: 1.1rem;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        .badge-unread { background: #dc3545; color: white; }
        .badge-read { background: #6c757d; color: white; }
        .badge-replied { background: #28a745; color: white; }
        .btn-mark-read {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope"></i> Contact Messages</h1>
            <a href="admin-dashboard.php" class="btn">← Back to Dashboard</a>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Messages</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['unread']; ?></h3>
                <p>Unread</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['read_count']; ?></h3>
                <p>Read</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['replied']; ?></h3>
                <p>Replied</p>
            </div>
        </div>
        
        <div class="filters">
            <select onchange="window.location.href='?status='+this.value">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                <option value="unread" <?php echo $status_filter == 'unread' ? 'selected' : ''; ?>>Unread</option>
                <option value="read" <?php echo $status_filter == 'read' ? 'selected' : ''; ?>>Read</option>
                <option value="replied" <?php echo $status_filter == 'replied' ? 'selected' : ''; ?>>Replied</option>
            </select>
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Search</button>
            </form>
        </div>
        
        <?php while($msg = $result->fetch_assoc()): ?>
        <div class="message-card <?php echo $msg['status']; ?>">
            <div class="message-header">
                <div>
                    <div class="message-subject"><?php echo htmlspecialchars($msg['subject'] ?: 'No Subject'); ?></div>
                    <div><strong>From:</strong> <?php echo htmlspecialchars($msg['fullname']); ?> (<?php echo htmlspecialchars($msg['email']); ?>)</div>
                    <div><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                </div>
                <div>
                    <span class="badge badge-<?php echo $msg['status']; ?>"><?php echo ucfirst($msg['status']); ?></span>
                    <?php if($msg['status'] == 'unread'): ?>
                    <button class="btn-mark-read" onclick="markAsRead(<?php echo $msg['id']; ?>)">Mark as Read</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="message-content">
                <strong>Message:</strong>
                <p style="margin-top: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    
    <script>
        async function markAsRead(id) {
            const response = await fetch('../api/mark-message-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            if (response.ok) {
                location.reload();
            }
        }
    </script>
</body>
</html>