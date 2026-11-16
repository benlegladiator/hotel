<?php
// ============================================================
// login.php — Page de Connexion Nouvelle Génération (Alma Hotel)
// Design Moderne, Haut de Gamme & Entièrement Sécurisé (Sans Démo)
// ============================================================

require_once 'config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';

// Redirection si déjà connecté
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';

// Traitement direct de la connexion (évite tout problème de redirection 404 entre dossiers)
$db_error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $password    = $_POST['mot_de_passe'] ?? '';

    if (!$identifiant || !$password) {
        $error = 'invalid';
    } else {
        try {
            if (!$pdo) {
                throw new Exception("Connexion PDO non disponible.");
            }
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE identifiant = ? LIMIT 1");
            $stmt->execute([$identifiant]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['mot_de_passe'])) {
                $error = 'invalid';
            } elseif (!$user['actif']) {
                $error = 'inactive';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nom']     = $user['nom'];
                $_SESSION['prenom']  = $user['prenom'];
                $_SESSION['role']    = $user['role'];

                // Cookie persistant automatique 90 jours UNIQUEMENT SUR MOBILE (PC partagé reste sans persistance)
                $is_mobile_client = (bool) preg_match('/(android|iphone|ipad|ipod|mobile|phone|blackberry|opera mini|windows phone)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
                if ($is_mobile_client) {
                    $remember_token = hash_hmac('sha256', $user['id'] . $user['mot_de_passe'], 'ALMA_HOTEL_SECRET_2026');
                    setcookie('hotel_remember', $user['id'] . ':' . $remember_token, [
                        'expires'  => time() + (86400 * 90),
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                    ]);
                }

                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'db_error';
            $db_error_message = $e->getMessage();
        }
    }
}

$hotel_nom = get_system_setting('nom_hotel', 'Alma Hotel');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Accès Sécurisé — <?= h($hotel_nom) ?></title>
    <meta name="description" content="Espace d'authentification pour <?= h($hotel_nom) ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json" crossorigin="use-credentials">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/pwa/favicon-32x32.png">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/pwa/favicon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/pwa/apple-touch-icon.png">
    <meta name="theme-color" content="#1e1b4b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script>window.HOTEL_BASE_URL = '<?= BASE_URL ?>';</script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --gold: #d97706;
            --gold-light: #fef3c7;
            
            --slate-950: #090d16;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50:  #f8fafc;
            
            --danger-bg: #fff1f2;
            --danger-border: #fecdd3;
            --danger-text: #e11d48;
            
            --r-sm: 8px;
            --r-md: 14px;
            --r-lg: 20px;
            --r-xl: 28px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #090d16;
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(217, 119, 6, 0.12) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.9) 0px, #090d16 100%);
            color: var(--slate-900);
            position: relative;
            overflow-x: hidden;
        }

        /* Effets de lueur d'ambiance */
        .glow-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 1;
        }
        .glow-1 {
            width: 400px;
            height: 400px;
            top: -100px;
            left: 20%;
            background: rgba(79, 70, 229, 0.2);
        }
        .glow-2 {
            width: 450px;
            height: 450px;
            bottom: -120px;
            right: 15%;
            background: rgba(217, 119, 6, 0.16);
        }

        /* Conteneur principal */
        .login-wrapper {
            width: 100%;
            max-width: 960px;
            min-height: 560px;
            background: #ffffff;
            border-radius: var(--r-xl);
            box-shadow: 
                0 30px 70px -15px rgba(0, 0, 0, 0.7),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            overflow: hidden;
            position: relative;
            z-index: 10;
        }

        /* Côté Gauche : Présentation Prestige de l'Hôtel */
        .brand-panel {
            background: linear-gradient(145deg, #0f172a 0%, #1e1b4b 100%);
            padding: 48px 42px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            color: #ffffff;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.6;
            pointer-events: none;
        }

        .brand-top {
            position: relative;
            z-index: 2;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #fde68a;
            backdrop-filter: blur(10px);
            margin-bottom: 28px;
        }

        .brand-icon-crown {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #d97706, #b45309);
            border: 1.5px solid rgba(253, 230, 138, 0.4);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 10px 25px rgba(217, 119, 6, 0.35);
            margin-bottom: 20px;
        }

        .brand-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 12px;
        }

        .brand-desc {
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.6;
            max-width: 340px;
        }

        .brand-features {
            margin: 32px 0;
            display: flex;
            flex-direction: column;
            gap: 14px;
            position: relative;
            z-index: 2;
        }

        .brand-feat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            color: #e2e8f0;
            font-weight: 500;
        }

        .brand-feat-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .brand-footer {
            position: relative;
            z-index: 2;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #94a3b8;
        }

        /* Côté Droit : Formulaire d'authentification */
        .form-panel {
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.02em;
        }

        .form-subtitle {
            font-size: 13.5px;
            color: var(--slate-600);
            margin-top: 4px;
        }

        /* Alerte d'erreur */
        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            padding: 13px 16px;
            border-radius: var(--r-md);
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Champs du formulaire */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--slate-800);
            margin-bottom: 8px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .form-input {
            width: 100%;
            height: 52px;
            padding: 0 46px 0 48px;
            font-size: 15px;
            font-weight: 500;
            color: var(--slate-900);
            background: var(--slate-50);
            border: 1.5px solid var(--slate-200);
            border-radius: var(--r-md);
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        .form-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .btn-toggle-password {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            border-radius: var(--r-sm);
            transition: color 0.15s ease;
        }

        .btn-toggle-password:hover {
            color: var(--slate-900);
        }

        .btn-login {
            width: 100%;
            height: 52px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: var(--r-md);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.3);
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(79, 70, 229, 0.38);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .security-badge {
            margin-top: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 12px;
            color: var(--slate-400);
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 860px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 460px;
            }
            .brand-panel {
                display: none;
            }
            .form-panel {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>

<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<div class="login-wrapper">
    
    <!-- ── Côté Gauche : Vitrine & Prestige ── -->
    <div class="brand-panel">
        <div class="brand-top">
            <div class="brand-badge">
                <span>✨</span> SYSTÈME DE GESTION HOTELIÈRE
            </div>
            
            <div class="brand-icon-crown">👑</div>
            <h1 class="brand-title"><?= h($hotel_nom) ?></h1>
            <p class="brand-desc">
                Plateforme intégrée de supervision, gestion des chambres, réservations et traçabilité financière de caisse.
            </p>

            <div class="brand-features">
                <div class="brand-feat-item">
                    <div class="brand-feat-icon">🔐</div>
                    <span>Accès sécurisé & authentification chiffrée</span>
                </div>
                <div class="brand-feat-item">
                    <div class="brand-feat-icon">⚡</div>
                    <span>Traçabilité en temps réel des flux de caisse</span>
                </div>
                <div class="brand-feat-item">
                    <div class="brand-feat-icon">🏨</div>
                    <span>Gestion instantanée des arrivées & départs</span>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            <span>&copy; <?= date('Y') ?> <?= h($hotel_nom) ?></span>
            <span>Version 2.0 Pro</span>
        </div>
    </div>

    <!-- ── Côté Droit : Connexion Sécurisée ── -->
    <div class="form-panel">
        <div class="form-header">
            <h2 class="form-title">Connexion à votre espace</h2>
            <p class="form-subtitle">Veuillez saisir vos identifiants autorisés</p>
        </div>

        <!-- ── Alerte Erreur ── -->
        <?php if ($error === 'db_error'): ?>
            <div class="alert-error" role="alert" style="background:#fff1f2; border-color:#fecdd3; color:#9f1239;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <strong>Erreur Base de Données :</strong> <?= h($db_error_message ?: 'Impossible de communiquer avec la table utilisateurs.') ?><br>
                    <a href="db_status.php" style="color:#4f46e5; font-weight:700; text-decoration:underline; font-size:12px; margin-top:4px; display:inline-block;">
                        👉 Ouvrir l'outil de diagnostic & réparation de la base
                    </a>
                </div>
            </div>
        <?php elseif ($error === 'invalid'): ?>
            <div class="alert-error" role="alert">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>Identifiant ou mot de passe incorrect.</span>
            </div>
        <?php elseif ($error === 'inactive'): ?>
            <div class="alert-error" role="alert">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                </svg>
                <span>Ce compte est temporairement désactivé.</span>
            </div>
        <?php endif; ?>

        <!-- ── Formulaire ── -->
        <form method="POST" action="login.php" id="loginForm">
            
            <div class="form-group">
                <label class="form-label" for="identifiant">Identifiant</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <input 
                        type="text" 
                        id="identifiant" 
                        name="identifiant" 
                        class="form-input" 
                        placeholder="Votre nom d'utilisateur" 
                        value="<?= h($_POST['identifiant'] ?? '') ?>" 
                        required 
                        autofocus 
                        autocomplete="username"
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="mot_de_passe">Mot de passe</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input 
                        type="password" 
                        id="mot_de_passe" 
                        name="mot_de_passe" 
                        class="form-input" 
                        placeholder="••••••••••••" 
                        required 
                        autocomplete="current-password"
                    >
                    <button type="button" class="btn-toggle-password" id="togglePasswordBtn" title="Afficher / Masquer le mot de passe">
                        <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btnLogin">
                <span>Se connecter</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>

        </form>

        <div class="security-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>Connexion chiffrée SSL &bull; Session protégée</span>
        </div>

    </div>

</div>

<script>
// Enregistrement silencieux du Service Worker en arrière-plan
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js').catch(function() {});
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const passInput = document.getElementById('mot_de_passe');
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const eyeIcon = document.getElementById('eyeIcon');

    toggleBtn.addEventListener('click', function() {
        const isPassword = passInput.type === 'password';
        passInput.type = isPassword ? 'text' : 'password';
        
        if (isPassword) {
            eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    });

    // Effet de chargement sur soumission
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('btnLogin');
    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.8';
        submitBtn.innerHTML = '<span>Connexion en cours...</span>';
    });
});
</script>

</body>
</html>
