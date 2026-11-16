<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Paramètres du Système";
$user = current_user();

$nom_hotel       = get_system_setting('nom_hotel', 'Alma Hotel');
$adresse_hotel   = get_system_setting('adresse_hotel', '');
$telephone_hotel = get_system_setting('telephone_hotel', '');
$email_hotel     = get_system_setting('email_hotel', '');
$devise          = get_system_setting('devise', 'FCFA');
$is_premium      = is_premium_enabled();

$active_tab = $_GET['tab'] ?? 'profil';

require_once '../../includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
    <div>
        <h1 class="page-header-title">Paramètres du Système</h1>
        <p class="text-secondary" style="margin-top:4px;">Personnalisation visuelle (Sombre/Clair + Couleur secondaire), profil et options</p>
    </div>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
</div>

<div class="tabs no-print">
    <button class="tab-btn <?= $active_tab==='profil'?'active':'' ?>" onclick="showTab('profil')">👤 Profil & Sécurité</button>
    <button class="tab-btn <?= $active_tab==='apparence'?'active':'' ?>" onclick="showTab('apparence')">🎨 Thème & Couleurs</button>
    <?php if (is_admin()): ?>
    <button class="tab-btn <?= $active_tab==='hotel'?'active':'' ?>" onclick="showTab('hotel')">🏨 Établissement & Impression</button>
    <button class="tab-btn <?= $active_tab==='premium'?'active':'' ?>" onclick="showTab('premium')">🌟 Modules Premium</button>
    <?php endif; ?>
</div>

<!-- 1. Onglet Profil & Sécurité -->
<div id="tab-profil" class="tab-pane <?= $active_tab==='profil'?'active':'' ?>">
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Informations Personnelles</h3></div>
        <div class="card-body">
            <form action="process_save.php" method="POST">
                <input type="hidden" name="section" value="profil">
                <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:16px;">
                    <div>
                        <label class="form-label">Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= h($user['nom'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= h($user['prenom'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Identifiant de connexion</label>
                        <input type="text" name="identifiant" class="form-control" value="<?= h($user['identifiant'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= h($user['email'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer mon Profil</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Changer de Mot de Passe</h3></div>
        <div class="card-body">
            <form action="process_save.php" method="POST">
                <input type="hidden" name="section" value="mot_de_passe">
                <div class="form-grid-3" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:16px;">
                    <div>
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="anc_mdp" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="nouveau_mdp" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Confirmer nouveau mot de passe</label>
                        <input type="password" name="conf_mdp" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary">Modifier mon Mot de Passe</button>
            </form>
        </div>
    </div>
</div>

<!-- 2. Onglet Thème & Couleurs -->
<div id="tab-apparence" class="tab-pane <?= $active_tab==='apparence'?'active':'' ?>">
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Personnalisation du Thème & Couleurs</h3></div>
        <div class="card-body">
            <form action="process_save.php" method="POST">
                <input type="hidden" name="section" value="apparence">
                
                <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
                    <div>
                        <label class="form-label" style="font-weight:700;">1. Thème Principal (Fond & Structure)</label>
                        <select name="theme" class="form-control" onchange="document.body.setAttribute('data-theme', this.value)">
                            <option value="dark" <?= get_user_theme()==='dark'?'selected':'' ?>>🌙 Mode Sombre Chic (Dark Theme)</option>
                            <option value="light" <?= get_user_theme()==='light'?'selected':'' ?>>☀️ Mode Clair Épuré (Light Theme)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">2. Langue de l'Interface</label>
                        <select name="langue" class="form-control">
                            <option value="fr" <?= get_user_lang()==='fr'?'selected':'' ?>>🇫🇷 Français</option>
                            <option value="en" <?= get_user_lang()==='en'?'selected':'' ?>>🇬🇧 English</option>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label" style="font-weight:700;">3. Couleur d'Accentuation Secondaire (Boutons, Surbrillances, Badges)</label>
                    <p class="text-secondary" style="font-size:13px; margin-bottom:12px;">Choisissez la couleur secondaire qui viendra compléter votre thème principal (Clair ou Sombre) sans déformer l'interface :</p>
                    
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px;">
                        <label style="cursor:pointer; background:var(--bg-app); border:2px solid var(--border-light); padding:12px; border-radius:var(--r-md); display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="accent" value="gold" <?= get_user_accent()==='gold'?'checked':'' ?> onchange="document.body.setAttribute('data-accent', this.value)">
                            <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:#c9a84c;"></span>
                            <strong>🟡 Or Luxe</strong>
                        </label>
                        <label style="cursor:pointer; background:var(--bg-app); border:2px solid var(--border-light); padding:12px; border-radius:var(--r-md); display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="accent" value="blue" <?= get_user_accent()==='blue'?'checked':'' ?> onchange="document.body.setAttribute('data-accent', this.value)">
                            <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:#2563eb;"></span>
                            <strong>🔵 Bleu Royal</strong>
                        </label>
                        <label style="cursor:pointer; background:var(--bg-app); border:2px solid var(--border-light); padding:12px; border-radius:var(--r-md); display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="accent" value="green" <?= get_user_accent()==='green'?'checked':'' ?> onchange="document.body.setAttribute('data-accent', this.value)">
                            <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:#10b981;"></span>
                            <strong>🟢 Vert Émeraude</strong>
                        </label>
                        <label style="cursor:pointer; background:var(--bg-app); border:2px solid var(--border-light); padding:12px; border-radius:var(--r-md); display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="accent" value="orange" <?= get_user_accent()==='orange'?'checked':'' ?> onchange="document.body.setAttribute('data-accent', this.value)">
                            <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:#f97316;"></span>
                            <strong>🟠 Orange Solaire</strong>
                        </label>
                        <label style="cursor:pointer; background:var(--bg-app); border:2px solid var(--border-light); padding:12px; border-radius:var(--r-md); display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="accent" value="red" <?= get_user_accent()==='red'?'checked':'' ?> onchange="document.body.setAttribute('data-accent', this.value)">
                            <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:#ef4444;"></span>
                            <strong>🔴 Rouge Rubis</strong>
                        </label>
                        <label style="cursor:pointer; background:var(--bg-app); border:2px solid var(--border-light); padding:12px; border-radius:var(--r-md); display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="accent" value="purple" <?= get_user_accent()==='purple'?'checked':'' ?> onchange="document.body.setAttribute('data-accent', this.value)">
                            <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:#8b5cf6;"></span>
                            <strong>🟣 Violet Impérial</strong>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Enregistrer mes Préférences de Couleurs</button>
            </form>
        </div>
    </div>
</div>

<?php if (is_admin()): ?>
<!-- 3. Onglet Établissement & Impression -->
<div id="tab-hotel" class="tab-pane <?= $active_tab==='hotel'?'active':'' ?>">
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Coordonnées de l'Hôtel & En-tête des Factures</h3></div>
        <div class="card-body">
            <form action="process_save.php" method="POST">
                <input type="hidden" name="section" value="hotel">
                <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:16px;">
                    <div>
                        <label class="form-label">Nom de l'Établissement</label>
                        <input type="text" name="nom_hotel" class="form-control" value="<?= h($nom_hotel) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Téléphone / Contact</label>
                        <input type="text" name="telephone_hotel" class="form-control" value="<?= h($telephone_hotel) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Adresse Physique & BP</label>
                        <input type="text" name="adresse_hotel" class="form-control" value="<?= h($adresse_hotel) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Email de contact</label>
                        <input type="email" name="email_hotel" class="form-control" value="<?= h($email_hotel) ?>">
                    </div>
                    <div>
                        <label class="form-label">Devise Monétaire</label>
                        <input type="text" name="devise" class="form-control" value="<?= h($devise) ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Mettre à jour l'En-tête d'Impression</button>
            </form>
        </div>
    </div>
</div>

<!-- 4. Onglet Modules Premium -->
<div id="tab-premium" class="tab-pane <?= $active_tab==='premium'?'active':'' ?>">
    <div class="card mb-4" style="border:2px solid var(--gold-500);">
        <div class="card-header" style="background:var(--navy-800); color:var(--gold-400);">
            <h3 class="card-title">🌟 Commutateur des Modules Optionnels (Standard / Premium)</h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom:20px; line-height:1.6;">
                En <strong>Mode Standard</strong>, le système masque les fonctionnalités complexes (Spa, Sauna, Cartes Cuisine et Salles) pour garder une utilisation rapide et simple au quotidien.<br>
                Vous pouvez activer le <strong>Mode Premium</strong> à tout moment ci-dessous :
            </p>
            <form action="process_save.php" method="POST">
                <input type="hidden" name="section" value="premium">
                <div style="background:var(--bg-app); padding:20px; border-radius:var(--r-md); margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <strong style="font-size:16px;">Activer les Modules Luxe & Restauration (Spa, Menus, Salles)</strong><br>
                        <span class="text-secondary" style="font-size:13px;">Affiche ou masque les menus secondaires dans la barre latérale.</span>
                    </div>
                    <div>
                        <select name="enable_premium_modules" class="form-control" style="width:160px; font-weight:700;">
                            <option value="0" <?= !$is_premium?'selected':'' ?>>🔒 Mode Standard</option>
                            <option value="1" <?= $is_premium?'selected':'' ?>>🌟 Mode Premium</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer la Configuration des Modules</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
