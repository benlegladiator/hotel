<?php
// ============================================================
// modules/rapports/print_shift_employe.php
// Fiche Individuelle de Clôture de Shift / Service Employé
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$selected_user  = (int)($_GET['id_user'] ?? 0);
$filtre_periode = $_GET['periode'] ?? 'aujourdhui';
$date_debut     = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin       = $_GET['date_fin'] ?? date('Y-m-d');

if ($selected_user <= 0) {
    die("Veuillez sélectionner un employé.");
}

// Récupérer l'employé
$stmt_u = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt_u->execute([$selected_user]);
$emp = $stmt_u->fetch();

if (!$emp) die("Employé introuvable.");

// Encaissements
$stmt_e = $pdo->prepare("
    SELECT f.*, c.nom as client_nom, c.prenom as client_prenom, ch.numero as chambre_num
    FROM factures f
    JOIN clients c ON f.id_client = c.id
    LEFT JOIN reservations r ON f.id_reservation = r.id
    LEFT JOIN chambres ch ON r.id_chambre = ch.id
    WHERE f.id_receptionniste = ? AND f.date_emission BETWEEN ? AND ? AND f.statut_paiement IN ('payee', 'partielle')
    ORDER BY f.created_at ASC
");
$stmt_e->execute([$selected_user, $date_debut, $date_fin]);
$encaissements = $stmt_e->fetchAll();

// Sorties
$stmt_s = $pdo->prepare("
    SELECT * FROM sorties_caisse 
    WHERE id_utilisateur = ? AND date_sortie BETWEEN ? AND ?
    ORDER BY created_at ASC
");
$stmt_s->execute([$selected_user, $date_debut, $date_fin]);
$sorties = $stmt_s->fetchAll();

// Ménages
$stmt_m = $pdo->prepare("
    SELECT tm.*, ch.numero as chambre_num, tc.nom as type_nom
    FROM taches_menage tm
    JOIN chambres ch ON tm.id_chambre = ch.id
    JOIN types_chambres tc ON ch.id_type_chambre = tc.id
    WHERE tm.id_utilisateur = ? AND tm.statut = 'terminee'
      AND (tm.date_prevue BETWEEN ? AND ? OR DATE(tm.created_at) BETWEEN ? AND ?)
    ORDER BY tm.created_at ASC
");
$stmt_m->execute([$selected_user, $date_debut, $date_fin, $date_debut, $date_fin]);
$menages = $stmt_m->fetchAll();

$depenses_fonctionnement = 0;
$verse_au_boss           = 0;
foreach ($sorties as $s) {
    if ($s['categorie'] === 'versement_patron') {
        $verse_au_boss += (float)$s['montant'];
    } else {
        $depenses_fonctionnement += (float)$s['montant'];
    }
}
$total_encaisse = array_sum(array_column($encaissements, 'montant_final'));
$total_sorties  = $depenses_fonctionnement + $verse_au_boss;
$solde_net      = $total_encaisse - $total_sorties; // Espèces physiques restantes dans le tiroir-caisse

$hotel_name    = get_system_setting('nom_hotel', 'Alma Hotel');
$hotel_tel     = get_system_setting('telephone_hotel', '');
$hotel_addr    = get_system_setting('adresse_hotel', '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Clôture de Shift — <?= h($emp['nom'] . ' ' . $emp['prenom']) ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #111; line-height: 1.4; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .hotel-title { font-size: 20px; font-weight: bold; text-transform: uppercase; }
        .doc-title { font-size: 16px; font-weight: bold; text-align: right; color: #1e3a8a; }
        .emp-box { background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .summary-box { display: flex; justify-content: space-between; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 24px; gap: 8px; }
        .summary-item { text-align: center; flex: 1; }
        .summary-label { font-size: 10.5px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .summary-val { font-size: 15px; font-weight: bold; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 10px; font-size: 11px; text-align: left; }
        td { border: 1px solid #e2e8f0; padding: 6px 10px; font-size: 11px; }
        .text-right { text-align: right; }
        .section-title { font-size: 13px; font-weight: bold; margin: 16px 0 8px 0; border-bottom: 1px solid #ddd; padding-bottom: 4px; color: #333; }
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
        <span>📄 Fiche de Service & Shift Individuelle</span>
        <div>
            <button onclick="window.print()" style="background:#10b981; color:#fff; border:none; padding:8px 16px; border-radius:4px; font-weight:bold; cursor:pointer;">🖨️ Imprimer cette Fiche</button>
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
            <div class="doc-title">FICHE DE CLÔTURE DE SHIFT</div>
            <div>Période : Du <strong><?= format_date($date_debut) ?></strong> au <strong><?= format_date($date_fin) ?></strong></div>
            <div>Édité le : <?= date('d/m/Y à H:i') ?></div>
        </div>
    </div>

    <div class="emp-box">
        <strong>Employé(e) :</strong> <?= h($emp['nom'] . ' ' . $emp['prenom']) ?> &nbsp;|&nbsp; 
        <strong>Rôle :</strong> <?= h(ucfirst($emp['role'])) ?> &nbsp;|&nbsp;
        <strong>Identifiant :</strong> <?= h($emp['identifiant']) ?>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <div class="summary-label">1. Total Encaissé</div>
            <div class="summary-val" style="color:#15803d;">+ <?= format_prix($total_encaisse) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">2. Dépenses Savon/Eau</div>
            <div class="summary-val" style="color:#b91c1c;">- <?= format_prix($depenses_fonctionnement) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">3. Versé au Boss</div>
            <div class="summary-val" style="color:#d97706;">- <?= format_prix($verse_au_boss) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">4. Solde Caisse en Main</div>
            <div class="summary-val" style="color:#1e3a8a; font-size:16px;"><?= format_prix($solde_net) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">5. Ménages Validés</div>
            <div class="summary-val"><?= count($menages) ?> chambre(s)</div>
        </div>
    </div>

    <div class="section-title">1. Détail des Encaissements Réalisés (<?= count($encaissements) ?>)</div>
    <table>
        <thead>
            <tr>
                <th>Date & Heure</th>
                <th>Client</th>
                <th>Chambre</th>
                <th>Mode</th>
                <th class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($encaissements)): ?>
                <tr><td colspan="5" style="text-align:center; color:#888;">Aucun encaissement sur ce shift.</td></tr>
            <?php else: ?>
                <?php foreach ($encaissements as $e): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></td>
                        <td><?= h($e['client_nom'] . ' ' . $e['client_prenom']) ?></td>
                        <td><?= $e['chambre_num'] ? 'Chambre ' . h($e['chambre_num']) : 'N/A' ?></td>
                        <td style="text-transform: capitalize;"><?= h($e['mode_paiement'] ?? 'Espèces') ?></td>
                        <td class="text-right" style="font-weight:bold; color:#15803d;">+ <?= format_prix($e['montant_final']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">2. Détail des Dépenses & Sorties de Caisse (<?= count($sorties) ?>)</div>
    <table>
        <thead>
            <tr>
                <th>Date & Heure</th>
                <th>Motif (Savon, Eau, etc.)</th>
                <th>Catégorie</th>
                <th class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sorties)): ?>
                <tr><td colspan="4" style="text-align:center; color:#888;">Aucune dépense sur ce shift.</td></tr>
            <?php else: ?>
                <?php foreach ($sorties as $s): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                        <td><?= h($s['libelle']) ?> <?= $s['notes'] ? '(' . h($s['notes']) . ')' : '' ?></td>
                        <td style="text-transform: capitalize;"><?= h($s['categorie']) ?></td>
                        <td class="text-right" style="font-weight:bold; color:#b91c1c;">- <?= format_prix($s['montant']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">3. Détail des Chambres Nettoyées (<?= count($menages) ?>)</div>
    <table>
        <thead>
            <tr>
                <th>Date & Heure de Fin</th>
                <th>Chambre</th>
                <th>Type</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($menages)): ?>
                <tr><td colspan="4" style="text-align:center; color:#888;">Aucun ménage enregistré sur ce shift.</td></tr>
            <?php else: ?>
                <?php foreach ($menages as $m): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                        <td><strong>Chambre <?= h($m['chambre_num']) ?></strong></td>
                        <td><?= h($m['type_nom']) ?></td>
                        <td><?= h($m['notes'] ?: 'Nettoyé et validé') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="signatures">
        <div class="sign-box">
            <strong>Signature de l'Employé(e)</strong><br>
            <?= h($emp['nom'] . ' ' . $emp['prenom']) ?><br><br>
        </div>
        <div class="sign-box">
            <strong>Visa et Contrôle du Patron</strong><br>
            (Montant de <strong><?= format_prix($solde_net) ?></strong> réceptionné)<br><br>
        </div>
    </div>

</body>
</html>
