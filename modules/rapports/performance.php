<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$page_title = "Analyse de Rentabilité & Performance Décisionnelle";

// Période dynamique (Jour, Semaine, Mois, Année, Custom)
$period_preset = $_GET['period'] ?? 'month';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

$period_info   = get_period_dates($period_preset, $custom_debut, $custom_fin);
$date_debut    = $period_info['debut'];
$date_fin      = $period_info['fin'];

// 1. TOP CLIENTS LES PLUS RENTABLES
$sql_top_clients = "SELECT cl.id, cl.nom, cl.prenom, cl.telephone, cl.nb_sejours,
                    COUNT(f.id) as total_factures,
                    SUM(f.montant_final) as ca_total,
                    AVG(f.montant_final) as panier_moyen
                    FROM clients cl
                    JOIN factures f ON cl.id = f.id_client
                    WHERE f.statut_paiement IN ('payee','partielle')
                    AND f.date_emission BETWEEN ? AND ?
                    GROUP BY cl.id
                    ORDER BY ca_total DESC LIMIT 5";
$stmt_tc = $pdo->prepare($sql_top_clients);
$stmt_tc->execute([$date_debut, $date_fin]);
$top_clients = $stmt_tc->fetchAll();

// CLIENTS MOINS RENTABLES / À RELANCER
$sql_low_clients = "SELECT cl.id, cl.nom, cl.prenom, cl.telephone, cl.nb_sejours,
                    SUM(f.montant_final) as ca_total
                    FROM clients cl
                    JOIN factures f ON cl.id = f.id_client
                    WHERE f.date_emission BETWEEN ? AND ?
                    GROUP BY cl.id
                    ORDER BY ca_total ASC LIMIT 5";
$stmt_lc = $pdo->prepare($sql_low_clients);
$stmt_lc->execute([$date_debut, $date_fin]);
$low_clients = $stmt_lc->fetchAll();

// 2. RENTABILITÉ & PERFORMANCE DES CHAMBRES
$sql_chambres_perf = "SELECT c.id, c.numero, tc.nom as type_nom, tc.prix_nuit,
                      COUNT(DISTINCT r.id) as total_sejours,
                      SUM(DATEDIFF(r.date_depart, r.date_arrivee)) as total_nuits,
                      SUM(DATEDIFF(r.date_depart, r.date_arrivee) * tc.prix_nuit) as ca_genere
                      FROM chambres c
                      JOIN types_chambres tc ON c.id_type_chambre = tc.id
                      LEFT JOIN reservations r ON c.id = r.id_chambre AND (r.date_arrivee <= ? AND r.date_depart >= ?) AND r.statut IN ('en_cours','terminee','confirmee')
                      GROUP BY c.id
                      ORDER BY ca_genere DESC";
$stmt_cp = $pdo->prepare($sql_chambres_perf);
$stmt_cp->execute([$date_fin, $date_debut]);
$chambres_perf = $stmt_cp->fetchAll();

// Separer top vs flop chambres
$top_chambres = array_slice($chambres_perf, 0, 4);
$flop_chambres = array_reverse(array_slice($chambres_perf, -4));

// 3. SERVICES ET PRESTATIONS EXTRAS (Rentables vs Coûteux / Peu vendant)
$sql_services = "SELECT s.id, s.nom, s.categorie, s.prix,
                 COALESCE(SUM(cl.quantite), 0) as quantite_vendue,
                 COALESCE(SUM(cl.sous_total), 0) as ca_généré
                 FROM catalogue_services s
                 LEFT JOIN commande_lignes cl ON s.id = cl.id_service
                 GROUP BY s.id
                 ORDER BY ca_généré DESC";
$services_perf = $pdo->query($sql_services)->fetchAll();

$top_services  = array_slice($services_perf, 0, 4);
$low_services  = array_reverse(array_slice($services_perf, -4));

// 4. PERFORMANCE & PRODUCTIVITÉ DU PERSONNEL
// A. Personnel Ménage
$sql_menage_perf = "SELECT p.id, p.nom, p.prenom,
                    COUNT(tm.id) as total_taches,
                    SUM(CASE WHEN tm.statut IN ('terminee','inspectee') THEN 1 ELSE 0 END) as terminees,
                    ROUND(AVG(tm.duree_minutes), 0) as duree_moy,
                    SUM(CASE WHEN tm.qualite_inspection = 'impeccable' THEN 1 ELSE 0 END) as nb_impeccable,
                    SUM(CASE WHEN tm.qualite_inspection = 'a_revoir' THEN 1 ELSE 0 END) as nb_a_revoir
                    FROM personnel p
                    LEFT JOIN taches_menage tm ON p.id = tm.id_agent AND (tm.date_prevue BETWEEN ? AND ?)
                    WHERE p.role = 'menage' AND p.actif = 1
                    GROUP BY p.id
                    ORDER BY terminees DESC";
$stmt_mp = $pdo->prepare($sql_menage_perf);
$stmt_mp->execute([$date_debut, $date_fin]);
$menage_perf = $stmt_mp->fetchAll();

// B. Performance Réceptionnistes (Encaissements)
$sql_recep_perf = "SELECT u.id, u.identifiant, u.nom, u.prenom,
                   COUNT(f.id) as factures_emises,
                   SUM(f.montant_final) as encaissements_totaux
                   FROM utilisateurs u
                   JOIN factures f ON u.id = f.id_receptionniste
                   WHERE f.date_emission BETWEEN ? AND ?
                   GROUP BY u.id
                   ORDER BY encaissements_totaux DESC";
$stmt_rp = $pdo->prepare($sql_recep_perf);
$stmt_rp->execute([$date_debut, $date_fin]);
$recep_perf = $stmt_rp->fetchAll();

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Tableau Décisionnel de Rentabilité & Performance Établissement</h2>
    <p>Période : <?= h($period_info['label']) ?> — Édité le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Rentabilité & Performance Décisionnelle</h1>
        <p class="text-secondary" style="margin-top:4px;">Analyse comparative des clients rentables, chambres performantes, prestations et productivité du personnel</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn-print" onclick="window.print()">
            🖨️ Imprimer l'Analyse Décisionnelle
        </button>
        <a href="bilan_employes.php" class="btn btn-outline">👥 Bilan Employés</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Période d'Analyse :</span>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= $period_preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois</a>
                <a href="?period=year" class="btn btn-sm <?= $period_preset==='year'?'btn-primary':'btn-outline' ?>">Cette Année</a>
            </div>

            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="period" value="custom">
                <span style="font-size:12px; color:var(--text-secondary);">Du :</span>
                <input type="date" name="date_debut" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_debut) ?>">
                <span style="font-size:12px; color:var(--text-secondary);">Au :</span>
                <input type="date" name="date_fin" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Calculer Performance</button>
            </div>
        </form>
    </div>
</div>

<!-- SECTION 1 : RENTABILITÉ CLIENTS (Top vs À Relancer) -->
<div class="card mb-4">
    <div class="card-header" style="background:var(--bg-app);">
        <h3 class="card-title" style="color:var(--gold-500);">👤 1. Rentabilité de la Clientèle (Clients les Plus Rentables vs À Relancer)</h3>
    </div>
    <div class="card-body p-0">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
            <!-- Top Clients -->
            <div style="border-right:1px solid var(--border-light); padding:16px;">
                <h4 style="margin:0 0 12px 0; color:var(--s-libre);">🏆 Top 5 Clients les Plus Rentables</h4>
                <table class="table">
                    <thead>
                        <tr><th>Client</th><th>Séjours</th><th>Panier Moyen</th><th>CA Généré</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_clients as $tc): ?>
                        <tr>
                            <td><strong><?= h($tc['prenom'].' '.$tc['nom']) ?></strong></td>
                            <td><?= $tc['nb_sejours'] ?> séjour(s)</td>
                            <td><?= format_money($tc['panier_moyen']) ?></td>
                            <td><strong style="color:var(--gold-500);"><?= format_money($tc['ca_total']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top_clients)): ?>
                        <tr><td colspan="4" class="text-center p-3 text-secondary">Aucune donnée sur cette période.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Clients Moins Rentables -->
            <div style="padding:16px;">
                <h4 style="margin:0 0 12px 0; color:var(--s-occupee);">⚠️ Clients à Faible Rendement / À Relancer</h4>
                <table class="table">
                    <thead>
                        <tr><th>Client</th><th>Téléphone</th><th>Cumul Séjours</th><th>CA Réalisé</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_clients as $lc): ?>
                        <tr>
                            <td><strong><?= h($lc['prenom'].' '.$lc['nom']) ?></strong></td>
                            <td><?= h($lc['telephone'] ?? '—') ?></td>
                            <td><?= $lc['nb_sejours'] ?> séjour(s)</td>
                            <td><strong><?= format_money($lc['ca_total']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($low_clients)): ?>
                        <tr><td colspan="4" class="text-center p-3 text-secondary">Aucune donnée sur cette période.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2 : PERFORMANCE DES CHAMBRES (Fort Rendement vs Sous-Performantes) -->
<div class="card mb-4">
    <div class="card-header" style="background:var(--bg-app);">
        <h3 class="card-title" style="color:var(--gold-500);">🏨 2. Rentabilité des Chambres (Chambres Star vs Chambres Sous-Occupées)</h3>
    </div>
    <div class="card-body p-0">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
            <!-- Top Chambres -->
            <div style="border-right:1px solid var(--border-light); padding:16px;">
                <h4 style="margin:0 0 12px 0; color:var(--s-libre);">⭐ Top Chambres les Plus Rentables</h4>
                <table class="table">
                    <thead>
                        <tr><th>Chambre</th><th>Type</th><th>Nuits Vendues</th><th>CA Généré</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_chambres as $ch_t): ?>
                        <tr>
                            <td><strong style="color:var(--gold-500);">N° <?= h($ch_t['numero']) ?></strong></td>
                            <td><?= h($ch_t['type_nom']) ?></td>
                            <td><?= $ch_t['total_nuits'] ?? 0 ?> nuit(s)</td>
                            <td><strong style="color:var(--s-libre);"><?= format_money($ch_t['ca_genere'] ?? 0) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Flop Chambres -->
            <div style="padding:16px;">
                <h4 style="margin:0 0 12px 0; color:var(--s-occupee);">📉 Chambres Sous-Performantes (À Promouvoir)</h4>
                <table class="table">
                    <thead>
                        <tr><th>Chambre</th><th>Type</th><th>Nuits Occupées</th><th>Piste d'Amélioration</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flop_chambres as $ch_f): ?>
                        <tr>
                            <td><strong>N° <?= h($ch_f['numero']) ?></strong></td>
                            <td><?= h($ch_f['type_nom']) ?></td>
                            <td><?= $ch_f['total_nuits'] ?? 0 ?> nuit(s)</td>
                            <td><small class="text-warning">Offrir réduction -15% ou rénover visuel</small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 3 : ANNALYSE DES SERVICES EXTRAS (Plus vs Moins Vendant) -->
<div class="card mb-4">
    <div class="card-header" style="background:var(--bg-app);">
        <h3 class="card-title" style="color:var(--gold-500);">📊 3. Rentabilité des Services & Prestations Extras</h3>
    </div>
    <div class="card-body p-0">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
            <!-- Top Services -->
            <div style="border-right:1px solid var(--border-light); padding:16px;">
                <h4 style="margin:0 0 12px 0; color:var(--s-libre);">📈 Prestations les Plus Demandées & Rentables</h4>
                <table class="table">
                    <thead>
                        <tr><th>Prestation</th><th>Catégorie</th><th>Quantités Vendantes</th><th>CA Généré</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_services as $ts): ?>
                        <tr>
                            <td><strong><?= h($ts['nom']) ?></strong></td>
                            <td><span class="badge badge-reservee"><?= h($ts['categorie']) ?></span></td>
                            <td><?= $ts['quantite_vendue'] ?> vente(s)</td>
                            <td><strong style="color:var(--gold-500);"><?= format_money($ts['ca_généré']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Services Moins Vendant -->
            <div style="padding:16px;">
                <h4 style="margin:0 0 12px 0; color:var(--s-occupee);">📉 Prestations Moins Vendues / À Ajuster</h4>
                <table class="table">
                    <thead>
                        <tr><th>Prestation</th><th>Prix Unitaire</th><th>Ventes</th><th>Recommandation</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_services as $ls): ?>
                        <tr>
                            <td><strong><?= h($ls['nom']) ?></strong></td>
                            <td><?= format_money($ls['prix']) ?></td>
                            <td><?= $ls['quantite_vendue'] ?></td>
                            <td><small class="text-secondary">Revoir tarif ou proposer en pack</small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 4 : QUALIFICATIONS & PRODUCTIVITÉ DU PERSONNEL -->
<div class="card mb-4">
    <div class="card-header" style="background:var(--bg-app);">
        <h3 class="card-title" style="color:var(--gold-500);">⚡ 4. Évaluation & Efficacité du Personnel de l'Établissement</h3>
    </div>
    <div class="card-body p-0">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
            <!-- Efficacité Personnel Ménage -->
            <div style="border-right:1px solid var(--border-light); padding:16px;">
                <h4 style="margin:0 0 12px 0;">🧹 Élimination & Qualité - Équipe Ménage</h4>
                <table class="table">
                    <thead>
                        <tr><th>Agent</th><th>Tâches Réalisées</th><th>Temps Moy.</th><th>Qualité Impeccable</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menage_perf as $mp): ?>
                        <tr>
                            <td><strong><?= h($mp['prenom'].' '.$mp['nom']) ?></strong></td>
                            <td><strong class="text-success"><?= $mp['terminees'] ?> / <?= $mp['total_taches'] ?></strong></td>
                            <td><?= $mp['duree_moy'] ? $mp['duree_moy'].' min' : '—' ?></td>
                            <td><span class="badge badge-libre">⭐ <?= $mp['nb_impeccable'] ?> impeccable(s)</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($menage_perf)): ?>
                        <tr><td colspan="4" class="text-center p-3 text-secondary">Aucune tâche enregistrée sur cette période.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Efficacité Réceptionnistes -->
            <div style="padding:16px;">
                <h4 style="margin:0 0 12px 0;">🏢 Encaissements & Accueil - Réceptionnistes</h4>
                <table class="table">
                    <thead>
                        <tr><th>Réceptionniste</th><th>Factures Traitées</th><th>Encaissements Validés</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recep_perf as $rp): ?>
                        <tr>
                            <td><strong><?= h($rp['prenom'].' '.$rp['nom']) ?> (<?= h($rp['identifiant']) ?>)</strong></td>
                            <td><?= $rp['factures_emises'] ?> facture(s)</td>
                            <td><strong style="color:var(--gold-500);"><?= format_money($rp['encaissements_totaux']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recep_perf)): ?>
                        <tr><td colspan="3" class="text-center p-3 text-secondary">Aucune facture enregistrée sur cette période.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 5 : RECOMMANDATIONS & CONSEILS D'AMÉLIORATION MÉTIER -->
<div class="card mb-4" style="border:2px solid var(--gold-500);">
    <div class="card-header" style="background:var(--navy-800); color:var(--gold-400);">
        <h3 class="card-title">💡 5. Recommandations Décisionnelles pour Maximiser la Rentabilité de l'Hôtel</h3>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; font-size:13.5px; line-height:1.6;">
            <div style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); border-left:4px solid var(--s-libre);">
                <h4 style="margin:0 0 8px 0; color:var(--s-libre);">🎯 Stratégie d'Optimisation des Chambres</h4>
                <ul>
                    <li><strong>Proposer des offres promotionnelles (-10% à -15%)</strong> sur les chambres à faible taux d'occupation pendant les jours de faible affluence (milieu de semaine).</li>
                    <li><strong>Ajuster le tarif des chambres Stars</strong> : Augmenter légèrement le prix de nuitée sur les chambres à fort rendement qui affichent un taux d'occupation supérieur à 85%.</li>
                </ul>
            </div>

            <div style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); border-left:4px solid var(--gold-500);">
                <h4 style="margin:0 0 8px 0; color:var(--gold-500);">🤝 Fidélisation des Clients VIP & Relance</h4>
                <ul>
                    <li><strong>Récompenser les Top Clients</strong> en leur attribuant automatiquement leurs chambres préférées et un accueil personnalisé lors du check-in.</li>
                    <li><strong>Mettre en place des relances SMS/Appel</strong> pour les clients réguliers n'ayant pas séjourné depuis plus de 60 jours.</li>
                </ul>
            </div>

            <div style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); border-left:4px solid var(--s-nettoyage);">
                <h4 style="margin:0 0 8px 0; color:var(--s-nettoyage);">⚡ Efficacité du Personnel & Motivation</h4>
                <ul>
                    <li><strong>Valoriser le personnel de ménage le plus qualifié</strong> (durée moyenne optimale < 30 min et note Impeccable) avec une prime d'assiduité.</li>
                    <li><strong>Organiser une formation rapide</strong> pour harmoniser les méthodes de nettoyage et réduire le temps mort entre deux check-ins.</li>
                </ul>
            </div>

            <div style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); border-left:4px solid var(--s-occupee);">
                <h4 style="margin:0 0 8px 0; color:var(--s-occupee);">📦 Maîtrise des Dépenses & Achats Stock</h4>
                <ul>
                    <li><strong>Contrôler hebdomadairement le journal des Sorties de Caisse</strong> pour éviter les achats de consommables en petite quantité plus coûteux.</li>
                    <li><strong>Négocier des tarifs de gros</strong> auprès des fournisseurs de détergents et de produits d'entretien.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Document officiel d'analyse de rentabilité et d'aide à la décision. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
