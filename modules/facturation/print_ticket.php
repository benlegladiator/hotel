<?php
// ============================================================
// modules/facturation/print_ticket.php
// Reçu / Ticket de caisse thermique (format 80mm et 58mm pour imprimante POS)
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('list.php');
}

$format_ticket = $_GET['format'] ?? '80mm'; // '80mm' ou '58mm'
$width_css = ($format_ticket === '58mm') ? '54mm' : '76mm';

$stmt = $pdo->prepare("
    SELECT f.*, c.nom, c.prenom, c.telephone, c.email, c.adresse, c.piece_identite,
           u.prenom as caissier_prenom, u.nom as caissier_nom, u.identifiant as caissier_user
    FROM factures f 
    JOIN clients c ON f.id_client = c.id 
    LEFT JOIN utilisateurs u ON f.id_receptionniste = u.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$facture = $stmt->fetch();

if (!$facture) {
    redirect('list.php');
}

// Services éventuels
$services = [];
$resInfo  = null;
if (!empty($facture['id_reservation'])) {
    $stmtServ = $pdo->prepare("
        SELECT s.nom, l.quantite, l.prix_unitaire, l.sous_total 
        FROM commande_lignes l 
        JOIN commandes c ON l.id_commande = c.id 
        JOIN catalogue_services s ON l.id_service = s.id 
        WHERE c.id_reservation = ?
    ");
    $stmtServ->execute([$facture['id_reservation']]);
    $services = $stmtServ->fetchAll();

    // Infos hébergement
    $stmtRes = $pdo->prepare("
        SELECT r.*, ch.numero as ch_num, t.nom as ch_type, t.prix_nuit, t.prix_sieste,
               s.nom as salle_nom
        FROM reservations r 
        LEFT JOIN chambres ch ON r.id_chambre = ch.id 
        LEFT JOIN types_chambres t ON ch.id_type_chambre = t.id 
        LEFT JOIN salles s ON r.id_salle = s.id
        WHERE r.id = ?
    ");
    $stmtRes->execute([$facture['id_reservation']]);
    $resInfo = $stmtRes->fetch();
}

// Description précise
$desc_chambre_titre = "Hébergement";
$desc_chambre_sub   = "";

if (!empty($resInfo['ch_num'])) {
    $type_nom = $resInfo['ch_type'] ?? 'Chambre';
    $desc_chambre_titre = "Chambre N° " . $resInfo['ch_num'] . " (" . $type_nom . ")";
    
    $is_prolongation = (stripos($resInfo['remarques'] ?? '', 'Bascule') !== false) || (stripos($resInfo['remarques'] ?? '', 'Prolongation') !== false);
    $is_sieste = !$is_prolongation && ((stripos($resInfo['remarques'] ?? '', 'sieste') !== false) || ($resInfo['date_arrivee'] === $resInfo['date_depart']));
    
    if ($is_sieste) {
        $desc_chambre_sub = "⚡ Sieste / Passage (" . format_date($resInfo['date_arrivee']) . ")";
    } else {
        $nb_nuits = max(1, nb_nuits($resInfo['date_arrivee'], $resInfo['date_depart']));
        $desc_chambre_sub = "🌙 Nuitée : " . format_date($resInfo['date_arrivee']) . " -> " . format_date($resInfo['date_depart']) . " (" . $nb_nuits . "n)";
        if ($is_prolongation) {
            $desc_chambre_sub .= " [Prolongation]";
        }
    }
} elseif (!empty($resInfo['salle_nom'])) {
    $desc_chambre_titre = "Salle : " . $resInfo['salle_nom'];
    $desc_chambre_sub   = "Du " . format_date($resInfo['date_arrivee']) . " au " . format_date($resInfo['date_depart']);
}

$num_facture = "#F-" . str_pad($facture['id'], 4, '0', STR_PAD_LEFT);
$caissier_affiche = trim(($facture['caissier_prenom'] ?? '') . ' ' . ($facture['caissier_nom'] ?? ''));
if (empty($caissier_affiche)) {
    $caissier_affiche = $facture['caissier_user'] ?? 'Réception';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket <?= $num_facture ?> - <?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></title>
    <style>
        /* Styles pour écran */
        body {
            background: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Courier New", monospace;
            margin: 0;
            padding: 20px;
            color: #000;
        }

        .actions-bar {
            max-width: 400px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: #1e293b; color: #fff; }
        .btn-success { background: #059669; color: #fff; }
        .btn-outline { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .btn-switch { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }

        /* Conteneur Ticket POS Thermique */
        .ticket-box {
            width: <?= $width_css ?>;
            margin: 0 auto;
            background: #ffffff;
            padding: 12px 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            font-size: 12px;
            line-height: 1.35;
            box-sizing: border-box;
        }

        .ticket-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .hotel-name {
            font-size: 17px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .hotel-sub {
            font-size: 11px;
            margin-top: 2px;
            color: #333;
        }

        .ticket-divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .ticket-double-divider {
            border-top: 2px double #000;
            margin: 8px 0;
        }

        .meta-line {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            margin-bottom: 2px;
        }

        .ticket-section-title {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
        }

        .item-desc {
            font-size: 10.5px;
            color: #222;
            margin-top: 1px;
        }

        .totals-table {
            width: 100%;
            margin-top: 6px;
        }

        .totals-table td {
            padding: 2px 0;
        }

        .grand-total {
            font-size: 16px;
            font-weight: 900;
        }

        .badge-paid {
            display: inline-block;
            border: 1.5px solid #000;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            margin-top: 6px;
        }

        .ticket-footer {
            text-align: center;
            margin-top: 12px;
            font-size: 10.5px;
            line-height: 1.3;
        }

        /* ── RÈGLES IMPRESSION THERMIQUE (80mm & 58mm) ── */
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .actions-bar {
                display: none !important;
            }
            .ticket-box {
                box-shadow: none;
                width: 100% !important;
                max-width: <?= $width_css ?>;
                padding: 4px 2px;
                margin: 0;
            }
            @page {
                size: <?= ($format_ticket === '58mm') ? '58mm' : '80mm' ?> auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<!-- Barre d'actions au guichet -->
<div class="actions-bar">
    <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimer Ticket</button>
    <a href="print.php?id=<?= $facture['id'] ?>" class="btn btn-outline">📄 Format Facture A4</a>
    <?php if ($format_ticket === '80mm'): ?>
        <a href="print_ticket.php?id=<?= $facture['id'] ?>&format=58mm" class="btn btn-switch" title="Changer en 58mm">Format 58mm</a>
    <?php else: ?>
        <a href="print_ticket.php?id=<?= $facture['id'] ?>&format=80mm" class="btn btn-switch" title="Changer en 80mm">Format 80mm</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn btn-success">+ Arrivée</a>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">← Dashboard</a>
</div>

<!-- LE TICKET THERMIQUE DE CAISSE -->
<div class="ticket-box">
    
    <div class="ticket-header">
        <h1 class="hotel-name"><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
        <div class="hotel-sub">
            <?= h(get_system_setting('adresse_hotel', 'Messassi - Stade Ancienne CNPS')) ?><br>
            Tél : <?= h(get_system_setting('telephone_hotel', '670 94 97 39 / 656 44 47 72')) ?>
        </div>
    </div>

    <div class="ticket-divider"></div>

    <div class="meta-line">
        <span><strong>TICKET :</strong> <?= $num_facture ?></span>
        <span><?= date('d/m/Y H:i', strtotime($facture['created_at'])) ?></span>
    </div>
    <div class="meta-line">
        <span><strong>Caissier :</strong> <?= h($caissier_affiche) ?></span>
        <span>Mode : <?= strtoupper(h($facture['mode_paiement'])) ?></span>
    </div>
    <div class="meta-line">
        <span><strong>Client :</strong> <?= h($facture['nom'] . ' ' . $facture['prenom']) ?></span>
    </div>
    <?php if (!empty($facture['telephone'])): ?>
    <div class="meta-line">
        <span>Tél Client : <?= h($facture['telephone']) ?></span>
    </div>
    <?php endif; ?>

    <div class="ticket-divider"></div>

    <!-- Prestation Hébergement -->
    <div class="ticket-section-title">PRESTATION :</div>
    <div class="item-row">
        <span><?= h($desc_chambre_titre) ?></span>
        <span><?= format_money($facture['montant_sejour']) ?></span>
    </div>
    <?php if (!empty($desc_chambre_sub)): ?>
        <div class="item-desc"><?= $desc_chambre_sub ?></div>
    <?php endif; ?>

    <!-- Services éventuels -->
    <?php if (!empty($services)): ?>
        <div class="ticket-divider"></div>
        <div class="ticket-section-title">SERVICES & EXTRAS :</div>
        <?php foreach ($services as $srv): ?>
            <div class="item-row">
                <span><?= $srv['quantite'] ?>x <?= h($srv['nom']) ?></span>
                <span><?= format_money($srv['sous_total']) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="ticket-double-divider"></div>

    <!-- Totaux -->
    <table class="totals-table">
        <tr>
            <td><strong>TOTAL :</strong></td>
            <td style="text-align: right;" class="grand-total"><?= format_money($facture['montant_final']) ?></td>
        </tr>
    </table>

    <div style="text-align: center; margin-top: 6px;">
        <?php if ($facture['statut_paiement'] === 'payee'): ?>
            <span class="badge-paid">★ RÉGLÉ & ENCAISSÉ ★</span>
        <?php elseif ($facture['statut_paiement'] === 'partielle'): ?>
            <span class="badge-paid" style="border-style: dashed;">ACOMPTE VERSÉ</span>
        <?php else: ?>
            <span class="badge-paid" style="border-style: dotted;">NON PAYÉ</span>
        <?php endif; ?>
    </div>

    <div class="ticket-divider"></div>

    <div class="ticket-footer">
        <strong>MERCI DE VOTRE VISITE !</strong><br>
        Alma Hôtel vous souhaite un agréable séjour.<br>
        <span style="font-size: 9.5px; color: #444;">Veuillez conserver ce ticket comme justificatif.</span>
    </div>

</div>

</body>
</html>
