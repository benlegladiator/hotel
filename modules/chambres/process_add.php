<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/chambres/list.php');
}

$numero          = trim($_POST['numero'] ?? '');
$id_type_chambre = $_POST['id_type_chambre'] ?? '';
$etage           = trim($_POST['etage'] ?? '1');
$statut          = $_POST['statut'] ?? 'libre';
$description     = trim($_POST['description'] ?? '');
$photo_name      = trim($_POST['photo_name'] ?? 'default.svg');

// Gestion du téléversement d'image
if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['photo_file']['tmp_name'];
    $filename = 'chambre_' . preg_replace('/[^a-zA-Z0-9]/', '_', $numero) . '_' . time() . '.jpg';
    $target   = BASE_PATH . '/assets/img/chambres/' . $filename;
    
    if (!is_dir(BASE_PATH . '/assets/img/chambres/')) {
        mkdir(BASE_PATH . '/assets/img/chambres/', 0777, true);
    }
    
    if (move_uploaded_file($tmp_name, $target)) {
        $photo_name = $filename;
    }
}

if (empty($numero) || empty($id_type_chambre)) {
    redirect(BASE_URL . '/modules/chambres/add.php', 'danger', 'Veuillez remplir les champs obligatoires.');
}

// Vérifier si le numéro existe déjà
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chambres WHERE numero = ?");
$stmt->execute([$numero]);
if ($stmt->fetchColumn() > 0) {
    redirect(BASE_URL . '/modules/chambres/add.php', 'danger', 'Ce numéro de chambre existe déjà.');
}

try {
    $stmt = $pdo->prepare("INSERT INTO chambres (numero, id_type_chambre, etage, statut, description, photo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$numero, $id_type_chambre, $etage, $statut, $description, $photo_name]);
    redirect(BASE_URL . '/modules/chambres/list.php', 'success', 'Chambre N° ' . $numero . ' ajoutée avec succès avec son visuel.');
} catch (PDOException $e) {
    redirect(BASE_URL . '/modules/chambres/add.php', 'danger', 'Erreur lors de l\'ajout : ' . $e->getMessage());
}
