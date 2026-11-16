// ============================================================
// Minabou Hotel — Main JavaScript
// ============================================================

// ── Sidebar & Mobile Navigation ───────────────────────────
const sidebarToggle   = document.getElementById('sidebarToggle');
const sidebar         = document.getElementById('sidebar');
const appMain         = document.getElementById('appMain');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');
const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

function toggleSidebar() {
    if (window.innerWidth <= 900) {
        if (!sidebar) return;
        const isOpen = sidebar.classList.toggle('open');
        if (sidebarBackdrop) {
            if (isOpen) {
                sidebarBackdrop.classList.add('active');
                document.body.style.overflow = 'hidden'; // Empêcher le scroll en arrière-plan
            } else {
                sidebarBackdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    } else {
        if (sidebar) sidebar.classList.toggle('collapsed');
        if (appMain) appMain.classList.toggle('expanded');
    }
}

function closeSidebarMobile() {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarBackdrop) sidebarBackdrop.classList.remove('active');
    document.body.style.overflow = '';
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}
if (sidebarCloseBtn) {
    sidebarCloseBtn.addEventListener('click', closeSidebarMobile);
}
if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebarMobile);
}

// Fermer le tiroir mobile lorsqu'on clique sur un lien de navigation
document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 900) {
            closeSidebarMobile();
        }
    });
});

// ── Auto-dismiss flash après 5 secondes ─────────────────────
const flashMsg = document.getElementById('flashMsg');
if (flashMsg) {
    setTimeout(() => {
        flashMsg.style.transition = 'opacity .4s';
        flashMsg.style.opacity    = '0';
        setTimeout(() => flashMsg.remove(), 400);
    }, 5000);
}

// ── Confirmation avant suppression ──────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
        const msg = this.dataset.confirm || 'Confirmer cette action ?';
        if (!confirm(msg)) e.preventDefault();
    });
});

// ── Filtre de recherche dans les tableaux ───────────────────
const tableSearch = document.getElementById('tableSearch');
if (tableSearch) {
    tableSearch.addEventListener('input', function () {
        const q   = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
        // Affiche un message si aucun résultat
        const noResult = document.getElementById('noResultRow');
        if (noResult) noResult.style.display = [...rows].every(r => r.style.display === 'none') ? '' : 'none';
    });
}

// ── Affichage dynamique du nom du fichier sélectionné ───────
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function () {
        const label = this.nextElementSibling;
        if (label && label.classList.contains('file-label')) {
            label.textContent = this.files[0]?.name || 'Choisir un fichier';
        }
    });
});

// ── Sélection de tous les checkboxes dans un tableau ────────
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('input[name="selected[]"]')
            .forEach(cb => (cb.checked = this.checked));
    });
}

// ============================================================
// ── Web Audio Feedback (Bruitage sonore tactile au clic) ────
// ============================================================
(function() {
    let audioCtx = null;
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) audioCtx = new AudioContext();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // Son de clic doux (Tap subtil et luxueux 35ms)
    window.playHotelTapSound = function() {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(650, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(220, ctx.currentTime + 0.035);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.035);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.035);
        } catch(e) {}
    };

    // Son de carillon de succès / Cloche d'encaissement (Chime)
    window.playHotelSuccessSound = function() {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;
            [523.25, 659.25, 783.99, 1046.50].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, now + i * 0.09);
                gain.gain.setValueAtTime(0.15, now + i * 0.09);
                gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.09 + 0.28);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + i * 0.09);
                osc.stop(now + i * 0.09 + 0.28);
            });
        } catch(e) {}
    };

    // Émettre un tap sonore au clic sur tout élément cliquable
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('button, .btn, .mobile-bottom-nav a, .mandel-bottom-nav a, .hotel-action-card-2, .mandel-action-card-2, .nav-tab, .sidebar-nav a, a.badge');
        if (btn) {
            playHotelTapSound();
        }
    }, true);
})();

// ============================================================
// ── Cloche Sonore des Notifications du Boss ─────────────────
// ============================================================
(function() {
    const bellBtn = document.getElementById('bossBellBtn');
    const bellDropdown = document.getElementById('bossBellDropdown');
    const bellBadge = document.getElementById('bossBellBadge');
    const bellList = document.getElementById('bossBellList');
    if (!bellBtn) return;

    let lastKnownId = localStorage.getItem('alma_last_boss_notif_id') || '';
    let isInitialCheck = (lastKnownId === '');

    window.toggleBossBellDropdown = function() {
        if (!bellDropdown) return;
        const isOpen = bellDropdown.style.display === 'block';
        bellDropdown.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            markAllBossBellRead();
        }
    };

    window.markAllBossBellRead = function() {
        if (bellBadge) bellBadge.style.display = 'none';
        const latestId = bellBtn.getAttribute('data-latest-id');
        if (latestId) {
            localStorage.setItem('alma_last_boss_notif_id', latestId);
        }
    };

    // Fermer le dropdown au clic en dehors
    document.addEventListener('click', function(e) {
        if (bellDropdown && !e.target.closest('#bossBellBtn') && !e.target.closest('#bossBellDropdown')) {
            bellDropdown.style.display = 'none';
        }
    });

    async function checkBossNotifications() {
        try {
            const resp = await fetch((window.BASE_URL || '') + '/modules/comptabilite/api_notifications.php');
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.success || !data.items) return;

            bellBtn.setAttribute('data-latest-id', data.latest_id);

            // Vérifier s'il y a du nouveau
            if (data.latest_id && data.latest_id !== 'none' && data.latest_id !== lastKnownId) {
                if (!isInitialCheck) {
                    // Jouer le carillon sonore pour le Boss !
                    if (typeof window.playHotelSuccessSound === 'function') {
                        window.playHotelSuccessSound();
                    }
                }
                if (bellBadge) {
                    bellBadge.textContent = data.count > 0 ? data.count : '!';
                    bellBadge.style.display = 'inline-block';
                }
                if (isInitialCheck) {
                    lastKnownId = data.latest_id;
                    localStorage.setItem('alma_last_boss_notif_id', lastKnownId);
                    isInitialCheck = false;
                }
            }

            // Mettre à jour la liste dans le dropdown
            if (bellList) {
                if (data.items.length === 0) {
                    bellList.innerHTML = '<div style="text-align:center; padding:15px; color:#94a3b8;">Aucune opération récente aujourd\'hui.</div>';
                } else {
                    let html = '';
                    data.items.forEach(item => {
                        const isEntree = (item.type === 'entree');
                        const icon = isEntree ? '🟢' : '🔴';
                        const color = isEntree ? '#059669' : '#dc2626';
                        const sign = isEntree ? '+' : '-';
                        html += `
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:3.5px solid ${color}; border-radius:10px; padding:8px 10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:${color}; font-size:12px;">${icon} ${sign} ${new Intl.NumberFormat('fr-FR').format(item.montant)} FCFA</strong>
                                    <span style="font-size:10px; color:#94a3b8;">${item.created_at ? item.created_at.substring(11, 16) : ''}</span>
                                </div>
                                <div style="font-size:11.5px; font-weight:700; color:#1e293b; margin-top:2px;">${item.motif}</div>
                                <div style="font-size:10.5px; color:#64748b; margin-top:1px;">Par ${item.operateur} (${item.mode_paiement})</div>
                            </div>
                        `;
                    });
                    bellList.innerHTML = html;
                }
            }
        } catch(e) {}
    }

    // Première vérification immédiate puis toutes les 25 secondes
    checkBossNotifications();
    setInterval(checkBossNotifications, 25000);
})();
