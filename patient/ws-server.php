<?php
// This is a simple WebSocket server for WebRTC signaling
// In production, you'd use Ratchet or similar, but for simplicity, we'll use a polling approach

header('Content-Type: application/json');
session_start();

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Store signaling data in session (for demo)
if (!isset($_SESSION['signaling_data'])) {
    $_SESSION['signaling_data'] = [];
}

switch($action) {
    case 'send_offer':
        $room = $_POST['room'];
        $offer = $_POST['offer'];
        $_SESSION['signaling_data'][$room]['offer'] = $offer;
        $_SESSION['signaling_data'][$room]['timestamp'] = time();
        echo json_encode(['success' => true]);
        break;
        
    case 'get_offer':
        $room = $_POST['room'];
        if (isset($_SESSION['signaling_data'][$room]['offer'])) {
            echo json_encode([
                'success' => true,
                'offer' => $_SESSION['signaling_data'][$room]['offer']
            ]);
            // Clear after sending
            unset($_SESSION['signaling_data'][$room]['offer']);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'send_answer':
        $room = $_POST['room'];
        $answer = $_POST['answer'];
        $_SESSION['signaling_data'][$room]['answer'] = $answer;
        echo json_encode(['success' => true]);
        break;
        
    case 'get_answer':
        $room = $_POST['room'];
        if (isset($_SESSION['signaling_data'][$room]['answer'])) {
            echo json_encode([
                'success' => true,
                'answer' => $_SESSION['signaling_data'][$room]['answer']
            ]);
            unset($_SESSION['signaling_data'][$room]['answer']);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'send_ice':
        $room = $_POST['room'];
        $candidate = $_POST['candidate'];
        if (!isset($_SESSION['signaling_data'][$room]['ice_candidates'])) {
            $_SESSION['signaling_data'][$room]['ice_candidates'] = [];
        }
        $_SESSION['signaling_data'][$room]['ice_candidates'][] = $candidate;
        echo json_encode(['success' => true]);
        break;
        
    case 'get_ice':
        $room = $_POST['room'];
        if (isset($_SESSION['signaling_data'][$room]['ice_candidates'])) {
            $candidates = $_SESSION['signaling_data'][$room]['ice_candidates'];
            $_SESSION['signaling_data'][$room]['ice_candidates'] = [];
            echo json_encode(['success' => true, 'candidates' => $candidates]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
}
?>