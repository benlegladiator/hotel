        </main><!-- /main-content -->
    </div><!-- /app-main -->
</div><!-- /app-layout -->

<!-- Modal Centre Hors-Ligne & Caisse Alma Hotel -->
<div id="hotelOfflineModal" class="no-print" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(4px); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:#ffffff; width:92%; max-width:760px; max-height:88vh; border-radius:18px; box-shadow:0 24px 60px rgba(0,0,0,0.3); display:flex; flex-direction:column; overflow:hidden; font-family:var(--font-sans, inherit); border:1px solid #e2e8f0;">
        
        <!-- Header Modal -->
        <div style="padding:18px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:24px;">🏨</span>
                <div>
                    <h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">Centre Hors-Ligne & Caisse Alma Hotel</h3>
                    <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">Suivi des encaissements physiques et synchronisation des arrivées locales</p>
                </div>
            </div>
            <button onclick="closeHotelOfflineModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">✕</button>
        </div>

        <!-- Body Modal -->
        <div style="padding:24px; overflow-y:auto; flex:1;">
            
            <!-- Barre Mode Forcé -->
            <div style="background:#f1f5f9; border-radius:12px; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <strong style="font-size:13px; color:#0f172a;">Forcer le Mode Hors-Ligne</strong>
                    <div style="font-size:11px; color:#64748b;">Activez pour enregistrer vos arrivées en local même si la connexion est lente ou instable.</div>
                </div>
                <label style="position:relative; display:inline-block; width:44px; height:24px; cursor:pointer;">
                    <input type="checkbox" id="hotelForceOfflineSwitch" onchange="toggleHotelForceOffline(this.checked)" style="opacity:0; width:0; height:0;">
                    <span style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#cbd5e1; transition:.3s; border-radius:24px;"></span>
                </label>
            </div>

            <!-- KPIs -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:22px;">
                <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:14px; padding:14px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#047857; text-transform:uppercase;">💵 Espèces Hors-Ligne</div>
                    <div id="hotelOfflineCashTotal" style="font-size:20px; font-weight:800; color:#065f46; margin-top:4px;">0 FCFA</div>
                    <div style="font-size:10px; color:#047857;">Encaissé physiquement au desk</div>
                </div>
                <div style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:14px; padding:14px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#4338ca; text-transform:uppercase;">💳 Total Encaissé</div>
                    <div id="hotelOfflineGlobalTotal" style="font-size:20px; font-weight:800; color:#3730a3; margin-top:4px;">0 FCFA</div>
                    <div style="font-size:10px; color:#4338ca;">Tous modes confondus</div>
                </div>
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:14px; padding:14px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#b45309; text-transform:uppercase;">⏳ En attente de synchro</div>
                    <div id="hotelOfflineCount" style="font-size:20px; font-weight:800; color:#92400e; margin-top:4px;">0</div>
                    <div style="font-size:10px; color:#b45309;">Arrivée(s) locale(s)</div>
                </div>
            </div>

            <!-- Titre & Bouton Synchro -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="margin:0; font-size:14px; font-weight:700; color:#0f172a;">Arrivées enregistrées localement</h4>
                <button type="button" id="hotelManualSyncBtn" onclick="triggerHotelManualSync()" style="background:#1e1b4b; color:#ffffff; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer;">
                    ⚡ Synchroniser Maintenant
                </button>
            </div>

            <!-- Table -->
            <div style="border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:12px; text-align:left;">
                    <thead style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569;">
                        <tr>
                            <th style="padding:10px 12px;">Réf & Heure</th>
                            <th style="padding:10px 12px;">Client</th>
                            <th style="padding:10px 12px;">Chambre</th>
                            <th style="padding:10px 12px; text-align:right;">Encaissé</th>
                            <th style="padding:10px 12px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hotelOfflineTableBody">
                        <!-- Rempli dynamiquement -->
                    </tbody>
                </table>
            </div>

            <div id="hotelOfflineEmptyState" style="text-align:center; padding:30px 10px; color:#64748b; font-size:13px;">
                🎉 Toutes vos arrivées locales sont synchronisées avec le serveur central !
            </div>

        </div>

        <!-- Footer Modal -->
        <div style="padding:12px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:11px; color:#64748b;">🔒 Données stockées localement en toute sécurité (IndexedDB).</span>
            <button onclick="closeHotelOfflineModal()" class="btn btn-sm btn-outline" style="border-radius:8px;">Fermer</button>
        </div>

    </div>
</div>

<script>
function openHotelOfflineModal() {
    var m = document.getElementById('hotelOfflineModal');
    if (m) {
        m.style.display = 'flex';
        if (typeof renderHotelOfflineModal === 'function') {
            renderHotelOfflineModal();
        }
    }
}
function closeHotelOfflineModal() {
    var m = document.getElementById('hotelOfflineModal');
    if (m) m.style.display = 'none';
}
</script>

<!-- Barre de Navigation Inférieure Mobile (Expérience Native App Style Orange Money) -->
<?php
$req_uri = $_SERVER['REQUEST_URI'] ?? '';
$is_act_accueil   = (strpos($req_uri, 'dashboard.php') !== false || substr(parse_url($req_uri, PHP_URL_PATH), -1) === '/');
$is_act_arrivee   = (strpos($req_uri, 'checkin') !== false && strpos($req_uri, 'checkout') === false);
$is_act_depart    = (strpos($req_uri, 'checkout') !== false);
$is_act_nettoyage = (strpos($req_uri, '/menage/') !== false);
$is_act_caisse    = (strpos($req_uri, '/comptabilite/') !== false);
?>
<nav class="mobile-bottom-nav no-print" id="mobileBottomNav">
    <!-- 1. Accueil -->
    <a href="<?= BASE_URL ?>/dashboard.php" class="<?= $is_act_accueil ? 'active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span>Accueil</span>
    </a>

    <!-- 2. Arrivée -->
    <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="<?= $is_act_arrivee ? 'active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        <span>Arrivée</span>
    </a>

    <!-- 3. Départ -->
    <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="<?= $is_act_depart ? 'active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Départ</span>
    </a>

    <!-- 4. Nettoyage -->
    <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="<?= $is_act_nettoyage ? 'active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
        <span>Nettoyage</span>
    </a>

    <!-- 5. Caisse -->
    <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="<?= $is_act_caisse ? 'active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <line x1="2" y1="10" x2="22" y2="10"/>
            <circle cx="7" cy="15" r="1.5"/>
            <circle cx="17" cy="15" r="1.5"/>
        </svg>
        <span>Caisse</span>
    </a>
</nav>

<!-- Bouton Flottant Défiler vers le Haut -->
<button id="scrollToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="no-print" title="Défiler vers le haut" style="display:none; position:fixed; bottom:24px; right:24px; z-index:9990; background:var(--gold-500); color:var(--navy-950); border:none; width:44px; height:44px; border-radius:50%; font-size:20px; font-weight:bold; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.3); transition:all 0.3s ease;">
    ↑
</button>

<script>
window.addEventListener('scroll', function() {
    var btn = document.getElementById('scrollToTopBtn');
    if (btn) {
        btn.style.display = (window.scrollY > 250) ? 'flex' : 'none';
        btn.style.justifyContent = 'center';
        btn.style.alignItems = 'center';
    }
});
</script>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
