<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'lire':
            $stmt = $conn->prepare('SELECT valeur FROM "DatesDisponibles" WHERE cle = :cle');
            $stmt->execute(['cle' => 'nbJours']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'nbJours' => (int)$result['valeur']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'nbJours' => 6
                ]);
            }
            break;

        case 'sauvegarder':
            if (!isset($input['nbJours']) || !is_numeric($input['nbJours'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Valeur nbJours invalide'
                ]);
                exit;
            }

            $nbJours = (int)$input['nbJours'];
            
            if ($nbJours < 1 || $nbJours > 30) {
                echo json_encode([
                    'success' => false,
                    'message' => 'La valeur doit être entre 1 et 30'
                ]);
                exit;
            }

            $stmt = $conn->prepare('
                INSERT INTO "DatesDisponibles" (cle, valeur) 
                VALUES (:cle, :valeur) 
                ON CONFLICT (cle) 
                DO UPDATE SET valeur = :valeur2, date_modification = CURRENT_TIMESTAMP
            ');
            $stmt->execute([
                'cle' => 'nbJours',
                'valeur' => $nbJours,
                'valeur2' => $nbJours
            ]);

            // Notification au serveur Node.js via le réseau Docker
            $nodeUrl = "http://socket-mvst:3000/emit-config-dates?nbJours=" . $nbJours;
            @file_get_contents($nodeUrl);

            echo json_encode([
                'success' => true,
                'message' => 'Configuration mise à jour',
                'nbJours' => $nbJours
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
    }
} catch (Exception $e) {
    error_log("Erreur datesDisponibles.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}
?>
