<?php
// ============================================================
// modules/comptabilite/api_notifications.php
// Endpoint JSON pour la Cloche Sonore des Notifications du Boss
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        (SELECT 
            'entree' as type,
            f.id,
            f.created_at,
            f.montant_final as montant,
            f.mode_paiement,
            CONCAT('Arrivée Ch. ', IFNULL(ch.numero, 'N/A'), ' — ', c.nom, ' ', c.prenom) as motif,
            CONCAT(IFNULL(u.prenom, ''), ' ', IFNULL(u.nom, '')) as operateur
        FROM factures f
        JOIN clients c ON f.id_client = c.id
        LEFT JOIN reservations r ON f.id_reservation = r.id
        LEFT JOIN chambres ch ON r.id_chambre = ch.id
        LEFT JOIN utilisateurs u ON f.id_receptionniste = u.id
        WHERE f.date_emission = ? AND f.statut_paiement IN ('payee', 'partielle'))
        
        UNION ALL
        
        (SELECT 
            'sortie' as type,
            d.id,
            d.created_at,
            d.montant,
            d.mode_reglement as mode_paiement,
            d.motif,
            CONCAT(IFNULL(u.prenom, ''), ' ', IFNULL(u.nom, '')) as operateur
        FROM depenses d
        LEFT JOIN utilisateurs u ON d.id_user = u.id
        WHERE d.date_depense = ?)
        
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$today, $today]);
    $items = $stmt->fetchAll();

    echo json_encode([
        'success'     => true,
        'count'       => count($items),
        'items'       => $items,
        'latest_id'   => !empty($items) ? ($items[0]['type'] . '_' . $items[0]['id']) : 'none',
        'server_time' => time()
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
