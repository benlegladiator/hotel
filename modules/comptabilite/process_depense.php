<?php
// ============================================================
// modules/comptabilite/process_depense.php
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $libelle        = post('libelle');
    $categorie      = post('categorie', 'fourniture');
    $montant        = post_float('montant');
    $date_sortie    = post('date_sortie', date('Y-m-d'));
    $mode_paiement  = post('mode_paiement', 'especes');
    $reference_recu = post('reference_recu');
    $notes          = post('notes');
    $user_id        = (int)(current_user()['id'] ?? 1);
    // Si l'administrateur enregistre un versement remis par une réceptionniste spécifique
    if (is_admin() && !empty($_POST['id_utilisateur_remettant'])) {
        $user_id = (int)$_POST['id_utilisateur_remettant'];
    }

    if ($categorie === 'versement_patron' && !is_supervisor()) {
        set_flash('error', "Seul le Chef de Réception ou la Direction peut enregistrer un versement au Boss.");
        redirect('/dashboard.php');
    }

    if ($libelle && $montant > 0 && $date_sortie) {
        $stmt = $pdo->prepare("
            INSERT INTO sorties_caisse (libelle, categorie, montant, date_sortie, mode_paiement, reference_recu, notes, id_utilisateur, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$libelle, $categorie, $montant, $date_sortie, $mode_paiement, $reference_recu, $notes, $user_id]);
        $depense_id = (int)$pdo->lastInsertId();

        if ($categorie === 'versement_patron') {
            set_flash('success', "👑 Versement au Patron de " . format_prix($montant) . " enregistré avec succès. Le reçu de décharge est prêt.");
        } elseif ($categorie === 'prelevement_patron') {
            set_flash('success', "👑 Prélèvement Patron de " . format_prix($montant) . " enregistré. La somme a été retirée de la trésorerie active.");
        } else {
            set_flash('success', "💸 Sortie de caisse de " . format_prix($montant) . " pour « $libelle » enregistrée avec succès.");
        }
        redirect("print_depense.php?id=$depense_id");
    } else {
        set_flash('error', "Veuillez renseigner le motif et un montant valide.");
        redirect('add_depense.php');
    }
}

redirect(BASE_URL . '/dashboard.php');
