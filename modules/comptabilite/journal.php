<?php
// ============================================================
// modules/comptabilite/journal.php — Journal de Caisse Patron (v2.0)
// Traçabilité unifiée des Entrées et des Sorties
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

// Filtres
$filtre_periode = $_GET['periode'] ?? 'aujourdhui';
$date_debut     = $_GET['date_debut'] ?? '';
$date_fin       = $_GET['date_fin'] ?? '';
$id_user        = (int)($_GET['id_user'] ?? 0);
$type_flux      = $_GET['type_flux'] ?? 'tous';

// Calcul des dates selon la période
$today = date('Y-m-d');
if ($filtre_periode === 'aujourdhui') {
    $date_debut = $today;
    $date_fin   = $today;
} elseif ($filtre_periode === 'hier') {
    $date_debut = date('Y-m-d', strtotime('-1 day'));
    $date_fin   = $date_debut;
} elseif ($filtre_periode === 'semaine') {
    $date_debut = date('Y-m-d', strtotime('monday this week'));
    $date_fin   = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filtre_periode === 'mois') {
    $date_debut = date('Y-m-01');
    $date_fin   = date('Y-m-t');
} else {
    if (empty($date_debut)) $date_debut = $today;
    if (empty($date_fin))   $date_fin   = $today;
}

// 1. Récupérer les ENTRÉES (Factures payées / partielles)
$sql_entrees = "
    SELECT 
        'entree' as type_mouvement,
        f.id as id_operation,
        f.created_at as date_heure,
        f.date_emission as date_op,
        f.montant_final as montant,
        f.mode_paiement,
        CONCAT('Paiement Séjour - Client: ', c.nom, ' ', c.prenom, IF(ch.numero IS NOT NULL, CONCAT(' (Ch. ', ch.numero, ')'), '')) as libelle,
        u.nom as user_nom,
        u.prenom as user_prenom,
        u.id as id_user
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

// 2. Récupérer les SORTIES (Dépenses / Achats)
$sql_sorties = "
    SELECT 
        'sortie' as type_mouvement,
        s.categorie,
        s.id as id_operation,
        s.created_at as date_heure,
        s.date_sortie as date_op,
        s.montant as montant,
        s.mode_paiement,
        CONCAT(s.libelle, IF(s.notes IS NOT NULL AND s.notes != '', CONCAT(' — ', s.notes), '')) as libelle,
        u.nom as user_nom,
        u.prenom as user_prenom,
        u.id as id_user
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

// Fusionner et trier par date/heure
$mouvements = [];
if ($type_flux !== 'sorties') {
    $mouvements = array_merge($mouvements, $entrees);
}
if ($type_flux !== 'entrees') {
    $mouvements = array_merge($mouvements, $sorties);
}

usort($mouvements, function($a, $b) {
    return strtotime($b['date_heure']) - strtotime($a['date_heure']);
});

// Calcul des totaux
$total_entrees = array_sum(array_column($entrees, 'montant'));
$total_sorties = array_sum(array_column($sorties, 'montant'));
$solde_net     = $total_entrees - $total_sorties;

// Liste des employés pour le filtre
$employes = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE actif = 1 ORDER BY nom ASC")->fetchAll();

$page_title = "Journal de Caisse & Traçabilité";
require_once '../../includes/header.php';
?>

<div style="max-width: 1240px; margin: 0 auto;">
    
    <!-- ── Bouton Retour au Tableau de Bord (Navigation Dédiée) ── -->
    <div>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-page-back">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>← Retour au Tableau de bord</span>
        </a>
    </div>

    <!-- ── En-tête Titre & Actions ── -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;" class="page-header">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.25; word-break: break-word;">
                📊 Journal Général de Caisse & Traçabilité
            </h1>
            <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13.5px;">
                Vue complète des entrées (séjours) et des sorties (charges) du 
                <strong><?= format_date($date_debut) ?></strong> au <strong><?= format_date($date_fin) ?></strong>.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;" class="page-header-actions">
            <a href="print_journal.php?periode=<?= urlencode($filtre_periode) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>&id_user=<?= $id_user ?>&type_flux=<?= urlencode($type_flux) ?>" target="_blank" class="btn btn-primary" style="box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);">
                <span>🖨️</span> Imprimer Bilan A4
            </a>
            <?php if (!is_admin()): ?>
                <a href="transfert_boss.php" class="btn" style="background:#d97706; color:#ffffff; font-weight:700; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.3);">
                    <span>👑</span> Verser au Boss
                </a>
            <?php endif; ?>
            <a href="add_depense.php" class="btn btn-danger">
                <span>💸</span> + Dépense
            </a>
        </div>
    </div>

    <!-- ── Cartes de Synthèse Financière (Metrics Grid) ── -->
    <div class="metrics-grid">
        <div class="metric-card" style="border-left: 4px solid #059669;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: #059669;">Total Entrées (Recettes)</span>
                <span class="badge badge-libre">🟢 Recettes</span>
            </div>
            <div class="metric-value" style="color: #059669;"><?= format_prix($total_entrees) ?></div>
            <div class="metric-sub"><?= count($entrees) ?> encaissement(s) sur la période</div>
        </div>

        <div class="metric-card" style="border-left: 4px solid #e11d48;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: #e11d48;">Total Sorties (Dépenses)</span>
                <span class="badge badge-occupee">🔴 Dépenses</span>
            </div>
            <div class="metric-value" style="color: #e11d48;"><?= format_prix($total_sorties) ?></div>
            <div class="metric-sub"><?= count($sorties) ?> sortie(s) enregistrée(s)</div>
        </div>

        <div class="metric-card" style="border-left: 4px solid <?= $solde_net >= 0 ? '#4f46e5' : '#e11d48' ?>;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: var(--primary);">Solde Net de Caisse</span>
                <span class="badge badge-nettoyage">⚖️ Bilan</span>
            </div>
            <div class="metric-value" style="color: <?= $solde_net >= 0 ? 'var(--text-primary)' : '#e11d48' ?>;"><?= format_prix($solde_net) ?></div>
            <div class="metric-sub">Entrées déduites des dépenses</div>
        </div>
    </div>

    <!-- ── Barre de Filtres ── -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 20px 24px;">
            <form method="GET" action="journal.php" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                
                <div style="min-width: 160px;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Période :</label>
                    <select name="periode" class="form-select" onchange="toggleCustomDates(this.value)">
                        <option value="aujourdhui" <?= $filtre_periode === 'aujourdhui' ? 'selected' : '' ?>>Aujourd'hui</option>
                        <option value="hier" <?= $filtre_periode === 'hier' ? 'selected' : '' ?>>Hier</option>
                        <option value="semaine" <?= $filtre_periode === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                        <option value="mois" <?= $filtre_periode === 'mois' ? 'selected' : '' ?>>Ce mois</option>
                        <option value="personnalisee" <?= $filtre_periode === 'personnalisee' ? 'selected' : '' ?>>Plage personnalisée...</option>
                    </select>
                </div>

                <div id="customDates" style="display: <?= $filtre_periode === 'personnalisee' ? 'flex' : 'none' ?>; gap: 10px;">
                    <div>
                        <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Du :</label>
                        <input type="date" name="date_debut" value="<?= $date_debut ?>" class="form-input">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Au :</label>
                        <input type="date" name="date_fin" value="<?= $date_fin ?>" class="form-input">
                    </div>
                </div>

                <div style="min-width: 200px;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Employé / Caissière :</label>
                    <select name="id_user" class="form-select">
                        <option value="0">-- Tous les employés --</option>
                        <?php foreach ($employes as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $id_user == $emp['id'] ? 'selected' : '' ?>>
                                👤 <?= h($emp['nom'] . ' ' . $emp['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="min-width: 150px;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Type de Flux :</label>
                    <select name="type_flux" class="form-select">
                        <option value="tous" <?= $type_flux === 'tous' ? 'selected' : '' ?>>Tous les flux</option>
                        <option value="entrees" <?= $type_flux === 'entrees' ? 'selected' : '' ?>>Entrées (+) seules</option>
                        <option value="sorties" <?= $type_flux === 'sorties' ? 'selected' : '' ?>>Sorties (-) seules</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="height: 46px;">
                    🔍 Filtrer
                </button>
                <a href="journal.php" class="btn btn-outline" style="height: 46px;">Réinitialiser</a>
            </form>
        </div>
    </div>

    <!-- ── Tableau du Journal ── -->
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Date & Heure</th>
                        <th style="width: 12%;">Type</th>
                        <th>Motif / Détail de l'opération</th>
                        <th style="width: 18%;">Employé(e)</th>
                        <th style="width: 10%;">Mode</th>
                        <th style="width: 15%; text-align: right;">Montant</th>
                        <th style="width: 10%; text-align: center;">Reçu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mouvements)): ?>
                        <tr>
                            <td colspan="7" style="padding: 40px 20px; text-align: center; color: var(--text-light); font-style: italic;">
                                Aucune opération enregistrée pour les critères sélectionnés.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mouvements as $m): 
                            $recu_url = ($m['type_mouvement'] === 'entree')
                                ? BASE_URL . '/modules/facturation/print.php?id=' . $m['id_operation']
                                : BASE_URL . '/modules/comptabilite/print_depense.php?id=' . $m['id_operation'];
                        ?>
                            <tr>
                                <td style="color: var(--text-secondary); font-weight: 500;">
                                    <?= date('d/m/Y H:i', strtotime($m['date_heure'])) ?>
                                </td>
                                <td>
                                    <?php if ($m['type_mouvement'] === 'entree'): ?>
                                        <span class="badge badge-libre">🟢 ENTRÉE</span>
                                    <?php elseif (($m['categorie'] ?? '') === 'versement_patron'): ?>
                                        <span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:800;">👑 VERS. BOSS</span>
                                    <?php else: ?>
                                        <span class="badge badge-occupee">🔴 SORTIE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 600; color: var(--text-primary);">
                                    <?= h($m['libelle']) ?>
                                </td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:6px; font-weight:500;">
                                        👤 <?= h(($m['user_prenom'] ?? '') . ' ' . ($m['user_nom'] ?? '')) ?: 'N/A' ?>
                                    </span>
                                </td>
                                <td style="text-transform: capitalize; color: var(--text-secondary);">
                                    <?= h($m['mode_paiement'] ?? 'Espèces') ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; font-size: 15px; color: <?= $m['type_mouvement'] === 'entree' ? '#059669' : '#e11d48' ?>;">
                                    <?= $m['type_mouvement'] === 'entree' ? '+' : '-' ?> <?= format_prix($m['montant']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?= $recu_url ?>" target="_blank" class="btn btn-sm btn-outline">
                                        🖨️ Reçu
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8fafc; font-weight: 800; font-size: 15px; border-top: 2px solid var(--border-light);">
                        <td colspan="5" style="padding: 18px; text-align: right;">SOLDE NET FINAL :</td>
                        <td style="padding: 18px; text-align: right; color: <?= $solde_net >= 0 ? '#059669' : '#e11d48' ?>; font-size: 18px;">
                            <?= format_prix($solde_net) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<script>
function toggleCustomDates(val) {
    document.getElementById('customDates').style.display = (val === 'personnalisee') ? 'flex' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
