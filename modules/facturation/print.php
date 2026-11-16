<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = $_GET['id'] ?? null;
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT f.*, c.nom, c.prenom, c.telephone, c.email, c.adresse, c.date_naissance, c.lieu_naissance,
                              u.prenom as caissier_prenom, u.nom as caissier_nom, u.identifiant as caissier_user 
                       FROM factures f 
                       JOIN clients c ON f.id_client = c.id 
                       LEFT JOIN utilisateurs u ON f.id_receptionniste = u.id
                       WHERE f.id = ?");
$stmt->execute([$id]);
$facture = $stmt->fetch();

if (!$facture) redirect('list.php');

// Optionnel: Récupérer les services via commandes si id_reservation
$services = [];
if ($facture['id_reservation']) {
    $stmtServ = $pdo->prepare("SELECT s.nom, l.quantite, l.prix_unitaire, l.sous_total 
                               FROM commande_lignes l 
                               JOIN commandes c ON l.id_commande = c.id 
                               JOIN catalogue_services s ON l.id_service = s.id 
                               WHERE c.id_reservation = ?");
    $stmtServ->execute([$facture['id_reservation']]);
    $services = $stmtServ->fetchAll();
    
    // Info chambre ou salle
    $stmtRes = $pdo->prepare("SELECT r.*, ch.numero as ch_num, ch.etage as ch_etage, ch.description as ch_desc, 
                                     t.nom as ch_type, t.prix_nuit, t.prix_sieste,
                                     s.nom as salle_nom, s.prix_location as salle_prix
                              FROM reservations r 
                              LEFT JOIN chambres ch ON r.id_chambre = ch.id 
                              LEFT JOIN types_chambres t ON ch.id_type_chambre = t.id 
                              LEFT JOIN salles s ON r.id_salle = s.id
                              WHERE r.id = ?");
    $stmtRes->execute([$facture['id_reservation']]);
    $resInfo = $stmtRes->fetch();
}

// Détermination précise de la description de l'hébergement
$desc_chambre_titre = "Prestation d'Hébergement";
$desc_chambre_sub   = "";

if (!empty($resInfo['ch_num'])) {
    $type_nom = $resInfo['ch_type'] ?? 'Chambre';
    $desc_chambre_titre = "Chambre N° " . h($resInfo['ch_num']) . " (" . h($type_nom) . ")";
    
    $is_prolongation = (stripos($resInfo['remarques'] ?? '', 'Bascule') !== false) || (stripos($resInfo['remarques'] ?? '', 'Prolongation') !== false);
    $is_sieste = !$is_prolongation && ((stripos($resInfo['remarques'] ?? '', 'sieste') !== false) || ($resInfo['date_arrivee'] === $resInfo['date_depart']));
    
    if ($is_sieste) {
        $desc_chambre_sub = "⚡ Formule Sieste / Passage — Le " . format_date($resInfo['date_arrivee']);
    } else {
        $nb_nuits = max(1, nb_nuits($resInfo['date_arrivee'], $resInfo['date_depart']));
        $desc_chambre_sub = "🌙 Formule Nuitée : du " . format_date($resInfo['date_arrivee']) . " au " . format_date($resInfo['date_depart']) . " (" . $nb_nuits . " nuit" . ($nb_nuits > 1 ? "s" : "") . ")";
        if ($is_prolongation) {
            $desc_chambre_sub .= " — <span style='color:#b45309; font-weight:600;'>Prolongation depuis Sieste</span>";
        }
    }
} elseif (!empty($resInfo['salle_nom'])) {
    $desc_chambre_titre = "Location : " . h($resInfo['salle_nom']);
    $desc_chambre_sub   = "Événement du " . format_date($resInfo['date_arrivee']) . " au " . format_date($resInfo['date_depart']);
}

$num_facture = "#F-" . str_pad($facture['id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture <?= $num_facture ?> - <?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f6f9; padding: 20px; font-family: 'Inter', sans-serif; }
        .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .invoice-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a237e; padding-bottom: 20px; margin-bottom: 30px; }
        .invoice-hotel-name { font-family: 'Playfair Display', serif; color: #d4af37; font-size: 2.2em; margin: 0; }
        .hotel-contact { color: #555; font-size: 0.9em; }
        .invoice-details { text-align: right; }
        .invoice-details h2 { margin: 0 0 10px 0; color: #1a237e; }
        .invoice-parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .bill-to h4 { margin-top: 0; color: #1a237e; }
        .invoice-body { margin-bottom: 30px; }
        .invoice-body table { width: 100%; border-collapse: collapse; }
        .invoice-body th, .invoice-body td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .invoice-body th { background: #f8f9fa; color: #333; }
        .invoice-total-box { width: 50%; float: right; margin-bottom: 40px; }
        .invoice-total-box table { width: 100%; }
        .invoice-total-box th, .invoice-total-box td { padding: 10px; text-align: right; }
        .invoice-total-amount { font-size: 1.2em; font-weight: bold; color: #1a237e; border-top: 2px solid #1a237e; }
        .invoice-footer { clear: both; text-align: center; color: #777; font-size: 0.9em; margin-top: 50px; border-top: 1px solid #ddd; padding-top: 20px; }
        .invoice-print-actions { text-align: center; margin-bottom: 20px; }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-container { box-shadow: none; padding: 0; }
            .invoice-print-actions { display: none; }
        }
    </style>
</head>
<body>

<div class="invoice-print-actions" style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
    <?php
    $wa_msg  = "🏨 *" . strtoupper(get_system_setting('nom_hotel', 'Alma Hotel')) . " — FACTURE " . $num_facture . "*\n";
    $wa_msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $wa_msg .= "👤 *Client :* " . $facture['nom'] . " " . $facture['prenom'] . "\n";
    if (!empty($resInfo['ch_num'])) {
        $wa_msg .= "🛏️ *Chambre :* N° " . $resInfo['ch_num'] . "\n";
    }
    $wa_msg .= "💵 *Montant :* " . format_prix($facture['montant_final']) . "\n";
    $wa_msg .= "💳 *Règlement :* " . ucfirst($facture['mode_paiement']) . " (" . strtoupper($facture['statut_paiement']) . ")\n";
    $wa_msg .= "👤 *Caissière :* " . ($facture['caissier_prenom'] ?? '') . " " . ($facture['caissier_nom'] ?? '') . "\n";
    $wa_link = get_boss_whatsapp_link($wa_msg);
    ?>
    <a href="<?= $wa_link ?>" target="_blank" class="btn" style="background:#25D366; color:#fff; font-weight:700; border-radius:6px; padding:10px 18px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;" title="Envoyer au Boss sur WhatsApp">
        📲 Notifier le Boss sur WhatsApp
    </a>
    <button onclick="window.print()" class="btn btn-primary" style="font-weight:700;">🖨️ Imprimer la Facture A4</button>
    <a href="print_ticket.php?id=<?= $facture['id'] ?>" class="btn" style="background:#b45309; color:#fff; font-weight:700; border-radius:6px; padding:10px 18px; text-decoration:none;">🧾 Format Ticket de Caisse (80mm)</a>
    <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn" style="background:#059669; color:#fff; font-weight:700; border-radius:6px; padding:10px 18px; text-decoration:none;">+ Nouvelle Arrivée</a>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary" style="font-weight:600;">← Tableau de Bord</a>
</div>

<div class="invoice-container">
    <div class="invoice-head">
        <div>
            <h1 class="invoice-hotel-name"><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
            <div class="hotel-contact">
                <?= h(get_system_setting('adresse_hotel', '')) ?><br>
                Tél : <?= h(get_system_setting('telephone_hotel', '')) ?><br>
                Email : <?= h(get_system_setting('email_hotel', '')) ?>
            </div>
        </div>
        <div class="invoice-details">
            <h2>FACTURE</h2>
            <div>N° Facture : <strong><?= $num_facture ?></strong></div>
            <div>Date : <?= format_date($facture['date_emission']) ?></div>
            <div>Statut : <?= status_badge($facture['statut_paiement']) ?></div>
        </div>
    </div>

    <div class="invoice-parties">
        <div class="bill-to">
            <h4>Facturé à :</h4>
            <strong style="font-size:16px; color:#1a237e;"><?= h($facture['nom'] . ' ' . $facture['prenom']) ?></strong><br>
            <?php if (!empty($facture['adresse'])): ?>
                Adresse : <?= h($facture['adresse']) ?><br>
            <?php endif; ?>
            Tél : <?= h($facture['telephone'] ?: '—') ?><br>
            Email : <?= h($facture['email'] ?: '—') ?>
        </div>
    </div>

    <div class="invoice-body">
        <h4>Détails de la Prestation / Hébergement</h4>
        <table>
            <thead>
                <tr>
                    <th style="width:70%;">Désignation & Formule</th>
                    <th style="width:30%; text-align: right;">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong style="font-size: 15px; color: #1a237e;"><?= $desc_chambre_titre ?></strong>
                        <?php if (!empty($desc_chambre_sub)): ?>
                            <div style="color: #475569; font-size: 13px; margin-top: 4px;"><?= $desc_chambre_sub ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; font-weight: 700; font-size: 15px; color: #0f172a; vertical-align: middle;">
                        <?= format_money($facture['montant_sejour']) ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($services)): ?>
        <h4 style="margin-top:20px;">Détails des Services</h4>
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Qté</th>
                    <th>Prix U.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $s): ?>
                <tr>
                    <td><?= h($s['nom']) ?></td>
                    <td><?= $s['quantite'] ?></td>
                    <td style="text-align: right;"><?= format_money($s['prix_unitaire']) ?></td>
                    <td style="text-align: right;"><?= format_money($s['sous_total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th colspan="3" style="text-align:right;">Total Services</th>
                    <th style="text-align:right;"><?= format_money($facture['montant_services']) ?></th>
                </tr>
            </tbody>
        </table>
        <?php elseif ($facture['montant_services'] > 0): ?>
        <h4 style="margin-top:20px;">Détails des Services</h4>
        <table>
            <tr>
                <td>Services supplémentaires divers</td>
                <td style="text-align: right;"><?= format_money($facture['montant_services']) ?></td>
            </tr>
        </table>
        <?php endif; ?>
    </div>

    <div class="invoice-total-box">
        <table>
            <tr>
                <th>Sous-Total :</th>
                <td><?= format_money($facture['montant_total']) ?></td>
            </tr>
            <?php if ($facture['reduction_pct'] > 0): ?>
            <tr>
                <th>Réduction (<?= $facture['reduction_pct'] ?>%) :<br><small><?= h($facture['reduction_motif']) ?></small></th>
                <td>-<?= format_money($facture['montant_total'] * ($facture['reduction_pct']/100)) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="invoice-total-amount">
                <th>MONTANT FINAL :</th>
                <td><?= format_money($facture['montant_final']) ?></td>
            </tr>
        </table>
        
        <div style="text-align:right; clear:both; margin-top: 20px; font-size:0.9em; color:#555;">
            Mode de paiement : <strong><?= ucfirst(h($facture['mode_paiement'])) ?></strong><br>
            Réf : <?= h($facture['reference_paiement'] ?: 'N/A') ?>
        </div>
    </div>

    <div class="invoice-footer">
        Merci de votre confiance !<br>
        Pour toute question concernant cette facture, veuillez nous contacter.
    </div>
</div>

</body>
</html>
