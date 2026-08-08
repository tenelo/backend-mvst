<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $sql  = "SELECT id, titre, description, lien_image, statut 
             FROM \"Images\" 
             WHERE statut = 'actif' 
             ORDER BY \"dateDeCreation\" DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $images = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "images"  => $images
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erreur : " . $e->getMessage()
    ]);
}
?>
