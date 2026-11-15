<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = post_int('id', $_GET['id'] ?? 0);
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.date_naissance as client_date_naiss, c.lieu_naissance as client_lieu_naiss, c.adresse as client_adresse, c.telephone as client_tel, c.email as client_email, c.piece_identite as client_piece,
                       ch.numero as chambre_num, tc.nom as type_chambre, tc.prix_nuit,
                       u.identifiant as recep_name
                       FROM reservations r
                       JOIN clients c ON r.id_client = c.id
                       LEFT JOIN chambres ch ON r.id_chambre = ch.id
                       LEFT JOIN types_chambres tc ON ch.id_type_chambre = tc.id
                       LEFT JOIN utilisateurs u ON r.id_receptionniste = u.id
                       WHERE r.id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) redirect('list.php', 'danger', 'Réservation introuvable.');

$page_title = "Fiche de Réservation #" . $res['id'];
require_once '../../includes/header.php';
?>

<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Fiche & Confirmation de Réservation N° #RES-<?= str_pad($res['id'], 4, '0', STR_PAD_LEFT) ?></h2>
    <p>Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Réservation #RES-<?= str_pad($res['id'], 4, '0', STR_PAD_LEFT) ?></h1>
        <p class="text-secondary" style="margin-top:4px;">Confirmation et détails du séjour client</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer la Fiche de Réservation (A4)</button>
        <a href="list.php" class="btn btn-secondary">← Retour à la liste</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Détails de la Réservation & Occupant</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <tr>
                <th style="width:30%;">Numéro Réservation</th>
                <td><strong>#RES-<?= str_pad($res['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
            </tr>
            <tr>
                <th>Nom & Prénom Client</th>
                <td><strong><?= h($res['client_nom'] . ' ' . $res['client_prenom']) ?></strong></td>
            </tr>
            <?php if (!empty($res['client_date_naiss']) || !empty($res['client_lieu_naiss'])): ?>
            <tr>
                <th>Date & Lieu de Naissance</th>
                <td><?= !empty($res['client_date_naiss']) ? format_date($res['client_date_naiss']) : '' ?><?= !empty($res['client_lieu_naiss']) ? ' à ' . h($res['client_lieu_naiss']) : '' ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Téléphone / Contact</th>
                <td><?= h($res['client_tel'] ?? '—') ?> <?= !empty($res['client_email']) ? '('.h($res['client_email']).')' : '' ?></td>
            </tr>
            <?php if (!empty($res['client_adresse'])): ?>
            <tr>
                <th>Adresse de Résidence</th>
                <td><?= h($res['client_adresse']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Pièce d'Identité</th>
                <td><?= h($res['client_piece'] ?? 'Non renseignée') ?></td>
            </tr>
            <tr>
                <th>Chambre Attribuée</th>
                <td><strong>Chambre N° <?= h($res['chambre_num'] ?? 'N/A') ?></strong> (<?= h($res['type_chambre'] ?? '') ?>)</td>
            </tr>
            <tr>
                <th>Période du Séjour</th>
                <td>
                    Du <strong><?= format_date($res['date_arrivee']) ?></strong> au <strong><?= format_date($res['date_depart']) ?></strong>
                    (<?= nb_nuits($res['date_arrivee'], $res['date_depart']) ?> nuits)
                </td>
            </tr>
            <tr>
                <th>Nombre de Personnes</th>
                <td><?= $res['nb_personnes'] ?> personne(s)</td>
            </tr>
            <tr>
                <th>Tarif par Nuit</th>
                <td><strong><?= format_money($res['prix_nuit'] ?? 0) ?></strong></td>
            </tr>
            <tr>
                <th>Montant Total Estimé</th>
                <td><strong style="color:var(--gold-500); font-size:16px;"><?= format_money(($res['prix_nuit'] ?? 0) * nb_nuits($res['date_arrivee'], $res['date_depart'])) ?></strong></td>
            </tr>
            <tr>
                <th>Statut Réservation</th>
                <td><?= status_badge($res['statut']) ?></td>
            </tr>
            <tr>
                <th>Réceptionniste</th>
                <td><?= h($res['recep_name'] ?? 'Établissement') ?></td>
            </tr>
            <tr>
                <th>Remarques & Remises</th>
                <td><?= h($res['remarques'] ?? 'Aucune observation particulière') ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="print-footer" style="margin-top:50px; display:flex; justify-content:space-between; text-align:left;">
    <div>
        <p><strong>Signature du Client :</strong></p>
        <br><br>
        <p>___________________________</p>
    </div>
    <div style="text-align:right;">
        <p><strong>Cachet & Signature Réception :</strong></p>
        <br><br>
        <p>___________________________</p>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
