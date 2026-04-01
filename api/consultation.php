<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__) . '/config.php';

class ConsultationAPI {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Save chat message
    public function saveMessage($consultation_id, $sender_id, $sender_type, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consultation_messages (consultation_id, sender_id, sender_type, message)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$consultation_id, $sender_id, $sender_type, $message]);
            return ['success' => true, 'message_id' => $this->pdo->lastInsertId()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Get consultation messages
    public function getMessages($consultation_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM consultation_messages 
                WHERE consultation_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$consultation_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    // Save consultation notes
    public function saveNotes($consultation_id, $notes, $doctor_id) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consultation_notes (consultation_id, doctor_id, notes) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE notes = ?, updated_at = NOW()
            ");
            $stmt->execute([$consultation_id, $doctor_id, $notes, $notes]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Complete consultation
    public function completeConsultation($consultation_id, $appointment_id, $patient_id, $doctor_id, $notes, $prescriptions) {
        try {
            // Update consultation status
            $stmt = $this->pdo->prepare("
                UPDATE consultations SET status = 'completed', end_time = NOW() WHERE id = ?
            ");
            $stmt->execute([$consultation_id]);
            
            // Update appointment status
            $stmt = $this->pdo->prepare("
                UPDATE appointments SET status = 'completed' WHERE id = ?
            ");
            $stmt->execute([$appointment_id]);
            
            // Save consultation notes
            if (!empty($notes)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO consultation_notes (consultation_id, doctor_id, notes) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$consultation_id, $doctor_id, $notes]);
            }
            
            // Save prescriptions
            foreach ($prescriptions as $pres) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO prescriptions (consultation_id, doctor_id, patient_id, medication_name, dosage, duration, instructions) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $consultation_id, 
                    $doctor_id, 
                    $patient_id, 
                    $pres['medName'], 
                    $pres['dosage'], 
                    $pres['duration'], 
                    $pres['instructions'] ?? ''
                ]);
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

$api = new ConsultationAPI($conn);

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($method) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action == 'message') {
            $result = $api->saveMessage(
                $data['consultation_id'],
                $data['sender_id'],
                $data['sender_type'],
                $data['message']
            );
        } elseif ($action == 'save_notes') {
            $result = $api->saveNotes(
                $data['consultation_id'],
                $data['notes'],
                $data['doctor_id']
            );
        } elseif ($action == 'complete') {
            $result = $api->completeConsultation(
                $data['consultation_id'],
                $data['appointment_id'],
                $data['patient_id'],
                $data['doctor_id'],
                $data['notes'],
                $data['prescriptions']
            );
        }
        echo json_encode($result);
        break;
        
    case 'GET':
        if ($action == 'messages' && isset($_GET['id'])) {
            $result = $api->getMessages($_GET['id']);
            echo json_encode($result);
        }
        break;
}
?>