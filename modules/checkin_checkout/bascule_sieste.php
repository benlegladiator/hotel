<?php
// ============================================================
// modules/checkin_checkout/bascule_sieste.php
// Conversion fluide d'une formule Sieste en Nuitée (avec calcul du reliquat)
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id_res = (int)($_GET['id'] ?? 0);
if ($id_res <= 0) {
    set_flash('error', "Séjour non spécifié.");
    redirect('checkout.php');
}

$stmt = $pdo->prepare("
    SELECT r.*, c.nom, c.prenom, c.telephone, c.piece_identite,
           ch.numero as chambre_numero, tc.nom as type_nom, tc.prix_nuit, tc.prix_sieste,
           f.id as id_facture, f.montant_sejour as fac_montant_sejour, f.montant_final as fac_montant_final, 
           f.statut_paiement as fac_statut_paiement, f.mode_paiement as fac_mode_paiement
    FROM reservations r
    JOIN clients c ON r.id_client = c.id
    JOIN chambres ch ON r.id_chambre = ch.id
    JOIN types_chambres tc ON ch.id_type_chambre = tc.id
    LEFT JOIN factures f ON f.id_reservation = r.id
    WHERE r.id = ? AND r.statut = 'en_cours'
    LIMIT 1
");
$stmt->execute([$id_res]);
$res = $stmt->fetch();

if (!$res) {
    set_flash('error', "Séjour introuvable ou déjà clôturé.");
    redirect('checkout.php');
}

// Vérifier si c'est bien une formule sieste
$is_sieste = (stripos($res['remarques'] ?? '', 'sieste') !== false) || ($res['date_arrivee'] === $res['date_depart']);
if (!$is_sieste) {
    set_flash('info', "Cette chambre est déjà enregistrée en formule Nuitée standard.");
    redirect('checkout.php');
}

// Calcul du tarif et du reliquat
$prix_nuitee = (float)$res['prix_nuit'];
$montant_deja_paye = (float)($res['fac_montant_sejour'] ?: ($res['prix_sieste'] ?? 10000));
$reliquat = max(0, $prix_nuitee - $montant_deja_paye);

$nouvelle_date_depart = date('Y-m-d', strtotime($res['date_arrivee'] . ' +1 day'));
if ($nouvelle_date_depart <= date('Y-m-d')) {
    $nouvelle_date_depart = date('Y-m-d', strtotime('+1 day'));
}

// Jeton anti-doublon
$bascule_token = bin2hex(random_bytes(16));
$_SESSION['bascule_token'] = $bascule_token;

$page_title = "Bascule Sieste ➔ Nuitée (Chambre " . $res['chambre_numero'] . ")";
require_once '../../includes/header.php';
?>

<div style="max-width: 760px; margin: 0 auto;">

    <!-- En-tête -->
    <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: var(--text-primary); margin: 0;">
                🌙 Prolongation : Basculer Sieste en Nuitée
            </h1>
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 13.5px;">
                Permet au client de passer la nuit dans sa chambre en ne réglant que la différence tarifaire (aucun doublon).
            </p>
        </div>
        <a href="checkout.php" class="btn btn-outline">← Retour Départs</a>
    </div>

    <form method="POST" action="process_bascule_sieste.php" id="basculeForm" autocomplete="off" class="card" style="box-shadow: var(--shadow-md);">
        <input type="hidden" name="id_reservation" value="<?= $res['id'] ?>">
        <input type="hidden" name="bascule_token" value="<?= $bascule_token ?>">
        <input type="hidden" name="reliquat_theorique" value="<?= $reliquat ?>">

        <div class="card-body" style="padding: 32px;">

            <!-- Carte Récapitulative du Client & Chambre -->
            <div style="background: #f8fafc; border: 1.5px solid var(--border-light); border-radius: var(--r-md); padding: 20px; margin-bottom: 24px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); letter-spacing: 0.5px;">Client Occupant :</div>
                        <div style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin-top: 3px;">
                            👤 <?= h($res['nom'] . ' ' . $res['prenom']) ?>
                        </div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                            📞 <?= h($res['telephone'] ?: 'Non renseigné') ?> &bull; CNI: <?= h($res['piece_identite'] ?: '—') ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); letter-spacing: 0.5px;">Chambre Actuelle :</div>
                        <div style="font-size: 17px; font-weight: 800; color: #d97706; margin-top: 3px;">
                            🛏️ Chambre N° <?= h($res['chambre_numero']) ?>
                        </div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                            Catégorie : <strong><?= h($res['type_nom']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Décompte de la Bascule Tarifaire -->
            <div style="margin-bottom: 28px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; background:#fef3c7; color:#b45309; font-weight:800; border-radius:50%; font-size:15px;">1</span>
                    <h3 style="font-size: 16.5px; font-weight: 700; color: var(--text-primary); margin: 0;">Décompte Financier & Prolongation</h3>
                </div>

                <div style="border: 2px dashed #cbd5e1; border-radius: var(--r-md); padding: 18px; background: #ffffff;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; font-size: 14px;">
                        <span style="color: var(--text-secondary);">Tarif officiel Nuitée (<?= h($res['type_nom']) ?>) :</span>
                        <strong style="color: var(--text-primary); font-size: 15px;"><?= format_prix($prix_nuitee) ?></strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px;">
                        <span style="color: #059669; font-weight: 600;">Déjà payé lors de l'arrivée (⚡ Sieste) :</span>
                        <strong style="color: #059669; font-size: 15px;">- <?= format_prix($montant_deja_paye) ?></strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px;">
                        <span style="color: var(--text-secondary);">Nouvelle date de départ prévue :</span>
                        <strong style="color: #4f46e5; font-size: 15px;">Demain <?= format_date($nouvelle_date_depart) ?> à 12h00</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 14px; margin-top: 4px;">
                        <div>
                            <span style="font-size: 14px; font-weight: 800; text-transform: uppercase; color: #b45309; letter-spacing: 0.5px;">Reliquat / Complément à Encaisser :</span>
                            <div style="font-size: 12px; color: var(--text-secondary);">Différence due pour prolonger en nuitée</div>
                        </div>
                        <div style="font-size: 26px; font-weight: 800; color: #059669;">
                            <?= format_prix($reliquat) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Règlement du reliquat -->
            <div style="margin-bottom: 30px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; background:#ecfdf5; color:#059669; font-weight:800; border-radius:50%; font-size:15px;">2</span>
                    <h3 style="font-size: 16.5px; font-weight: 700; color: var(--text-primary); margin: 0;">Encaissement du Complément</h3>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label class="form-label">Montant perçu maintenant <span style="color:#e11d48;">*</span></label>
                        <input type="number" step="any" name="montant_paye" id="montantPaye" value="<?= $reliquat ?>" class="form-input" required style="font-weight:800; font-size:18px; color:#059669;">
                    </div>
                    <div>
                        <label class="form-label">Mode de règlement</label>
                        <select name="mode_paiement" class="form-select" style="font-weight:600;">
                            <option value="especes" <?= ($res['fac_mode_paiement'] ?? '') === 'especes' ? 'selected' : '' ?>>💵 Espèces</option>
                            <option value="mobile" <?= ($res['fac_mode_paiement'] ?? '') === 'mobile' ? 'selected' : '' ?>>📱 Mobile Money</option>
                            <option value="carte" <?= ($res['fac_mode_paiement'] ?? '') === 'carte' ? 'selected' : '' ?>>💳 Carte Bancaire</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Boutons de validation -->
            <div style="display: flex; gap: 14px; align-items: center;">
                <button type="submit" id="submitBasculeBtn" class="btn btn-primary btn-lg" style="flex: 1; font-weight: 800;">
                    <span>🌙</span> Valider la Bascule en Nuitée (<?= format_prix($reliquat) ?>)
                </button>
                <a href="checkout.php" class="btn btn-outline btn-lg">Annuler</a>
            </div>

        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basculeForm = document.getElementById('basculeForm');
    const submitBasculeBtn = document.getElementById('submitBasculeBtn');
    let isSubmitting = false;

    if (basculeForm) {
        basculeForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            if (submitBasculeBtn) {
                submitBasculeBtn.disabled = true;
                submitBasculeBtn.style.opacity = '0.75';
                submitBasculeBtn.innerHTML = '<span>⏳</span> Enregistrement & Mise à jour facture...';
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
