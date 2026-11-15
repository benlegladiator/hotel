<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

// Toutes les chambres
$stmt = $pdo->query("SELECT id, numero, statut FROM chambres ORDER BY numero");
$chambres = $stmt->fetchAll();

// Personnel de ménage
$stmt = $pdo->query("SELECT id, nom, prenom FROM personnel WHERE role = 'menage' AND actif = 1 ORDER BY nom");
$agents = $stmt->fetchAll();

$page_title = 'Assigner une Tâche de Ménage';
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Assigner Tâches de Ménage</h1>
        <p class="text-secondary" style="margin-top:4px;">Planification ponctuelle ou récurrente (quotidienne sur 1 semaine, 1 mois ou plage libre)</p>
    </div>
    <div class="page-header-actions">
        <a href="tracabilite.php" class="btn btn-outline">📊 Traçabilité Ménage</a>
        <a href="list.php" class="btn btn-secondary">Retour aux tâches</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_assign.php" method="POST">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="id_chambre">Chambre à nettoyer *</label>
                    <select id="id_chambre" name="id_chambre" class="form-select" required>
                        <option value="">Sélectionner une chambre...</option>
                        <?php foreach ($chambres as $chambre): ?>
                            <option value="<?= $chambre['id'] ?>">Chambre N° <?= h($chambre['numero']) ?> (Statut : <?= h($chambre['statut']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="id_agent">Agent de ménage affecté</label>
                    <select id="id_agent" name="id_agent" class="form-select">
                        <option value="">Non assigné (à affecter ultérieurement)</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id'] ?>"><?= h($agent['prenom'] . ' ' . $agent['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="type_tache">Type d'intervention *</label>
                    <select id="type_tache" name="type_tache" class="form-select" required>
                        <option value="periodique">Ménage Quotidien / Entretien courant</option>
                        <option value="entree">Nettoyage d'Entrée / Préparation</option>
                        <option value="sortie">Nettoyage de Sortie / Désinfection</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="priorite">Priorité d'Intervention *</label>
                    <select id="priorite" name="priorite" class="form-select" required>
                        <option value="normale" selected>Normale</option>
                        <option value="haute">Haute (Client bientôt là)</option>
                        <option value="urgente">Urgente (Client en attente à la réception)</option>
                    </select>
                </div>
            </div>

            <!-- Récurrence & Durée de la planification -->
            <div class="form-group mb-4" style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); border:1px solid var(--border-mid);">
                <label class="form-label" style="font-weight:700; color:var(--gold-500);">📅 Récurrence & Période de Planification</label>
                <div class="form-grid-2" style="margin-top:10px;">
                    <div>
                        <label class="form-label">Mode de planification</label>
                        <select name="mode_recurrence" id="mode_recurrence" class="form-select" onchange="toggleRecurrence(this.value)">
                            <option value="une_fois" selected>Une seule fois (Pour la date choisie)</option>
                            <option value="semaine">Quotidien pendant 1 Semaine (7 jours)</option>
                            <option value="mois">Quotidien pendant 1 Mois (30 jours)</option>
                            <option value="periode">Période sur mesure (Dates libres)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date de début *</label>
                        <input type="date" id="date_prevue" name="date_prevue" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div id="div_date_fin" style="display:none; margin-top:12px;">
                    <label class="form-label">Date de fin (Pour période sur mesure)</label>
                    <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="notes">Notes & Instructions spécifiques pour l'agent</label>
                <textarea id="notes" name="notes" class="form-textarea" rows="2" placeholder="Ex: Changer la literie, remettre 2 serviettes neuves, réapprovisionner l'eau minérale..."></textarea>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Enregistrer & Générer les Tâches</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRecurrence(val) {
    document.getElementById('div_date_fin').style.display = (val === 'periode') ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
