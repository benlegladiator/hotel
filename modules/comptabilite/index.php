<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$page_title = "Comptabilité & Journal de Caisse";

// Période dynamique (Jour, Semaine, Mois, Année, Custom)
$period_preset = $_GET['period'] ?? 'week';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

$period_info   = get_period_dates($period_preset, $custom_debut, $custom_fin);
$date_debut    = $period_info['debut'];
$date_fin      = $period_info['fin'];

// Action de suppression / annulation d'une sortie de caisse
if (isset($_GET['action']) && $_GET['action'] === 'delete_depense' && isset($_GET['id_depense'])) {
    $id_d = (int)$_GET['id_depense'];
    if ($id_d > 0) {
        $stmt_del = $pdo->prepare("DELETE FROM sorties_caisse WHERE id = ?");
        $stmt_del->execute([$id_d]);
        set_flash('success', 'La sortie de caisse a été supprimée. Le solde de caisse a été réajusté.');
    }
    redirect("index.php?period=$period_preset");
}

// 1. Synthèse globale des recettes sur la période
$sql_bilan = "SELECT 
              SUM(montant_sejour) as total_sejour,
              SUM(montant_services) as total_services,
              SUM(montant_total) as total_brut,
              SUM(montant_final) as total_net,
              SUM(CASE WHEN statut_paiement = 'payee' THEN montant_final 
                       WHEN statut_paiement = 'partielle' THEN (montant_final / 2) 
                       ELSE 0 END) as encaisse_estime,
              SUM(CASE WHEN statut_paiement = 'impayee' THEN montant_final 
                       WHEN statut_paiement = 'partielle' THEN (montant_final / 2) 
                       ELSE 0 END) as reste_a_recouvrer
              FROM factures
              WHERE date_emission BETWEEN ? AND ?";

$stmt_b = $pdo->prepare($sql_bilan);
$stmt_b->execute([$date_debut, $date_fin]);
$bilan = $stmt_b->fetch();

$total_encaisse = (float)($bilan['encaisse_estime'] ?? 0);
$reste_recouvrer = (float)($bilan['reste_a_recouvrer'] ?? 0);

// 2. Synthèse des sorties de caisse & dépenses stock sur la période
$sql_sorties = "SELECT SUM(montant) as total FROM sorties_caisse WHERE date_sortie BETWEEN ? AND ?";
$stmt_s = $pdo->prepare($sql_sorties);
$stmt_s->execute([$date_debut, $date_fin]);
$total_sorties = (float)($stmt_s->fetchColumn() ?? 0);

// SOLDE NET REEL DE CAISSE = Total Encaissements - Total Sorties
$solde_net_caisse = $total_encaisse - $total_sorties;

// 3. Ventilation par mode de paiement des recettes
$sql_modes = "SELECT mode_paiement, SUM(montant_final) as total, COUNT(*) as nb
              FROM factures
              WHERE statut_paiement IN ('payee','partielle')
              AND date_emission BETWEEN ? AND ?
              GROUP BY mode_paiement";
$stmt_m = $pdo->prepare($sql_modes);
$stmt_m->execute([$date_debut, $date_fin]);
$modes_paiement = $stmt_m->fetchAll();

// 4. Journal chronologique des factures
$sql_factures = "SELECT f.*, cl.nom as client_nom, cl.prenom as client_prenom, u.identifiant as recep_name
                 FROM factures f
                 JOIN clients cl ON f.id_client = cl.id
                 LEFT JOIN utilisateurs u ON f.id_receptionniste = u.id
                 WHERE f.date_emission BETWEEN ? AND ?
                 ORDER BY f.date_emission DESC, f.id DESC";
$stmt_f = $pdo->prepare($sql_factures);
$stmt_f->execute([$date_debut, $date_fin]);
$factures = $stmt_f->fetchAll();

// 5. Journal chronologique des sorties de caisse
$sql_depenses_list = "SELECT s.*, u.identifiant as agent_name
                      FROM sorties_caisse s
                      JOIN utilisateurs u ON s.id_utilisateur = u.id
                      WHERE s.date_sortie BETWEEN ? AND ?
                      ORDER BY s.date_sortie DESC, s.id DESC";
$stmt_dl = $pdo->prepare($sql_depenses_list);
$stmt_dl->execute([$date_debut, $date_fin]);
$sorties_list = $stmt_dl->fetchAll();

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Rapport Financier, Solde de Caisse & Achats Stock (<?= h($period_info['label']) ?>)</h2>
    <p>Édité le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Comptabilité & Caisse</h1>
        <p class="text-secondary" style="margin-top:4px;">Journal des encaissements, sorties de stock/charges et bilan de caisse net</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="add_depense.php" class="btn btn-danger">📤 Enregistrer une Sortie de Caisse</a>
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer le Bilan (<?= h($period_info['label']) ?>)
        </button>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <!-- Raccourcis de sélection rapide -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Période Financière :</span>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= $period_preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois</a>
                <a href="?period=year" class="btn btn-sm <?= $period_preset==='year'?'btn-primary':'btn-outline' ?>">Cette Année</a>
            </div>

            <!-- Dates sur mesure -->
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="period" value="custom">
                <span style="font-size:12px; color:var(--text-secondary);">Du :</span>
                <input type="date" name="date_debut" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_debut) ?>">
                <span style="font-size:12px; color:var(--text-secondary);">Au :</span>
                <input type="date" name="date_fin" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Filtrer Période</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Bilan Financier avec Solde Réel -->
<div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:24px;">
    <div class="card p-3 text-center" style="border-left:4px solid var(--s-libre);">
        <span class="text-secondary" style="font-size:13px;">Total Recettes Encaissées</span>
        <h2 style="margin:8px 0; font-size:22px; color:var(--s-libre);"><?= format_money($total_encaisse) ?></h2>
        <span class="text-secondary" style="font-size:12px;">Hébergement + Prestations</span>
    </div>
    
    <div class="card p-3 text-center" style="border-left:4px solid var(--s-occupee);">
        <span class="text-secondary" style="font-size:13px;">Sorties de Caisse & Stock</span>
        <h2 style="margin:8px 0; font-size:22px; color:var(--s-occupee);">- <?= format_money($total_sorties) ?></h2>
        <span class="text-secondary" style="font-size:12px;">Achats, entretien, maintenance</span>
    </div>

    <div class="card p-3 text-center" style="border-left:4px solid <?= $solde_net_caisse >= 0 ? 'var(--gold-500)' : 'var(--s-occupee)' ?>; background:var(--bg-app);">
        <span class="text-secondary" style="font-size:13px; font-weight:700;">⚖️ SOLDE NET REEL DE CAISSE</span>
        <h2 style="margin:8px 0; font-size:24px; color:<?= $solde_net_caisse >= 0 ? 'var(--gold-500)' : 'var(--s-occupee)' ?>; font-weight:700;">
            <?= format_money($solde_net_caisse) ?>
        </h2>
        <span class="text-secondary" style="font-size:12px;">Disponibilité nette en caisse</span>
    </div>

    <div class="card p-3 text-center" style="border-left:4px solid var(--s-reservee);">
        <span class="text-secondary" style="font-size:13px;">Créances À Recouvrer</span>
        <h2 style="margin:8px 0; font-size:22px; color:var(--s-reservee);"><?= format_money($reste_recouvrer) ?></h2>
        <span class="text-secondary" style="font-size:12px;">Factures en attente</span>
    </div>
</div>

<!-- 1. Journal des Sorties de Caisse & Achats Stock -->
<div class="card mb-4">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title" style="color:var(--s-occupee);">📤 Journal des Sorties de Caisse & Achats Stock (<?= count($sorties_list) ?>)</h3>
        <a href="add_depense.php" class="btn btn-sm btn-danger no-print">+ Enregistrer une Sortie</a>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>N° Bon</th>
                    <th>Date</th>
                    <th>Motif / Libellé Achat</th>
                    <th>Catégorie</th>
                    <th>Montant Décaissé</th>
                    <th>Mode Règlement</th>
                    <th>Réf. Reçu</th>
                    <th>Agent</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sorties_list as $sort): ?>
                <tr>
                    <td><strong>#DEP-<?= str_pad($sort['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= format_date($sort['date_sortie']) ?></td>
                    <td>
                        <strong><?= h($sort['libelle']) ?></strong>
                        <?php if ($sort['notes']): ?>
                            <br><small class="text-secondary"><?= h($sort['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-reservee" style="text-transform:uppercase;"><?= h($sort['categorie']) ?></span></td>
                    <td><strong style="color:var(--s-occupee);">- <?= format_money($sort['montant']) ?></strong></td>
                    <td><small style="text-transform:uppercase;"><?= h($sort['mode_paiement']) ?></small></td>
                    <td><small><?= h($sort['reference_recu'] ?? '—') ?></small></td>
                    <td><small><?= h($sort['agent_name']) ?></small></td>
                    <td class="no-print" style="display:flex; gap:4px;">
                        <a href="print_depense.php?id=<?= $sort['id'] ?>" class="btn btn-sm btn-outline" target="_blank" title="Imprimer Bon de Sortie">📄 Bon</a>
                        <a href="?action=delete_depense&id_depense=<?= $sort['id'] ?>&period=<?= h($period_preset) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Annuler/Supprimer cette sortie de caisse ?')">✕</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sorties_list)): ?>
                <tr><td colspan="9" class="text-center p-4 text-secondary">Aucune sortie de caisse enregistrée sur cette période.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 2. Journal Chronologique des Encaissements -->
<div class="card mb-4">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title" style="color:var(--s-libre);">📥 Journal Chronologique des Encaissements & Factures (<?= count($factures) ?>)</h3>
        <a href="<?= BASE_URL ?>/modules/facturation/create.php" class="btn btn-sm btn-primary no-print">+ Nouvelle Facture</a>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>N° Facture</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Séjour</th>
                    <th>Extras</th>
                    <th>Montant Total</th>
                    <th>Statut Règlement</th>
                    <th>Mode</th>
                    <th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($factures as $f): ?>
                <tr>
                    <td><strong>#F-<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= format_date($f['date_emission']) ?></td>
                    <td><?= h($f['client_nom'].' '.$f['client_prenom']) ?></td>
                    <td><?= format_money($f['montant_sejour']) ?></td>
                    <td><?= format_money($f['montant_services']) ?></td>
                    <td><strong style="color:var(--gold-500);"><?= format_money($f['montant_final']) ?></strong></td>
                    <td><?= status_badge($f['statut_paiement']) ?></td>
                    <td><small style="text-transform:uppercase;"><?= h($f['mode_paiement'] ?? '—') ?></small></td>
                    <td class="no-print">
                        <a href="<?= BASE_URL ?>/modules/facturation/print.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline" target="_blank">📄 Facture</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($factures)): ?>
                <tr><td colspan="9" class="text-center p-4 text-secondary">Aucune facture enregistrée pour cette période.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Document officiel de comptabilité, sorties de stock et solde de caisse. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
