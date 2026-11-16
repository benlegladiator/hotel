<?php
// ============================================================
// config/db.php — Connexion PDO à la base de données
// ============================================================

require_once __DIR__ . '/app.php';

// Définir le fuseau horaire du Cameroun (UTC+1)
date_default_timezone_set('Africa/Douala');

// Détection automatique de l'environnement (Local XAMPP vs Hébergement InfinityFree)
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host_only = explode(':', $http_host)[0];

$is_local = ($host_only === 'localhost')
            || (strpos($host_only, '127.') === 0)
            || ($host_only === '::1')
            || (strpos($host_only, '192.168.') === 0)
            || (strpos($host_only, '10.') === 0)
            || (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host_only) === 1)
            || (php_sapi_name() === 'cli');

if ($is_local) {
    // ── 1. Paramètres Locaux (XAMPP sur votre PC) ────────────
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'minabou_hotel');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]
        );
    } catch (PDOException $e) {
        $pdo = null;
        throw $e;
    }
} else {
    // ── 2. Paramètres InfinityFree (En Ligne) ─────────────────
    $db_host = 'sql113.infinityfree.com';
    $db_user = 'if0_39882531';
    $db_pass = 'cmTJtR2Z2yq8MO';

    // Recherche de la base de données hôtelière parmi les bases créées
    $preferred_dbs = [
        'if0_39882531_hotel',
        'if0_39882531_alma',
        'if0_39882531_almahotel',
        'if0_39882531_mandel'
    ];

    $pdo = null;
    $connected_db = null;

    foreach ($preferred_dbs as $candidate_db) {
        try {
            $test_pdo = new PDO(
                "mysql:host={$db_host};dbname={$candidate_db};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 4,
                ]
            );
            $pdo = $test_pdo;
            $connected_db = $candidate_db;
            break;
        } catch (PDOException $ex) {
            // Tentative sur la base suivante
        }
    }

    // Si aucune connexion spécifique n'a marché, tenter la connexion générique
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host={$db_host};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 4,
            ]
        );
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($dbs as $db_item) {
            if (strpos($db_item, 'hotel') !== false || strpos($db_item, 'alma') !== false) {
                $connected_db = $db_item;
                $pdo->exec("USE `$connected_db`");
                break;
            }
        }
        if (!$connected_db && !empty($dbs)) {
            $connected_db = $dbs[0];
            $pdo->exec("USE `$connected_db`");
        }
    }

    define('DB_HOST', $db_host);
    define('DB_USER', $db_user);
    define('DB_PASS', $db_pass);
    define('DB_NAME', $connected_db ?? 'if0_39882531_mandel');
}

try {
    // Synchroniser l'heure MySQL avec le fuseau horaire UTC+1
    try {
        $pdo->exec("SET time_zone = '+01:00'");
    } catch (Exception $e) {}

    // Vérifier si la table utilisateurs existe, sinon auto-installer le schéma complet
    $check_table = $pdo->query("SHOW TABLES LIKE 'utilisateurs'")->fetch();
    if (!$check_table) {
        $schema_file = dirname(__DIR__) . '/sql/hotel.sql';
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
                    try {
                        $pdo->exec($q);
                    } catch (Exception $ex) {}
                }
            }
        }

        // Insérer les données initiales (chambres, admin, réceptionniste)
        $demo_file = dirname(__DIR__) . '/sql/demo_data.sql';
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
    }

    // Auto-migration silencieuse des tables indispensables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sorties_caisse` (
          `id`                  INT AUTO_INCREMENT PRIMARY KEY,
          `libelle`             VARCHAR(255) NOT NULL,
          `categorie`           VARCHAR(100) NOT NULL DEFAULT 'fourniture',
          `montant`             DECIMAL(12,2) NOT NULL,
          `date_sortie`         DATE NOT NULL,
          `mode_paiement`       VARCHAR(50) NOT NULL DEFAULT 'especes',
          `reference_recu`      VARCHAR(100) DEFAULT NULL,
          `notes`               TEXT,
          `id_utilisateur`      INT NOT NULL,
          `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Vérifier si id_utilisateur existe dans taches_menage
    try {
        $pdo->exec("ALTER TABLE `taches_menage` ADD COLUMN `id_utilisateur` INT DEFAULT NULL AFTER `id_agent`");
    } catch (Exception $e) {
        // Déjà existant
    }

    // Auto-migration du rôle utilisateur (Support de Chef Réceptionniste)
    try {
        $pdo->exec("ALTER TABLE `utilisateurs` MODIFY COLUMN `role` ENUM('admin', 'chef_receptionniste', 'receptionniste') NOT NULL DEFAULT 'receptionniste'");
    } catch (Exception $e) {
        // Déjà à jour
    }

    // Auto-migration clients (date_naissance, lieu_naissance)
    try {
        $pdo->exec("ALTER TABLE `clients` ADD COLUMN `date_naissance` DATE DEFAULT NULL AFTER `prenom`");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `clients` ADD COLUMN `lieu_naissance` VARCHAR(150) DEFAULT NULL AFTER `date_naissance`");
    } catch (Exception $e) {}

    // Auto-migration types_chambres (prix_sieste)
    try {
        $pdo->exec("ALTER TABLE `types_chambres` ADD COLUMN `prix_sieste` DECIMAL(12,2) NOT NULL DEFAULT 10000.00 AFTER `prix_nuit`");
    } catch (Exception $e) {}

    // Mettre à jour le nom de l'hôtel par défaut si nécessaire
    try {
        $pdo->exec("UPDATE `parametres_systeme` SET `valeur` = 'Alma Hotel' WHERE `cle` = 'nom_hotel' AND `valeur` LIKE '%Minabou%'");
        $pdo->exec("UPDATE `parametres_systeme` SET `valeur` = 'light' WHERE `cle` = 'theme_defaut'");
        $pdo->exec("UPDATE `utilisateurs` SET `theme` = 'light' WHERE `theme` = 'dark'");
    } catch (Exception $e) {
        // Ignorer si table non encore créée
    }

} catch (PDOException $e) {
    $err_msg = htmlspecialchars($e->getMessage());
    $curr_host = htmlspecialchars(DB_HOST);
    $curr_db   = htmlspecialchars(DB_NAME);
    $curr_user = htmlspecialchars(DB_USER);

    die('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
    <title>Erreur de Connexion Base de Données — Alma Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;
        min-height:100vh;background:#0a1628;margin:0;padding:20px;}
        .box{background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.3);border-radius:16px;
        padding:36px;max-width:560px;text-align:center;color:#fff;box-shadow:0 20px 50px rgba(0,0,0,0.5);}
        h2{color:#c9a84c;margin-bottom:12px;font-size:22px;}
        p{color:rgba(255,255,255,.75);font-size:14px;line-height:1.6;}
        .err-details{background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;padding:12px;border-radius:8px;font-size:13px;text-align:left;margin:16px 0;word-break:break-all;}
        .help-list{text-align:left;font-size:13px;color:rgba(255,255,255,.8);background:rgba(255,255,255,0.04);padding:14px 20px;border-radius:8px;margin-top:16px;}
        .help-list li{margin-bottom:8px;}
        code{background:rgba(255,255,255,.12);color:#e2cf9a;padding:2px 6px;border-radius:4px;font-size:12px;}
    </style></head><body>
    <div class="box">
        <h2>⚠️ Erreur de Connexion BDD</h2>
        <div class="err-details"><strong>Détail de l\'erreur :</strong><br>' . $err_msg . '</div>
        <p>Tentative de connexion vers : <code>' . $curr_host . '</code> | Base : <code>' . $curr_db . '</code> | User : <code>' . $curr_user . '</code></p>
        
        <div class="help-list">
            <strong>💡 Comment résoudre ce problème :</strong>
            <ul style="padding-left:18px; margin-top:8px;">
                <li><strong>En Ligne (InfinityFree) :</strong> L\'hôte <code>localhost</code> ne fonctionne pas sur InfinityFree. Ouvrez votre panneau de contrôle InfinityFree ➜ <em>MySQL Databases</em> ➜ copiez le <strong>MySQL Hostname</strong> exact (ex: <code>sql300.infinityfree.com</code>) et inscrivez votre mot de passe vPanel dans <code>config/db.php</code>.</li>
                <li><strong>En Local (PC / XAMPP) :</strong> Vérifiez que le module <strong>MySQL</strong> est bien démarré en vert dans le panneau XAMPP.</li>
                <li>Vérifiez que la base de données <code>' . $curr_db . '</code> a bien été créée et que le fichier <code>sql/hotel.sql</code> a été importé dans phpMyAdmin.</li>
            </ul>
        </div>
    </div></body></html>');
}
