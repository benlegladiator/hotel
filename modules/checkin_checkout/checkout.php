<?php
// ============================================================
// modules/checkin_checkout/checkout.php — Départs & Clôture Séjours
// Affichage direct des occupations en cours + Validation départ en 1-clic
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id_res_selected = (int)($_GET['id'] ?? 0);

// 1. Récupération de toutes les occupations en cours
$stmt = $pdo->query("
    SELECT r.id as res_id, r.id_client, r.id_chambre, r.id_salle, r.type_reservation,
           r.date_arrivee, r.date_depart, r.statut, r.remarques, r.created_at,
           c.id as client_id, c.nom, c.prenom, c.telephone, c.email,
           ch.numero as chambre_numero, tc.nom as type_chambre, tc.prix_nuit,
           s.nom as salle_nom, s.prix_location as salle_prix,
           (SELECT COALESCE(SUM(l.sous_total), 0) 
            FROM commande_lignes l 
            JOIN commandes cmd ON cmd.id = l.id_commande 
            WHERE cmd.id_reservation = r.id) as total_commandes,
           (SELECT f.id FROM factures f WHERE f.id_reservation = r.id ORDER BY f.id DESC LIMIT 1) as id_facture_exist,
           (SELECT f.statut_paiement FROM factures f WHERE f.id_reservation = r.id ORDER BY f.id DESC LIMIT 1) as statut_facture,
           (SELECT f.montant_final FROM factures f WHERE f.id_reservation = r.id ORDER BY f.id DESC LIMIT 1) as montant_facture
    FROM reservations r
    JOIN clients c ON r.id_client = c.id
    LEFT JOIN chambres ch ON r.id_chambre = ch.id
    LEFT JOIN types_chambres tc ON ch.id_type_chambre = tc.id
    LEFT JOIN salles s ON r.id_salle = s.id
    WHERE r.statut = 'en_cours'
    ORDER BY r.date_depart ASC, r.id ASC
");
$occupations = $stmt->fetchAll();

// Statistiques rapides des départs
$nb_total_occupations = count($occupations);
$nb_departs_aujourdhui = 0;
$nb_departs_retard = 0;
$today = date('Y-m-d');

foreach ($occupations as $occ) {
    if ($occ['date_depart'] === $today) {
        $nb_departs_aujourdhui++;
    } elseif ($occ['date_depart'] < $today) {
        $nb_departs_retard++;
    }
}

// Détail du séjour sélectionné pour confirmation (si spécifié)
$selected_stay = null;
if ($id_res_selected > 0) {
    foreach ($occupations as $occ) {
        if ($occ['res_id'] == $id_res_selected) {
            $selected_stay = $occ;
            break;
        }
    }
}

$page_title = "Départs & Check-out";
require_once '../../includes/header.php';
?>

<div style="max-width: 1180px; margin: 0 auto;">
    
    <!-- ── Bouton Retour Dédié ── -->
    <div>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-page-back">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>← Retour au Tableau de bord</span>
        </a>
    </div>

    <!-- ── En-tête de page ── -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;" class="page-header">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.25; word-break: break-word;">
                📋 Départs Clients & Clôture (Check-out)
            </h1>
            <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13.5px;">
                Consultez la liste des occupations actuelles et validez le départ d'un client.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;" class="page-header-actions">
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn btn-primary" style="box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);">
                + Nouvelle Arrivée
            </a>
        </div>
    </div>

    <!-- ── Bandeau Synthèse Rapide ── -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <div style="background: #ffffff; border: 1px solid var(--border-light); border-left: 4px solid var(--primary); border-radius: var(--r-md); padding: 12px 18px; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Occupations en Cours</div>
                <div style="font-size: 22px; font-weight: 800; color: var(--text-primary); line-height: 1.1; margin-top: 2px;"><?= $nb_total_occupations ?></div>
            </div>
            <div style="font-size: 26px;">🏨</div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-light); border-left: 4px solid #059669; border-radius: var(--r-md); padding: 12px 18px; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">Départs Prévus Aujourd'hui</div>
                <div style="font-size: 22px; font-weight: 800; color: #059669; line-height: 1.1; margin-top: 2px;"><?= $nb_departs_aujourdhui ?></div>
            </div>
            <div style="font-size: 26px;">📅</div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-light); border-left: 4px solid <?= $nb_departs_retard > 0 ? '#e11d48' : '#cbd5e1' ?>; border-radius: var(--r-md); padding: 12px 18px; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: <?= $nb_departs_retard > 0 ? '#e11d48' : 'var(--text-secondary)' ?>; text-transform: uppercase;">Séjours Dépassés / Prolongés</div>
                <div style="font-size: 22px; font-weight: 800; color: <?= $nb_departs_retard > 0 ? '#e11d48' : 'var(--text-primary)' ?>; line-height: 1.1; margin-top: 2px;"><?= $nb_departs_retard ?></div>
            </div>
            <div style="font-size: 26px;">⚠️</div>
        </div>
    </div>

    <!-- ── Si une réservation spécifique est sélectionnée : Bloc de validation détaillée ── -->
    <?php if ($selected_stay): 
        $nuits = max(1, nb_nuits($selected_stay['date_arrivee'], $selected_stay['date_depart']));
        $prix_unit = $selected_stay['type_reservation'] === 'chambre' ? (float)$selected_stay['prix_nuit'] : (float)$selected_stay['salle_prix'];
        $montant_sejour = $nuits * $prix_unit;
        $total_cmd = (float)$selected_stay['total_commandes'];
        $grand_total = $montant_sejour + $total_cmd;
    ?>
        <div class="card" style="margin-bottom: 30px; border: 2px solid #818cf8; background: #faf5ff; box-shadow: var(--shadow-md);">
            <div class="card-header" style="background: transparent; border-bottom: 1px solid #e0e7ff; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📋</span>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #3730a3; margin: 0;">
                            Validation du Départ : <?= h($selected_stay['nom'] . ' ' . $selected_stay['prenom']) ?>
                        </h2>
                        <div style="font-size: 13px; color: #6366f1;">
                            <?= $selected_stay['type_reservation'] === 'chambre' ? 'Chambre ' . h($selected_stay['chambre_numero']) . ' (' . h($selected_stay['type_chambre']) . ')' : 'Salle ' . h($selected_stay['salle_nom']) ?>
                        </div>
                    </div>
                </div>
                <a href="checkout.php" class="btn btn-sm btn-outline">Fermer ce volet ✕</a>
            </div>
            
            <div class="card-body" style="padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    
                    <!-- Détails Séjour -->
                    <div style="background: #ffffff; border: 1px solid #e0e7ff; border-radius: var(--r-md); padding: 18px;">
                        <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-light); margin-bottom: 10px;">
                            Détails de l'occupation
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 14px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Date d'Arrivée :</span>
                                <strong><?= format_date($selected_stay['date_arrivee']) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Date de Départ :</span>
                                <strong><?= format_date($selected_stay['date_depart']) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Nombre de nuits :</span>
                                <strong><?= $nuits ?> nuit(s)</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Tarif / nuit :</span>
                                <strong><?= format_prix($prix_unit) ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Récapitulatif Financier -->
                    <div style="background: #ffffff; border: 1px solid #e0e7ff; border-radius: var(--r-md); padding: 18px;">
                        <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-light); margin-bottom: 10px;">
                            Décompte Financier
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 14px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Hébergement :</span>
                                <strong><?= format_prix($montant_sejour) ?></strong>
                            </div>
                            <?php if ($total_cmd > 0): ?>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-secondary);">Services & Boissons :</span>
                                    <strong><?= format_prix($total_cmd) ?></strong>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; border-top: 1.5px dashed var(--border-light); padding-top: 8px; font-size: 16px;">
                                <span style="font-weight: 800; color: var(--text-primary);">Total à Clôturer :</span>
                                <strong style="color: #059669;"><?= format_prix($grand_total) ?></strong>
                            </div>
                            <div style="margin-top: 4px;">
                                <span class="badge <?= $selected_stay['statut_facture'] === 'payee' ? 'badge-libre' : 'badge-occupee' ?>" style="font-size: 11px;">
                                    <?= $selected_stay['statut_facture'] === 'payee' ? '✔ Séjour déjà réglé' : '⚠️ Facture en attente de paiement' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>

                <?php 
                $sel_is_sieste = ($selected_stay['type_reservation'] === 'chambre') && ((stripos($selected_stay['remarques'] ?? '', 'sieste') !== false) || ($selected_stay['date_arrivee'] === $selected_stay['date_depart']));
                ?>
                <div style="display: flex; gap: 14px; align-items: center; justify-content: flex-end; flex-wrap: wrap;">
                    <a href="checkout.php" class="btn btn-outline">Annuler</a>
                    <?php if ($sel_is_sieste): ?>
                        <a href="bascule_sieste.php?id=<?= $selected_stay['res_id'] ?>" class="btn btn-lg" style="background: #fffbeb; color: #b45309; border: 2px solid #fde68a; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
                            <span>🌙</span> Basculer ce Séjour en Nuitée
                        </a>
                    <?php endif; ?>
                    <form method="POST" action="process_checkout.php" style="margin: 0;">
                        <input type="hidden" name="id_reservation" value="<?= $selected_stay['res_id'] ?>">
                        <input type="hidden" name="id_client" value="<?= $selected_stay['client_id'] ?>">
                        <input type="hidden" name="montant_total" value="<?= $grand_total ?>">
                        
                        <button type="submit" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35); font-weight: 800;">
                            🚪 Valider le Départ & Libérer la Chambre
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── LISTE DIRECTE DES OCCUPATIONS EN COURS ── -->
    <div class="card" style="box-shadow: var(--shadow-sm);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">🛏️</span>
                <h2 class="card-title" style="margin: 0; font-size: 17px;">
                    Occupations & Séjours Actuellement en Cours (<?= $nb_total_occupations ?>)
                </h2>
            </div>
            <span style="font-size: 12.5px; color: var(--text-secondary);">
                Cliquez sur <strong>« Valider Départ »</strong> pour clôturer une chambre
            </span>
        </div>

        <?php if (empty($occupations)): ?>
            <div class="card-body" style="text-align: center; padding: 48px 24px;">
                <div style="font-size: 48px; margin-bottom: 12px;">✨</div>
                <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
                    Aucune occupation en cours
                </h3>
                <p style="color: var(--text-secondary); font-size: 14px; max-width: 420px; margin: 0 auto 20px auto;">
                    Toutes les chambres de l'hôtel sont actuellement libres ou en cours de nettoyage.
                </p>
                <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn btn-primary">
                    + Enregistrer une nouvelle arrivée
                </a>
            </div>
        <?php else: ?>
            <!-- ── VUE MOBILE CARTE OCCUPATIONS (SMARTPHONE) ── -->
            <div class="mobile-only" style="padding: 12px; gap: 12px;">
                <?php foreach ($occupations as $occ): 
                    $nuits = max(1, nb_nuits($occ['date_arrivee'], $occ['date_depart']));
                    $prix_unit = $occ['type_reservation'] === 'chambre' ? (float)$occ['prix_nuit'] : (float)$occ['salle_prix'];
                    $montant_sejour = $nuits * $prix_unit;
                    $total_cmd = (float)$occ['total_commandes'];
                    $total_occ = $montant_sejour + $total_cmd;
                    $is_today = ($occ['date_depart'] === $today);
                    $is_late  = ($occ['date_depart'] < $today);
                    $is_sieste_occ = ($occ['type_reservation'] === 'chambre') && ((stripos($occ['remarques'] ?? '', 'sieste') !== false) || ($occ['date_arrivee'] === $occ['date_depart']));
                ?>
                    <div class="card" style="margin-bottom: 14px; border: 1.5px solid <?= $is_late ? '#fda4af' : '#e2e8f0' ?>; border-radius: 14px; padding: 14px; background: #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 42px; height: 42px; border-radius: 10px; background: #fff1f2; color: #e11d48; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 15px; border: 1px solid #fecdd3; flex-shrink: 0;">
                                    <?= h($occ['chambre_numero'] ?? 'S') ?>
                                </div>
                                <div>
                                    <strong style="font-size: 15px; color: #0f172a;"><?= $occ['type_reservation'] === 'chambre' ? 'Chambre ' . h($occ['chambre_numero']) : 'Salle ' . h($occ['salle_nom']) ?></strong>
                                    <div style="font-size: 12px; color: #64748b;"><?= h($occ['type_chambre'] ?? 'Standard') ?></div>
                                </div>
                            </div>
                            <div>
                                <?php if ($is_late): ?>
                                    <span style="background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 800;">⚠️ Dépassé</span>
                                <?php elseif ($is_today): ?>
                                    <span style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 800;">📅 Aujourd'hui</span>
                                <?php else: ?>
                                    <span style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 800;">En cours</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="background: #f8fafc; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 12.5px;">
                            <div style="font-weight: 700; color: #0f172a; margin-bottom: 4px;">👤 <?= h($occ['nom'] . ' ' . $occ['prenom']) ?> <?= $occ['telephone'] ? '('.h($occ['telephone']).')' : '' ?></div>
                            <div style="color: #64748b; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px; font-size: 12px;">
                                <span>Arrivée : <?= format_date($occ['date_arrivee']) ?></span>
                                <span>Départ : <?= format_date($occ['date_depart']) ?> (<?= $nuits ?> <?= $nuits > 1 ? 'nuits' : 'nuit' ?>)</span>
                            </div>
                            <div style="margin-top: 6px; font-weight: 800; color: #059669; font-size: 13px;">
                                Total : <?= format_prix($total_occ) ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px; flex-wrap: wrap;" class="no-print">
                            <?php if ($is_sieste_occ): ?>
                                <a href="bascule_sieste.php?id=<?= $occ['res_id'] ?>" class="btn btn-sm" style="background: #fffbeb; color: #b45309; border: 1.5px solid #fde68a; font-weight: 700; flex: 1 1 auto; text-align: center; border-radius: 8px;">
                                    🌙 En Nuitée
                                </a>
                            <?php endif; ?>
                            
                            <form method="POST" action="process_checkout.php" onsubmit="return confirm('Confirmez-vous le départ de <?= addslashes(h($occ['nom'].' '.$occ['prenom'])) ?> (Ch. <?= addslashes(h($occ['chambre_numero'])) ?>) ? La chambre passera automatiquement en statut Nettoyage.');" style="margin: 0; flex: 2 1 auto;">
                                <input type="hidden" name="id_reservation" value="<?= $occ['res_id'] ?>">
                                <input type="hidden" name="id_client" value="<?= $occ['client_id'] ?>">
                                <input type="hidden" name="montant_total" value="<?= $total_occ ?>">
                                
                                <button type="submit" class="btn btn-sm btn-primary" style="width: 100%; font-weight: 800; border-radius: 8px; justify-content: center; padding: 8px 12px;">
                                    🚪 Valider Départ
                                </button>
                            </form>

                            <a href="checkout.php?id=<?= $occ['res_id'] ?>" class="btn btn-sm btn-outline" style="border-radius: 8px; padding: 8px 12px;" title="Détails">
                                🔍
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ── VUE TABLEAU DESKTOP ── -->
            <div class="table-responsive desktop-only">
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Chambre / Espace</th>
                            <th>Client & Contact</th>
                            <th>Arrivée</th>
                            <th>Départ Prévu</th>
                            <th>Durée</th>
                            <th>Montant Total</th>
                            <th>Règlement</th>
                            <th style="text-align: right;">Action Départ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($occupations as $occ): 
                            $nuits = max(1, nb_nuits($occ['date_arrivee'], $occ['date_depart']));
                            $prix_unit = $occ['type_reservation'] === 'chambre' ? (float)$occ['prix_nuit'] : (float)$occ['salle_prix'];
                            $montant_sejour = $nuits * $prix_unit;
                            $total_cmd = (float)$occ['total_commandes'];
                            $total_occ = $montant_sejour + $total_cmd;

                            $is_today = ($occ['date_depart'] === $today);
                            $is_late  = ($occ['date_depart'] < $today);
                            $is_sieste_occ = ($occ['type_reservation'] === 'chambre') && ((stripos($occ['remarques'] ?? '', 'sieste') !== false) || ($occ['date_arrivee'] === $occ['date_depart']));
                        ?>
                            <tr style="<?= $occ['res_id'] == $id_res_selected ? 'background: #f5f3ff;' : '' ?>">
                                
                                <!-- Chambre -->
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #fff1f2; color: #e11d48; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; border: 1px solid #fecdd3;">
                                            <?= h($occ['chambre_numero'] ?? 'S') ?>
                                        </div>
                                        <div>
                                            <strong style="color: var(--text-primary); font-size: 14px;">
                                                <?= $occ['type_reservation'] === 'chambre' ? 'Chambre ' . h($occ['chambre_numero']) : 'Salle ' . h($occ['salle_nom']) ?>
                                            </strong>
                                            <div style="font-size: 11.5px; color: var(--text-secondary);">
                                                <?= h($occ['type_chambre'] ?? 'Location') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Client -->
                                <td>
                                    <strong style="color: var(--text-primary); font-size: 14px;">
                                        <?= h($occ['nom'] . ' ' . $occ['prenom']) ?>
                                    </strong>
                                    <?php if ($occ['telephone']): ?>
                                        <div style="font-size: 12px; color: var(--text-light);">
                                            📞 <?= h($occ['telephone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Arrivée -->
                                <td style="font-size: 13.5px; color: var(--text-secondary);">
                                    <?= format_date($occ['date_arrivee']) ?>
                                </td>

                                <!-- Départ Prévu -->
                                <td>
                                    <div style="font-size: 13.5px; font-weight: 600; color: <?= $is_late ? '#e11d48' : ($is_today ? '#d97706' : 'var(--text-primary)') ?>;">
                                        <?= format_date($occ['date_depart']) ?>
                                    </div>
                                    <?php if ($is_today): ?>
                                        <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 10px;">
                                            Aujourd'hui
                                        </span>
                                    <?php elseif ($is_late): ?>
                                        <span class="badge" style="background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; font-size: 10px;">
                                            Dépassé
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Durée -->
                                <td style="font-size: 13.5px;">
                                    <?php if ($is_sieste_occ): ?>
                                        <span class="badge" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a; font-weight:700; font-size:11px;">⚡ Sieste</span>
                                    <?php else: ?>
                                        <strong><?= $nuits ?></strong> nuit(s)
                                    <?php endif; ?>
                                </td>

                                <!-- Montant Total -->
                                <td>
                                    <strong style="color: var(--text-primary); font-size: 14px;">
                                        <?= format_prix($total_occ) ?>
                                    </strong>
                                    <?php if ($total_cmd > 0): ?>
                                        <div style="font-size: 10.5px; color: #4f46e5;">
                                            + <?= format_prix($total_cmd) ?> extras
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Règlement -->
                                <td>
                                    <?php if ($occ['statut_facture'] === 'payee'): ?>
                                        <span class="badge badge-libre" style="font-size: 11px;">✔ Payé</span>
                                    <?php elseif ($occ['statut_facture'] === 'partielle'): ?>
                                        <span class="badge badge-reservee" style="font-size: 11px;">Partiel</span>
                                    <?php else: ?>
                                        <span class="badge badge-occupee" style="font-size: 11px;">En attente</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                        
                                        <?php if ($is_sieste_occ): ?>
                                            <!-- Bouton Bascule Sieste -> Nuitée -->
                                            <a href="bascule_sieste.php?id=<?= $occ['res_id'] ?>" class="btn btn-sm" style="background: #fffbeb; color: #b45309; border: 1.5px solid #fde68a; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;" title="Convertir en Formule Nuitée (payer le reliquat)">
                                                <span>🌙</span> En Nuitée
                                            </a>
                                        <?php endif; ?>

                                        <!-- Bouton Validation Rapide Directe -->
                                        <form method="POST" action="process_checkout.php" onsubmit="return confirm('Confirmez-vous le départ de <?= addslashes(h($occ['nom'].' '.$occ['prenom'])) ?> (Ch. <?= addslashes(h($occ['chambre_numero'])) ?>) ? La chambre passera automatiquement en statut Nettoyage.');" style="margin: 0;">
                                            <input type="hidden" name="id_reservation" value="<?= $occ['res_id'] ?>">
                                            <input type="hidden" name="id_client" value="<?= $occ['client_id'] ?>">
                                            <input type="hidden" name="montant_total" value="<?= $total_occ ?>">
                                            
                                            <button type="submit" class="btn btn-sm btn-primary" style="font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                                                <span>🚪</span> Valider Départ
                                            </button>
                                        </form>

                                        <!-- Bouton Détails / Décompte -->
                                        <a href="checkout.php?id=<?= $occ['res_id'] ?>" class="btn btn-sm btn-outline" title="Voir le décompte détaillé">
                                            🔍
                                        </a>

                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>
