<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // Vérifier les paramètres requis
    if (!isset($data['idUtilisateur']) || !isset($data['nom']) || !isset($data['prenoms']) || !isset($data['telephone'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $idUtilisateur = $data['idUtilisateur'];
    $idAuth = $data['idAuth'] ?? '';
    $nom = $data['nom'];
    $prenoms = $data['prenoms'];
    $residence = $data['residence'] ?? '';
    $telephone = $data['telephone'];
    $points = $data['points'] ?? 3;
    $mail = $data['mail'] ?? $telephone . '@gmail.com';

    // Vérifier si l'utilisateur existe déjà
    $stmt = $conn->prepare('SELECT "idUtilisateur" FROM "Utilisateurs" WHERE telephone = :telephone OR "idUtilisateur" = :idUtilisateur');
    $stmt->execute([':telephone' => $telephone, ':idUtilisateur' => $idUtilisateur]);
    
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(["success" => false, "message" => "Utilisateur déjà existant"]);
        exit();
    }

    // Insérer le nouvel utilisateur
    $sql = 'INSERT INTO "Utilisateurs" ("idUtilisateur", "idAuth", nom, prenoms, residence, telephone, points, mail, "dateDeCreation") 
            VALUES (:idUtilisateur, :idAuth, :nom, :prenoms, :residence, :telephone, :points, :mail, NOW())';
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':idUtilisateur' => $idUtilisateur,
        ':idAuth' => $idAuth,
        ':nom' => $nom,
        ':prenoms' => $prenoms,
        ':residence' => $residence,
        ':telephone' => $telephone,
        ':points' => $points,
        ':mail' => $mail
    ]);

    if ($result) {
        echo json_encode(["success" => true, "message" => "Utilisateur créé avec succès"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur lors de l'insertion"]);
    }
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
