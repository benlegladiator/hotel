<?php
// ============================================================
// modules/menage/quick_clean.php — Validation 1-clic Ménage
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id_chambre = (int)($_POST['id_chambre'] ?? $_GET['id_chambre'] ?? 0);
$current_u  = current_user();
$user_id    = $current_u['id'];
$user_name  = trim(($current_u['prenom'] ?? '') . ' ' . ($current_u['nom'] ?? ''));

if ($id_chambre > 0) {
    try {
        $pdo->beginTransaction();

        // 1. Récupérer le numéro de la chambre
        $stmt_ch = $pdo->prepare("SELECT numero, statut FROM chambres WHERE id = ?");
        $stmt_ch->execute([$id_chambre]);
        $chambre = $stmt_ch->fetch();

        if ($chambre) {
            // 2. Mettre la chambre en statut libre
            $stmt_up = $pdo->prepare("UPDATE chambres SET statut = 'libre' WHERE id = ?");
            $stmt_up->execute([$id_chambre]);

            // 3. Enregistrer ou clôturer la tâche de ménage
            $stmt_tk = $pdo->prepare("
                INSERT INTO taches_menage (id_chambre, id_utilisateur, type_tache, priorite, date_prevue, heure_fin, statut, notes)
                VALUES (?, ?, 'sortie', 'normale', CURDATE(), NOW(), 'terminee', ?)
            ");
            $stmt_tk->execute([
                $id_chambre,
                $user_id,
                "Nettoyé et validé par " . ($user_name ?: 'Caissière') . " (" . date('d/m/Y H:i') . ")"
            ]);

            $pdo->commit();
            set_flash('success', "✨ Chambre {$chambre['numero']} marquée comme nettoyée et prête par {$user_name} !");
        } else {
            $pdo->rollBack();
            set_flash('error', "Chambre introuvable.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', "Erreur lors de la validation du ménage : " . $e->getMessage());
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/dashboard.php');
header("Location: $redirect");
exit;
