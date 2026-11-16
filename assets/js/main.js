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
    const b64Click = "data:audio/wav;base64,UklGRgQCAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YeABAACA1PbTgjEOLHjH7tiSRBckYq7h4K1kKh1DhsbhzJFPJilXlsvbwIdNKzBalMTVwI5YNDFRg7TOx6JxRjRBaJa8yrqTZ0Q5SW2XucS3lm5NPkdkiau+u6SBYElEU3CRrbq0n4BiTklUbImks7SmjXJbTk9edY6ksK+ijXVgVFJcboOYp6ynmYZyYVdXX26AkqCop56Pf25hW1tjcH+Om6Kkn5WHemxjXl9mcH2JlJygn5mQhXpvZ2NiZm12gIqTmZybmJGJgHhwamdmaG1ze4KJkJWYmJaTjYeAenRvbGpqbG9zeH6DiI2Qk5SUk5CNiYSAfHh0cW9ubm5wcnV4fH+ChYiLjY+QkJCPjo2LiIaDgX99enh3dXRzcnJycnN0dXZ3eHp7fX6AgIKDhIWHh4iJioqKi4uLi4uLi4uKioqKiYmJiIiHh4eGhoaGhYWFhYSEhISEhISEg4ODg4SEhISEhISEhIWFhYWFhYaGhoaGhoeHh4eHh4eHh4aGhoaFhYWEhIOCgoGAgIB/f359fXx8e3t7e3t7e3t8fH1+f3+AgIGCg4OEhISFhISEg4OCgYCAf359fXx8fHx9fX5/gICBgoKDg4SDg4KCgYCAf359fX19fX5/gICBgoKDg4OCgYE=";
    let fallbackAudio = null;

    function unlockAudio() {
        try {
            if (!audioCtx) {
                const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                if (AudioCtxClass) audioCtx = new AudioCtxClass();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
        } catch(e) {}
    }

    // Déverrouiller le contexte audio dès le premier geste tactile
    ['touchstart', 'touchend', 'pointerdown', 'mousedown', 'click'].forEach(evt => {
        document.addEventListener(evt, unlockAudio, { passive: true, once: true });
    });

    // Son de clic / tap tactile net et bien audible
    window.playHotelTapSound = function() {
        try {
            unlockAudio();
            if (audioCtx) {
                const playOsc = function() {
                    try {
                        const now = audioCtx.currentTime;
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();

                        // Onde triangulaire riche (audible sur haut-parleur mobile)
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(880, now); // La5 (880Hz)
                        osc.frequency.exponentialRampToValueAtTime(340, now + 0.055); // Descente vers 340Hz

                        // Volume franc et net (0.4) avec décroissance rapide (55ms)
                        gain.gain.setValueAtTime(0.4, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.055);

                        osc.connect(gain);
                        gain.connect(audioCtx.destination);

                        osc.start(now);
                        osc.stop(now + 0.06);
                    } catch(err) {
                        playFallback();
                    }
                };

                if (audioCtx.state === 'suspended') {
                    audioCtx.resume().then(playOsc).catch(() => playFallback());
                } else {
                    playOsc();
                }
                return;
            }
        } catch(e) {}

        playFallback();
    };

    function playFallback() {
        try {
            if (!fallbackAudio) {
                fallbackAudio = new Audio(b64Click);
                fallbackAudio.volume = 0.6;
            }
            fallbackAudio.currentTime = 0;
            fallbackAudio.play().catch(() => {});
        } catch(e) {}
    }

    // Son de carillon de succès / Cloche d'encaissement (Chime)
    window.playHotelSuccessSound = function() {
        try {
            unlockAudio();
            if (!audioCtx) return;
            const playChime = function() {
                const now = audioCtx.currentTime;
                [523.25, 659.25, 783.99, 1046.50].forEach((freq, i) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(freq, now + i * 0.09);
                    gain.gain.setValueAtTime(0.25, now + i * 0.09);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.09 + 0.28);
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(now + i * 0.09);
                    osc.stop(now + i * 0.09 + 0.28);
                });
            };
            if (audioCtx.state === 'suspended') {
                audioCtx.resume().then(playChime);
            } else {
                playChime();
            }
        } catch(e) {}
    };

    // Émettre un tap sonore instantané sur tout bouton ou élément cliquable
    let lastSoundTime = 0;
    function handleSoundTrigger(e) {
        const now = Date.now();
        if (now - lastSoundTime < 80) return; // Évite double déclenchement pointerdown + click

        const interactive = e.target.closest('button, .btn, .mobile-bottom-nav a, .hotel-action-card-2, .hotel-occupation-bar, .alma-tresorerie-card, .alma-btn-eye, .nav-tab, .sidebar-nav a, a.badge, .room-cards-container .card, .occupations-cards-mobile .card, input[type="submit"], input[type="button"], a[href]');
        if (interactive) {
            lastSoundTime = now;
            playHotelTapSound();
        }
    }

    // Déclenchement dès le contact tactile (0ms de latence) + au clic souris
    document.addEventListener('pointerdown', handleSoundTrigger, { passive: true });
    document.addEventListener('click', handleSoundTrigger, true);
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
            checkBossNotifications(); // Chargement uniquement à la demande lors du clic !
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

    // Déclenchement sonore immédiat au clic de soumission d'une arrivée ou opération
    document.addEventListener('submit', function() {
        if (typeof window.playHotelSuccessSound === 'function') {
            window.playHotelSuccessSound();
        }
    });
})();
