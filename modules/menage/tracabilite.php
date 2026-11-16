<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Traçabilité du Personnel de Nettoyage";

// Période dynamique (Jour, Semaine, Mois, Année, Custom)
$period_preset = $_GET['period'] ?? 'today';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

$period_info   = get_period_dates($period_preset, $custom_debut, $custom_fin);
$date_debut    = $period_info['debut'];
$date_fin      = $period_info['fin'];

// Action rapide de mise à jour de statut depuis la traçabilité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_tache'])) {
    $id_tache = post_int('id_tache');
    $action   = post('action_tache');

    if ($id_tache > 0) {
        $stmt_t = $pdo->prepare("SELECT * FROM taches_menage WHERE id = ?");
        $stmt_t->execute([$id_tache]);
        $tache = $stmt_t->fetch();

        if ($tache) {
            if ($action === 'demarrer') {
                $stmt_u = $pdo->prepare("UPDATE taches_menage SET heure_debut = NOW(), statut = 'en_cours' WHERE id = ?");
                $stmt_u->execute([$id_tache]);
                set_flash('success', 'Tâche de nettoyage démarrée (horodatage enregistré).');
            } elseif ($action === 'terminer') {
                // Calcul automatique de la durée en minutes
                $heure_debut = $tache['heure_debut'] ? new DateTime($tache['heure_debut']) : new DateTime();
                $now = new DateTime();
                $diff_min = max(5, (int)round(($now->getTimestamp() - $heure_debut->getTimestamp()) / 60));

                $stmt_u = $pdo->prepare("UPDATE taches_menage SET heure_fin = NOW(), duree_minutes = ?, statut = 'terminee' WHERE id = ?");
                $stmt_u->execute([$diff_min, $id_tache]);

                // Passage de la chambre à 'libre' si besoin
                $stmt_c = $pdo->prepare("UPDATE chambres SET statut = 'libre' WHERE id = ?");
                $stmt_c->execute([$tache['id_chambre']]);

                set_flash('success', "Tâche terminée en $diff_min minutes. La chambre a été marquée comme Libre.");
            } elseif ($action === 'inspecter') {
                $qualite = post('qualite_inspection', 'impeccable');
                $stmt_u = $pdo->prepare("UPDATE taches_menage SET statut = 'inspectee', qualite_inspection = ? WHERE id = ?");
                $stmt_u->execute([$qualite, $id_tache]);
                set_flash('success', 'Inspection enregistrée avec succès.');
            }
        }
    }
    redirect('tracabilite.php?period=' . $period_preset);
}

// Filtres supplémentaires
$filter_agent  = post_int('id_agent', $_GET['id_agent'] ?? 0);
$filter_statut = post('statut', $_GET['statut'] ?? '');

// 1. Tâches de ménage filtrées par période dynamique
$sql_taches = "SELECT tm.*, c.numero as chambre_num, c.etage, tc.nom as type_nom,
               p.nom as agent_nom, p.prenom as agent_prenom, p.telephone as agent_tel,
               u.nom as user_nom, u.prenom as user_prenom
               FROM taches_menage tm
               JOIN chambres c ON tm.id_chambre = c.id
               JOIN types_chambres tc ON c.id_type_chambre = tc.id
               LEFT JOIN personnel p ON tm.id_agent = p.id
               LEFT JOIN utilisateurs u ON tm.id_utilisateur = u.id
               WHERE (tm.date_prevue BETWEEN ? AND ? OR DATE(tm.created_at) BETWEEN ? AND ?)";

$params_t = [$date_debut, $date_fin, $date_debut, $date_fin];

if ($filter_agent > 0) {
    $sql_taches .= " AND tm.id_agent = ?";
    $params_t[] = $filter_agent;
}
if ($filter_statut) {
    $sql_taches .= " AND tm.statut = ?";
    $params_t[] = $filter_statut;
}

$sql_taches .= " ORDER BY CASE tm.statut WHEN 'en_cours' THEN 1 WHEN 'assignee' THEN 2 WHEN 'terminee' THEN 3 ELSE 4 END, tm.id DESC";
$stmt = $pdo->prepare($sql_taches);
$stmt->execute($params_t);
$taches = $stmt->fetchAll();

// 2. Liste des statistiques individuelles sur la période (Caissières connectées + Personnel)
$stmt_u_stats = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.role,
           COUNT(tm.id) as total_taches,
           SUM(CASE WHEN tm.statut IN ('terminee','inspectee') THEN 1 ELSE 0 END) as terminees
    FROM utilisateurs u
    JOIN taches_menage tm ON tm.id_utilisateur = u.id AND (tm.date_prevue BETWEEN ? AND ? OR DATE(tm.created_at) BETWEEN ? AND ?)
    GROUP BY u.id
    ORDER BY terminees DESC
");
$stmt_u_stats->execute([$date_debut, $date_fin, $date_debut, $date_fin]);
$user_clean_stats = $stmt_u_stats->fetchAll();

$agents = $pdo->query("SELECT * FROM personnel WHERE role = 'menage' AND actif = 1 ORDER BY nom ASC")->fetchAll();

$stmt_ag = $pdo->prepare("SELECT p.id, p.nom, p.prenom, p.telephone,
                          COUNT(tm.id) as total_taches,
                          SUM(CASE WHEN tm.statut IN ('terminee','inspectee') THEN 1 ELSE 0 END) as terminees,
                          ROUND(AVG(tm.duree_minutes), 0) as duree_moyenne
                          FROM personnel p
                          LEFT JOIN taches_menage tm ON p.id = tm.id_agent AND (tm.date_prevue BETWEEN ? AND ?)
                          WHERE p.role = 'menage' AND p.actif = 1
                          GROUP BY p.id");
$stmt_ag->execute([$date_debut, $date_fin]);
$agent_stats = $stmt_ag->fetchAll();

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Registre & Traçabilité du Nettoyage (<?= h($period_info['label']) ?>)</h2>
    <p>Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<!-- ── Bouton Retour Dédié ── -->
<div class="no-print">
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn-page-back">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>← Retour au Tableau de bord</span>
    </a>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title" style="margin:0; line-height:1.2;">Traçabilité du Nettoyage des Chambres</h1>
        <p class="text-secondary" style="margin:5px 0 0 0; font-size:13.5px;">Suivi chronologique des chambres nettoyées par le personnel</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;" class="page-header-actions">
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer la Traçabilité
        </button>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <!-- Raccourcis de sélection rapide -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Période :</span>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= $period_preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois</a>
                <a href="?period=year" class="btn btn-sm <?= $period_preset==='year'?'btn-primary':'btn-outline' ?>">Cette Année</a>
            </div>

            <!-- Dates sur mesure -->
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="date_debut" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_debut) ?>">
                <input type="date" name="date_fin" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Synthèse des Chambres Nettoyées -->
<div class="card mb-4">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Bilan du Nettoyage par Employé(e) / Caissière</h3>
        <span class="badge badge-libre"><?= h($period_info['label']) ?></span>
    </div>
    <div class="card-body p-0">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px; padding:16px;">
            <?php if (!empty($user_clean_stats)): ?>
                <?php foreach ($user_clean_stats as $ug): ?>
                <div style="background: #f8fafc; border: 1.5px solid #c7d2fe; padding:14px; border-radius:var(--r-md); box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong style="font-size:15px; color: var(--primary);">👤 <?= h($ug['prenom'].' '.$ug['nom']) ?></strong>
                        <span class="badge badge-nettoyage" style="font-size:11px;"><?= ucfirst(h($ug['role'])) ?></span>
                    </div>
                    <div style="font-size:13px; color:var(--text-secondary);">
                        • Chambres nettoyées & validées : <strong style="color: #059669; font-size: 15px;"><?= $ug['terminees'] ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php foreach ($agent_stats as $ag): ?>
            <div style="background:var(--bg-app); border:1px solid var(--border-light); padding:14px; border-radius:var(--r-md);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong style="font-size:15px; color:var(--gold-500);"><?= h($ag['prenom'].' '.$ag['nom']) ?></strong>
                    <span class="badge badge-libre" style="font-size:11px;"><?= h($ag['telephone'] ?? 'Ménage') ?></span>
                </div>
                <div style="font-size:13px; color:var(--text-secondary);">
                    • Tâches effectuées : <strong><?= $ag['terminees'] ?> / <?= $ag['total_taches'] ?></strong><br>
                    • Durée moyenne / chambre : <strong><?= $ag['duree_moyenne'] ? $ag['duree_moyenne'].' min' : '—' ?></strong>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($user_clean_stats) && empty($agent_stats)): ?>
                <div style="padding: 12px; color: var(--text-light); font-style: italic;">
                    Aucun nettoyage enregistré sur cette période.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Table de Traçabilité des Tâches -->
<div class="card mb-4">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Journal Chronologique d'Intervention Ménage</h3>
        <span class="badge badge-nettoyage"><?= count($taches) ?> interventions enregistrées</span>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Chambre</th>
                    <th>Agent Affecté</th>
                    <th>Type & Priorité</th>
                    <th>Heure Début</th>
                    <th>Heure Fin</th>
                    <th>Durée Est.</th>
                    <th>Statut</th>
                    <th>Inspection</th>
                    <th class="no-print">Actions Rapides</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taches as $t): ?>
                <tr>
                    <td>
                        <strong>N° <?= h($t['chambre_num']) ?></strong> (Étage <?= $t['etage'] ?>)<br>
                        <small class="text-secondary"><?= h($t['type_nom']) ?></small>
                    </td>
                    <td>
                        <?php if ($t['agent_nom']): ?>
                            <strong><?= h($t['agent_prenom'].' '.$t['agent_nom']) ?></strong>
                        <?php elseif (!empty($t['user_prenom'])): ?>
                            <strong>👤 <?= h($t['user_prenom'].' '.$t['user_nom']) ?></strong> (Caissière)
                        <?php else: ?>
                            <span class="text-secondary">Caissière / Agent</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:600; text-transform:capitalize;"><?= h($t['type_tache']) ?></span><br>
                        <small class="<?= $t['priorite']==='urgente'?'text-danger':($t['priorite']==='haute'?'text-warning':'text-secondary') ?>">
                            Priorité : <?= h(ucfirst($t['priorite'])) ?>
                        </small>
                    </td>
                    <td><?= $t['heure_debut'] ? format_datetime($t['heure_debut']) : '—' ?></td>
                    <td><?= $t['heure_fin'] ? format_datetime($t['heure_fin']) : '—' ?></td>
                    <td>
                        <?php if ($t['duree_minutes']): ?>
                            <span class="badge badge-libre"><?= $t['duree_minutes'] ?> min</span>
                        <?php else: ?>
                            <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($t['statut']) ?></td>
                    <td>
                        <?php if ($t['qualite_inspection']): ?>
                            <?= status_badge($t['qualite_inspection']) ?>
                        <?php else: ?>
                            <span class="text-secondary" style="font-size:12px;">En attente</span>
                        <?php endif; ?>
                    </td>
                    <td class="no-print">
                        <form method="POST" style="display:inline-flex; gap:4px;">
                            <input type="hidden" name="id_tache" value="<?= $t['id'] ?>">
                            <?php if ($t['statut'] === 'assignee'): ?>
                                <button type="submit" name="action_tache" value="demarrer" class="btn btn-sm btn-primary">▶ Démarrer</button>
                            <?php elseif ($t['statut'] === 'en_cours'): ?>
                                <button type="submit" name="action_tache" value="terminer" class="btn btn-sm btn-success">✓ Terminer</button>
                            <?php elseif ($t['statut'] === 'terminee'): ?>
                                <button type="submit" name="action_tache" value="inspecter" class="btn btn-sm btn-outline">🔍 Valider Inspection</button>
                            <?php else: ?>
                                <span class="text-success" style="font-size:12px;">Conforme</span>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($taches)): ?>
                <tr>
                    <td colspan="9" class="text-center p-4 text-secondary">Aucune tâche de ménage enregistrée pour cette période.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Registre officiel d'émargement du personnel de nettoyage. Document imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
