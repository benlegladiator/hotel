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
