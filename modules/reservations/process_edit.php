<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $date_arrivee = $_POST['date_arrivee'] ?? '';
    $date_depart = $_POST['date_depart'] ?? '';
    $nb_personnes = $_POST['nb_personnes'] ?? 1;
    $remarques = $_POST['remarques'] ?? '';
    
    if (!$id || !$date_arrivee || !$date_depart || $date_arrivee >= $date_depart) {
        set_flash('danger', 'Dates invalides.');
        redirect(BASE_URL . '/modules/reservations/list.php');
    }
    
    $stmt = $pdo->prepare("UPDATE reservations SET date_arrivee=?, date_depart=?, nb_personnes=?, remarques=? WHERE id=?");
    $stmt->execute([
        $date_arrivee, $date_depart, $nb_personnes, $remarques, $id
    ]);
    
    set_flash('success', 'Réservation modifiée avec succès.');
    redirect(BASE_URL . '/modules/reservations/list.php');
}
