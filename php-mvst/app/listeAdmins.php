<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $stmt = $conn->prepare('SELECT id, telephone, role, gare, nom, prenoms, mail, "dateDeCreation" FROM "Admins" ORDER BY "dateDeCreation" DESC');
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "admins" => $admins]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
