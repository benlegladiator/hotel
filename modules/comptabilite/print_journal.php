<?php
// ============================================================
// modules/comptabilite/print_journal.php
// Impression A4 du Journal de Caisse & Traçabilité
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$filtre_periode = $_GET['periode'] ?? 'aujourdhui';
$date_debut     = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin       = $_GET['date_fin'] ?? date('Y-m-d');
$id_user        = (int)($_GET['id_user'] ?? 0);
$type_flux      = $_GET['type_flux'] ?? 'tous';

// Récupérer les ENTRÉES
$sql_entrees = "
    SELECT 
        'entree' as type_mouvement,
        f.created_at as date_heure,
        f.montant_final as montant,
        f.mode_paiement,
        CONCAT('Paiement Séjour - Client: ', c.nom, ' ', c.prenom, IF(ch.numero IS NOT NULL, CONCAT(' (Ch. ', ch.numero, ')'), '')) as libelle,
        u.nom as user_nom,
        u.prenom as user_prenom
    FROM factures f
    JOIN clients c ON f.id_client = c.id
    LEFT JOIN reservations r ON f.id_reservation = r.id
    LEFT JOIN chambres ch ON r.id_chambre = ch.id
    LEFT JOIN utilisateurs u ON f.id_receptionniste = u.id
    WHERE f.date_emission BETWEEN ? AND ?
      AND f.statut_paiement IN ('payee', 'partielle')
";
$params_e = [$date_debut, $date_fin];
if ($id_user > 0) {
    $sql_entrees .= " AND f.id_receptionniste = ?";
    $params_e[] = $id_user;
}
$stmt_e = $pdo->prepare($sql_entrees);
$stmt_e->execute($params_e);
$entrees = $stmt_e->fetchAll();

// Récupérer les SORTIES
$sql_sorties = "
    SELECT 
        'sortie' as type_mouvement,
        s.created_at as date_heure,
        s.montant as montant,
        s.mode_paiement,
        CONCAT(s.libelle, IF(s.notes IS NOT NULL AND s.notes != '', CONCAT(' (', s.notes, ')'), '')) as libelle,
        u.nom as user_nom,
        u.prenom as user_prenom
    FROM sorties_caisse s
    LEFT JOIN utilisateurs u ON s.id_utilisateur = u.id
    WHERE s.date_sortie BETWEEN ? AND ?
";
$params_s = [$date_debut, $date_fin];
if ($id_user > 0) {
    $sql_sorties .= " AND s.id_utilisateur = ?";
    $params_s[] = $id_user;
}
$stmt_s = $pdo->prepare($sql_sorties);
$stmt_s->execute($params_s);
$sorties = $stmt_s->fetchAll();

$mouvements = [];
if ($type_flux !== 'sorties') $mouvements = array_merge($mouvements, $entrees);
if ($type_flux !== 'entrees') $mouvements = array_merge($mouvements, $sorties);

usort($mouvements, function($a, $b) {
    return strtotime($a['date_heure']) - strtotime($b['date_heure']);
});

$total_entrees = array_sum(array_column($entrees, 'montant'));
$total_sorties = array_sum(array_column($sorties, 'montant'));
$solde_net     = $total_entrees - $total_sorties;

$hotel_name    = get_system_setting('nom_hotel', 'Alma Hotel');
$hotel_tel     = get_system_setting('telephone_hotel', '');
$hotel_addr    = get_system_setting('adresse_hotel', '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal de Caisse — <?= h($hotel_name) ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #111; line-height: 1.4; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .hotel-title { font-size: 20px; font-weight: bold; text-transform: uppercase; }
        .doc-title { font-size: 16px; font-weight: bold; text-align: right; color: #1e3a8a; }
        .summary-box { display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .summary-item { text-align: center; }
        .summary-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .summary-val { font-size: 16px; font-weight: bold; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 10px; font-size: 12px; text-align: left; }
        td { border: 1px solid #e2e8f0; padding: 8px 10px; font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tag-entree { color: #166534; font-weight: bold; }
        .tag-sortie { color: #991b1b; font-weight: bold; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
        .sign-box { width: 45%; border-top: 1px dashed #64748b; padding-top: 8px; text-align: center; font-size: 12px; }
        .no-print-bar { background: #1e293b; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; margin: -20px -20px 20px -20px; }
        @media print {
            .no-print-bar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <span>📄 Aperçu Impression A4 — Journal de Caisse Officiel</span>
        <div>
            <button onclick="window.print()" style="background:#10b981; color:#fff; border:none; padding:8px 16px; border-radius:4px; font-weight:bold; cursor:pointer;">🖨️ Imprimer / Enregistrer en PDF</button>
            <button onclick="window.close()" style="background:#64748b; color:#fff; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; margin-left:8px;">Fermer</button>
        </div>
    </div>

    <div class="header">
        <div>
            <div class="hotel-title"><?= h($hotel_name) ?></div>
            <div><?= h($hotel_addr) ?></div>
            <div>Tél : <?= h($hotel_tel) ?></div>
        </div>
        <div>
            <div class="doc-title">JOURNAL DE CAISSE & TRAÇABILITÉ</div>
            <div>Période : Du <strong><?= format_date($date_debut) ?></strong> au <strong><?= format_date($date_fin) ?></strong></div>
            <div>Édité le : <?= date('d/m/Y à H:i') ?> par <?= h(current_user()['prenom'] . ' ' . current_user()['nom']) ?></div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <div class="summary-label">Total Entrées (Recettes)</div>
            <div class="summary-val" style="color:#15803d;">+ <?= format_prix($total_entrees) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Sorties (Dépenses)</div>
            <div class="summary-val" style="color:#b91c1c;">- <?= format_prix($total_sorties) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Solde Net de Caisse</div>
            <div class="summary-val" style="color:#1e3a8a; font-size:18px;"><?= format_prix($solde_net) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Opérations</div>
            <div class="summary-val"><?= count($mouvements) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Date & Heure</th>
                <th style="width: 12%;">Flux</th>
                <th>Motif / Détail de l'opération</th>
                <th style="width: 18%;">Responsable</th>
                <th style="width: 10%;">Mode</th>
                <th style="width: 15%;" class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mouvements)): ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #64748b;">Aucun mouvement enregistré sur cette période.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($mouvements as $m): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($m['date_heure'])) ?></td>
                        <td class="<?= $m['type_mouvement'] === 'entree' ? 'tag-entree' : 'tag-sortie' ?>">
                            <?= $m['type_mouvement'] === 'entree' ? '🟢 ENTRÉE' : '🔴 SORTIE' ?>
                        </td>
                        <td><?= h($m['libelle']) ?></td>
                        <td><?= h(($m['user_prenom'] ?? '') . ' ' . ($m['user_nom'] ?? '')) ?: 'N/A' ?></td>
                        <td style="text-transform: capitalize;"><?= h($m['mode_paiement'] ?? 'Espèces') ?></td>
                        <td class="text-right" style="font-weight: bold; color: <?= $m['type_mouvement'] === 'entree' ? '#15803d' : '#b91c1c' ?>;">
                            <?= $m['type_mouvement'] === 'entree' ? '+' : '-' ?> <?= format_prix($m['montant']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f8fafc; font-weight:bold; font-size:13px;">
                <td colspan="5" class="text-right">SOLDE NET RESTANT EN CAISSE :</td>
                <td class="text-right" style="color: <?= $solde_net >= 0 ? '#15803d' : '#b91c1c' ?>; font-size:14px;"><?= format_prix($solde_net) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <div class="sign-box">
            <strong>Signature de la Caissière / Responsable de quart</strong><br>
            <br><br><br>
            (Nom et prénom)
        </div>
        <div class="sign-box">
            <strong>Visa de la Direction / Contrôle Patron</strong><br>
            <br><br><br>
            (Date et signature)
        </div>
    </div>

</body>
</html>
