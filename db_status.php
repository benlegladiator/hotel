<?php
// ============================================================
// db_status.php — Diagnostic & Initialisation Automatique BDD
// ============================================================
require_once __DIR__ . '/config/app.php';

$action = $_POST['action'] ?? '';
$message = '';
$message_type = 'info';

// Initialisation de la connexion PDO avec capture propre d'erreur
$pdo = null;
$pdo_error = null;

try {
    require_once __DIR__ . '/config/db.php';
} catch (Throwable $e) {
    $pdo_error = $e->getMessage();
}

// Action d'initialisation manuelle ou réparation
if ($action === 'init_hotel_db' && $pdo) {
    try {
        $schema_file = __DIR__ . '/sql/hotel.sql';
        $demo_file   = __DIR__ . '/sql/demo_data.sql';
        
        $count_tables = 0;
        if (file_exists($schema_file)) {
            $raw_sql = file_get_contents($schema_file);
            $lines = explode("\n", $raw_sql);
            $clean_lines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) continue;
                $clean_lines[] = $line;
            }
            $clean_sql = implode("\n", $clean_lines);
            $queries = array_filter(array_map('trim', explode(';', $clean_sql)));
            foreach ($queries as $q) {
                if (!empty($q)) {
                    $pdo->exec($q);
                    $count_tables++;
                }
            }
        }

        if (file_exists($demo_file)) {
            $raw_demo = file_get_contents($demo_file);
            $demo_lines = explode("\n", $raw_demo);
            $clean_demo_lines = [];
            foreach ($demo_lines as $dl) {
                $trimmed = trim($dl);
                if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) continue;
                $clean_demo_lines[] = $dl;
            }
            $clean_demo_sql = implode("\n", $clean_demo_lines);
            $demo_queries = array_filter(array_map('trim', explode(';', $clean_demo_sql)));
            foreach ($demo_queries as $dq) {
                if (!empty($dq)) {
                    try {
                        $pdo->exec($dq);
                    } catch (Exception $ex) {}
                }
            }
        }

        $message = "Base de données initialisée avec succès ! Les tables et les comptes de départ sont prêts.";
        $message_type = 'success';
    } catch (Throwable $e) {
        $message = "Erreur lors de l'initialisation : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Récupération de l'état des tables
$tables = [];
$users_count = 0;
$rooms_count = 0;
$dbs_list = [];

if ($pdo) {
    try {
        $dbs_list = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}

    try {
        $tbl_res = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tbl_res as $tbl) {
            try {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $tables[$tbl] = $cnt;
            } catch (Throwable $e) {
                $tables[$tbl] = 'Erreur';
            }
        }
        if (isset($tables['utilisateurs'])) {
            $users_count = $tables['utilisateurs'];
        }
        if (isset($tables['chambres'])) {
            $rooms_count = $tables['chambres'];
        }
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic BDD — Alma Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --success: #059669;
            --danger: #e11d48;
            --navy: #1e1b4b;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 30px 20px;
        }
        .container {
            max-width: 820px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 28px;
            margin-bottom: 24px;
        }
        h1 { margin: 0 0 6px 0; font-size: 24px; color: var(--navy); }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #ffe4e6; color: #9f1239; }
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .table-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .btn {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-success { background: var(--success); }
        .btn:hover { opacity: 0.95; }
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    </style>
</head>
<body>

<div class="container">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1>🏨 Diagnostic Base de Données Alma Hotel</h1>
            <p style="margin:4px 0 0 0; color:#64748b; font-size:14px;">Vérification de la connectivité et des tables du système hôtelier</p>
        </div>
        <div>
            <a href="login.php" class="btn" style="background:#fff; color:#4f46e5; border:1px solid #c7d2fe;">
                ← Retour Connexion
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Statut Général -->
    <div class="card">
        <h2 style="font-size:16px; margin:0 0 16px 0; color:#0f172a;">1. Connectivité MySQL</h2>
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <tr>
                <td style="padding:8px 0; color:#64748b; width:180px;">État Connexion :</td>
                <td>
                    <?php if ($pdo): ?>
                        <span class="badge badge-success">✔ Connecté avec succès</span>
                    <?php else: ?>
                        <span class="badge badge-danger">✖ Échec de connexion</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding:8px 0; color:#64748b;">Hôte MySQL :</td>
                <td><strong><?= htmlspecialchars(defined('DB_HOST') ? DB_HOST : 'Non défini') ?></strong></td>
            </tr>
            <tr>
                <td style="padding:8px 0; color:#64748b;">Base Active :</td>
                <td><strong style="color:var(--primary);"><?= htmlspecialchars(defined('DB_NAME') ? DB_NAME : 'Non définie') ?></strong></td>
            </tr>
            <tr>
                <td style="padding:8px 0; color:#64748b;">Utilisateur :</td>
                <td><code><?= htmlspecialchars(defined('DB_USER') ? DB_USER : 'Non défini') ?></code></td>
            </tr>
            <?php if (!empty($dbs_list)): ?>
            <tr>
                <td style="padding:8px 0; color:#64748b;">Bases du Compte :</td>
                <td><?= implode(', ', array_map('htmlspecialchars', $dbs_list)) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($pdo_error): ?>
            <tr>
                <td style="padding:8px 0; color:#e11d48;">Détail Erreur :</td>
                <td style="color:#e11d48;"><code><?= htmlspecialchars($pdo_error) ?></code></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Statut des Tables -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="font-size:16px; margin:0; color:#0f172a;">2. Tables du Système (<?= count($tables) ?> détectée(s))</h2>
                <p style="margin:4px 0 0 0; color:#64748b; font-size:13px;">
                    Comptes utilisateurs : <strong><?= $users_count ?></strong> &bull; Chambres : <strong><?= $rooms_count ?></strong>
                </p>
            </div>
            <form method="POST" action="db_status.php" onsubmit="return confirm('Confirmez-vous l\'initialisation/réparation des tables de l\'hôtel ?');">
                <input type="hidden" name="action" value="init_hotel_db">
                <button type="submit" class="btn btn-success" style="font-size:13.5px; padding:10px 18px;">
                    ⚡ <?= empty($tables) ? 'Créer les Tables Maintenant' : 'Réparer / Réinstaller les Tables' ?>
                </button>
            </form>
        </div>

        <?php if (!empty($tables)): ?>
            <div class="table-grid">
                <?php foreach ($tables as $tname => $tcnt): ?>
                    <div class="table-item">
                        <span><strong><?= htmlspecialchars($tname) ?></strong></span>
                        <span style="color:#64748b;"><?= htmlspecialchars((string)$tcnt) ?> lig.</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:16px; margin-top:16px; color:#92400e; font-size:13.5px;">
                ⚠️ <strong>Aucune table trouvée dans cette base de données.</strong><br>
                Cliquez sur le bouton vert <strong>« Créer les Tables Maintenant »</strong> ci-dessus pour déployer automatiquement le schéma et créer le compte administrateur <code>admin</code> / <code>admin123</code>.
            </div>
        <?php endif; ?>
    </div>

    <!-- Identifiants de Connexion par défaut -->
    <div class="card">
        <h2 style="font-size:16px; margin:0 0 12px 0; color:#0f172a;">3. Accès & Identifiants Disponibles</h2>
        <div style="background:#f1f5f9; border-radius:10px; padding:14px; font-size:13.5px;">
            <div style="margin-bottom:8px;">
                👑 <strong>Direction (Admin) :</strong> Identifiant : <code>admin</code> | Mot de passe : <code>admin123</code>
            </div>
            <div>
                🛎️ <strong>Réception :</strong> Identifiant : <code>recep</code> | Mot de passe : <code>admin123</code>
            </div>
        </div>
        <div style="margin-top:16px;">
            <a href="login.php" class="btn">
                👉 Aller à la Page de Connexion
            </a>
        </div>
    </div>

</div>

</body>
</html>
