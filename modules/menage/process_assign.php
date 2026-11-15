<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_chambre      = post_int('id_chambre');
    $id_agent        = post_int('id_agent');
    if (!$id_agent) $id_agent = null;

    $type_tache      = post('type_tache', 'periodique');
    $priorite        = post('priorite', 'normale');
    $date_prevue     = post('date_prevue', date('Y-m-d'));
    $mode_recurrence = post('mode_recurrence', 'une_fois');
    $notes           = post('notes');

    if ($id_chambre && $type_tache && $date_prevue) {
        $dates = [];
        $d_start = new DateTime($date_prevue);

        if ($mode_recurrence === 'semaine') {
            for ($i = 0; $i < 7; $i++) {
                $dates[] = $d_start->format('Y-m-d');
                $d_start->modify('+1 day');
            }
        } elseif ($mode_recurrence === 'mois') {
            for ($i = 0; $i < 30; $i++) {
                $dates[] = $d_start->format('Y-m-d');
                $d_start->modify('+1 day');
            }
        } elseif ($mode_recurrence === 'periode') {
            $date_fin = post('date_fin', $date_prevue);
            $d_end = new DateTime($date_fin);
            if ($d_end < $d_start) $d_end = clone $d_start;
            
            while ($d_start <= $d_end) {
                $dates[] = $d_start->format('Y-m-d');
                $d_start->modify('+1 day');
            }
        } else {
            $dates[] = $date_prevue;
        }

        $stmt = $pdo->prepare("INSERT INTO taches_menage (id_chambre, id_agent, type_tache, priorite, date_prevue, statut, notes, created_at) VALUES (?, ?, ?, ?, ?, 'assignee', ?, NOW())");
        
        $nb_inserted = 0;
        foreach ($dates as $d) {
            $stmt->execute([$id_chambre, $id_agent, $type_tache, $priorite, $d, $notes]);
            $nb_inserted++;
        }

        // Met la chambre en statut 'nettoyage' si la date d'aujourd'hui est concernée
        if (in_array(date('Y-m-d'), $dates)) {
            $stmt_c = $pdo->prepare("UPDATE chambres SET statut = 'nettoyage' WHERE id = ?");
            $stmt_c->execute([$id_chambre]);
        }

        set_flash('success', "$nb_inserted tâche(s) de ménage générée(s) et affectée(s) avec succès.");
    }
}

redirect('tracabilite.php');
