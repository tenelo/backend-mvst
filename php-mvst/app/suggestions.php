<?php
// suggestions.php
// Méthode : POST JSON
// Body : { "nomClient": "...", "telephoneClient": "...", "suggestion": "..." }
// Retourne : { "success": true, "message": "Suggestion enregistrée" }

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['nomClient']) || !isset($data['telephoneClient']) || !isset($data['suggestion'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $nomClient       = $data['nomClient'];
    $telephoneClient = $data['telephoneClient'];
    $suggestion      = $data['suggestion'];

    $sql = "INSERT INTO \"Suggestions\" (\"nomClient\", \"telephoneClient\", suggestion)
            VALUES (:nomClient, :telephoneClient, :suggestion)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nomClient'       => $nomClient,
        ':telephoneClient' => $telephoneClient,
        ':suggestion'      => $suggestion,
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Suggestion enregistrée avec succès"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erreur : " . $e->getMessage()
    ]);
}
?>
