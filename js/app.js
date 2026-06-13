/* ============================================================
   app.js — JavaScript global MVENGINEERING
   Compatible ES5 / XAMPP 3.3.0
   ============================================================ */

'use strict';

/* ─── Chemin absolu vers php/ ────────────────────────────────
   Calculé depuis l'URL réelle de la page.
   Ex: http://localhost/mveng_v2/reception.html
       → API_BASE = http://localhost/mveng_v2/php/
   ─────────────────────────────────────────────────────────── */
var API_BASE = (function() {
  var href  = window.location.href.split('?')[0].split('#')[0];
  var parts = href.split('/');
  parts.pop();
  return parts.join('/') + '/php/';
})();

console.info('[MVENGINEERING] API_BASE =', API_BASE);

/* ─── Sidebar ─────────────────────────────────────────────── */
(function initSidebar() {
  var btn     = document.getElementById('hamburger-btn');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (!btn || !sidebar) { return; }

  function openSidebar() {
    btn.classList.add('open');
    sidebar.classList.add('open');
    if (overlay) { overlay.classList.add('open'); }
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    btn.classList.remove('open');
    sidebar.classList.remove('open');
    if (overlay) { overlay.classList.remove('open'); }
    document.body.style.overflow = '';
  }

  btn.addEventListener('click', function() {
    if (sidebar.classList.contains('open')) { closeSidebar(); } else { openSidebar(); }
  });
  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeSidebar(); }
  });

  var currentPage = window.location.pathname.split('/').pop() || 'index.html';
  var links = sidebar.querySelectorAll('nav a[href]');
  for (var i = 0; i < links.length; i++) {
    if (links[i].getAttribute('href') === currentPage) {
      links[i].classList.add('active');
    }
  }
})();

/* ─── Toast notifications ─────────────────────────────────── */
var Toast = (function() {
  var ICONS = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };

  function getContainer() {
    var c = document.getElementById('toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container';
      document.body.appendChild(c);
    }
    return c;
  }

  function removeToast(toast) {
    toast.style.opacity   = '0';
    toast.style.transform = 'translateX(120%)';
    toast.style.transition = 'all .3s ease';
    setTimeout(function() {
      if (toast.parentNode) { toast.parentNode.removeChild(toast); }
    }, 300);
  }

  function show(message, type, duration) {
    if (!type)     { type = 'info'; }
    if (!duration) { duration = 4500; }
    var icon  = ICONS[type] || 'ℹ️';
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<span class="toast-icon">' + icon + '</span>'
      + '<span>' + message + '</span>'
      + '<button class="toast-close" aria-label="Fermer">\u00d7</button>';
    toast.querySelector('.toast-close').addEventListener('click', function() {
      removeToast(toast);
    });
    getContainer().appendChild(toast);
    if (duration > 0) {
      setTimeout(function() { removeToast(toast); }, duration);
    }
    return toast;
  }

  return {
    show:    show,
    success: function(m) { return show(m, 'success'); },
    error:   function(m) { return show(m, 'error'); },
    info:    function(m) { return show(m, 'info'); },
    warning: function(m) { return show(m, 'warning'); }
  };
})();

/* ─── API Helper ──────────────────────────────────────────── */
var API = {

  /* POST JSON → script PHP */
  post: function(script, data) {
    if (!data) { data = {}; }
    var url = API_BASE + script;

    return fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(data)
    })
    .then(function(res) {
      return res.text().then(function(text) {
        var json;
        try {
          json = JSON.parse(text);
        } catch (e) {
          console.error('[API] Réponse non-JSON ←', url, '\n', text.substring(0, 600));
          var parseErr = new Error('parse');
          throw parseErr;
        }

        /* Redirection serveur (session expirée) */
        if (json && json.redirect) {
          Toast.warning(json.message || 'Session expirée.');
          setTimeout(function() {
            window.location.href = json.redirect;
          }, 1500);
          var redirErr = new Error('redirect');
          throw redirErr;
        }

        if (!res.ok) {
          console.error('[API] HTTP', res.status, url, json ? json.message : '');
          var httpErr = new Error('http_' + res.status);
          throw httpErr;
        }

        return json;
      });
    })
    .catch(function(err) {
      /* Transformer les erreurs réseau TypeError en Error('network') */
      if (err instanceof TypeError || err.message === 'Failed to fetch') {
        console.error('[API] Erreur réseau →', url);
        var netErr = new Error('network');
        throw netErr;
      }
      throw err;
    });
  },

  /* POST FormData (upload fichier) */
  postForm: function(script, formData) {
    var url = API_BASE + script;

    return fetch(url, {
      method: 'POST',
      body:   formData
    })
    .then(function(res) {
      return res.text().then(function(text) {
        var json;
        try {
          json = JSON.parse(text);
        } catch (e) {
          console.error('[API] Réponse non-JSON (form) ←', url, '\n', text.substring(0, 600));
          throw new Error('parse');
        }
        if (!res.ok) {
          throw new Error('http_' + res.status);
        }
        return json;
      });
    })
    .catch(function(err) {
      if (err instanceof TypeError || err.message === 'Failed to fetch') {
        throw new Error('network');
      }
      throw err;
    });
  }
};

/* ─── Message d'erreur humain ─────────────────────────────── */
function apiErrMsg(err) {
  if (!err || !err.message) { return 'Erreur inconnue.'; }
  if (err.message === 'network')  { return 'Apache non démarré — vérifiez XAMPP.'; }
  if (err.message === 'parse')    { return 'Réponse PHP invalide (F12 > Console).'; }
  if (err.message === 'redirect') { return 'Session expirée.'; }
  if (err.message.indexOf('http_') === 0) {
    return 'Erreur serveur ' + err.message.replace('http_', 'HTTP ') + ' (F12 > Console).';
  }
  return 'Erreur : ' + err.message;
}

/* ─── Formatage date ──────────────────────────────────────── */
function formatDate(dateStr) {
  if (!dateStr) { return '\u2014'; }
  var d = new Date(dateStr);
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

/* ─── Badge statut ────────────────────────────────────────── */
function badgeStatut(statut) {
  var MAP = {
    en_attente: { cls: 'badge-attente', label: 'En attente' },
    accepte:    { cls: 'badge-accepte', label: 'Accept\u00e9' },
    refuse:     { cls: 'badge-refuse',  label: 'Refus\u00e9' },
    actif:      { cls: 'badge-actif',   label: 'Actif' },
    termine:    { cls: 'badge-termine', label: 'Termin\u00e9' }
  };
  var s = MAP[statut] || { cls: '', label: statut };
  return '<span class="badge ' + s.cls + '">' + s.label + '</span>';
}

/* ─── Échappement HTML ────────────────────────────────────── */
function escHtml(str) {
  var d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

/* ─── Tri de colonnes ─────────────────────────────────────── */
function initSortableTable(tableId) {
  var table = document.getElementById(tableId);
  if (!table) { return; }
  var headers = table.querySelectorAll('thead th[data-sort]');

  for (var i = 0; i < headers.length; i++) {
    (function(th, allHeaders) {
      th.style.cursor = 'pointer';
      th.addEventListener('click', function() {
        var dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';

        for (var j = 0; j < allHeaders.length; j++) {
          delete allHeaders[j].dataset.dir;
          allHeaders[j].textContent = allHeaders[j].textContent.replace(/ [▲▼]$/, '');
        }
        th.dataset.dir   = dir;
        th.textContent  += (dir === 'asc') ? ' \u25b2' : ' \u25bc';

        var tbody = table.querySelector('tbody');
        var rows  = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var idx   = Array.prototype.indexOf.call(allHeaders, th);

        rows.sort(function(a, b) {
          var va = a.cells[idx] ? a.cells[idx].textContent.trim() : '';
          var vb = b.cells[idx] ? b.cells[idx].textContent.trim() : '';
          return dir === 'asc' ? va.localeCompare(vb, 'fr') : vb.localeCompare(va, 'fr');
        });
        for (var k = 0; k < rows.length; k++) {
          tbody.appendChild(rows[k]);
        }
      });
    })(headers[i], headers);
  }
}

/* ─── Export CSV ──────────────────────────────────────────── */
function exportTableCSV(tableId, filename) {
  if (!filename) { filename = 'export.csv'; }
  var table = document.getElementById(tableId);
  if (!table) { return; }
  var rows = [];
  var allRows = table.querySelectorAll('tr');
  for (var i = 0; i < allRows.length; i++) {
    var cells = allRows[i].querySelectorAll('th, td');
    var line  = [];
    /* Exclure la dernière colonne (Actions) */
    for (var j = 0; j < cells.length - 1; j++) {
      line.push('"' + cells[j].textContent.replace(/"/g, '""').trim() + '"');
    }
    if (line.length) { rows.push(line.join(',')); }
  }
  var blob = new Blob(['\ufeff' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = filename;
  a.click();
  URL.revokeObjectURL(a.href);
}

function exportPDF() { window.print(); }

/* ─── Modal de confirmation ───────────────────────────────── */
function confirmDialog(message, titre) {
  if (!titre) { titre = 'Confirmation'; }
  return new Promise(function(resolve) {
    var overlay = document.getElementById('confirm-modal');
    if (!overlay) {
      resolve(window.confirm(message));
      return;
    }
    document.getElementById('confirm-title').textContent   = titre;
    document.getElementById('confirm-message').textContent = message;
    overlay.classList.add('open');

    var yes = document.getElementById('confirm-yes');
    var no  = document.getElementById('confirm-no');

    function done(val) {
      overlay.classList.remove('open');
      yes.onclick = null;
      no.onclick  = null;
      resolve(val);
    }
    yes.onclick = function() { done(true); };
    no.onclick  = function() { done(false); };
  });
}

/* ─── Session admin ───────────────────────────────────────── */
function checkAdminSession() {
  return API.post('auth.php', { action: 'check' })
    .then(function(r) { return r.success ? r.user : null; })
    .catch(function() { return null; });
}

function logout() {
  API.post('auth.php', { action: 'logout' })
    .catch(function() { /* session déjà expirée */ })
    .then(function() {
      window.location.href = 'login.html';
    });
}

/* ─── Compteur animé ──────────────────────────────────────── */
function animateCounter(el, target, duration) {
  if (!el) { return; }
  if (!duration) { duration = 800; }
  var start = Date.now();
  function step() {
    var p = Math.min((Date.now() - start) / duration, 1);
    el.textContent = Math.round(p * target);
    if (p < 1) { requestAnimationFrame(step); }
  }
  requestAnimationFrame(step);
}
