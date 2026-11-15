<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id_client = post_int('id_client', $_GET['id_client'] ?? 0);
$id_chambre= post_int('id_chambre', $_GET['id_chambre'] ?? 0);

$query_params = [];
if ($id_client) $query_params['id_client'] = $id_client;
if ($id_chambre) $query_params['id_chambre'] = $id_chambre;

$redir_url = BASE_URL . '/modules/checkin_checkout/express_checkin.php';
if (!empty($query_params)) {
    $redir_url .= '?' . http_build_query($query_params);
}
redirect($redir_url);
$stmt = $pdo->query("
    SELECT r.id, r.date_arrivee, r.date_depart, c.nom, c.prenom, ch.numero as chambre_numero, r.type_reservation
    FROM reservations r
    JOIN clients c ON r.id_client = c.id
    LEFT JOIN chambres ch ON r.id_chambre = ch.id
    WHERE r.statut = 'confirmee'
    ORDER BY r.date_arrivee ASC
");
$reservations = $stmt->fetchAll();

// 2. Liste de tous les clients existants (pour client habitué / retour)
$stmt_c = $pdo->query("SELECT id, nom, prenom, telephone, piece_identite, nb_sejours FROM clients ORDER BY nom ASC, prenom ASC");
$clients_existants = $stmt_c->fetchAll();

// 3. Liste des chambres libres ou en nettoyage
$stmt_ch = $pdo->query("SELECT c.id, c.numero, c.statut, tc.nom as type_nom, tc.prix_nuit FROM chambres c JOIN types_chambres tc ON c.id_type_chambre = tc.id ORDER BY c.numero ASC");
$chambres = $stmt_ch->fetchAll();

$selected_res = null;
if ($id_res) {
    foreach ($reservations as $r) {
        if ($r['id'] == $id_res) {
            $selected_res = $r;
            break;
        }
    }
}

$page_title = "Enregistrement Check-in & Occupation";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Enregistrement Check-in / Occupation</h1>
        <p class="text-secondary" style="margin-top:4px;">Enregistrez une arrivée depuis une réservation ou directement pour un client existant (client habitué)</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="btn btn-outline">📊 Traçabilité Occupation</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Option 1 : Check-in depuis une Réservation Confirmée</h3></div>
    <div class="card-body">
        <form method="GET" action="checkin.php" class="d-flex mb-3" style="gap: 10px;">
            <select name="id" class="form-select" onchange="this.form.submit()">
                <option value="">Sélectionner une réservation confirmée...</option>
                <?php foreach($reservations as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $id_res == $r['id'] ? 'selected' : '' ?>>
                        #RES-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?> - <?= h($r['nom'].' '.$r['prenom']) ?> (Chambre <?= h($r['chambre_numero'] ?? 'N/A') ?> — Arrivée: <?= format_date($r['date_arrivee']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selected_res): ?>
            <div class="alert alert-info" style="background:var(--bg-app); border:1px solid var(--gold-500); padding:16px; border-radius:var(--r-md); margin-bottom:16px;">
                <h4 style="color:var(--gold-500); margin-top:0;">Détails de la Réservation #RES-<?= str_pad($selected_res['id'], 4, '0', STR_PAD_LEFT) ?></h4>
                <p><strong>Client :</strong> <?= h($selected_res['nom'].' '.$selected_res['prenom']) ?></p>
                <p><strong>Chambre :</strong> Chambre N° <?= h($selected_res['chambre_numero']) ?></p>
                <p><strong>Période du séjour :</strong> Du <?= format_date($selected_res['date_arrivee']) ?> au <?= format_date($selected_res['date_depart']) ?> (<?= nb_nuits($selected_res['date_arrivee'], $selected_res['date_depart']) ?> nuits)</p>
            </div>
            
            <form method="POST" action="process_checkin.php">
                <input type="hidden" name="type_checkin" value="reservation">
                <input type="hidden" name="id_reservation" value="<?= $selected_res['id'] ?>">
                <button type="submit" class="btn btn-success" style="padding:10px 24px;">📥 Valider le Check-in de cette Réservation</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4" style="border:2px solid var(--gold-500);">
    <div class="card-header" style="background:var(--bg-app);">
        <h3 class="card-title" style="color:var(--gold-500);">Option 2 : Check-in Direct / Client Habitué (Sans Réservation Préalable)</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="process_checkin.php">
            <input type="hidden" name="type_checkin" value="direct">
            
            <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:16px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700;">👤 Sélectionner le Client Existant (Client Habitué) *</label>
                    <p class="text-secondary" style="font-size:12px; margin-bottom:8px;">Tapez ou sélectionnez le nom/téléphone du client sans avoir à ressaisir ses informations.</p>
                    <select name="id_client" class="form-select" required style="font-size:14px; font-weight:600;">
                        <option value="">Sélectionner un client dans le registre...</option>
                        <?php foreach ($clients_existants as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $id_client == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['nom'] . ' ' . $c['prenom']) ?> — Tél: <?= h($c['telephone'] ?? '—') ?> (<?= $c['nb_sejours'] ?> séjour(s))
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top:6px;">
                        <a href="<?= BASE_URL ?>/modules/clients/add.php" class="btn btn-sm btn-outline">+ Créer un tout nouveau client</a>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700;">🔑 Sélectionner la Chambre *</label>
                    <p class="text-secondary" style="font-size:12px; margin-bottom:8px;">Choisissez la chambre attribuée pour ce séjour.</p>
                    <select name="id_chambre" class="form-select" required style="font-size:14px; font-weight:600;">
                        <option value="">Sélectionner une chambre...</option>
                        <?php foreach ($chambres as $ch): ?>
                            <option value="<?= $ch['id'] ?>" <?= $id_chambre == $ch['id'] ? 'selected' : '' ?>>
                                Chambre N° <?= h($ch['numero']) ?> (<?= h($ch['type_nom']) ?> — <?= format_money($ch['prix_nuit']) ?> / nuit) [Statut: <?= h(strtoupper($ch['statut'])) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:16px;">
                <div class="form-group">
                    <label class="form-label">Date & Heure d'Arrivée (Check-in) *</label>
                    <input type="datetime-local" name="date_arrivee" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date Départ Prévue (Check-out) *</label>
                    <input type="datetime-local" name="date_depart_prevue" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>" required>
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="form-label">Remarques & Observations du Séjour</label>
                <textarea name="remarques" class="form-textarea" rows="2" placeholder="Ex: Paiement espèces effectué à l'arrivée, demande lit supplémentaire..."></textarea>
            </div>

            <button type="submit" class="btn btn-success" style="padding:10px 24px;">📥 Enregistrer l'Occupation (Check-in Direct)</button>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
