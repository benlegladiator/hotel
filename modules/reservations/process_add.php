<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/reservations/list.php');
}

$id_client    = post_int('id_client');
$type         = post('type_reservation');
$id_chambre   = post_int('id_chambre') ?: null;
$id_salle     = post_int('id_salle')   ?: null;
$date_arrivee = post('date_arrivee');
$date_depart  = post('date_depart');
$nb_personnes = post_int('nb_personnes', 1);
$remarques    = post('remarques');

if (!$id_client || !$date_arrivee || !$date_depart || $date_arrivee >= $date_depart) {
    redirect(BASE_URL . '/modules/reservations/add.php', 'error', 'Dates invalides ou client manquant.');
}
if ($type === 'chambre' && !$id_chambre) {
    redirect(BASE_URL . '/modules/reservations/add.php', 'error', 'Sélectionnez une chambre.');
}
if ($type === 'salle' && !$id_salle) {
    redirect(BASE_URL . '/modules/reservations/add.php', 'error', 'Sélectionnez une salle.');
}

// Vérification chevauchement chambre
if ($type === 'chambre' && $id_chambre) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE id_chambre = ? AND statut NOT IN ('annulee','terminee')
         AND date_arrivee < ? AND date_depart > ?"
    );
    $stmt->execute([$id_chambre, $date_depart, $date_arrivee]);
    if ($stmt->fetchColumn() > 0) {
        redirect(BASE_URL . '/modules/reservations/add.php', 'error', 'Cette chambre est déjà réservée pour ces dates.');
    }
}

// INSERT
$pdo->prepare(
    "INSERT INTO reservations
     (id_client, id_chambre, id_salle, type_reservation,
      date_arrivee, date_depart, statut, nb_personnes, remarques, id_receptionniste)
     VALUES (?, ?, ?, ?, ?, ?, 'confirmee', ?, ?, ?)"
)->execute([
    $id_client,
    $type === 'chambre' ? $id_chambre : null,
    $type === 'salle'   ? $id_salle   : null,
    $type, $date_arrivee, $date_depart,
    $nb_personnes, $remarques, $_SESSION['user_id']
]);

// Mise à jour statut chambre/salle
if ($type === 'chambre' && $id_chambre) {
    $pdo->prepare("UPDATE chambres SET statut='reservee' WHERE id=?")->execute([$id_chambre]);
} elseif ($type === 'salle' && $id_salle) {
    $pdo->prepare("UPDATE salles SET statut='reservee' WHERE id=?")->execute([$id_salle]);
}

redirect(BASE_URL . '/modules/reservations/list.php', 'success', 'Réservation ajoutée avec succès.');
