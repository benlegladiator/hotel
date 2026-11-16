<?php
// ============================================================
// modules/comptabilite/add_depense.php — Sortie de Caisse Express (v2.0)
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Enregistrer une Dépense / Sortie de Caisse";
require_once '../../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    
    <!-- ── Bouton Retour au Journal de Caisse ── -->
    <div>
        <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn-page-back">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>← Retour au Journal de Caisse</span>
        </a>
    </div>

    <div style="margin-bottom: 20px;">
        <h1 style="font-size: 22px; font-weight: 800; color: #e11d48; margin: 0; line-height: 1.25; word-break: break-word;">💸 Sortie de Caisse / Dépense Directe</h1>
        <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13.5px;">Enregistrez les achats quotidiens (savon, eau, ampoules, maintenance) qui diminuent la caisse.</p>
    </div>

    <!-- ── Suggestions Rapides en 1 Clic ── -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px 18px;">
            <div style="font-size: 12.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 10px;">⚡ Motifs Fréquents (Cliquez pour remplir automatiquement) :</div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php if (!is_admin()): ?>
                    <button type="button" class="btn btn-sm" onclick="setMotif('Versement Espèces au Patron / Caisse Boss', 'versement_patron')" style="background:#fef3c7; border:1.5px solid #fde68a; color:#b45309; border-radius:20px; font-weight:800; font-size:12px; padding:5px 12px;">👑 Versement au Boss (Patron)</button>
                <?php else: ?>
                    <button type="button" class="btn btn-sm" onclick="setMotif('Prélèvement Personnel Direction / Retrait Coffre', 'prelevement_patron')" style="background:#fef3c7; border:1.5px solid #fde68a; color:#b45309; border-radius:20px; font-weight:800; font-size:12px; padding:5px 12px;">👑 Prélèvement Patron</button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm" onclick="setMotif('Achat Savon & Détergents', 'nettoyage')" style="background:#fff1f2; border:1.5px solid #fecdd3; color:#e11d48; border-radius:20px; font-weight:700; font-size:12px; padding:5px 12px;">🧼 Achat Savon</button>
                <button type="button" class="btn btn-sm" onclick="setMotif('Paiement Facture Eau / Ravitaillement', 'facture')" style="background:#eff6ff; border:1.5px solid #bfdbfe; color:#1d4ed8; border-radius:20px; font-weight:700; font-size:12px; padding:5px 12px;">💧 Payer l'Eau</button>
                <button type="button" class="btn btn-sm" onclick="setMotif('Achat Ampoules / Petit Matériel', 'materiel')" style="background:#fffbeb; border:1.5px solid #fde68a; color:#b45309; border-radius:20px; font-weight:700; font-size:12px; padding:5px 12px;">💡 Ampoules / Matériel</button>
                <button type="button" class="btn btn-sm" onclick="setMotif('Produits d\'Entretien & Ménage', 'nettoyage')" style="background:#ecfdf5; border:1.5px solid #a7f3d0; color:#059669; border-radius:20px; font-weight:700; font-size:12px; padding:5px 12px;">🧹 Produits Ménage</button>
                <button type="button" class="btn btn-sm" onclick="setMotif('Recharge Électricité / Énergie', 'facture')" style="background:#faf5ff; border:1.5px solid #e9d5ff; color:#7e22ce; border-radius:20px; font-weight:700; font-size:12px; padding:5px 12px;">⚡ Électricité</button>
                <button type="button" class="btn btn-sm" onclick="setMotif('Petite Réparation Plomberie/Clim', 'maintenance')" style="background:#f8fafc; border:1.5px solid #cbd5e1; color:#334155; border-radius:20px; font-weight:700; font-size:12px; padding:5px 12px;">🛠️ Réparation</button>
            </div>
        </div>
    </div>

    <form action="process_depense.php" method="POST" class="card" style="box-shadow: var(--shadow-md);">
        
        <div class="card-body" style="padding: 36px;">
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="libelle">Motif / Libellé de la dépense ou versement <span style="color:#e11d48;">*</span></label>
                <input type="text" id="libelle" name="libelle" class="form-input" required placeholder="Ex: Versement d'espèces en main propre à M. le Directeur ou Achat savon" style="font-size:16px; font-weight:600; height:50px;">
            </div>

            <div class="form-grid-2" style="grid-template-columns: 1.2fr 1fr; gap: 20px; margin-bottom: 24px;">
                <div class="form-group">
                    <label class="form-label" for="montant">Montant Décaissé / Transféré (FCFA) <span style="color:#e11d48;">*</span></label>
                    <input type="number" id="montant" name="montant" class="form-input" min="1" step="any" placeholder="Ex: 50000" required style="font-size:22px; font-weight:800; color:#e11d48; height:50px;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="categorie">Catégorie</label>
                    <select id="categorie" name="categorie" class="form-select" style="height:50px;">
                        <?php if (is_admin()): ?>
                            <option value="prelevement_patron">👑 Prélèvement Patron (Retrait Coffre Personnel)</option>
                        <?php else: ?>
                            <option value="versement_patron">👑 Versement au Patron (Caisse Boss)</option>
                        <?php endif; ?>
                        <option value="nettoyage">🧹 Produits d'Entretien (Savon, Javel...)</option>
                        <option value="facture">💧 Eau / Électricité / Services</option>
                        <option value="maintenance">🛠️ Maintenance & Réparations</option>
                        <option value="materiel">🏨 Équipement & Matériel</option>
                        <option value="fourniture">📦 Fournitures Réception</option>
                        <option value="autre">📌 Autre Dépense</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-2" style="gap: 20px; margin-bottom: 24px;">
                <div class="form-group">
                    <label class="form-label" for="date_sortie">Date de la Dépense</label>
                    <input type="date" id="date_sortie" name="date_sortie" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="mode_paiement">Mode de Règlement</label>
                    <select id="mode_paiement" name="mode_paiement" class="form-select">
                        <option value="especes" selected>💵 Espèces (Direct caisse)</option>
                        <option value="mobile">📱 Mobile Money</option>
                        <option value="carte">💳 Carte / Virement</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 32px;">
                <label class="form-label" for="notes">Détails ou Remarques (Optionnel)</label>
                <textarea id="notes" name="notes" class="form-textarea" rows="2" placeholder="Ex: Acheté à la boutique du coin par la caissière..."></textarea>
            </div>

            <div style="display: flex; gap: 16px; align-items: center;">
                <button type="submit" class="btn btn-danger btn-lg" style="flex: 1; font-weight: 800;">
                    <span>💸</span> Valider la Sortie de Caisse
                </button>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline btn-lg">Annuler</a>
            </div>

        </div>

    </form>
</div>

<script>
function setMotif(titre, cat) {
    document.getElementById('libelle').value = titre;
    document.getElementById('categorie').value = cat;
    document.getElementById('montant').focus();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
