// ============================================================
// Minabou Hotel — Main JavaScript
// ============================================================

// ── Sidebar toggle ──────────────────────────────────────────
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.getElementById('sidebar');
const appMain       = document.getElementById('appMain');

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        appMain && appMain.classList.toggle('expanded');
    });
}

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
