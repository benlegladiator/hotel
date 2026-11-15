<?php
// ============================================================
// modules/checkin_checkout/express_checkin.php — Arrivée Express (v2.0)
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$pre_chambre_id = (int)($_GET['id_chambre'] ?? 0);
$pre_client_id  = (int)($_GET['id_client'] ?? 0);

// Liste STRICTE des chambres libres (exclut totalement les chambres occupées ou en nettoyage)
$stmt_ch = $pdo->query("
    SELECT c.id, c.numero, c.statut, tc.nom as type_nom, tc.prix_nuit, tc.prix_sieste
    FROM chambres c
    JOIN types_chambres tc ON c.id_type_chambre = tc.id
    WHERE c.statut = 'libre'
    ORDER BY CAST(c.numero AS UNSIGNED) ASC, c.numero ASC
");
$chambres = $stmt_ch->fetchAll();

// Si une chambre était passée en paramètre GET, vérifier qu'elle fait bien partie des chambres libres
$chambres_libres_ids = array_column($chambres, 'id');
if ($pre_chambre_id > 0 && !in_array($pre_chambre_id, $chambres_libres_ids)) {
    // La chambre demandée n'est pas libre (occupée ou en nettoyage)
    $stmt_check_ch = $pdo->prepare("SELECT numero, statut FROM chambres WHERE id = ?");
    $stmt_check_ch->execute([$pre_chambre_id]);
    $ch_indispo = $stmt_check_ch->fetch();
    if ($ch_indispo) {
        $st_msg = ($ch_indispo['statut'] === 'nettoyage') ? 'est actuellement en cours de nettoyage' : 'est actuellement occupée';
        set_flash('error', "Attention : La chambre N° {$ch_indispo['numero']} $st_msg et ne peut pas être attribuée.");
    }
    $pre_chambre_id = 0;
}

// Compter les chambres en nettoyage
$nb_nettoyage = (int)$pdo->query("SELECT COUNT(*) FROM chambres WHERE statut = 'nettoyage'")->fetchColumn();

// Génération du jeton anti-doublon (usage unique)
$checkin_token = bin2hex(random_bytes(16));
$_SESSION['checkin_token'] = $checkin_token;

// Liste des clients existants
$stmt_cl = $pdo->query("SELECT id, nom, prenom, date_naissance, lieu_naissance, telephone, piece_identite, nationalite, adresse, email FROM clients ORDER BY nom ASC, prenom ASC");
$clients = $stmt_cl->fetchAll();

// Détection des clients ayant déjà une chambre active ('en_cours')
$stmt_act_cl = $pdo->query("
    SELECT r.id as id_res_active, r.id_client, r.id_chambre, ch.numero as chambre_numero, tc.nom as type_nom, r.remarques, r.date_arrivee, r.date_depart
    FROM reservations r
    JOIN chambres ch ON r.id_chambre = ch.id
    JOIN types_chambres tc ON ch.id_type_chambre = tc.id
    WHERE r.statut = 'en_cours'
");
$active_clients_map = [];
while ($row = $stmt_act_cl->fetch()) {
    $active_clients_map[(int)$row['id_client']] = $row;
}

$page_title = "Arrivée Client Express (Walk-in)";
require_once '../../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    
    <!-- En-tête de page -->
    <div style="margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: var(--text-primary); margin: 0;">🏨 Arrivée Client & Encaissement Express</h1>
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 14px;">Enregistrez un client et son paiement en quelques secondes sur une seule page aérée.</p>
        </div>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">← Retour Dashboard</a>
    </div>

    <form method="POST" action="process_express_checkin.php" id="expressForm" autocomplete="off" class="card" style="box-shadow: var(--shadow-md);">
        <input type="hidden" name="checkin_token" value="<?= $checkin_token ?>">
        
        <div class="card-body" style="padding: 36px;">
            
            <!-- ── Section 1 : Le Client ── -->
            <div style="margin-bottom: 36px; padding-bottom: 30px; border-bottom: 1px solid var(--border-light);">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; background:#eef2ff; color:#4f46e5; font-weight:800; border-radius:50%; font-size:16px;">1</span>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0;">Informations du Client</h3>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label">Client habitué / déjà enregistré :</label>
                    <select id="selectClientExistant" class="form-select">
                        <option value="">-- Nouveau Client (Remplir les champs ci-dessous) --</option>
                        <?php foreach ($clients as $c): 
                            $is_active = isset($active_clients_map[$c['id']]);
                            $act_ch_num = $is_active ? $active_clients_map[$c['id']]['chambre_numero'] : '';
                            $act_res_id = $is_active ? (int)$active_clients_map[$c['id']]['id_res_active'] : 0;
                        ?>
                            <option value="<?= $c['id'] ?>" 
                                data-nom="<?= h($c['nom']) ?>" 
                                data-prenom="<?= h($c['prenom']) ?>" 
                                data-date-naissance="<?= h($c['date_naissance'] ?? '') ?>"
                                data-lieu-naissance="<?= h($c['lieu_naissance'] ?? '') ?>"
                                data-tel="<?= h($c['telephone']) ?>"
                                data-piece="<?= h($c['piece_identite']) ?>"
                                data-nationalite="<?= h($c['nationalite']) ?>"
                                data-adresse="<?= h($c['adresse']) ?>"
                                data-email="<?= h($c['email']) ?>"
                                data-is-active="<?= $is_active ? '1' : '0' ?>"
                                data-active-chambre="<?= h($act_ch_num) ?>"
                                data-active-res="<?= $act_res_id ?>"
                                <?= $c['id'] === $pre_client_id ? 'selected' : '' ?>>
                                <?php if ($is_active): ?>
                                    ⚠️ <?= h($c['nom'] . ' ' . $c['prenom']) ?> — DÉJÀ LOGÉ EN CH. <?= h($act_ch_num) ?> (Actif)
                                <?php else: ?>
                                    👤 <?= h($c['nom'] . ' ' . $c['prenom']) ?> <?= $c['telephone'] ? '('.h($c['telephone']).')' : '' ?> <?= $c['piece_identite'] ? '— CNI: '.h($c['piece_identite']) : '' ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Alerte dynamique si le client est déjà en cours de séjour -->
                <div id="alertClientOccupe" style="display:none; background:#fff1f2; border:2px solid #fda4af; border-radius:10px; padding:16px; margin-bottom:18px;">
                    <div style="font-weight:800; color:#be123c; font-size:15px; display:flex; align-items:center; gap:8px;">
                        <span>⛔</span> ATTENTION : Ce client occupe DÉJÀ la Chambre <span id="numChambreOccupeeBadge" style="background:#be123c; color:#fff; padding:2px 8px; border-radius:6px;"></span> actuellement !
                    </div>
                    <p style="color:#9f1239; font-size:13px; margin:8px 0 14px 0; line-height:1.4;">
                        Dans la logique hôtelière, un même client ne peut pas occuper deux chambres en même temps pour lui-même.<br>
                        Pour continuer, veuillez choisir l'une des deux options ci-dessous :
                    </p>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <a href="#" id="linkCheckoutActive" class="btn btn-sm btn-danger" style="font-weight:700;">
                                🚪 Clôturer son séjour précédent (Départ Ch. <span id="numChambreOccupeeBtn"></span>)
                            </a>
                            <span style="font-size:12px; color:#64748b; font-weight:600;">— OU —</span>
                            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#1e293b; cursor:pointer; background:#fff; padding:6px 12px; border:1.5px solid #cbd5e1; border-radius:8px;">
                                <input type="checkbox" name="est_pour_tiers" id="checkTiers" value="1" style="width:16px; height:16px; accent-color:#be123c;">
                                Cette 2ème chambre est pour un invité / tiers pris en charge
                            </label>
                        </div>
                        <div id="champTiersContainer" style="display:none; background:#fff; padding:14px; border-radius:8px; border:1.5px solid #fecdd3; margin-top:4px;">
                            <label class="form-label" style="color:#9f1239; font-weight:700; margin-bottom:6px;">
                                Nom, Prénom & N° CNI du véritable occupant <span style="color:#e11d48;">*</span> (Obligation légale fiche de police) :
                            </label>
                            <input type="text" name="nom_occupant_reel" id="nomOccupantReel" class="form-input" placeholder="Ex: Jean Paul TIENTCHEU — CNI 108923412 (Chauffeur / Invité)">
                            <small style="color:#64748b; display:block; margin-top:4px; font-size:11.5px;">
                                La facture sera au nom du client payeur, mais la fiche de police portera le nom du véritable occupant.
                            </small>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="id_client_existant" id="idClientExistant" value="">

                <!-- Ligne 1 : Nom, Prénom, Téléphone -->
                <div class="form-grid-2" style="grid-template-columns: 1fr 1fr 1fr; gap: 18px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label">Nom de Famille <span style="color:#e11d48;">*</span></label>
                        <input type="text" name="nom" id="clientNom" class="form-input" required placeholder="Ex: TRAORÉ">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prénom(s) <span style="color:#e11d48;">*</span></label>
                        <input type="text" name="prenom" id="clientPrenom" class="form-input" required placeholder="Ex: Moussa">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Numéro de Téléphone <span style="color:#e11d48;">*</span></label>
                        <input type="tel" name="telephone" id="clientTel" class="form-input" required placeholder="Ex: +237 600 00 00 00">
                    </div>
                </div>

                <!-- Ligne 2 : Date de Naissance, Lieu de Naissance, Nationalité -->
                <div class="form-grid-2" style="grid-template-columns: 1fr 1.2fr 1fr; gap: 18px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label">Date de Naissance</label>
                        <input type="date" name="date_naissance" id="clientDateNaissance" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lieu de Naissance</label>
                        <input type="text" name="lieu_naissance" id="clientLieuNaissance" class="form-input" placeholder="Ex: Douala, Yaoundé...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nationalité</label>
                        <input type="text" name="nationalite" id="clientNationalite" class="form-input" placeholder="Ex: Camerounaise" value="Camerounaise">
                    </div>
                </div>

                <!-- Ligne 3 : Type de Pièce, N° de CNI/Pièce, Email -->
                <div class="form-grid-2" style="grid-template-columns: 1fr 1.2fr 1fr; gap: 18px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label">Type de Pièce</label>
                        <select name="type_piece" id="clientTypePiece" class="form-select">
                            <option value="CNI" selected>🪪 CNI (Carte Nationale d'Identité)</option>
                            <option value="Passeport">🛂 Passeport</option>
                            <option value="Permis">🚗 Permis de Conduire</option>
                            <option value="Carte de Séjour">🌍 Carte de Séjour / Consulaire</option>
                            <option value="Autre">📄 Autre pièce</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Numéro CNI / Pièce d'Identité <span style="color:#e11d48;">*</span></label>
                        <input type="text" name="numero_piece" id="clientPieceNum" class="form-input" required placeholder="Ex: 1029384756" style="font-weight:700;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (Optionnel)</label>
                        <input type="email" name="email" id="clientEmail" class="form-input" placeholder="Ex: client@email.com">
                    </div>
                </div>

                <!-- Ligne 4 : Adresse / Ville -->
                <div class="form-group">
                    <label class="form-label">Adresse / Ville de Résidence</label>
                    <input type="text" name="adresse" id="clientAdresse" class="form-input" placeholder="Ex: Douala, Akwa">
                </div>
            </div>

            <!-- ── Section 2 : Chambre & Durée ── -->
            <div style="margin-bottom: 36px; padding-bottom: 30px; border-bottom: 1px solid var(--border-light);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; background:#ecfdf5; color:#059669; font-weight:800; border-radius:50%; font-size:16px;">2</span>
                        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0;">Chambre & Formule du Séjour</h3>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <label id="lblNuitee" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; padding:8px 16px; border:2px solid #4f46e5; border-radius:8px; background:#eef2ff; color:#3730a3; font-size:13.5px;">
                            <input type="radio" name="type_sejour" value="nuitee" id="formuleNuitee" checked style="accent-color:#4f46e5;">
                            🌙 Nuitée Standard
                        </label>
                        <label id="lblSieste" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; padding:8px 16px; border:2px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#64748b; font-size:13.5px;">
                            <input type="radio" name="type_sejour" value="sieste" id="formuleSieste" style="accent-color:#d97706;">
                            ⚡ Sieste / Passage
                        </label>
                    </div>
                </div>

                <div class="form-grid-2" style="grid-template-columns: 1.5fr 1fr 1fr; gap: 18px;">
                    <div class="form-group">
                        <label class="form-label">Choisir la Chambre Libre <span style="color:#e11d48;">*</span></label>
                        <?php if (empty($chambres)): ?>
                            <div style="background:#fee2e2; border:1.5px solid #fca5a5; color:#991b1b; padding:12px 14px; border-radius:var(--r-md); font-weight:700; font-size:13px; margin-bottom:8px;">
                                ⛔ Aucune chambre libre disponible en ce moment.
                            </div>
                            <?php if ($nb_nettoyage > 0): ?>
                                <div style="background:#fffbeb; border:1px solid #fde68a; color:#b45309; padding:10px 14px; border-radius:var(--r-md); font-size:12.5px; margin-bottom:8px;">
                                    🧹 <strong><?= $nb_nettoyage ?> chambre(s) sont en cours de nettoyage :</strong><br>
                                    <a href="<?= BASE_URL ?>/modules/chambres/list.php?statut=nettoyage" style="font-weight:700; color:#b45309; text-decoration:underline;">Cliquez ici pour les valider comme propres</a> afin de pouvoir les attribuer.
                                </div>
                            <?php endif; ?>
                            <select name="id_chambre" id="chambreSelect" class="form-select" disabled>
                                <option value="" data-prix-nuit="0" data-prix-sieste="0">-- Aucune chambre libre --</option>
                            </select>
                        <?php else: ?>
                            <select name="id_chambre" id="chambreSelect" class="form-select" required style="font-weight:600;">
                                <option value="" data-prix-nuit="0" data-prix-sieste="0">-- Sélectionner une chambre libre (<?= count($chambres) ?> disponible<?= count($chambres) > 1 ? 's' : '' ?>) --</option>
                                <?php foreach ($chambres as $ch): ?>
                                    <option value="<?= $ch['id'] ?>" 
                                        data-prix-nuit="<?= $ch['prix_nuit'] ?>" 
                                        data-prix-sieste="<?= $ch['prix_sieste'] ?? 10000 ?>" 
                                        <?= $ch['id'] === $pre_chambre_id ? 'selected' : '' ?>>
                                        🛏️ Ch. <?= h($ch['numero']) ?> — <?= h($ch['type_nom']) ?> (Nuit: <?= format_prix($ch['prix_nuit']) ?> | Sieste: <?= format_prix($ch['prix_sieste'] ?? 10000) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($nb_nettoyage > 0): ?>
                                <small style="display:block; margin-top:6px; color:#64748b; font-size:12px;">
                                    ℹ️ <?= $nb_nettoyage ?> autre(s) chambre(s) sont actuellement en nettoyage (masquées tant qu'elles ne sont pas faites).
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" id="nbNuitsContainer">
                        <label class="form-label" id="labelDuree">Nombre de Nuits <span style="color:#e11d48;">*</span></label>
                        <input type="number" name="nb_nuits" id="nbNuits" value="1" min="1" max="90" class="form-input" required style="font-weight:700; font-size:16px;">
                    </div>

                    <div class="form-group" id="siesteBadgeContainer" style="display:none;">
                        <label class="form-label">Durée de Séjour</label>
                        <div style="background:#fffbeb; border:1.5px solid #fde68a; color:#b45309; padding:9px 12px; border-radius:var(--r-md); font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:8px;">
                            ⚡ 1 Sieste / Passage
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date d'arrivée</label>
                        <input type="date" name="date_arrivee" id="dateArrivee" value="<?= date('Y-m-d') ?>" class="form-input">
                    </div>
                </div>
            </div>

            <!-- ── Section 3 : Encaissement & Règlement ── -->
            <div style="margin-bottom: 32px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; background:#fffbeb; color:#d97706; font-weight:800; border-radius:50%; font-size:16px;">3</span>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0;">Règlement & Caisse</h3>
                </div>

                <div style="background: #f8fafc; border: 1.5px solid var(--border-light); border-radius: var(--r-lg); padding: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center;">
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Montant Total à Payer :</div>
                        <div id="displayTotal" style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-top: 4px;">0 FCFA</div>
                        <input type="hidden" name="montant_total" id="montantTotalInput" value="0">
                    </div>

                    <div>
                        <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap: 14px;">
                            <div>
                                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Montant Encaissé <span style="color:#e11d48;">*</span></label>
                                <input type="number" step="any" name="montant_paye" id="montantPaye" class="form-input" required style="font-weight:800; color:#059669; font-size:18px;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Mode de Paiement</label>
                                <select name="mode_paiement" class="form-select" style="font-size:13px;">
                                    <option value="especes">💵 Espèces</option>
                                    <option value="mobile">📱 Mobile Money</option>
                                    <option value="carte">💳 Carte Bancaire</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Bouton d'action géant ── -->
            <div style="display: flex; gap: 16px; align-items: center;">
                <button type="submit" id="submitBtn" class="btn btn-success btn-lg" style="flex: 1; font-weight: 800;" <?= empty($chambres) ? 'disabled' : '' ?>>
                    <span>🏨</span> Enregistrer l'Arrivée & Valider l'Encaissement
                </button>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline btn-lg">Annuler</a>
            </div>

        </div>

    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectClient = document.getElementById('selectClientExistant');
    const idClientExistant = document.getElementById('idClientExistant');
    const alertClientOccupe = document.getElementById('alertClientOccupe');
    const numChambreOccupeeBadge = document.getElementById('numChambreOccupeeBadge');
    const numChambreOccupeeBtn = document.getElementById('numChambreOccupeeBtn');
    const linkCheckoutActive = document.getElementById('linkCheckoutActive');
    const checkTiers = document.getElementById('checkTiers');
    const champTiersContainer = document.getElementById('champTiersContainer');
    const nomOccupantReel = document.getElementById('nomOccupantReel');

    const clientNom = document.getElementById('clientNom');
    const clientPrenom = document.getElementById('clientPrenom');
    const clientDateNaissance = document.getElementById('clientDateNaissance');
    const clientLieuNaissance = document.getElementById('clientLieuNaissance');
    const clientTel = document.getElementById('clientTel');
    const clientPieceNum = document.getElementById('clientPieceNum');
    const clientTypePiece = document.getElementById('clientTypePiece');
    const clientNationalite = document.getElementById('clientNationalite');
    const clientAdresse = document.getElementById('clientAdresse');
    const clientEmail = document.getElementById('clientEmail');
    
    const chambreSelect = document.getElementById('chambreSelect');
    const nbNuits = document.getElementById('nbNuits');
    const displayTotal = document.getElementById('displayTotal');
    const montantTotalInput = document.getElementById('montantTotalInput');
    const montantPaye = document.getElementById('montantPaye');

    // Auto-remplissage client
    selectClient.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (this.value) {
            idClientExistant.value = this.value;
            clientNom.value = opt.getAttribute('data-nom') || '';
            clientPrenom.value = opt.getAttribute('data-prenom') || '';
            clientDateNaissance.value = opt.getAttribute('data-date-naissance') || '';
            clientLieuNaissance.value = opt.getAttribute('data-lieu-naissance') || '';
            clientTel.value = opt.getAttribute('data-tel') || '';
            
            const rawPiece = opt.getAttribute('data-piece') || '';
            clientPieceNum.value = rawPiece;
            
            // Si la pièce commence par Passeport, CNI, etc.
            if (rawPiece.toLowerCase().indexOf('passeport') !== -1) {
                clientTypePiece.value = 'Passeport';
            } else if (rawPiece.toLowerCase().indexOf('permis') !== -1) {
                clientTypePiece.value = 'Permis';
            } else if (rawPiece.toLowerCase().indexOf('séjour') !== -1 || rawPiece.toLowerCase().indexOf('sejour') !== -1) {
                clientTypePiece.value = 'Carte de Séjour';
            } else {
                clientTypePiece.value = 'CNI';
            }

            clientNationalite.value = opt.getAttribute('data-nationalite') || 'Camerounaise';
            clientAdresse.value = opt.getAttribute('data-adresse') || '';
            clientEmail.value = opt.getAttribute('data-email') || '';

            // Détection si client déjà logé
            const isActive = (opt.getAttribute('data-is-active') === '1');
            const activeChNum = opt.getAttribute('data-active-chambre') || '';
            const activeResId = opt.getAttribute('data-active-res') || '';

            if (isActive) {
                alertClientOccupe.style.display = 'block';
                numChambreOccupeeBadge.textContent = 'N° ' + activeChNum;
                numChambreOccupeeBtn.textContent = activeChNum;
                linkCheckoutActive.href = '<?= BASE_URL ?>/modules/checkin_checkout/checkout.php?id=' + activeResId;
            } else {
                alertClientOccupe.style.display = 'none';
                if (checkTiers) checkTiers.checked = false;
                if (champTiersContainer) champTiersContainer.style.display = 'none';
                if (nomOccupantReel) {
                    nomOccupantReel.value = '';
                    nomOccupantReel.required = false;
                }
            }
        } else {
            idClientExistant.value = '';
            clientNom.value = '';
            clientPrenom.value = '';
            clientDateNaissance.value = '';
            clientLieuNaissance.value = '';
            clientTel.value = '';
            clientPieceNum.value = '';
            clientTypePiece.value = 'CNI';
            clientNationalite.value = 'Camerounaise';
            clientAdresse.value = '';
            clientEmail.value = '';

            alertClientOccupe.style.display = 'none';
            if (checkTiers) checkTiers.checked = false;
            if (champTiersContainer) champTiersContainer.style.display = 'none';
            if (nomOccupantReel) {
                nomOccupantReel.value = '';
                nomOccupantReel.required = false;
            }
        }
    });

    // Bascule champ invité / tiers
    if (checkTiers) {
        checkTiers.addEventListener('change', function() {
            if (this.checked) {
                champTiersContainer.style.display = 'block';
                nomOccupantReel.required = true;
                nomOccupantReel.focus();
            } else {
                champTiersContainer.style.display = 'none';
                nomOccupantReel.required = false;
            }
        });
    }

    const formuleNuitee = document.getElementById('formuleNuitee');
    const formuleSieste = document.getElementById('formuleSieste');
    const lblNuitee = document.getElementById('lblNuitee');
    const lblSieste = document.getElementById('lblSieste');
    const nbNuitsContainer = document.getElementById('nbNuitsContainer');
    const siesteBadgeContainer = document.getElementById('siesteBadgeContainer');

    function toggleFormule() {
        const isSieste = formuleSieste.checked;
        if (isSieste) {
            lblSieste.style.borderColor = '#d97706';
            lblSieste.style.background = '#fffbeb';
            lblSieste.style.color = '#b45309';

            lblNuitee.style.borderColor = '#e2e8f0';
            lblNuitee.style.background = '#f8fafc';
            lblNuitee.style.color = '#64748b';

            nbNuitsContainer.style.display = 'none';
            siesteBadgeContainer.style.display = 'block';
        } else {
            lblNuitee.style.borderColor = '#4f46e5';
            lblNuitee.style.background = '#eef2ff';
            lblNuitee.style.color = '#3730a3';

            lblSieste.style.borderColor = '#e2e8f0';
            lblSieste.style.background = '#f8fafc';
            lblSieste.style.color = '#64748b';

            nbNuitsContainer.style.display = 'block';
            siesteBadgeContainer.style.display = 'none';
        }
        calculerTotal();
    }

    formuleNuitee.addEventListener('change', toggleFormule);
    formuleSieste.addEventListener('change', toggleFormule);

    // Calcul automatique du total
    function calculerTotal() {
        const opt = chambreSelect.options[chambreSelect.selectedIndex];
        const isSieste = formuleSieste.checked;
        let prix = 0;
        let nuits = 1;

        if (isSieste) {
            prix = parseFloat(opt ? opt.getAttribute('data-prix-sieste') : 0) || 0;
            nuits = 1;
        } else {
            prix = parseFloat(opt ? opt.getAttribute('data-prix-nuit') : 0) || 0;
            nuits = parseInt(nbNuits.value) || 1;
        }

        const total = prix * nuits;

        displayTotal.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
        montantTotalInput.value = total;
        montantPaye.value = total;
    }

    chambreSelect.addEventListener('change', calculerTotal);
    nbNuits.addEventListener('input', calculerTotal);

    // Initialisation automatique si pré-sélection
    if (selectClient.value) {
        selectClient.dispatchEvent(new Event('change'));
    }
    if (chambreSelect.value) {
        calculerTotal();
    }

    // Protection anti-double soumission (double-clic)
    const expressForm = document.getElementById('expressForm');
    const submitBtn = document.getElementById('submitBtn');
    let formSubmitted = false;

    if (expressForm) {
        expressForm.addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }

            if (!chambreSelect.value) {
                e.preventDefault();
                alert("Veuillez sélectionner une chambre libre disponible.");
                chambreSelect.focus();
                return false;
            }

            // Vérification si client déjà logé
            const currentOpt = selectClient.options[selectClient.selectedIndex];
            const isClientActive = currentOpt && (currentOpt.getAttribute('data-is-active') === '1');

            if (isClientActive && (!checkTiers || !checkTiers.checked)) {
                e.preventDefault();
                alert("⛔ Attention : Ce client occupe déjà la Chambre N° " + (currentOpt.getAttribute('data-active-chambre') || '') + " !\n\nDans la logique hôtelière, un client ne peut pas occuper deux chambres simultanément pour lui-même.\n\nVeuillez soit clôturer son séjour précédent (Départ), soit cocher la case 'Cette 2ème chambre est pour un invité / tiers pris en charge'.");
                return false;
            }

            if (isClientActive && checkTiers && checkTiers.checked && (!nomOccupantReel || !nomOccupantReel.value.trim())) {
                e.preventDefault();
                alert("Veuillez saisir le Nom, Prénom & N° CNI du véritable occupant de cette 2ème chambre (obligation réglementaire de la fiche de police).");
                if (nomOccupantReel) nomOccupantReel.focus();
                return false;
            }

            // ── Interception Mode Hors-Ligne (Coupure Internet ou Mode Forcé) ──
            if (typeof isHotelAppOffline === 'function' && isHotelAppOffline()) {
                e.preventDefault();
                e.stopPropagation();

                const chOpt = chambreSelect.options[chambreSelect.selectedIndex];
                const modePaieSelect = expressForm.querySelector('select[name="mode_paiement"]');

                const checkinData = {
                    id_client_existant: parseInt(idClientExistant ? idClientExistant.value : 0) || 0,
                    nom: clientNom ? clientNom.value.trim() : '',
                    prenom: clientPrenom ? clientPrenom.value.trim() : '',
                    telephone: clientTel ? clientTel.value.trim() : '',
                    date_naissance: clientDateNaissance ? clientDateNaissance.value : '',
                    lieu_naissance: clientLieuNaissance ? clientLieuNaissance.value.trim() : '',
                    nationalite: clientNationalite ? clientNationalite.value.trim() : 'Camerounaise',
                    type_piece: clientTypePiece ? clientTypePiece.value : 'CNI',
                    numero_piece: clientPieceNum ? clientPieceNum.value.trim() : '',
                    email: clientEmail ? clientEmail.value.trim() : '',
                    adresse: clientAdresse ? clientAdresse.value.trim() : '',
                    type_sejour: (formuleSieste && formuleSieste.checked) ? 'sieste' : 'nuitee',
                    est_pour_tiers: checkTiers ? checkTiers.checked : false,
                    nom_occupant_reel: nomOccupantReel ? nomOccupantReel.value.trim() : '',
                    id_chambre: parseInt(chambreSelect.value) || 0,
                    numero_chambre: chOpt ? chOpt.textContent.trim().split('—')[0].replace('🛏️', '').trim() : 'Chambre ' + chambreSelect.value,
                    nb_nuits: parseInt(nbNuits.value) || 1,
                    date_arrivee: dateArrivee ? dateArrivee.value : '<?= date('Y-m-d') ?>',
                    montant_paye: parseFloat(montantPaye ? montantPaye.value : 0) || 0,
                    mode_paiement: modePaieSelect ? modePaieSelect.value : 'especes',
                    user_id: <?= (int)($user['id'] ?? 1) ?>
                };

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span>💾</span> Sauvegarde locale en cours...';
                }

                saveOfflineCheckin(checkinData).then(function(saved) {
                    if (saved) {
                        showHotelToast('🎉 Arrivée et encaissement enregistrés avec succès en local (Mode Hors-Ligne) !', 'success');
                        printHotelOfflineReceipt(saved);
                        expressForm.reset();
                        if (idClientExistant) idClientExistant.value = '';
                        calculerTotal();
                    } else {
                        alert("Erreur lors de l'enregistrement hors-ligne. Veuillez réessayer.");
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                        submitBtn.style.cursor = 'pointer';
                        submitBtn.innerHTML = '<span>🏨</span> Enregistrer l\'Arrivée & Valider l\'Encaissement';
                    }
                });
                return false;
            }

            formSubmitted = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.75';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.innerHTML = '<span>⏳</span> Enregistrement & Encaissement en cours...';
            }
        });
    }

    // Réinitialisation si retour en arrière (pageshow / bfcache)
    window.addEventListener('pageshow', function(event) {
        formSubmitted = false;
        if (submitBtn && chambreSelect && chambreSelect.options.length > 1) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            submitBtn.innerHTML = '<span>🏨</span> Enregistrer l\'Arrivée & Valider l\'Encaissement';
        }
        if (event.persisted && expressForm) {
            expressForm.reset();
            if (idClientExistant) idClientExistant.value = '';
            calculerTotal();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
