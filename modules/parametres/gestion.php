<?php
// ============================================================
// modules/parametres/gestion.php — Hub Central de Gestion & CRUD (Boss)
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin(); // Réservé à l'administrateur / Propriétaire

// Statistiques rapides
$nb_chambres = (int)$pdo->query("SELECT COUNT(*) FROM chambres")->fetchColumn();
$nb_types    = (int)$pdo->query("SELECT COUNT(*) FROM types_chambres")->fetchColumn();
$nb_employes = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE actif = 1")->fetchColumn();
$nb_clients  = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

$page_title = "Paramètres & Gestion Globale (CRUD) — Alma Hotel";
require_once '../../includes/header.php';
?>

<div style="max-width: 1240px; margin: 0 auto;">

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

    <!-- ── En-tête ── -->
    <div style="margin-bottom: 24px;" class="page-header">
        <h1 style="font-size: 24px; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.2;">⚙️ Paramètres & Gestion Globale (CRUD)</h1>
        <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13.5px;">
            Centre de configuration administrative : chambres, tarifs, comptes et clients.
        </p>
    </div>

    <!-- ── Grille des Espaces de Gestion (CRUD) ── -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">

        <!-- 1. GESTION DES CHAMBRES -->
        <div class="card" style="border-top: 4px solid #059669; box-shadow: var(--shadow-sm);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfdf5; display: flex; align-items: center; justify-content: center; font-size: 24px;">🛏️</div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #059669; margin: 0;">Chambres de l'Hôtel</h2>
                        <span style="font-size: 12.5px; color: var(--text-secondary);"><?= $nb_chambres ?> chambre(s) configurée(s)</span>
                    </div>
                </div>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                    Ajoutez de nouvelles chambres, modifiez les numéros, associez les catégories et visualisez l'état du parc.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="btn btn-primary" style="flex: 1;">
                        📋 Voir la Liste
                    </a>
                    <a href="<?= BASE_URL ?>/modules/chambres/add.php" class="btn btn-outline">
                        + Ajouter
                    </a>
                </div>
            </div>
        </div>

        <!-- 2. GESTION DES CATÉGORIES & TARIFS -->
        <div class="card" style="border-top: 4px solid #4f46e5; box-shadow: var(--shadow-sm);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #eef2ff; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏷️</div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #4f46e5; margin: 0;">Types & Tarifs par Nuit</h2>
                        <span style="font-size: 12.5px; color: var(--text-secondary);"><?= $nb_types ?> catégorie(s) de chambres</span>
                    </div>
                </div>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                    Définissez vos tarifs par nuitée (Standard, VIP, Suite), descriptions et capacités d'accueil.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/modules/types_chambres/list.php" class="btn btn-primary" style="flex: 1;">
                        🏷️ Gérer les Prix
                    </a>
                    <a href="<?= BASE_URL ?>/modules/types_chambres/add.php" class="btn btn-outline">
                        + Nouveau Type
                    </a>
                </div>
            </div>
        </div>

        <!-- 3. COMPTES EMPLOYÉS & ACCÈS -->
        <div class="card" style="border-top: 4px solid #d97706; box-shadow: var(--shadow-sm);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 24px;">👥</div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #d97706; margin: 0;">Comptes Employés & Caissières</h2>
                        <span style="font-size: 12.5px; color: var(--text-secondary);"><?= $nb_employes ?> employé(s) actif(s)</span>
                    </div>
                </div>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                    Créez les identifiants pour chaque caissière, modifiez les mots de passe et gérez les droits d'accès.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/modules/utilisateurs/list.php" class="btn btn-primary" style="flex: 1;">
                        👥 Liste Utilisateurs
                    </a>
                    <a href="<?= BASE_URL ?>/modules/utilisateurs/add.php" class="btn btn-outline">
                        + Créer Compte
                    </a>
                </div>
            </div>
        </div>

        <!-- 4. FICHIER CLIENTS -->
        <div class="card" style="border-top: 4px solid #0891b2; box-shadow: var(--shadow-sm);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfeff; display: flex; align-items: center; justify-content: center; font-size: 24px;">👤</div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #0891b2; margin: 0;">Fichier & Répertoire Clients</h2>
                        <span style="font-size: 12.5px; color: var(--text-secondary);"><?= $nb_clients ?> client(s) répertorié(s)</span>
                    </div>
                </div>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                    Consultez la base de données des clients de l'hôtel, coordonnées, pièces d'identité et historique de séjour.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/modules/clients/list.php" class="btn btn-primary" style="flex: 1;">
                        👤 Répertoire Clients
                    </a>
                    <a href="<?= BASE_URL ?>/modules/clients/add.php" class="btn btn-outline">
                        + Nouveau Client
                    </a>
                </div>
            </div>
        </div>

        <!-- 5. CONFIGURATION GÉNÉRALE & PARAMÈTRES HÔTEL -->
        <div class="card" style="border-top: 4px solid #475569; box-shadow: var(--shadow-sm);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-size: 24px;">⚙️</div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #475569; margin: 0;">Paramètres de l'Établissement</h2>
                        <span style="font-size: 12.5px; color: var(--text-secondary);">Nom, contact, entête reçus</span>
                    </div>
                </div>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                    Modifiez le nom officiel de l'hôtel, adresse, numéro de téléphone, devise et coordonnées figurant sur les reçus A4.
                </p>
                <a href="<?= BASE_URL ?>/modules/parametres/index.php?tab=hotel" class="btn btn-outline" style="width: 100%;">
                    ⚙️ Modifier les Coordonnées
                </a>
            </div>
        </div>

        <!-- 6. PERSONNALISATION VISUELLE & THÈME -->
        <div class="card" style="border-top: 4px solid #e11d48; box-shadow: var(--shadow-sm);">
            <div class="card-body" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #fff1f2; display: flex; align-items: center; justify-content: center; font-size: 24px;">🎨</div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #e11d48; margin: 0;">Thème & Apparence</h2>
                        <span style="font-size: 12.5px; color: var(--text-secondary);">Mode clair / sombre</span>
                    </div>
                </div>
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                    Personnalisez vos préférences d'affichage pour une ergonomie optimale.
                </p>
                <a href="<?= BASE_URL ?>/modules/parametres/index.php?tab=apparence" class="btn btn-outline" style="width: 100%;">
                    🎨 Gérer l'Apparence
                </a>
            </div>
        </div>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>
