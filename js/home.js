/* ============================================================
   home.js — Page d'accueil MVENGINEERING
   Compatible ES5 / XAMPP 3.3.0
   ============================================================ */

'use strict';

var publicPage = 1;
var publicData = [];
var chartStatutInstance  = null;
var chartEvolInstance    = null;

/* ─── Chargement des données publiques ───────────────────── */
function loadPublicData() {
  API.post('action.php', { action: 'get_demandes', page: publicPage })
    .then(function(r) {
      if (!r.success) { return; }
      publicData = r.demandes;
      renderPublicTable(r.demandes);
      renderPagination(r.pages, r.page);
      if (publicPage === 1) { loadStats(); }
    })
    .catch(function(err) {
      console.error('[accueil] Erreur chargement:', err);
      renderDemoStats();
      if (err.message === 'network') {
        Toast.error('Serveur PHP inaccessible \u2014 v\u00e9rifiez Apache dans XAMPP.');
      } else if (err.message === 'parse') {
        Toast.error('Erreur PHP (F12 > Console pour le d\u00e9tail).');
      }
    });
}

function renderPublicTable(demandes) {
  var tbody = document.getElementById('public-tbody');
  if (!tbody) { return; }
  if (!demandes || !demandes.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gris-500)">Aucune demande pour l\'instant</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < demandes.length; i++) {
    var d = demandes[i];
    html += '<tr>'
      + '<td><strong>' + escHtml(d.nom_complet) + '</strong></td>'
      + '<td>' + escHtml(d.ecole)    + '</td>'
      + '<td>' + escHtml(d.filiere)  + '</td>'
      + '<td>' + formatDate(d.date_souhaitee) + '</td>'
      + '<td>' + badgeStatut(d.statut) + '</td>'
      + '</tr>';
  }
  tbody.innerHTML = html;
}

function renderPagination(pages, current) {
  var el = document.getElementById('public-pagination');
  if (!el) { return; }
  if (pages <= 1) { el.innerHTML = ''; return; }

  var html = '<button onclick="goPage(' + (current - 1) + ')" '
           + (current === 1 ? 'disabled' : '') + '>\u2039</button>';
  for (var i = 1; i <= pages; i++) {
    html += '<button class="' + (i === current ? 'active' : '') + '" '
          + 'onclick="goPage(' + i + ')">' + i + '</button>';
  }
  html += '<button onclick="goPage(' + (current + 1) + ')" '
        + (current === pages ? 'disabled' : '') + '>\u203a</button>';
  el.innerHTML = html;
}

function goPage(p) {
  publicPage = p;
  loadPublicData();
}

/* ─── Statistiques + graphiques ──────────────────────────── */
function loadStats() {
  API.post('action.php', { action: 'get_stats' })
    .then(function(r) {
      if (!r.success) { return; }
      var s = r.stats;
      animateCounter(document.getElementById('cnt-total'),   s.total);
      animateCounter(document.getElementById('cnt-attente'), s.en_attente);
      animateCounter(document.getElementById('cnt-accepte'), s.acceptees);
      animateCounter(document.getElementById('cnt-refuse'),  s.refusees);
      renderChartStatuts(s);
      if (r.evolution) { renderChartEvolution(r.evolution); }
    })
    .catch(function(err) {
      console.error('[accueil] loadStats:', err);
    });
}

function renderDemoStats() {
  var ids = { 'cnt-total': 5, 'cnt-attente': 2, 'cnt-accepte': 2, 'cnt-refuse': 1 };
  for (var id in ids) {
    var el = document.getElementById(id);
    if (el) { el.textContent = ids[id]; }
  }
  renderChartStatuts({ total: 5, en_attente: 2, acceptees: 2, refusees: 1 });

  var tbody = document.getElementById('public-tbody');
  if (!tbody) { return; }
  var demoData = [
    ['Jean-Pierre Mballa', 'Univ. Yaound\u00e9 I', 'Informatique',  '1 f\u00e9vr. 2025', 'accepte'],
    ['Aminata Diallo',     'ISTDI Douala',          'Gestion',       '15 f\u00e9vr. 2025','en_attente'],
    ['Christian Fopa',     'ENSP Yaound\u00e9',     'G\u00e9nie civil','1 mars 2025',    'refuse'],
    ['Marie-Claire Ngo',   'Univ. Douala',           'Finance',       '20 janv. 2025',    'en_attente'],
    ['Samuel Tchamba',     'IUT Ngaound\u00e9r\u00e9','R\u00e9seaux', '10 f\u00e9vr. 2025','accepte']
  ];
  var html = '';
  for (var i = 0; i < demoData.length; i++) {
    var r = demoData[i];
    html += '<tr>'
      + '<td><strong>' + r[0] + '</strong></td>'
      + '<td>' + r[1] + '</td>'
      + '<td>' + r[2] + '</td>'
      + '<td>' + r[3] + '</td>'
      + '<td>' + badgeStatut(r[4]) + '</td>'
      + '</tr>';
  }
  tbody.innerHTML = html;
}

function renderChartStatuts(s) {
  var ctx = document.getElementById('chart-statuts');
  if (!ctx || typeof Chart === 'undefined') { return; }
  /* Détruire l'instance précédente pour éviter le bug "canvas already in use" */
  if (chartStatutInstance) { chartStatutInstance.destroy(); }
  chartStatutInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['En attente', 'Accept\u00e9es', 'Refus\u00e9es'],
      datasets: [{
        data: [s.en_attente, s.acceptees, s.refusees],
        backgroundColor: ['#fef3c7', '#d1fae5', '#fee2e2'],
        borderColor:     ['#d97706', '#059669', '#dc2626'],
        borderWidth: 2
      }]
    },
    options: {
      responsive: false,
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8 } }
      },
      cutout: '65%'
    }
  });
}

function renderChartEvolution(evolution) {
  var ctx = document.getElementById('chart-evolution');
  if (!ctx || !evolution || !evolution.length || typeof Chart === 'undefined') { return; }
  if (chartEvolInstance) { chartEvolInstance.destroy(); }

  var labels = [];
  var values = [];
  for (var i = 0; i < evolution.length; i++) {
    var parts = evolution[i].mois.split('-');
    labels.push(new Date(parseInt(parts[0]), parseInt(parts[1]) - 1)
      .toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }));
    values.push(evolution[i].total);
  }

  chartEvolInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Demandes',
        data:  values,
        backgroundColor: 'rgba(26,86,219,.2)',
        borderColor: '#1a56db',
        borderWidth: 2,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } },
        x: { grid: { display: false } }
      }
    }
  });
}

/* ─── Compteurs animés hero ───────────────────────────────── */
function initCounters() {
  if (typeof IntersectionObserver === 'undefined') {
    /* Fallback pour navigateurs sans IntersectionObserver */
    var els = document.querySelectorAll('.stat-number[data-target]');
    for (var i = 0; i < els.length; i++) {
      animateCounter(els[i], parseInt(els[i].dataset.target), 1200);
    }
    return;
  }
  var observer = new IntersectionObserver(function(entries) {
    for (var i = 0; i < entries.length; i++) {
      var entry = entries[i];
      if (entry.isIntersecting) {
        var el = entry.target;
        animateCounter(el, parseInt(el.dataset.target), 1200);
        observer.unobserve(el);
      }
    }
  }, { threshold: 0.5 });

  var targets = document.querySelectorAll('.stat-number[data-target]');
  for (var j = 0; j < targets.length; j++) {
    observer.observe(targets[j]);
  }
}

/* ─── Recherche locale dans le tableau public ─────────────── */
function initPublicSearch() {
  var input = document.getElementById('search-public');
  if (!input) { return; }
  var timer;
  input.addEventListener('input', function() {
    clearTimeout(timer);
    timer = setTimeout(function() {
      var q = input.value.toLowerCase().trim();
      if (!q) { renderPublicTable(publicData); return; }
      var filtered = publicData.filter(function(d) {
        var fields = [d.nom_complet, d.ecole, d.filiere, d.statut];
        for (var i = 0; i < fields.length; i++) {
          if (fields[i] && fields[i].toLowerCase().indexOf(q) !== -1) { return true; }
        }
        return false;
      });
      renderPublicTable(filtered);
    }, 250);
  });
}

/* ─── Init ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  initCounters();
  initPublicSearch();
  initSortableTable('public-table');
  loadPublicData();
  /* Actualisation automatique toutes les 30 secondes */
  setInterval(loadPublicData, 30000);
});
