<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = post_int('id', $_GET['id'] ?? 0);
if (!$id) redirect('index.php');

$stmt = $pdo->prepare("SELECT s.*, u.identifiant as agent_name, u.prenom as agent_prenom, u.nom as agent_nom 
                       FROM sorties_caisse s 
                       JOIN utilisateurs u ON s.id_utilisateur = u.id 
                       WHERE s.id = ?");
$stmt->execute([$id]);
$depense = $stmt->fetch();

if (!$depense) redirect('index.php', 'danger', 'Sortie introuvable.');

$is_versement_patron = ($depense['categorie'] === 'versement_patron');
$titre_doc = $is_versement_patron 
    ? "REÇU DE DÉCHARGE / VERSEMENT EN CAISSE DU BOSS N° #VER-" . str_pad($depense['id'], 4, '0', STR_PAD_LEFT)
    : "BON DE SORTIE DE CAISSE & DÉPENSE N° #DEP-" . str_pad($depense['id'], 4, '0', STR_PAD_LEFT);

$page_title = $is_versement_patron 
    ? "Reçu de Décharge Versement Boss #" . $depense['id'] 
    : "Bon de Sortie de Caisse #" . $depense['id'];

require_once '../../includes/header.php';
?>

<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2><?= $titre_doc ?></h2>
    <p>Édité le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title"><?= $titre_doc ?></h1>
        <p class="text-secondary" style="margin-top:4px;">
            <?= $is_versement_patron ? 'Pièce justificative de remise de fonds en main propre à la Direction / Boss' : 'Pièce justificative d\'un décaissement de stock / charge' ?>
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer Pièce Justificative (A4)</button>
        <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn btn-secondary">← Retour Journal</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h3 class="card-title"><?= $is_versement_patron ? '👑 Détails du Versement au Patron' : 'Détails de la Dépense / Achat Stock' ?></h3></div>
    <div class="card-body p-0">
        <table class="table">
            <tr>
                <th style="width:30%;">N° Référence</th>
                <td><strong><?= $is_versement_patron ? '#VER-' : '#DEP-' ?><?= str_pad($depense['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
            </tr>
            <tr>
                <th>Date de l'Opération</th>
                <td><strong><?= format_date($depense['date_sortie']) ?></strong> (<?= date('H:i', strtotime($depense['created_at'])) ?>)</td>
            </tr>
            <tr>
                <th>Motif / Objet</th>
                <td><strong><?= h($depense['libelle']) ?></strong></td>
            </tr>
            <tr>
                <th>Type d'Opération</th>
                <td>
                    <?php if ($is_versement_patron): ?>
                        <span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:800;">👑 VERSEMENT CAISSE BOSS</span>
                    <?php else: ?>
                        <span class="badge badge-reservee" style="text-transform:uppercase;"><?= h($depense['categorie']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Montant Transféré / Décaissé</th>
                <td><strong style="color: <?= $is_versement_patron ? '#d97706' : '#e11d48' ?>; font-size:22px;"><?= format_money($depense['montant']) ?></strong></td>
            </tr>
            <tr>
                <th>Mode de Règlement</th>
                <td><strong style="text-transform:uppercase;"><?= h($depense['mode_paiement']) ?></strong></td>
            </tr>
            <tr>
                <th>Caissière / Agent Remettant</th>
                <td>👤 <strong><?= h(trim($depense['agent_prenom'] . ' ' . $depense['agent_nom']) ?: $depense['agent_name']) ?></strong></td>
            </tr>
            <tr>
                <th>Notes / Observations</th>
                <td><?= h($depense['notes'] ?? 'Aucun détail supplémentaire') ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="print-footer" style="margin-top:60px; display:flex; justify-content:space-between; text-align:left;">
    <div>
        <p><strong><?= $is_versement_patron ? 'Signature Caissière (Remettant des fonds) :' : 'Bénéficiaire / Fournisseur :' ?></strong></p>
        <p style="font-size:12px; color:#64748b;"><?= h(trim($depense['agent_prenom'] . ' ' . $depense['agent_nom']) ?: $depense['agent_name']) ?></p>
        <br><br>
        <p>___________________________</p>
    </div>
    <div style="text-align:right;">
        <p><strong><?= $is_versement_patron ? 'Signature Direction / Boss (Réceptionnaire) :' : 'Visa de la Direction / Caisse :' ?></strong></p>
        <p style="font-size:12px; color:#64748b;">M. le Directeur / Administrateur</p>
        <br><br>
        <p>___________________________</p>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
