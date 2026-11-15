<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom")->fetchAll();
$reservations = $pdo->query("SELECT r.id, r.id_client, c.numero as chambre_numero, r.date_arrivee, r.date_depart 
                             FROM reservations r 
                             JOIN chambres c ON r.id_chambre = c.id 
                             WHERE r.statut IN ('en_cours', 'terminee') 
                             ORDER BY r.date_arrivee DESC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Créer une Facture</h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary btn-sm">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_create.php" method="post">
            <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label req">Client</label>
                    <select name="id_client" class="form-select" required>
                        <option value="">Sélectionner un client</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= h($c['nom'] . ' ' . $c['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Réservation liée (Optionnel)</label>
                    <select name="id_reservation" class="form-select">
                        <option value="">Aucune</option>
                        <?php foreach ($reservations as $r): ?>
                            <option value="<?= $r['id'] ?>" data-client="<?= $r['id_client'] ?>">
                                Réservation #<?= $r['id'] ?> - Ch. <?= h($r['chambre_numero']) ?> (<?= format_date($r['date_arrivee']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Si sélectionnée, cela peut aider à récupérer les détails.</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Date d'émission</label>
                    <input type="date" name="date_emission" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Montant Séjour</label>
                    <input type="number" step="0.01" name="montant_sejour" id="montant_sejour" class="form-control" value="0.00" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Montant Services</label>
                    <input type="number" step="0.01" name="montant_services" id="montant_services" class="form-control" value="0.00" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Montant Total (Calculé)</label>
                    <input type="number" id="montant_total" class="form-control" value="0.00" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Réduction (%)</label>
                    <input type="number" step="0.01" name="reduction_pct" id="reduction_pct" class="form-control" value="0" min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Motif de réduction</label>
                    <input type="text" name="reduction_motif" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Montant Final (Calculé)</label>
                    <input type="text" id="montant_final" class="form-control" value="0.00" disabled style="font-weight: bold; background: #e9ecef;">
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Mode de Paiement</label>
                    <select name="mode_paiement" class="form-select" required>
                        <option value="especes">Espèces</option>
                        <option value="carte">Carte</option>
                        <option value="mobile">Mobile Money</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Référence Paiement</label>
                    <input type="text" name="reference_paiement" class="form-control" placeholder="Numéro de transaction, etc.">
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Statut Paiement</label>
                    <select name="statut_paiement" class="form-select" required>
                        <option value="impayee">Impayée</option>
                        <option value="partielle">Partielle</option>
                        <option value="payee">Payée</option>
                    </select>
                </div>
            </div>
            <div class="form-actions mt-3">
                <button type="submit" class="btn btn-primary">Enregistrer la Facture</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sejour = document.getElementById('montant_sejour');
    const services = document.getElementById('montant_services');
    const total = document.getElementById('montant_total');
    const reduction = document.getElementById('reduction_pct');
    const final = document.getElementById('montant_final');

    function calculate() {
        let vSejour = parseFloat(sejour.value) || 0;
        let vServices = parseFloat(services.value) || 0;
        let vTotal = vSejour + vServices;
        total.value = vTotal.toFixed(2);
        
        let vRed = parseFloat(reduction.value) || 0;
        let vFinal = vTotal - (vTotal * (vRed / 100));
        final.value = vFinal.toFixed(2) + ' CFA';
    }

    sejour.addEventListener('input', calculate);
    services.addEventListener('input', calculate);
    reduction.addEventListener('input', calculate);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
