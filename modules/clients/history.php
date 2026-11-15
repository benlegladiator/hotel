<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id_client = post_int('id', $_GET['id'] ?? 0);
if (!$id_client) redirect('list.php');

// Informations du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id_client]);
$client = $stmt->fetch();

if (!$client) redirect('list.php', 'danger', 'Client introuvable.');

// Historique complet des séjours de ce client précis
$stmt_h = $pdo->prepare("
    SELECT h.*, c.numero as chambre_num, tc.nom as type_chambre, tc.prix_nuit, u.identifiant as recep_name
    FROM historique_occupation h
    JOIN chambres c ON h.id_chambre = c.id
    JOIN types_chambres tc ON c.id_type_chambre = tc.id
    LEFT JOIN utilisateurs u ON h.id_receptionniste = u.id
    WHERE h.id_client = ?
    ORDER BY h.date_arrivee DESC
");
$stmt_h->execute([$id_client]);
$historique_sejours = $stmt_h->fetchAll();

// Historique des réservations de ce client
$stmt_r = $pdo->prepare("
    SELECT r.*, c.numero as chambre_num
    FROM reservations r
    LEFT JOIN chambres c ON r.id_chambre = c.id
    WHERE r.id_client = ?
    ORDER BY r.date_arrivee DESC
");
$stmt_r->execute([$id_client]);
$reservations = $stmt_r->fetchAll();

// Historique des factures de ce client
$stmt_f = $pdo->prepare("
    SELECT * FROM factures WHERE id_client = ? ORDER BY date_emission DESC
");
$stmt_f->execute([$id_client]);
$factures = $stmt_f->fetchAll();

$total_sejours = count($historique_sejours);
$total_depense = 0;
foreach ($factures as $f) {
    if ($f['statut_paiement'] === 'payee') $total_depense += (float)$f['montant_final'];
}

$page_title = "Fiche & Historique — " . $client['nom'] . ' ' . $client['prenom'];
require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Fiche Individuelle & Historique des Séjours Client</h2>
    <p>Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Fiche Client & Historique des Séjours</h1>
        <p class="text-secondary" style="margin-top:4px;">Traçabilité complète des séjours de <?= h($client['prenom'] . ' ' . $client['nom']) ?></p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer la Fiche Client (A4)</button>
        <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn btn-success">📥 Nouveau Check-in (Arrivée Express)</a>
        <a href="list.php" class="btn btn-secondary">← Retour aux clients</a>
    </div>
</div>

<!-- Profil du Client -->
<div class="card mb-4">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Profil & Coordonnées du Client</h3>
        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-secondary no-print">✏️ Modifier Profil</a>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <tr>
                <th style="width:25%;">Nom & Prénom</th>
                <td><strong style="color:var(--gold-500); font-size:16px;"><?= h($client['nom'] . ' ' . $client['prenom']) ?></strong></td>
                <th style="width:25%;">🎂 Né(e) le / à</th>
                <td><?= !empty($client['date_naissance']) ? format_date($client['date_naissance']) : '—' ?><?= !empty($client['lieu_naissance']) ? ' à ' . h($client['lieu_naissance']) : '' ?></td>
            </tr>
            <tr>
                <th>Téléphone / Contact</th>
                <td><strong><?= h($client['telephone'] ?? '—') ?></strong></td>
                <th>Email</th>
                <td><?= h($client['email'] ?? '—') ?></td>
            </tr>
            <tr>
                <th>🪪 N° CNI / Pièce</th>
                <td><strong style="color:var(--text-primary); font-size:14px; background:#e2e8f0; padding:2px 8px; border-radius:4px;"><?= h($client['piece_identite'] ?? 'Non renseignée') ?></strong></td>
                <th>Nationalité</th>
                <td><?= h($client['nationalite'] ?? '—') ?></td>
            </tr>
            <tr>
                <th>Adresse Physique / Ville</th>
                <td colspan="3"><?= h($client['adresse'] ?? '—') ?></td>
            </tr>
            <tr>
                <th>Préférences / Observations</th>
                <td colspan="3"><?= h($client['preferences'] ?? 'Aucune préférence particulière renseignée') ?></td>
            </tr>
            <tr>
                <th>Cumul des Séjours</th>
                <td><strong class="text-success"><?= $total_sejours ?> séjour(s) effectué(s)</strong></td>
                <th>Total Factures Payées</th>
                <td><strong style="color:var(--gold-500); font-size:15px;"><?= format_money($total_depense) ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<!-- 1. Historique Chronologique des Séjours en Chambre -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">📜 Historique des Séjours & Traçabilité des Occupations de Chambre</h3>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Chambre Occupée</th>
                    <th>Type de Chambre</th>
                    <th>Date & Heure Arrivée</th>
                    <th>Départ Prévu / Effectif</th>
                    <th>Durée</th>
                    <th>Statut Séjour</th>
                    <th>Réceptionniste</th>
                    <th>Remarques</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historique_sejours as $h_item): ?>
                <tr>
                    <td>
                        <strong style="color:var(--gold-500);">Chambre N° <?= h($h_item['chambre_num']) ?></strong>
                    </td>
                    <td><?= h($h_item['type_chambre']) ?> (<?= format_money($h_item['prix_nuit']) ?> / nuit)</td>
                    <td><?= format_datetime($h_item['date_arrivee']) ?></td>
                    <td>
                        <?= format_datetime($h_item['date_depart_prevue']) ?>
                        <?php if ($h_item['date_depart_effective']): ?>
                            <br><small class="text-success">Parti le <?= format_datetime($h_item['date_depart_effective']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= nb_nuits($h_item['date_arrivee'], $h_item['date_depart_prevue']) ?> nuit(s)</td>
                    <td><?= status_badge($h_item['statut_sejour']) ?></td>
                    <td><small><?= h($h_item['recep_name'] ?? 'Établissement') ?></small></td>
                    <td><small><?= h($h_item['remarques'] ?? '—') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($historique_sejours)): ?>
                <tr><td colspan="8" class="text-center p-4 text-secondary">Aucun séjour enregistré dans l'historique pour ce client.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Fiche individuelle d'historique des séjours client. Imprimée le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
