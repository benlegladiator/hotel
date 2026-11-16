<?php
// ============================================================
// Fonctions utilitaires globales & Configuration dynamique
// ============================================================

// ---------- Flash messages ----------

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ---------- Paramètres Système (DB) ----------

function get_system_setting(string $key, string $default = ''): string {
    global $pdo;
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres_systeme WHERE cle = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function set_system_setting(string $key, string $valeur): void {
    global $pdo;
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO parametres_systeme (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?");
        $stmt->execute([$key, $valeur, $valeur]);
    } catch (Exception $e) {
        // silent
    }
}

function is_premium_enabled(): bool {
    return (bool)(int)get_system_setting('enable_premium_modules', '0');
}

// ---------- Langue & Thème ----------

function get_user_lang(): string {
    if (isset($_SESSION['user']['langue'])) return $_SESSION['user']['langue'];
    return get_system_setting('langue_defaut', 'fr');
}

function get_user_theme(): string {
    if (isset($_SESSION['user']['theme'])) return $_SESSION['user']['theme'];
    return get_system_setting('theme_defaut', 'light');
}

/** Fonction de traduction simple Fr/En */
function __(string $key): string {
    $lang = get_user_lang();
    if ($lang === 'fr') return $key;

    $dict = [
        'Tableau de bord' => 'Dashboard',
        'Chambres' => 'Rooms',
        'Types de chambres' => 'Room Types',
        'Ménage' => 'Housekeeping',
        'Clients' => 'Guests',
        'Réservations' => 'Reservations',
        'Check-in / Check-out' => 'Check-in / Check-out',
        'Facturation' => 'Invoicing',
        'Factures' => 'Invoices',
        'Comptabilité' => 'Accounting',
        'Rapports' => 'Reports',
        'Recherche' => 'Search',
        'Paramètres' => 'Settings',
        'Déconnexion' => 'Logout',
        'Personnel' => 'Staff',
        'Commandes' => 'Orders',
        'Catalogue' => 'Catalog',
        'Menus cuisine' => 'Kitchen Menus',
        'Salles de fête' => 'Event Halls',
        'Traçabilité Occupation' => 'Occupancy Traceability',
        'Traçabilité Ménage' => 'Housekeeping Logs',
        'Imprimer' => 'Print',
        'Rechercher' => 'Search',
        'Libre' => 'Available',
        'Occupée' => 'Occupied',
        'Réservée' => 'Reserved',
        'Nettoyage' => 'Cleaning',
        'Maintenance' => 'Maintenance',
    ];

    return $dict[$key] ?? $key;
}

// ---------- Helper Période Dynamique (Jour, Hier, Semaine, Mois, 2 Mois, 3 Mois, Année, Custom) ----------

function get_period_dates(string $preset = 'month', string $custom_start = '', string $custom_end = ''): array {
    $today = date('Y-m-d');
    
    switch ($preset) {
        case 'today':
        case 'aujourdhui':
            return [
                'debut'  => $today,
                'fin'    => $today,
                'label'  => "Aujourd'hui (" . date('d/m/Y') . ")",
                'preset' => 'today'
            ];
        case 'yesterday':
        case 'hier':
            $hier = date('Y-m-d', strtotime('-1 day'));
            return [
                'debut'  => $hier,
                'fin'    => $hier,
                'label'  => "Hier (" . date('d/m/Y', strtotime($hier)) . ")",
                'preset' => 'yesterday'
            ];
        case 'week':
        case 'semaine':
            $lundi = date('Y-m-d', strtotime('monday this week'));
            $dimanche = date('Y-m-d', strtotime('sunday this week'));
            return [
                'debut'  => $lundi,
                'fin'    => $dimanche,
                'label'  => "Cette Semaine (du " . date('d/m/Y', strtotime($lundi)) . " au " . date('d/m/Y', strtotime($dimanche)) . ")",
                'preset' => 'week'
            ];
        case 'month':
        case 'mois':
        case '1mois':
            $first = date('Y-m-01');
            $last  = date('Y-m-t');
            return [
                'debut'  => $first,
                'fin'    => $last,
                'label'  => "Ce Mois-ci (" . date('F Y') . ")",
                'preset' => 'month'
            ];
        case '2months':
        case '2mois':
            $first2 = date('Y-m-01', strtotime('-1 month'));
            return [
                'debut'  => $first2,
                'fin'    => $today,
                'label'  => "Les 2 Derniers Mois (du " . date('d/m/Y', strtotime($first2)) . " au " . date('d/m/Y') . ")",
                'preset' => '2months'
            ];
        case '3months':
        case '3mois':
            $first3 = date('Y-m-01', strtotime('-2 month'));
            return [
                'debut'  => $first3,
                'fin'    => $today,
                'label'  => "Les 3 Derniers Mois (du " . date('d/m/Y', strtotime($first3)) . " au " . date('d/m/Y') . ")",
                'preset' => '3months'
            ];
        case 'year':
        case 'annee':
            $first = date('Y-01-01');
            $last  = date('Y-12-31');
            return [
                'debut'  => $first,
                'fin'    => $last,
                'label'  => "Cette Année (" . date('Y') . ")",
                'preset' => 'year'
            ];
        case 'custom':
            $d_start = $custom_start ?: $today;
            $d_end   = $custom_end ?: $today;
            return [
                'debut'  => $d_start,
                'fin'    => $d_end,
                'label'  => "Période personnalisée (du " . date('d/m/Y', strtotime($d_start)) . " au " . date('d/m/Y', strtotime($d_end)) . ")",
                'preset' => 'custom'
            ];
        default:
            return get_period_dates('month');
    }
}

/** Composant HTML de barre de filtrage de période réutilisable */
function render_period_filter_bar(array $period_info, string $form_action = ''): string {
    $preset = $period_info['preset'];
    $debut  = $period_info['debut'];
    $fin    = $period_info['fin'];
    
    ob_start();
    ?>
    <div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
        <div class="card-body p-3">
            <form method="GET" action="<?= h($form_action) ?>" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
                <!-- Raccourcis de sélection rapide -->
                <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Période :</span>
                    <a href="?period=today" class="btn btn-sm <?= $preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                    <a href="?period=week" class="btn btn-sm <?= $preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                    <a href="?period=month" class="btn btn-sm <?= $preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois (1 mois)</a>
                    <a href="?period=2months" class="btn btn-sm <?= $preset==='2months'?'btn-primary':'btn-outline' ?>">2 Mois</a>
                    <a href="?period=3months" class="btn btn-sm <?= $preset==='3months'?'btn-primary':'btn-outline' ?>">3 Mois</a>
                </div>

                <!-- Dates sur mesure -->
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="period" value="custom">
                    <span style="font-size:12px; color:var(--text-secondary);">Du :</span>
                    <input type="date" name="date_debut" class="form-control" style="width:145px; padding:4px 8px; font-size:13px;" value="<?= h($debut) ?>">
                    <span style="font-size:12px; color:var(--text-secondary);">Au :</span>
                    <input type="date" name="date_fin" class="form-control" style="width:145px; padding:4px 8px; font-size:13px;" value="<?= h($fin) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary">Filtrer Dates</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------- Formatage ----------

function format_money(float $amount): string {
    $devise = get_system_setting('devise', 'FCFA');
    return number_format($amount, 0, ',', ' ') . ' ' . $devise;
}

function format_prix(float $amount): string {
    return format_money($amount);
}

function format_date(string $date): string {
    if (!$date || $date === '0000-00-00') return '—';
    return date('d/m/Y', strtotime($date));
}

function format_datetime(string $dt): string {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
    return date('d/m/Y H:i', strtotime($dt));
}

function nb_nuits(string $arrivee, string $depart): int {
    $d1 = new DateTime($arrivee);
    $d2 = new DateTime($depart);
    return max(1, (int)$d1->diff($d2)->days);
}

// ---------- Couleur d'Accentuation du Thème ----------

function get_user_accent(): string {
    if (isset($_SESSION['user']['accent'])) return $_SESSION['user']['accent'];
    return get_system_setting('accent_defaut', 'gold');
}

// ---------- Sécurité ----------

/** Échappe HTML (accepte null en toute sécurité) */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/** Redirection avec message flash optionnel */
function redirect(string $url, string $type = '', string $msg = ''): void {
    if ($type && $msg) set_flash($type, $msg);
    header('Location: ' . $url);
    exit;
}

// ---------- Badges de statut ----------

function status_badge(string $statut): string {
    $map = [
        'libre'        => [__('Libre'),        'badge-libre'],
        'occupee'      => [__('Occupée'),      'badge-occupee'],
        'reservee'     => [__('Réservée'),     'badge-reservee'],
        'nettoyage'    => [__('Nettoyage'),    'badge-nettoyage'],
        'maintenance'  => [__('Maintenance'),  'badge-maintenance'],
        'confirmee'    => ['Confirmée',    'badge-libre'],
        'annulee'      => ['Annulée',      'badge-occupee'],
        'en_cours'     => ['En cours',     'badge-reservee'],
        'terminee'     => ['Terminée',     'badge-libre'],
        'inspectee'    => ['Inspectée',    'badge-libre'],
        'impeccable'   => ['Impeccable',   'badge-libre'],
        'satisfaisant' => ['Satisfaisant', 'badge-reservee'],
        'a_revoir'     => ['À revoir',     'badge-occupee'],
        'impayee'      => ['Impayée',      'badge-occupee'],
        'partielle'    => ['Partielle',    'badge-reservee'],
        'payee'        => ['Payée',        'badge-libre'],
        'assignee'     => ['Assignée',     'badge-nettoyage'],
        'servie'       => ['Servie',       'badge-libre'],
    ];
    $info = $map[$statut] ?? [ucfirst($statut), 'badge-maintenance'];
    return '<span class="badge ' . $info[1] . '">' . $info[0] . '</span>';
}

// ---------- Pagination ----------

function paginate(int $total, int $per_page, int $page): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page        = max(1, min($page, $total_pages));
    return [
        'total'       => $total,
        'per_page'    => $per_page,
        'current'     => $page,
        'total_pages' => $total_pages,
        'offset'      => ($page - 1) * $per_page,
    ];
}

// ---------- POST helpers ----------

function post(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $default);
}

function post_int(string $key, int $default = 0): int {
    return (int)($_POST[$key] ?? $default);
}

function post_float(string $key, float $default = 0.0): float {
    return (float)str_replace(',', '.', $_POST[$key] ?? $default);
}

// ---------- Notifications WhatsApp Boss ----------

function get_boss_whatsapp_number(): string {
    $num = get_system_setting('boss_whatsapp_number', '237691670626');
    $num = preg_replace('/[^0-9]/', '', $num);
    if (empty($num)) $num = '237691670626';
    return $num;
}

function get_boss_whatsapp_link(string $message): string {
    $num = get_boss_whatsapp_number();
    return 'https://wa.me/' . $num . '?text=' . urlencode($message);
}

function notify_boss_whatsapp(string $titre, string $details, float $montant = 0): void {
    $hotel_nom = get_system_setting('nom_hotel', 'Alma Hotel');
    $msg  = "🏨 *" . strtoupper($hotel_nom) . " — " . strtoupper($titre) . "*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= $details . "\n";
    if ($montant > 0) {
        $msg .= "💵 *Montant :* " . format_prix($montant) . "\n";
    }
    $msg .= "📅 *Heure :* " . date('d/m/Y H:i') . "\n";
    $msg .= "👤 *Opérateur :* " . ($_SESSION['prenom'] ?? 'Desk') . " " . ($_SESSION['nom'] ?? '') . "\n";

    // Si une API passerelle est configurée (UltraMsg, Green API, etc.)
    $api_url   = get_system_setting('boss_whatsapp_api_url', '');
    $api_token = get_system_setting('boss_whatsapp_api_token', '');
    $boss_num  = get_boss_whatsapp_number();

    if (!empty($api_url) && !empty($boss_num)) {
        try {
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'token' => $api_token,
                'to'    => $boss_num,
                'body'  => $msg,
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) {}
    }
}
