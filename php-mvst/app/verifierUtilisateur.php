<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['idUtilisateur'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }
    $idUtilisateur = $data['idUtilisateur'];
    $stmt = $conn->prepare('SELECT nom, prenoms, telephone, points FROM "Utilisateurs" WHERE "idUtilisateur" = :idUtilisateur');
    $stmt->execute([':idUtilisateur' => $idUtilisateur]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$utilisateur) {
        echo json_encode(["success" => false, "message" => "Utilisateur introuvable"]);
        exit();
    }
    echo json_encode([
        "success"   => true,
        "nom"       => $utilisateur['nom'],
        "prenoms"   => $utilisateur['prenoms'],
        "telephone" => $utilisateur['telephone'],
        "points"    => (int)$utilisateur['points'],
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "PDO: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur: " . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(["success" => false, "message" => "Fatal: " . $e->getMessage()]);
}
?>
