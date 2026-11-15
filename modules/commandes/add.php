<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

// Récupérer les clients pour le select
$stmt = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC");
$clients = $stmt->fetchAll();

// Récupérer les réservations en cours
$stmt = $pdo->query("
    SELECT r.id, r.id_client, ch.numero as chambre_numero, cl.nom, cl.prenom 
    FROM reservations r 
    JOIN chambres ch ON r.id_chambre = ch.id 
    JOIN clients cl ON r.id_client = cl.id
    WHERE r.statut = 'en_cours'
    ORDER BY cl.nom ASC
");
$reservations = $stmt->fetchAll();

// Récupérer les services disponibles
$stmt = $pdo->query("SELECT id, nom, prix, categorie FROM catalogue_services WHERE disponible = 1 ORDER BY categorie, nom");
$services = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Nouvelle Commande</h2>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour aux commandes</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_add.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="id_client">Client *</label>
                <select id="id_client" name="id_client" class="form-select" required>
                    <option value="">Sélectionner un client...</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= h($client['nom'] . ' ' . $client['prenom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="id_reservation">Réservation liée (Optionnel)</label>
                <select id="id_reservation" name="id_reservation" class="form-select">
                    <option value="">Aucune réservation / Client de passage</option>
                    <?php foreach ($reservations as $res): ?>
                        <option value="<?= $res['id'] ?>"><?= h($res['nom'] . ' ' . $res['prenom']) ?> - Chambre <?= h($res['chambre_numero']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="notes">Notes spéciales</label>
                <textarea id="notes" name="notes" class="form-textarea" rows="2"></textarea>
            </div>

            <h4 class="mt-4 mb-3">Articles</h4>
            <div id="articles-container">
                <?php for($i=0; $i<5; $i++): ?>
                <div class="article-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <div style="flex: 2;">
                        <select name="service_id[]" class="form-select">
                            <option value="">Sélectionner un article...</option>
                            <?php 
                            $currentCat = '';
                            foreach ($services as $service): 
                                if ($currentCat != $service['categorie']):
                                    if ($currentCat != '') echo '</optgroup>';
                                    $currentCat = $service['categorie'];
                                    echo '<optgroup label="'.ucfirst(h($currentCat)).'">';
                                endif;
                            ?>
                                <option value="<?= $service['id'] ?>"><?= h($service['nom']) ?> - <?= format_money($service['prix']) ?></option>
                            <?php endforeach; if($currentCat != '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <input type="number" name="quantite[]" class="form-control" placeholder="Qté" min="1" value="<?= $i==0 ? 1 : '' ?>">
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            <p class="text-muted"><small>Laissez vide pour ignorer la ligne. Jusqu'à 5 articles par commande.</small></p>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Créer la commande</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
