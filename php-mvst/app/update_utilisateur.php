<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['idUtilisateur'])) {
        echo json_encode(["success" => false, "message" => "ID utilisateur manquant"]);
        exit();
    }

    $idUtilisateur = $data['idUtilisateur'];
    
    // Construire la requête dynamiquement
    $fields = [];
    $params = [':idUtilisateur' => $idUtilisateur];
    
    // Seul ces champs sont modifiables (pas le téléphone)
    if (isset($data['nom'])) {
        $fields[] = 'nom = :nom';
        $params[':nom'] = $data['nom'];
    }
    if (isset($data['prenoms'])) {
        $fields[] = 'prenoms = :prenoms';
        $params[':prenoms'] = $data['prenoms'];
    }
    if (isset($data['residence'])) {
        $fields[] = 'residence = :residence';
        $params[':residence'] = $data['residence'];
    }

    if (empty($fields)) {
        echo json_encode(["success" => false, "message" => "Aucune donnée à mettre à jour"]);
        exit();
    }

    $sql = 'UPDATE "Utilisateurs" SET ' . implode(', ', $fields) . ' WHERE "idUtilisateur" = :idUtilisateur';
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);

    if ($result) {
        echo json_encode(["success" => true, "message" => "Profil mis à jour avec succès"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur lors de la mise à jour"]);
    }
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
