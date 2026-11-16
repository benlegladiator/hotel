<?php
// ============================================================
// modules/comptabilite/transfert_boss.php — Versement au Patron (Caisse Boss)
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
if (!is_supervisor()) {
    set_flash('error', 'Seul le Chef de Réception ou la Direction peut enregistrer un versement au Boss.');
    redirect('/dashboard.php');
}
$current_u = current_user();
$pre_user_id = (int)($_GET['id_user'] ?? 0);
$pre_montant = (float)($_GET['montant'] ?? 0);

$liste_reps = [];
if (is_admin()) {
    $stmt_reps = $pdo->query("SELECT id, nom, prenom, role FROM utilisateurs WHERE actif = 1 AND role IN ('receptionniste', 'chef_receptionniste') ORDER BY nom ASC");
    $liste_reps = $stmt_reps->fetchAll();
}

$page_title = "Versement au Patron (Caisse Boss)";
require_once '../../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    
    <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: #d97706; margin: 0; line-height: 1.25; word-break: break-word;">👑 Versement au Patron / Caisse du Boss</h1>
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 13.5px;">
                Transférez une somme d'espèces d'un tiroir-caisse vers le coffre/compte du Patron (clôture ou versement).
            </p>
        </div>
        <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn btn-outline">← Retour Journal</a>
    </div>

    <!-- ── Note explicative / Traçabilité ── -->
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid #f59e0b; background: #fffbeb;">
        <div class="card-body" style="padding: 16px 20px;">
            <div style="font-weight: 700; color: #92400e; font-size: 13.5px; margin-bottom: 4px;">
                📌 Pourquoi ce versement est important ?
            </div>
            <div style="font-size: 12.5px; color: #78350f; line-height: 1.45;">
                Lorsque vous remettez de l'argent en main propre au Boss, cette opération est déduite du solde physique de caisse de la réceptionniste. 
                Un <strong>Reçu de Décharge officiel</strong> est automatiquement généré pour faire foi.
            </div>
        </div>
    </div>

    <form action="process_depense.php" method="POST" class="card" style="box-shadow: var(--shadow-md); border-top: 4px solid #f59e0b;">
        
        <!-- Champs masqués pour forcer la catégorie versement_patron -->
        <input type="hidden" name="categorie" value="versement_patron">
        <input type="hidden" name="mode_paiement" value="especes">

        <div class="card-body" style="padding: 30px;">

            <?php if (is_admin() && !empty($liste_reps)): ?>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="id_utilisateur_remettant">Réceptionniste remettant les espèces <span style="color:#e11d48;">*</span></label>
                    <select name="id_utilisateur_remettant" id="id_utilisateur_remettant" class="form-select" style="height:48px; font-weight:700;">
                        <?php foreach ($liste_reps as $lr): ?>
                            <option value="<?= $lr['id'] ?>" <?= $lr['id'] === $pre_user_id ? 'selected' : '' ?>>
                                👤 <?= h($lr['prenom'] . ' ' . $lr['nom']) ?> (<?= $lr['role'] === 'chef_receptionniste' ? 'Chef Réception' : 'Réceptionniste' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="libelle">Motif / Contexte du Versement <span style="color:#e11d48;">*</span></label>
                <input type="text" id="libelle" name="libelle" class="form-input" required 
                       value="Versement d'espèces au Boss / Direction" 
                       placeholder="Ex: Versement d'espèces sur ordre de M. le Directeur" 
                       style="font-size:15px; font-weight:600; height:48px;">
            </div>

            <div class="form-grid-2" style="grid-template-columns: 1.2fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label" for="montant">Montant Versé au Patron (FCFA) <span style="color:#e11d48;">*</span></label>
                    <input type="number" id="montant" name="montant" class="form-input" min="1" step="any" 
                           value="<?= $pre_montant > 0 ? (int)$pre_montant : '' ?>"
                           placeholder="Ex: 50000" required autofocus style="font-size:22px; font-weight:800; color:#d97706; height:48px;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_sortie">Date du Versement</label>
                    <input type="date" id="date_sortie" name="date_sortie" class="form-input" value="<?= date('Y-m-d') ?>" required style="height:48px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 32px;">
                <label class="form-label" for="notes">Référence / Note de décharge (Optionnel)</label>
                <textarea id="notes" name="notes" class="form-textarea" rows="2" placeholder="Ex: Remis en main propre à 14h30 sur demande téléphonique du Boss..."></textarea>
            </div>

            <div style="display: flex; gap: 16px; align-items: center;">
                <button type="submit" class="btn btn-lg" style="background:#d97706; color:#ffffff; flex: 1; font-weight: 800; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);">
                    <span>👑</span> Valider le Versement au Boss & Générer Reçu
                </button>
                <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn btn-outline btn-lg">Annuler</a>
            </div>

        </div>

    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
