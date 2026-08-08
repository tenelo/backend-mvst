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

    if ($action === 'get_all') {
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin   = $_GET['date_fin']   ?? null;
        $where  = [];
        $params = [];
        if ($dateDebut) {
            $where[]               = 'createdat >= :date_debut';
            $params[':date_debut'] = $dateDebut . ' 00:00:00';
        }
        if ($dateFin) {
            $where[]             = 'createdat <= :date_fin';
            $params[':date_fin'] = $dateFin . ' 23:59:59';
        }
        $sql = 'SELECT * FROM "Suggestions"';
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY createdat DESC';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'suggestions' => _formatRows($rows)]);

    } elseif ($action === 'get_by_user') {
        $idutilisateur = trim($_GET['idutilisateur'] ?? '');
        if (empty($idutilisateur)) {
            echo json_encode(['success' => false, 'error' => 'idutilisateur requis']);
            exit;
        }
        $stmt = $conn->prepare(
            'SELECT * FROM "Suggestions" WHERE idutilisateur = :id ORDER BY createdat DESC'
        );
        $stmt->execute([':id' => $idutilisateur]);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'suggestions' => _formatRows($rows)]);

    } else {
        echo json_encode(['success' => false, 'error' => 'action inconnue']);
    }
    exit;
}

if ($method === 'POST') {

    if ($action === 'add') {
        $nom           = trim($input['nom']           ?? '');
        $telephone     = trim($input['telephone']     ?? '');
        $message       = trim($input['message']       ?? '');
        $categorie     = trim($input['categorie']     ?? 'Autre');
        $idutilisateur = trim($input['idutilisateur'] ?? '');

        if (empty($message) || empty($idutilisateur)) {
            echo json_encode(['success' => false, 'error' => 'Message et identifiant requis']);
            exit;
        }
        if (empty($nom))       $nom       = 'Utilisateur';
        if (empty($telephone)) $telephone = '-';

        $stmt = $conn->prepare(
            'INSERT INTO "Suggestions" (nom, telephone, message, categorie, idutilisateur)
             VALUES (:nom, :telephone, :message, :categorie, :idutilisateur)
             RETURNING id, createdat'
        );
        $stmt->execute([
            ':nom'           => $nom,
            ':telephone'     => $telephone,
            ':message'       => $message,
            ':categorie'     => $categorie,
            ':idutilisateur' => $idutilisateur,
        ]);
        $row = $stmt->fetch();
        echo json_encode([
            'success'   => true,
            'id'        => (int)$row['id'],
            'createdat' => $row['createdat'],
        ]);

    } elseif ($action === 'update_statut') {
        $id     = (int)($input['id']     ?? 0);
        $statut = trim($input['statut'] ?? '');
        if ($id <= 0 || !in_array($statut, ['en_attente', 'lu', 'traite'])) {
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE "Suggestions" SET statut = :statut WHERE id = :id');
        $stmt->execute([':statut' => $statut, ':id' => $id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        $id            = (int)($input['id']            ?? 0);
        $idutilisateur = trim($input['idutilisateur'] ?? '');
        if ($id <= 0 || empty($idutilisateur)) {
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }
        $stmt = $conn->prepare(
            'DELETE FROM "Suggestions" WHERE id = :id AND idutilisateur = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $idutilisateur]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);

    } elseif ($action === 'admin_delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID invalide']);
            exit;
        }
        $stmt = $conn->prepare('SELECT idutilisateur FROM "Suggestions" WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        $idutilisateur = $row ? $row['idutilisateur'] : '';
        $stmt = $conn->prepare('DELETE FROM "Suggestions" WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode([
            'success'       => $stmt->rowCount() > 0,
            'idutilisateur' => $idutilisateur,
        ]);

    } else {
        echo json_encode(['success' => false, 'error' => 'action inconnue']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);

function _formatRows(array $rows): array {
    return array_map(function ($r) {
        $r['id'] = (int)$r['id'];
        return $r;
    }, $rows);
}
