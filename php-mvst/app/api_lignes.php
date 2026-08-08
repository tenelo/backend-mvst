<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
require_once __DIR__ . '/config.php';
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');
if ($method === 'GET') {
    $type = trim($_GET['type'] ?? 'standard');
    $sansPrix = isset($_GET['sans_prix']) && $_GET['sans_prix'] === '1';
    if ($type === 'all') {
        $stmt = $conn->prepare('SELECT * FROM "Lignes" ORDER BY id ASC');
        $stmt->execute();
    } elseif ($sansPrix) {
        $stmt = $conn->prepare('SELECT * FROM "Lignes" WHERE type = :type AND prix <= 0 ORDER BY id ASC');
        $stmt->execute(['type' => $type]);
    } else {
        $stmt = $conn->prepare('SELECT * FROM "Lignes" WHERE type = :type ORDER BY id ASC');
        $stmt->execute(['type' => $type]);
    }
    $rows = $stmt->fetchAll();
    $lignes = array_map(function ($r) {
        $r['id']   = (int)$r['id'];
        $r['prix'] = (int)$r['prix'];
        return $r;
    }, $rows);
    echo json_encode(['success' => true, 'lignes' => $lignes]);
    exit;
}
if ($method === 'POST') {
    if ($action === 'ajouter') {
        $depart      = trim($input['depart']      ?? '');
        $destination = trim($input['destination'] ?? '');
        $ligne       = trim($input['ligne']       ?? '');
        $prix        = (int)($input['prix']       ?? 0);
        $type        = trim($input['type']        ?? 'standard');
        if (empty($depart) || empty($destination) || $prix <= 0) {
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }
        if (empty($ligne)) $ligne = "$depart $destination";
        $stmt = $conn->prepare(
            'INSERT INTO "Lignes" (depart, destination, ligne, prix, type)
             VALUES (:depart, :destination, :ligne, :prix, :type)
             RETURNING id'
        );
        $stmt->execute([
            'depart'      => $depart,
            'destination' => $destination,
            'ligne'       => $ligne,
            'prix'        => $prix,
            'type'        => $type,
        ]);
        $row = $stmt->fetch();
        echo json_encode(['success' => true, 'id' => (int)$row['id']]);
    } elseif ($action === 'modifier') {
        $id          = (int)($input['id']          ?? 0);
        $depart      = trim($input['depart']      ?? '');
        $destination = trim($input['destination'] ?? '');
        $ligne       = trim($input['ligne']       ?? '');
        $prix        = (int)($input['prix']       ?? 0);
        if ($id <= 0 || empty($depart) || empty($destination) || $prix <= 0) {
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }
        if (empty($ligne)) $ligne = "$depart $destination";
        $stmt = $conn->prepare(
            'UPDATE "Lignes"
             SET depart = :depart, destination = :destination, ligne = :ligne, prix = :prix
             WHERE id = :id'
        );
        $stmt->execute([
            'id'          => $id,
            'depart'      => $depart,
            'destination' => $destination,
            'ligne'       => $ligne,
            'prix'        => $prix,
        ]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'supprimer') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID invalide']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM "Lignes" WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
    } else {
        echo json_encode(['success' => false, 'error' => 'action inconnue']);
    }
    exit;
}
echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
