<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

// Module Réservations désactivé temporairement selon la consigne
set_flash('info', "Le module Réservations est temporairement désactivé. Enregistrez directement les occupants via l'Arrivée Express.");
redirect(BASE_URL . '/modules/checkin_checkout/express_checkin.php');
$clients = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, numero, id_type_chambre FROM chambres WHERE statut = 'libre'");
$chambres = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, prix_nuit FROM types_chambres");
$types_chambres = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->query("SELECT id, nom, prix_location FROM salles WHERE statut = 'libre'");
$salles = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Nouvelle réservation</h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="process_add.php" id="form-reservation">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label req">Client</label>
                    <select name="id_client" class="form-select" required>
                        <option value="">Sélectionner un client...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= h($c['nom'] . ' ' . $c['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Type de réservation</label>
                    <div>
                        <input type="radio" name="type_reservation" value="chambre" id="type_c" checked> <label for="type_c">Chambre</label>
                        <input type="radio" name="type_reservation" value="salle" id="type_s" style="margin-left:15px;"> <label for="type_s">Salle</label>
                    </div>
                </div>

                <div class="form-group" id="grp_chambre">
                    <label class="form-label req">Chambre</label>
                    <select name="id_chambre" class="form-select">
                        <option value="">Sélectionner...</option>
                        <?php foreach($chambres as $ch): ?>
                            <option value="<?= $ch['id'] ?>" data-prix="<?= $types_chambres[$ch['id_type_chambre']] ?? 0 ?>">Chambre <?= h($ch['numero']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="grp_salle" style="display:none;">
                    <label class="form-label req">Salle</label>
                    <select name="id_salle" class="form-select">
                        <option value="">Sélectionner...</option>
                        <?php foreach($salles as $s): ?>
                            <option value="<?= $s['id'] ?>" data-prix="<?= $s['prix_location'] ?>"><?= h($s['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label req">Date d'arrivée</label>
                    <input type="date" name="date_arrivee" id="d_arr" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label req">Date de départ</label>
                    <input type="date" name="date_depart" id="d_dep" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre de personnes</label>
                    <input type="number" name="nb_personnes" class="form-control" min="1" value="1">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Remarques</label>
                <textarea name="remarques" class="form-textarea"></textarea>
            </div>
            
            <div class="alert alert-info" style="margin-top:20px;">
                Montant estimé: <strong id="montant_estime">0 €</strong>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radioChambre = document.getElementById('type_c');
    const radioSalle = document.getElementById('type_s');
    const grpChambre = document.getElementById('grp_chambre');
    const grpSalle = document.getElementById('grp_salle');
    const selChambre = document.querySelector('select[name="id_chambre"]');
    const selSalle = document.querySelector('select[name="id_salle"]');
    const dArr = document.getElementById('d_arr');
    const dDep = document.getElementById('d_dep');
    const affMontant = document.getElementById('montant_estime');

    function updateForm() {
        if(radioChambre.checked) {
            grpChambre.style.display = 'block';
            grpSalle.style.display = 'none';
        } else {
            grpChambre.style.display = 'none';
            grpSalle.style.display = 'block';
        }
        calcMontant();
    }

    function calcMontant() {
        let prix = 0;
        if(radioChambre.checked && selChambre.selectedIndex > 0) {
            prix = selChambre.options[selChambre.selectedIndex].dataset.prix;
        } else if(radioSalle.checked && selSalle.selectedIndex > 0) {
            prix = selSalle.options[selSalle.selectedIndex].dataset.prix;
        }
        
        let nuits = 0;
        if(dArr.value && dDep.value) {
            const date1 = new Date(dArr.value);
            const date2 = new Date(dDep.value);
            const diffTime = Math.abs(date2 - date1);
            nuits = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
            if(nuits === 0) nuits = 1;
        }
        
        const total = parseFloat(prix) * nuits;
        affMontant.innerText = isNaN(total) || total === 0 ? '—' : total.toLocaleString('fr-FR') + ' F CFA';
    }

    radioChambre.addEventListener('change', updateForm);
    radioSalle.addEventListener('change', updateForm);
    selChambre.addEventListener('change', calcMontant);
    selSalle.addEventListener('change', calcMontant);
    dArr.addEventListener('change', calcMontant);
    dDep.addEventListener('change', calcMontant);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
