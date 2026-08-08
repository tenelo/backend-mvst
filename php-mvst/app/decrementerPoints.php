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
    $mettreAZero = isset($data['mettreAZero']) ? $data['mettreAZero'] : false;

    $stmt = $conn->prepare('SELECT nom, prenoms, telephone, points FROM "Utilisateurs" WHERE "idUtilisateur" = :id');
    $stmt->execute([':id' => $idUtilisateur]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$utilisateur) {
        echo json_encode(["success" => false, "message" => "Utilisateur introuvable"]);
        exit();
    }

    $anciensPoints = intval($utilisateur['points']);
    $nom = $utilisateur['nom'];
    $prenoms = $utilisateur['prenoms'] ?? '';
    $telephone = $utilisateur['telephone'];

    if ($mettreAZero) {
        $nouveauxPoints = 0;
        $motif = 'Blocage automatique : 4ème série de tentatives Code Secret échouées';
    } else {
        $nouveauxPoints = max(0, $anciensPoints - 1);
        $motif = 'Suppression de ticket par administrateur (-1 point)';
    }

    $stmt = $conn->prepare('UPDATE "Utilisateurs" SET points = :points WHERE "idUtilisateur" = :id');
    $stmt->execute([':points' => $nouveauxPoints, ':id' => $idUtilisateur]);

    $stmt = $conn->prepare('INSERT INTO "historique_actions" ("idUtilisateur", nom, prenoms, telephone, anciens_points, nouveaux_points, motif, date) VALUES (:id, :nom, :prenoms, :tel, :ancien, :nouveau, :motif, NOW())');
    $stmt->execute([
        ':id' => $idUtilisateur,
        ':nom' => $nom,
        ':prenoms' => $prenoms,
        ':tel' => $telephone,
        ':ancien' => $anciensPoints,
        ':nouveau' => $nouveauxPoints,
        ':motif' => $motif
    ]);

    echo json_encode([
        "success" => true,
        "points" => $nouveauxPoints
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
