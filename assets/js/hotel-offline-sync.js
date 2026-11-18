/**
 * Alma Hotel — PWA & Offline Engine v1.0
 * Gestion complète du mode hors-ligne, encaissements locaux et synchronisation des arrivées.
 */

const HOTEL_DB_NAME = 'HotelOfflineDB';
const HOTEL_DB_VERSION = 1;

// -------------------------------------------------------------
// GESTION D'INDEXEDDB LOCAL
// -------------------------------------------------------------
function openHotelDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(HOTEL_DB_NAME, HOTEL_DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('offline_checkins')) {
        db.createObjectStore('offline_checkins', { keyPath: 'id', autoIncrement: true });
      }
    };
    req.onsuccess = (e) => resolve(e.target.result);
    req.onerror = (e) => reject(e.target.error);
  });
}

async function saveOfflineCheckin(data) {
  try {
    const db = await openHotelDB();
    const tx = db.transaction('offline_checkins', 'readwrite');
    const store = tx.objectStore('offline_checkins');
    data.saved_at = new Date().toISOString();
    if (!data.reference) {
      data.reference = 'HOTEL-OFF-' + Date.now().toString().slice(-6);
    }
    const addReq = store.add(data);
    return new Promise((resolve) => {
      addReq.onsuccess = (e) => {
        data.id = e.target.result;
        updateHotelNetworkIndicators();
        resolve(data);
      };
      addReq.onerror = () => resolve(false);
    });
  } catch (err) {
    console.error('[Hotel Offline DB] Erreur sauvegarde:', err);
    return false;
  }
}

function getOfflineCheckins() {
  return new Promise(async (resolve) => {
    try {
      const db = await openHotelDB();
      const tx = db.transaction('offline_checkins', 'readonly');
      const store = tx.objectStore('offline_checkins');
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => resolve([]);
    } catch (e) {
      resolve([]);
    }
  });
}

async function deleteOfflineCheckin(id) {
  try {
    const db = await openHotelDB();
    const tx = db.transaction('offline_checkins', 'readwrite');
    tx.objectStore('offline_checkins').delete(id);
    return new Promise((resolve) => {
      tx.oncomplete = () => {
        updateHotelNetworkIndicators();
        renderHotelOfflineModal();
        resolve(true);
      };
    });
  } catch (e) {
    return false;
  }
}

async function clearAllOfflineCheckins() {
  try {
    const db = await openHotelDB();
    const tx = db.transaction('offline_checkins', 'readwrite');
    tx.objectStore('offline_checkins').clear();
    return new Promise((resolve) => {
      tx.oncomplete = () => {
        updateHotelNetworkIndicators();
        renderHotelOfflineModal();
        resolve(true);
      };
    });
  } catch (e) {
    return false;
  }
}

// -------------------------------------------------------------
// MODE HORS-LIGNE FORCÉ & ÉTAT RÉSEAU
// -------------------------------------------------------------
function isHotelForcedOffline() {
  return localStorage.getItem('hotel_force_offline') === 'true';
}

function isHotelAppOffline() {
  return !navigator.onLine || isHotelForcedOffline();
}

function toggleHotelForceOffline(enabled) {
  localStorage.setItem('hotel_force_offline', enabled ? 'true' : 'false');
  updateHotelNetworkIndicators();
  renderHotelOfflineModal();

  if (enabled) {
    showHotelToast('🟠 Mode Hors-Ligne FORCÉ activé. Arrivées et encaissements enregistrés en mémoire locale.', 'warning');
  } else {
    showHotelToast('🟢 Mode En Ligne activé. Connexion au serveur central rétablie.', 'success');
    if (navigator.onLine) {
      syncHotelOfflineData();
    }
  }
}

// -------------------------------------------------------------
// SYNCHRONISATION DES DONNÉES
// -------------------------------------------------------------
let isHotelSyncing = false;

function getHotelSyncUrl() {
  const loc = window.location;
  const path = loc.pathname;
  if (path.includes('/modules/')) {
    const base = path.substring(0, path.indexOf('/modules/'));
    return `${loc.origin}${base}/modules/checkin_checkout/sync_offline.php`;
  }
  return 'modules/checkin_checkout/sync_offline.php';
}

async function syncHotelOfflineData() {
  if (isHotelForcedOffline()) return;
  if (!navigator.onLine) return;
  if (isHotelSyncing) return;

  const items = await getOfflineCheckins();
  if (items.length === 0) return;

  isHotelSyncing = true;
  const syncBtn = document.getElementById('hotelManualSyncBtn');
  if (syncBtn) {
    syncBtn.disabled = true;
    syncBtn.innerHTML = '🔄 Synchronisation en cours...';
  }

  showHotelToast(`🔄 Envoi de ${items.length} arrivée(s) vers le serveur central...`, 'info');

  try {
    const syncUrl = getHotelSyncUrl();
    const res = await fetch(syncUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ checkins: items })
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);

    const data = await res.json();
    if (data && data.success) {
      if (Array.isArray(data.synced_ids) && data.synced_ids.length > 0) {
        for (const sid of data.synced_ids) {
          await deleteOfflineCheckin(sid);
        }
      } else {
        await clearAllOfflineCheckins();
      }
      showHotelToast(`✅ Synchronisation réussie ! ${data.synced_count || items.length} arrivée(s) enregistrée(s) au serveur central.`, 'success');
      await updateHotelNetworkIndicators();
      await renderHotelOfflineModal();
    } else {
      showHotelToast(`⚠️ Synchronisation partielle : ${data.message || 'Erreur serveur'}`, 'warning');
    }
  } catch (err) {
    console.warn('[Hotel Sync] Échec envoi:', err);
    showHotelToast('⚠️ Connexion instable. Vos données restent conservées en sécurité locale.', 'warning');
  } finally {
    isHotelSyncing = false;
    if (syncBtn) {
      syncBtn.disabled = false;
      syncBtn.innerHTML = '⚡ Synchroniser Maintenant';
    }
  }
}

async function triggerHotelManualSync() {
  if (!navigator.onLine) {
    alert("Impossible de synchroniser : Cet appareil n'est pas connecté à Internet.");
    return;
  }
  if (isHotelForcedOffline()) {
    if (confirm("Le mode Hors-Ligne Forcé est actif. Désactiver pour synchroniser maintenant ?")) {
      toggleHotelForceOffline(false);
      return;
    }
    return;
  }
  const items = await getOfflineCheckins();
  if (items.length === 0) {
    showHotelToast('✅ Aucune arrivée en attente. Tout est déjà à jour sur le serveur !', 'info');
    return;
  }
  await syncHotelOfflineData();
}

// -------------------------------------------------------------
// FORMATAGE ET INTERFACE DU MODAL
// -------------------------------------------------------------
function formatHotelMoney(num) {
  return (Math.round(num) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

async function updateHotelNetworkIndicators() {
  const items = await getOfflineCheckins();
  const count = items.length;
  const forced = isHotelForcedOffline();
  const online = navigator.onLine;

  const btn = document.getElementById('hotelNetStatusBtn');
  const dot = document.getElementById('hotelNetStatusDot');
  const txt = document.getElementById('hotelNetStatusText');
  const badge = document.getElementById('hotelNetPendingBadge');

  if (btn && dot && txt) {
    if (forced) {
      btn.style.background = '#fef3c7';
      btn.style.color = '#b45309';
      btn.style.borderColor = '#f59e0b';
      dot.style.background = '#d97706';
      txt.textContent = 'Hors-Ligne (Forcé)';
    } else if (!online) {
      btn.style.background = '#ffe4e6';
      btn.style.color = '#e11d48';
      btn.style.borderColor = '#f43f5e';
      dot.style.background = '#e11d48';
      txt.textContent = 'Hors-Ligne';
    } else {
      btn.style.background = '#ecfdf5';
      btn.style.color = '#047857';
      btn.style.borderColor = '#10b981';
      dot.style.background = '#10b981';
      txt.textContent = 'En ligne';
    }
  }

  if (badge) {
    if (count > 0) {
      badge.textContent = count + ' en attente';
      badge.style.display = 'inline-block';
    } else {
      badge.style.display = 'none';
    }
  }
}

async function renderHotelOfflineModal() {
  const items = await getOfflineCheckins();
  const forced = isHotelForcedOffline();
  const online = navigator.onLine;

  const switchEl = document.getElementById('hotelForceOfflineSwitch');
  if (switchEl) switchEl.checked = forced;

  let totalCash = 0;
  let totalMomo = 0;
  let totalGlobal = 0;

  items.forEach(it => {
    const paid = parseFloat(it.montant_paye) || 0;
    totalGlobal += paid;
    const pm = (it.mode_paiement || 'especes').toLowerCase();
    if (pm === 'especes') totalCash += paid;
    else if (pm === 'mobile') totalMomo += paid;
  });

  const cashEl = document.getElementById('hotelOfflineCashTotal');
  const countEl = document.getElementById('hotelOfflineCount');
  const globalEl = document.getElementById('hotelOfflineGlobalTotal');

  if (cashEl) cashEl.textContent = formatHotelMoney(totalCash) + ' FCFA';
  if (countEl) countEl.textContent = items.length;
  if (globalEl) globalEl.textContent = formatHotelMoney(totalGlobal) + ' FCFA';

  const tbody = document.getElementById('hotelOfflineTableBody');
  const emptyBox = document.getElementById('hotelOfflineEmptyState');
  if (tbody) {
    tbody.innerHTML = '';
    if (items.length === 0) {
      if (emptyBox) emptyBox.style.display = 'block';
    } else {
      if (emptyBox) emptyBox.style.display = 'none';
      items.forEach(it => {
        const tr = document.createElement('tr');
        const dt = it.saved_at ? new Date(it.saved_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        tr.innerHTML = `
          <td><strong>${it.reference}</strong><div style="font-size:11px; color:#64748b;">${dt}</div></td>
          <td><strong>${it.nom} ${it.prenom}</strong><div style="font-size:11px; color:#64748b;">${it.telephone || '-'}</div></td>
          <td>Chambre <strong>${it.numero_chambre || it.id_chambre}</strong></td>
          <td style="text-align:right; font-weight:700;">${formatHotelMoney(it.montant_paye)} F</td>
          <td style="text-align:center;">
            <button class="btn btn-sm btn-outline" onclick='printHotelOfflineReceipt(${JSON.stringify(it).replace(/'/g, "&#39;")})' title="Imprimer le reçu" style="padding:4px 8px;">🖨️</button>
            <button class="btn btn-sm btn-outline" onclick="deleteOfflineCheckin(${it.id})" title="Supprimer" style="padding:4px 8px; color:#e11d48;">🗑️</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }
  }
}

// -------------------------------------------------------------
// IMPRESSION DU REÇU HORS-LIGNE
// -------------------------------------------------------------
function printHotelOfflineReceipt(checkin) {
  const win = window.open('', '_blank', 'width=420,height=600');
  if (!win) {
    alert("Veuillez autoriser les popups pour imprimer la facturette.");
    return;
  }
  const dateStr = checkin.saved_at ? new Date(checkin.saved_at).toLocaleString('fr-FR') : new Date().toLocaleString('fr-FR');
  const stayText = (checkin.type_sejour === 'sieste') ? 'Sieste / Passage' : `${checkin.nb_nuits || 1} nuit(s)`;

  win.document.write(`
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="utf-8">
      <title>Reçu Arrivée - ${checkin.reference}</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; margin: 0; padding: 16px; color: #000; }
        .header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 12px; margin-bottom: 12px; }
        .hotel-name { font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .hotel-sub { font-size: 10px; color: #333; margin-top: 2px; }
        .meta-line { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .table th, .table td { text-align: left; padding: 6px 0; border-bottom: 1px dotted #ccc; }
        .total-box { border-top: 2px dashed #000; padding-top: 10px; margin-top: 10px; text-align: right; }
        .total-val { font-size: 15px; font-weight: 800; }
        .footer { text-align: center; font-size: 10px; color: #555; margin-top: 20px; border-top: 1px dotted #ccc; padding-top: 10px; }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="hotel-name">ALMA HOTEL</div>
        <div class="hotel-sub">Hébergement, Suites & Caisse</div>
        <div style="font-weight: bold; margin-top: 6px;">REÇU D'ARRIVÉE CLIENT</div>
      </div>
      <div class="meta-line"><span>Réf :</span><strong>${checkin.reference}</strong></div>
      <div class="meta-line"><span>Date :</span><span>${dateStr}</span></div>
      <div class="meta-line"><span>Client :</span><strong>${checkin.nom} ${checkin.prenom}</strong></div>
      <div class="meta-line"><span>Pièce ID :</span><span>${checkin.type_piece || 'CNI'} ${checkin.numero_piece || ''}</span></div>
      <div class="meta-line"><span>Chambre :</span><strong>N° ${checkin.numero_chambre || checkin.id_chambre}</strong></div>
      <div class="meta-line"><span>Formule :</span><span>${stayText}</span></div>

      <table class="table">
        <thead>
          <tr><th>Description</th><th style="text-align:right;">Montant</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>Séjour Chambre N° ${checkin.numero_chambre || checkin.id_chambre} (${stayText})</td>
            <td style="text-align:right;">${formatHotelMoney(checkin.montant_paye)} F</td>
          </tr>
        </tbody>
      </table>

      <div class="total-box">
        <div>Montant Payé (${checkin.mode_paiement || 'Espèces'}) :</div>
        <div class="total-val">${formatHotelMoney(checkin.montant_paye)} FCFA</div>
      </div>

      <div class="footer">
        <div>Merci de votre séjour à Alma Hotel !</div>
        <div>Excellent repos dans notre établissement.</div>
      </div>
      <script>
        window.onload = function() { window.print(); };
      </script>
    </body>
    </html>
  `);
  win.document.close();
}

// -------------------------------------------------------------
// TOASTS DE NOTIFICATION
// -------------------------------------------------------------
function showHotelToast(msg, type = 'info') {
  let box = document.getElementById('hotelToastBox');
  if (!box) {
    box = document.createElement('div');
    box.id = 'hotelToastBox';
    box.style.cssText = 'position:fixed; bottom:20px; right:20px; z-index:99999; display:flex; flex-direction:column; gap:10px; max-width:380px;';
    document.body.appendChild(box);
  }
  const toast = document.createElement('div');
  const bg = type === 'success' ? '#047857' : (type === 'warning' ? '#b45309' : '#1e1b4b');
  toast.style.cssText = `background:${bg}; color:#ffffff; padding:12px 16px; border-radius:10px; font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.25); display:flex; justify-content:space-between; align-items:center; animation:hotelFade 0.3s ease-out;`;
  toast.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:16px; cursor:pointer; margin-left:12px;">✕</button>`;
  box.appendChild(toast);
  setTimeout(() => { toast.remove(); }, 6000);
}

// -------------------------------------------------------------
// ÉCOUTEURS D'ÉVÉNEMENTS RÉSEAU & FOCUS
// -------------------------------------------------------------
window.addEventListener('online', () => {
  updateHotelNetworkIndicators();
  renderHotelOfflineModal();
  if (!isHotelForcedOffline()) {
    showHotelToast('🌐 Connexion Internet rétablie ! Lancement de la synchronisation automatique...', 'success');
    setTimeout(() => {
      syncHotelOfflineData();
    }, 1200);
  }
});

window.addEventListener('offline', () => {
  updateHotelNetworkIndicators();
  renderHotelOfflineModal();
  showHotelToast('🔴 Coupure réseau : Alma Hotel bascule en mode Hors-Ligne automatique.', 'warning');
});

window.addEventListener('focus', () => {
  if (navigator.onLine && !isHotelForcedOffline()) {
    getOfflineCheckins().then(items => {
      if (items.length > 0 && !isHotelSyncing) {
        syncHotelOfflineData();
      }
    });
  }
});

document.addEventListener('DOMContentLoaded', () => {
  updateHotelNetworkIndicators();
  if (navigator.onLine && !isHotelForcedOffline()) {
    syncHotelOfflineData();
  }
});

// -------------------------------------------------------------
// GESTION DE L'INSTALLATION AUTOMATIQUE PWA (UNE SEULE FOIS APRÈS CONNEXION)
// -------------------------------------------------------------
let deferredHotelPwaPrompt = null;
let hotelPwaInstallCardShown = false;

// Détecter si l'application est déjà lancée en mode autonome PWA (standalone)
function isHotelRunningStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches ||
         window.navigator.standalone === true ||
         document.referrer.includes('android-app://');
}

// Vérifier si le prompt doit être proposé
function shouldShowHotelInstallPrompt() {
  if (isHotelRunningStandalone()) return false;
  if (localStorage.getItem('alma_pwa_installed') === 'true') return false;
  if (sessionStorage.getItem('hotel_pwa_dismissed') === 'true') return false;
  const path = window.location.pathname.toLowerCase();
  if (path.endsWith('login.php') || path.includes('/login.php')) return false;
  return true;
}

// Événement natif du navigateur pour l'installation PWA
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredHotelPwaPrompt = e;
  console.log('[Hotel PWA] Événement beforeinstallprompt capturé.');

  if (shouldShowHotelInstallPrompt()) {
    setTimeout(showHotelAutoInstallPrompt, 1500);
  }
});

// Événement déclenché dès que l'application est installée avec succès
window.addEventListener('appinstalled', () => {
  console.log('[Hotel PWA] Application installée avec succès.');
  localStorage.setItem('alma_pwa_installed', 'true');
  dismissHotelInstallPrompt();
  showHotelToast('🎉 Application Alma Hotel installée avec succès !', 'success');
});

// Affichage de la carte d'installation automatique
function showHotelAutoInstallPrompt() {
  if (!shouldShowHotelInstallPrompt() || hotelPwaInstallCardShown) return;

  hotelPwaInstallCardShown = true;
  let card = document.getElementById('hotelAutoInstallPrompt');
  if (!card) {
    card = document.createElement('div');
    card.id = 'hotelAutoInstallPrompt';
    card.style.cssText = `
      position: fixed;
      bottom: calc(75px + env(safe-area-inset-bottom, 0px));
      right: 16px;
      left: 16px;
      max-width: 440px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 20px 45px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(15, 23, 42, 0.08);
      z-index: 1000005;
      padding: 16px 18px;
      font-family: inherit;
      animation: hotelSlideUp 0.35s ease-out;
    `;
    const baseUrl = window.HOTEL_BASE_URL || '';
    card.innerHTML = `
      <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px;">
        <img src="${baseUrl}/assets/img/pwa/icon-192.png" alt="Alma Hotel" style="width: 44px; height: 44px; border-radius: 12px; object-fit: contain; background: #1e1b4b; padding: 4px; box-shadow: 0 4px 10px rgba(30,27,75,0.2); flex-shrink: 0;">
        <div style="flex: 1; min-width: 0;">
          <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3;">Installer l'application Alma Hotel</h4>
          <p style="margin: 4px 0 0 0; font-size: 12.5px; color: #64748b; line-height: 1.4;">Installez l'application sur cet appareil pour un accès direct depuis votre bureau / écran d'accueil et profiter de la caisse rapide.</p>
        </div>
        <button onclick="dismissHotelInstallPrompt()" aria-label="Fermer" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; padding: 2px 6px; line-height: 1;">✕</button>
      </div>
      <div id="hotelInstallActions" style="display: flex; gap: 10px;">
        <button onclick="triggerHotelPwaInstall()" style="flex: 1; background: #1e1b4b; color: #ffffff; border: none; border-radius: 10px; padding: 11px 16px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(30,27,75,0.25); transition: opacity 0.2s;">
          <span>📲</span> <span>Installer l'application</span>
        </button>
        <button onclick="dismissHotelInstallPrompt()" style="background: #f1f5f9; color: #475569; border: none; border-radius: 10px; padding: 11px 16px; font-size: 13px; font-weight: 600; cursor: pointer;">
          Plus tard
        </button>
      </div>
      <div id="hotelInstallGuide" style="display: none; margin-top: 12px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 12.5px; color: #334155; line-height: 1.5;">
      </div>
    `;
    document.body.appendChild(card);
  }
}

// Déclenchement de l'installation
function triggerHotelPwaInstall() {
  if (deferredHotelPwaPrompt) {
    deferredHotelPwaPrompt.prompt();
    deferredHotelPwaPrompt.userChoice.then((choiceResult) => {
      console.log('[Hotel PWA] Choix utilisateur:', choiceResult.outcome);
      if (choiceResult.outcome === 'accepted') {
        localStorage.setItem('alma_pwa_installed', 'true');
        showHotelToast('🎉 Application Alma Hotel installée avec succès !', 'success');
        dismissHotelInstallPrompt();
      }
      deferredHotelPwaPrompt = null;
    }).catch(() => {
      dismissHotelInstallPrompt();
    });
  } else {
    const guide = document.getElementById('hotelInstallGuide');
    const actions = document.getElementById('hotelInstallActions');
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    if (guide && actions) {
      if (isIOS) {
        guide.innerHTML = `
          <strong>Sur iPhone / iPad :</strong><br>
          1. Appuyez sur le bouton de partage <strong>⎋</strong> en bas de Safari.<br>
          2. Faites défiler et touchez <strong>« Sur l'écran d'accueil » ➕</strong>.
        `;
      } else {
        guide.innerHTML = `
          <strong>Sur votre navigateur :</strong><br>
          1. Cliquez sur l'icône <strong>📥 Installer</strong> dans la barre d'adresse (ou dans le menu ⋮).<br>
          2. Confirmez l'installation.
        `;
      }
      guide.style.display = 'block';
      actions.innerHTML = `
        <button onclick="confirmHotelInstalledManual()" style="flex: 1; background: #059669; color: #ffffff; border: none; border-radius: 10px; padding: 11px 16px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
          <span>✓</span> <span>C'est fait, application installée</span>
        </button>
        <button onclick="dismissHotelInstallPrompt()" style="background: #f1f5f9; color: #475569; border: none; border-radius: 10px; padding: 11px 16px; font-size: 13px; font-weight: 600; cursor: pointer;">
          Fermer
        </button>
      `;
    }
  }
}

// Confirmation manuelle si navigateur sans prompt JS natif
function confirmHotelInstalledManual() {
  localStorage.setItem('alma_pwa_installed', 'true');
  showHotelToast('✓ Installation enregistrée !', 'success');
  dismissHotelInstallPrompt();
}

// Fermeture du prompt
function dismissHotelInstallPrompt() {
  sessionStorage.setItem('hotel_pwa_dismissed', 'true');
  const card = document.getElementById('hotelAutoInstallPrompt');
  if (card) card.remove();
}

// Initialisation au chargement de la page pour les utilisateurs connectés
document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    if (shouldShowHotelInstallPrompt()) {
      showHotelAutoInstallPrompt();
    }
  }, 1800);
});

// Enregistrement Service Worker avec support chemin absolu et relatif
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const baseUrl = window.HOTEL_BASE_URL || '';
    const swUrl = baseUrl ? (baseUrl + '/sw.js') : './sw.js';
    navigator.serviceWorker.register(swUrl)
      .then(reg => console.log('[Hotel PWA] Service Worker actif, scope:', reg.scope))
      .catch(err => {
        // Fallback relatif
        navigator.serviceWorker.register('./sw.js')
          .then(reg => console.log('[Hotel PWA] Service Worker fallback actif:', reg.scope))
          .catch(e => console.warn('[Hotel PWA] Échec enregistrement SW:', e));
      });
  });
}
