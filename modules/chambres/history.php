<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id_chambre = post_int('id', $_GET['id'] ?? 0);
if (!$id_chambre) redirect('list.php');

// Informations de la chambre
$stmt = $pdo->prepare("SELECT c.*, tc.nom as type_nom, tc.prix_nuit, tc.avantages 
                       FROM chambres c 
                       JOIN types_chambres tc ON c.id_type_chambre = tc.id 
                       WHERE c.id = ?");
$stmt->execute([$id_chambre]);
$chambre = $stmt->fetch();

if (!$chambre) redirect('list.php', 'danger', 'Chambre introuvable.');

// Historique complet des occupations pour cette chambre précise
$stmt_h = $pdo->prepare("
    SELECT h.*, cl.nom as client_nom, cl.prenom as client_prenom, cl.telephone as client_tel, cl.piece_identite as client_piece, u.identifiant as recep_name
    FROM historique_occupation h
    JOIN clients cl ON h.id_client = cl.id
    LEFT JOIN utilisateurs u ON h.id_receptionniste = u.id
    WHERE h.id_chambre = ?
    ORDER BY h.date_arrivee DESC
");
$stmt_h->execute([$id_chambre]);
$historique_chambre = $stmt_h->fetchAll();

// Historique du ménage pour cette chambre
$stmt_m = $pdo->prepare("
    SELECT tm.*, p.nom as agent_nom, p.prenom as agent_prenom
    FROM taches_menage tm
    LEFT JOIN personnel p ON tm.id_agent = p.id
    WHERE tm.id_chambre = ?
    ORDER BY tm.date_prevue DESC LIMIT 50
");
$stmt_m->execute([$id_chambre]);
$historique_menage = $stmt_m->fetchAll();

$total_occupations = count($historique_chambre);

$page_title = "Historique d'Occupation — Chambre N° " . $chambre['numero'];
require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Fiche d'Historique d'Occupation de la Chambre N° <?= h($chambre['numero']) ?></h2>
    <p>Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Historique Chambre N° <?= h($chambre['numero']) ?></h1>
        <p class="text-secondary" style="margin-top:4px;">Traçabilité complète des clients ayant séjourné dans la Chambre <?= h($chambre['numero']) ?></p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer l'Historique Chambre (A4)</button>
        <a href="tracabilite.php" class="btn btn-secondary">← Retour à la Traçabilité</a>
    </div>
</div>

<!-- Fiche d'Identité de la Chambre -->
<div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Caractéristiques de la Chambre</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <tr>
                <th style="width:25%;">Numéro & Étage</th>
                <td><strong style="color:var(--gold-500); font-size:16px;">Chambre N° <?= h($chambre['numero']) ?> (Étage <?= $chambre['etage'] ?>)</strong></td>
                <th style="width:25%;">Type & Tarif</th>
                <td><strong><?= h($chambre['type_nom']) ?></strong> (<?= format_money($chambre['prix_nuit']) ?> / nuit)</td>
            </tr>
            <tr>
                <th>Statut Actuel</th>
                <td><?= status_badge($chambre['statut']) ?></td>
                <th>Cumul Historique</th>
                <td><strong class="text-success"><?= $total_occupations ?> occupation(s) enregistrée(s)</strong></td>
            </tr>
            <tr>
                <th>Description & Équipements</th>
                <td colspan="3"><?= h($chambre['description'] ?? $chambre['avantages'] ?? 'Chambre standard équipée') ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- 1. Journal Historique des Clients ayant occupé la chambre -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">📜 Journal Chronologique des Clients d'Occupation</h3>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom & Prénom Client</th>
                    <th>Téléphone</th>
                    <th>Pièce d'Identité</th>
                    <th>Date & Heure Arrivée</th>
                    <th>Départ Prévu / Effectif</th>
                    <th>Durée</th>
                    <th>Statut Séjour</th>
                    <th>Réceptionniste</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historique_chambre as $h_item): ?>
                <tr>
                    <td>
                        <strong style="color:var(--gold-500);"><?= h($h_item['client_nom'] . ' ' . $h_item['client_prenom']) ?></strong>
                        <br><a href="<?= BASE_URL ?>/modules/clients/history.php?id=<?= $h_item['id_client'] ?>" class="no-print text-secondary" style="font-size:12px;">🔍 Voir fiche client</a>
                    </td>
                    <td><?= h($h_item['client_tel'] ?? '—') ?></td>
                    <td><small><?= h($h_item['client_piece'] ?? '—') ?></small></td>
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
                </tr>
                <?php endforeach; ?>
                <?php if (empty($historique_chambre)): ?>
                <tr><td colspan="8" class="text-center p-4 text-secondary">Aucune occupation historique enregistrée pour cette chambre.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 2. Historique du Ménage de cette chambre -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">🧹 Historique des Interventions de Ménage</h3>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Date Prévue</th>
                    <th>Agent de Ménage</th>
                    <th>Type d'Intervention</th>
                    <th>Statut Tâche</th>
                    <th>Heure Début / Fin</th>
                    <th>Durée</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historique_menage as $m): ?>
                <tr>
                    <td><?= format_date($m['date_prevue']) ?></td>
                    <td><?= h($m['agent_prenom'] ? $m['agent_prenom'].' '.$m['agent_nom'] : 'Non assigné') ?></td>
                    <td><span class="badge badge-reservee" style="text-transform:uppercase;"><?= h($m['type_tache']) ?></span></td>
                    <td><?= status_badge($m['statut']) ?></td>
                    <td>
                        <?= format_datetime($m['heure_debut']) ?> — <?= format_datetime($m['heure_fin']) ?>
                    </td>
                    <td><?= $m['duree_minutes'] ? $m['duree_minutes'].' min' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($historique_menage)): ?>
                <tr><td colspan="6" class="text-center p-3 text-secondary">Aucune intervention ménage enregistrée.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Registre de traçabilité d'occupation de chambre. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
