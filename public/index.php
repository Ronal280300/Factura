<?php ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IVA Sync &mdash; Facturas Electrónicas CR</title>
  <style>
    /* ── Variables ─────────────────────────────────────────── */
    :root {
      --blue:      #1a6fc4;
      --blue-dark: #145299;
      --green:     #2e7d32;
      --green-lt:  #e8f5e9;
      --yellow:    #f59f00;
      --yellow-lt: #fff9e6;
      --red:       #c62828;
      --red-lt:    #fdecea;
      --gray:      #6c757d;
      --gray-lt:   #f4f6f8;
      --border:    #dee2e6;
      --text:      #212529;
      --text-sm:   #6c757d;
      --card-bg:   #ffffff;
      --shadow:    0 1px 4px rgba(0,0,0,.10);
      --radius:    8px;
      --font:      system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
    }

    /* ── Reset ─────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: var(--gray-lt); color: var(--text); font-size: 14px; }
    button { cursor: pointer; font-family: inherit; }
    input  { font-family: inherit; }

    /* ── Header ────────────────────────────────────────────── */
    header {
      background: var(--blue);
      color: #fff;
      padding: 0 24px;
      display: flex;
      align-items: center;
      height: 58px;
      box-shadow: 0 2px 6px rgba(0,0,0,.18);
    }
    header h1  { font-size: 18px; font-weight: 700; letter-spacing: .3px; }
    header p   { font-size: 12px; opacity: .82; margin-top: 1px; }
    .header-badge {
      margin-left: 14px;
      background: rgba(255,255,255,.18);
      border-radius: 4px;
      padding: 2px 8px;
      font-size: 11px;
      letter-spacing: .5px;
      text-transform: uppercase;
    }

    /* ── Layout ────────────────────────────────────────────── */
    .container { max-width: 1280px; margin: 0 auto; padding: 24px 20px; }

    /* ── Tabs ──────────────────────────────────────────────── */
    .tabs {
      display: flex;
      gap: 4px;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 6px;
      margin-bottom: 20px;
      width: fit-content;
      box-shadow: var(--shadow);
    }
    .tab {
      background: none;
      border: none;
      padding: 8px 22px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      color: var(--text-sm);
      transition: background .15s, color .15s;
    }
    .tab:hover    { background: var(--gray-lt); color: var(--text); }
    .tab.active   { background: var(--blue); color: #fff; }
    .tab-content  { display: none; }
    .tab-content.active { display: block; }

    /* ── Card ──────────────────────────────────────────────── */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 20px 24px;
      margin-bottom: 18px;
    }
    .card-title {
      font-size: 13px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: var(--text-sm);
      margin-bottom: 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border);
    }

    /* ── Form ──────────────────────────────────────────────── */
    .form-row {
      display: flex;
      align-items: flex-end;
      gap: 14px;
      flex-wrap: wrap;
    }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-sm);
      text-transform: uppercase;
      letter-spacing: .4px;
    }
    input[type="date"] {
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 8px 12px;
      font-size: 14px;
      color: var(--text);
      outline: none;
      transition: border-color .15s;
      background: #fff;
    }
    input[type="date"]:focus { border-color: var(--blue); }

    /* ── Buttons ───────────────────────────────────────────── */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 20px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      border: none;
      transition: background .15s, opacity .15s;
    }
    .btn-primary  { background: var(--blue);  color: #fff; }
    .btn-primary:hover  { background: var(--blue-dark); }
    .btn-success  { background: var(--green); color: #fff; }
    .btn-success:hover  { background: #1b5e20; }
    .btn-outline  {
      background: #fff;
      color: var(--blue);
      border: 1.5px solid var(--blue);
    }
    .btn-outline:hover { background: #edf3fb; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }

    /* ── Progress ──────────────────────────────────────────── */
    .progress-wrap { margin-top: 6px; }
    .progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      color: var(--text-sm);
      margin-bottom: 6px;
    }
    .progress-bar {
      height: 14px;
      background: #e9ecef;
      border-radius: 7px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, var(--blue), #41a0f5);
      border-radius: 7px;
      transition: width .4s ease;
    }
    .progress-fill.done { background: linear-gradient(90deg, var(--green), #66bb6a); }

    /* ── Stats Grid ────────────────────────────────────────── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
      gap: 12px;
      margin-top: 4px;
    }
    .stat-card {
      background: var(--gray-lt);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 16px;
      text-align: center;
    }
    .stat-value {
      font-size: 26px;
      font-weight: 700;
      line-height: 1;
      color: var(--text);
    }
    .stat-label {
      font-size: 11px;
      color: var(--text-sm);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-top: 5px;
    }
    .stat-card.green  { border-color: #a5d6a7; background: var(--green-lt); }
    .stat-card.green  .stat-value { color: var(--green); }
    .stat-card.yellow { border-color: #ffe082; background: var(--yellow-lt); }
    .stat-card.yellow .stat-value { color: var(--yellow); }
    .stat-card.red    { border-color: #ef9a9a; background: var(--red-lt); }
    .stat-card.red    .stat-value { color: var(--red); }

    /* ── Status Badge ──────────────────────────────────────── */
    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
    }
    .badge-running { background: #bbdefb; color: #1565c0; }
    .badge-done    { background: #c8e6c9; color: var(--green); }
    .badge-failed  { background: #ffcdd2; color: var(--red); }
    .badge-pending { background: #f5f5f5; color: var(--gray); }

    /* ── Log Console ───────────────────────────────────────── */
    .log-console {
      background: #1e1e2e;
      color: #cdd6f4;
      border-radius: var(--radius);
      padding: 12px 14px;
      height: 200px;
      overflow-y: auto;
      font-family: "Consolas", "Courier New", monospace;
      font-size: 12px;
      line-height: 1.7;
    }
    .log-line       { display: block; }
    .log-ok         { color: #a6e3a1; }
    .log-dup        { color: #f9e2af; }
    .log-err        { color: #f38ba8; }
    .log-skip       { color: #6c7086; }
    .log-info       { color: #89dceb; }
    .log-ts         { color: #585b70; margin-right: 6px; }

    /* ── Table ─────────────────────────────────────────────── */
    .table-wrap {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      min-width: 900px;
    }
    thead th {
      background: #f0f4fa;
      border-bottom: 2px solid var(--border);
      padding: 10px 12px;
      text-align: right;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      color: var(--text-sm);
      white-space: nowrap;
      position: sticky;
      top: 0;
    }
    thead th:first-child,
    thead th:nth-child(2) { text-align: left; }
    tbody tr:nth-child(even) { background: #fafbfc; }
    tbody tr:hover { background: #edf3fb; }
    tbody td {
      padding: 9px 12px;
      border-bottom: 1px solid #f0f0f0;
      white-space: nowrap;
      text-align: right;
    }
    tbody td:first-child,
    tbody td:nth-child(2) { text-align: left; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
    tfoot td {
      padding: 10px 12px;
      background: #e8f0fe;
      font-weight: 700;
      border-top: 2px solid var(--blue);
      text-align: right;
      white-space: nowrap;
    }
    tfoot td:first-child,
    tfoot td:nth-child(2) { text-align: left; color: var(--blue); font-size: 12px; text-transform: uppercase; }
    .col-diff-pos { color: var(--red);   font-weight: 600; }
    .col-diff-neg { color: var(--green); font-weight: 600; }
    .col-zero     { color: #bbb; }

    /* ── History Table ─────────────────────────────────────── */
    .hist-table thead th { text-align: left; }
    .hist-table tbody td { text-align: left; }

    /* ── Empty State ───────────────────────────────────────── */
    .empty {
      text-align: center;
      padding: 48px 20px;
      color: var(--text-sm);
    }
    .empty-icon {
      font-size: 40px;
      opacity: .3;
      margin-bottom: 10px;
    }

    /* ── Info bar ──────────────────────────────────────────── */
    .info-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }
    .info-bar span { font-size: 13px; color: var(--text-sm); }
    .info-bar strong { color: var(--text); }

    /* ── Totales resumen IVA ───────────────────────────────── */
    .iva-summary {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 6px;
    }
    .iva-chip {
      background: #e8f0fe;
      border: 1px solid #c5d8f8;
      border-radius: 6px;
      padding: 8px 14px;
      text-align: center;
      min-width: 100px;
    }
    .iva-chip .chip-val  { font-size: 16px; font-weight: 700; color: var(--blue); }
    .iva-chip .chip-lbl  { font-size: 11px; color: var(--text-sm); margin-top: 2px; }
    .iva-chip.total      { background: #e8f5e9; border-color: #a5d6a7; }
    .iva-chip.total .chip-val { color: var(--green); }

    /* ── Utility ───────────────────────────────────────────── */
    .hidden   { display: none !important; }
    .mt-4     { margin-top: 4px; }
    .mt-12    { margin-top: 12px; }
    .flex-gap { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .text-sm  { font-size: 12px; color: var(--text-sm); }
    .nota     {
      font-size: 12px;
      background: var(--yellow-lt);
      border: 1px solid #ffe082;
      border-radius: 6px;
      padding: 8px 12px;
      color: #5f4700;
      margin-top: 10px;
    }
  </style>
</head>
<body>

<header>
  <div>
    <h1>IVA Sync &mdash; Facturas Electrónicas</h1>
    <p>Sincronización y reporte de IVA &bull; Costa Rica</p>
  </div>
  <span class="header-badge">v1.0</span>
</header>

<div class="container">

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" data-tab="sync">Sincronizar</button>
    <button class="tab"        data-tab="report">Reporte</button>
    <button class="tab"        data-tab="history">Historial</button>
  </div>

  <!-- ════════════════════════════════════════
       TAB 1: SINCRONIZAR
  ════════════════════════════════════════ -->
  <div id="tab-sync" class="tab-content active">

    <!-- Formulario -->
    <div class="card">
      <div class="card-title">Rango de fechas a sincronizar</div>
      <div class="form-row">
        <div class="form-group">
          <label for="s-from">Desde</label>
          <input type="date" id="s-from">
        </div>
        <div class="form-group">
          <label for="s-to">Hasta</label>
          <input type="date" id="s-to">
        </div>
        <button class="btn btn-primary" id="btn-sync">Sincronizar</button>
      </div>
      <p class="text-sm mt-4">
        El sistema buscara correos con un buffer de &plusmn;5 dias y validara la fecha de emision del XML.
      </p>
    </div>

    <!-- Progreso (oculto hasta iniciar) -->
    <div class="card hidden" id="sync-progress-card">
      <div class="card-title">Progreso de sincronizacion
        <span id="sync-badge" class="badge badge-pending" style="margin-left:8px;">Pendiente</span>
      </div>
      <div class="progress-wrap">
        <div class="progress-label">
          <span id="prog-text">0 de 0 correos</span>
          <span id="prog-pct">0%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" id="prog-fill"></div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="card hidden" id="sync-stats-card">
      <div class="card-title">Estadisticas</div>
      <div class="stats-grid" id="stats-grid">
        <div class="stat-card">
          <div class="stat-value" id="st-total">0</div>
          <div class="stat-label">Correos encontrados</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" id="st-proc">0</div>
          <div class="stat-label">Procesados</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" id="st-xml">0</div>
          <div class="stat-label">XML encontrados</div>
        </div>
        <div class="stat-card green">
          <div class="stat-value" id="st-new">0</div>
          <div class="stat-label">Facturas nuevas</div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-value" id="st-dup">0</div>
          <div class="stat-label">Duplicadas</div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-value" id="st-oor">0</div>
          <div class="stat-label">Fuera de rango</div>
        </div>
        <div class="stat-card red">
          <div class="stat-value" id="st-err">0</div>
          <div class="stat-label">Errores</div>
        </div>
      </div>
    </div>

    <!-- Log -->
    <div class="card hidden" id="sync-log-card">
      <div class="card-title">Log de operaciones</div>
      <div class="log-console" id="log-console"></div>
    </div>

  </div><!-- /tab-sync -->


  <!-- ════════════════════════════════════════
       TAB 2: REPORTE
  ════════════════════════════════════════ -->
  <div id="tab-report" class="tab-content">

    <!-- Filtros -->
    <div class="card">
      <div class="card-title">Generar reporte de IVA</div>
      <div class="form-row">
        <div class="form-group">
          <label for="r-from">Desde</label>
          <input type="date" id="r-from">
        </div>
        <div class="form-group">
          <label for="r-to">Hasta</label>
          <input type="date" id="r-to">
        </div>
        <button class="btn btn-primary" id="btn-report">Generar reporte</button>
      </div>
      <p class="text-sm mt-4">
        Solo se muestran facturas cuya <strong>FechaEmision del XML</strong> este dentro del rango indicado.
      </p>
    </div>

    <!-- Resumen IVA (oculto hasta generar) -->
    <div class="card hidden" id="report-summary-card">
      <div class="card-title">Resumen de IVA del periodo</div>
      <div class="iva-summary" id="iva-summary"></div>
      <div class="nota" id="report-nota" style="display:none"></div>
    </div>

    <!-- Tabla (oculto hasta generar) -->
    <div class="card hidden" id="report-table-card">
      <div class="info-bar">
        <span id="report-count">0 facturas</span>
        <div class="flex-gap">
          <button class="btn btn-outline hidden" id="btn-export">Exportar CSV</button>
        </div>
      </div>

      <div class="table-wrap">
        <table id="report-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Emisor</th>
              <th>Base gravada</th>
              <th>Exento</th>
              <th>IVA 1%</th>
              <th>IVA 2%</th>
              <th>IVA 4%</th>
              <th>IVA 10%</th>
              <th>IVA 13%</th>
              <th>No sujeto</th>
              <th>Total</th>
              <th>Diferencia</th>
            </tr>
          </thead>
          <tbody id="report-tbody"></tbody>
          <tfoot id="report-tfoot"></tfoot>
        </table>
      </div>

      <div class="nota">
        <strong>Nota:</strong> La columna <em>Diferencia</em> muestra discrepancias de redondeo entre la
        suma de IVA por linea del XML vs. el TotalImpuesto del ResumenFactura. Valores cercanos a cero son normales.
        Las columnas <em>Motivo</em> y <em>Categoria</em> estan pendientes de implementar (requieren reglas por emisor).
      </div>
    </div>

    <!-- Empty state inicial -->
    <div class="card" id="report-empty">
      <div class="empty">
        <div class="empty-icon">[ ]</div>
        <p>Selecciona un rango de fechas y presiona <strong>Generar reporte</strong>.</p>
      </div>
    </div>

  </div><!-- /tab-report -->


  <!-- ════════════════════════════════════════
       TAB 3: HISTORIAL
  ════════════════════════════════════════ -->
  <div id="tab-history" class="tab-content">

    <div class="card">
      <div class="card-title" style="display:flex;align-items:center;justify-content:space-between;">
        Ultimas 30 sincronizaciones
        <button class="btn btn-outline" id="btn-refresh-hist" style="font-size:12px;padding:5px 12px;">Actualizar</button>
      </div>

      <div class="table-wrap">
        <table class="hist-table" id="hist-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Iniciado</th>
              <th>Rango</th>
              <th>Estado</th>
              <th>Correos</th>
              <th>XML</th>
              <th>Nuevas</th>
              <th>Dups</th>
              <th>F. Rango</th>
              <th>Errores</th>
              <th>Duracion</th>
            </tr>
          </thead>
          <tbody id="hist-tbody">
            <tr><td colspan="11" style="text-align:center;padding:24px;color:#999;">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /tab-history -->

</div><!-- /container -->

<script>
/* ────────────────────────────────────────────
   UTILIDADES GLOBALES
───────────────────────────────────────────── */
const $ = id => document.getElementById(id);

function fmt(n) {
  if (n === null || n === undefined) return '—';
  const v = parseFloat(n);
  if (isNaN(v)) return '—';
  return v.toLocaleString('es-CR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(s) {
  if (!s) return '—';
  return s.split(' ')[0]; // solo parte YYYY-MM-DD
}

async function post(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data || {})
  });
  return r.json();
}

function badgeHtml(status) {
  const map = {
    running:   'badge-running',
    done:      'badge-done',
    failed:    'badge-failed',
    pending:   'badge-pending',
    cancelled: 'badge-pending',
  };
  return `<span class="badge ${map[status] || 'badge-pending'}">${status}</span>`;
}

/* ────────────────────────────────────────────
   TABS
───────────────────────────────────────────── */
let histLoaded = false;

document.querySelectorAll('.tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    const id = 'tab-' + btn.dataset.tab;
    document.getElementById(id).classList.add('active');
    if (btn.dataset.tab === 'history' && !histLoaded) {
      loadHistory();
      histLoaded = true;
    }
  });
});

/* ────────────────────────────────────────────
   TAB 1: SINCRONIZAR
───────────────────────────────────────────── */
const logEl = $('log-console');

function logLine(msg) {
  const ts   = new Date().toLocaleTimeString('es-CR');
  const cls  = msg.startsWith('OK')   ? 'log-ok'
             : msg.startsWith('DUP')  ? 'log-dup'
             : msg.startsWith('ERR')  ? 'log-err'
             : msg.startsWith('SKIP') ? 'log-skip'
             : 'log-info';
  const line = document.createElement('span');
  line.className = `log-line ${cls}`;
  line.innerHTML = `<span class="log-ts">[${ts}]</span>${escHtml(msg)}`;
  logEl.prepend(line);
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function updateProgress(state) {
  const total = parseInt(state.total_messages) || 0;
  const proc  = parseInt(state.processed_messages) || 0;
  const pct   = total > 0 ? Math.min(100, Math.round(proc / total * 100)) : 0;
  $('prog-fill').style.width = pct + '%';
  $('prog-fill').className = 'progress-fill' + (state.status === 'done' ? ' done' : '');
  $('prog-text').textContent = `${proc} de ${total} correos`;
  $('prog-pct').textContent  = pct + '%';

  const badge = $('sync-badge');
  badge.className = 'badge ' + (
    state.status === 'done'    ? 'badge-done'    :
    state.status === 'running' ? 'badge-running' :
    state.status === 'failed'  ? 'badge-failed'  : 'badge-pending'
  );
  badge.textContent = state.status;
}

function updateStats(state) {
  $('st-total').textContent = state.total_messages     || 0;
  $('st-proc').textContent  = state.processed_messages || 0;
  $('st-xml').textContent   = state.found_xml          || 0;
  $('st-new').textContent   = state.new_invoices        || 0;
  $('st-dup').textContent   = state.duplicates          || 0;
  $('st-oor').textContent   = state.out_of_range        || 0;
  $('st-err').textContent   = state.errors              || 0;
}

$('btn-sync').addEventListener('click', async () => {
  const from = $('s-from').value;
  const to   = $('s-to').value;
  if (!from || !to) { alert('Selecciona el rango de fechas.'); return; }
  if (from > to)    { alert('La fecha "Desde" no puede ser mayor que "Hasta".'); return; }

  const btn = $('btn-sync');
  btn.disabled = true;
  btn.textContent = 'Sincronizando...';

  // Mostrar cards
  ['sync-progress-card','sync-stats-card','sync-log-card'].forEach(id => {
    $(id).classList.remove('hidden');
  });
  logEl.innerHTML = '';
  $('prog-fill').style.width = '0%';

  try {
    logLine('Conectando a Gmail y buscando correos...');
    const start = await post('../api/start_sync.php', { from, to });
    if (!start.ok) { logLine('ERR ' + start.error); btn.disabled = false; btn.textContent = 'Sincronizar'; return; }

    logLine(`Sync #${start.sync_run_id} creado. ${start.total_messages} correo(s) encontrado(s).`);
    updateProgress(start.state);
    updateStats(start.state);

    // Loop por lotes
    while (true) {
      const resp = await post('../api/process_next.php', { sync_run_id: start.sync_run_id });
      if (!resp.ok) { logLine('ERR ' + resp.error); break; }

      updateProgress(resp.state);
      updateStats(resp.state);
      (resp.last_items || []).forEach(it => logLine(it));

      if (resp.state.status === 'done' || resp.state.status === 'failed') {
        logLine('-- Sincronizacion finalizada. ' + resp.state.new_invoices + ' factura(s) nueva(s) guardada(s). --');
        histLoaded = false; // reset para recargar historial
        break;
      }
    }
  } catch (e) {
    logLine('ERR (JS) ' + e.message);
  }

  btn.disabled = false;
  btn.textContent = 'Sincronizar';
});

/* ────────────────────────────────────────────
   TAB 2: REPORTE
───────────────────────────────────────────── */
let reportData = null;
let reportRange = { from: '', to: '' };

$('btn-report').addEventListener('click', async () => {
  const from = $('r-from').value;
  const to   = $('r-to').value;
  if (!from || !to) { alert('Selecciona el rango de fechas.'); return; }
  if (from > to)    { alert('La fecha "Desde" no puede ser mayor que "Hasta".'); return; }

  const btn = $('btn-report');
  btn.disabled = true;
  btn.textContent = 'Generando...';

  try {
    const resp = await post('../api/report.php', { from, to });
    if (!resp.ok) { alert('Error: ' + resp.error); btn.disabled = false; btn.textContent = 'Generar reporte'; return; }

    reportData  = resp;
    reportRange = { from, to };
    renderReport(resp);
  } catch(e) {
    alert('Error de conexion: ' + e.message);
  }

  btn.disabled = false;
  btn.textContent = 'Generar reporte';
});

function renderReport(resp) {
  const { rows, totals, count } = resp;

  // Ocultar empty, mostrar cards
  $('report-empty').classList.add('hidden');
  $('report-summary-card').classList.remove('hidden');
  $('report-table-card').classList.remove('hidden');

  // Resumen IVA
  const summary = $('iva-summary');
  summary.innerHTML = [
    { lbl: 'Base Gravada', val: totals.total_gravado },
    { lbl: 'IVA 1%',      val: totals.iva_1  },
    { lbl: 'IVA 2%',      val: totals.iva_2  },
    { lbl: 'IVA 4%',      val: totals.iva_4  },
    { lbl: 'IVA 10%',     val: totals.iva_10 },
    { lbl: 'IVA 13%',     val: totals.iva_13 },
    { lbl: 'IVA TOTAL',   val: totals.iva_total, cls: 'total' },
    { lbl: 'Total Comprobantes', val: totals.total_comprobante, cls: 'total' },
  ].map(c => `
    <div class="iva-chip ${c.cls || ''}">
      <div class="chip-val">&#x20A1;${fmt(c.val)}</div>
      <div class="chip-lbl">${c.lbl}</div>
    </div>
  `).join('');

  // Info bar
  $('report-count').innerHTML = `<strong>${count}</strong> factura(s) en el periodo`;
  $('btn-export').classList.remove('hidden');

  // Tbody
  const tbody = $('report-tbody');
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:32px;color:#999;">No hay facturas en este rango.</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => {
      const diff = parseFloat(r.diferencia) || 0;
      const diffCls = Math.abs(diff) < 0.01 ? 'col-zero'
                    : diff > 0 ? 'col-diff-pos' : 'col-diff-neg';
      return `<tr>
        <td>${fmtDate(r.fecha)}</td>
        <td title="${escHtml(r.emisor || '')}">${escHtml(r.emisor || '—')}</td>
        <td>${fmt(r.total_gravado)}</td>
        <td>${fmt(r.total_exento)}</td>
        <td>${fmt(r.iva_1)}</td>
        <td>${fmt(r.iva_2)}</td>
        <td>${fmt(r.iva_4)}</td>
        <td>${fmt(r.iva_10)}</td>
        <td>${fmt(r.iva_13)}</td>
        <td>${fmt(r.no_sujeto_base)}</td>
        <td><strong>${fmt(r.total_comprobante)}</strong></td>
        <td class="${diffCls}">${diff === 0 ? '0.00' : fmt(diff)}</td>
      </tr>`;
    }).join('');
  }

  // Tfoot totales
  $('report-tfoot').innerHTML = `<tr>
    <td>TOTAL (${count})</td>
    <td></td>
    <td>${fmt(totals.total_gravado)}</td>
    <td>${fmt(totals.total_exento)}</td>
    <td>${fmt(totals.iva_1)}</td>
    <td>${fmt(totals.iva_2)}</td>
    <td>${fmt(totals.iva_4)}</td>
    <td>${fmt(totals.iva_10)}</td>
    <td>${fmt(totals.iva_13)}</td>
    <td>${fmt(totals.no_sujeto_base)}</td>
    <td>${fmt(totals.total_comprobante)}</td>
    <td>${fmt(totals.diferencia)}</td>
  </tr>`;
}

// Exportar CSV
$('btn-export').addEventListener('click', () => {
  if (!reportData) return;
  const { rows, totals } = reportData;

  const headers = ['Fecha','Emisor','Cedula','Base Gravada','Exento',
                   'IVA 1%','IVA 2%','IVA 4%','IVA 10%','IVA 13%',
                   'No Sujeto','Total','Diferencia'];

  const esc = v => {
    if (v === null || v === undefined) return '';
    const s = String(v);
    return s.includes(',') || s.includes('"') || s.includes('\n')
      ? '"' + s.replace(/"/g,'""') + '"'
      : s;
  };

  const lines = [headers.join(',')];
  for (const r of rows) {
    lines.push([
      r.fecha, esc(r.emisor), r.cedula,
      r.total_gravado, r.total_exento,
      r.iva_1, r.iva_2, r.iva_4, r.iva_10, r.iva_13,
      r.no_sujeto_base, r.total_comprobante, r.diferencia
    ].join(','));
  }
  lines.push([
    'TOTAL','','',
    totals.total_gravado, totals.total_exento,
    totals.iva_1, totals.iva_2, totals.iva_4, totals.iva_10, totals.iva_13,
    totals.no_sujeto_base, totals.total_comprobante, totals.diferencia
  ].join(','));

  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `reporte_iva_${reportRange.from}_${reportRange.to}.csv`;
  a.click();
  URL.revokeObjectURL(url);
});

/* ────────────────────────────────────────────
   TAB 3: HISTORIAL
───────────────────────────────────────────── */
async function loadHistory() {
  $('hist-tbody').innerHTML = '<tr><td colspan="11" style="text-align:center;padding:24px;color:#999;">Cargando...</td></tr>';
  try {
    const resp = await post('../api/history.php', {});
    if (!resp.ok) {
      $('hist-tbody').innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--red);padding:20px;">Error: ${escHtml(resp.error)}</td></tr>`;
      return;
    }
    const runs = resp.runs;
    if (!runs.length) {
      $('hist-tbody').innerHTML = '<tr><td colspan="11" style="text-align:center;padding:32px;color:#999;">No hay sincronizaciones aun.</td></tr>';
      return;
    }
    $('hist-tbody').innerHTML = runs.map(r => {
      const dur = r.duracion_seg !== null
        ? (r.duracion_seg >= 60
            ? Math.floor(r.duracion_seg/60) + 'm ' + (r.duracion_seg%60) + 's'
            : r.duracion_seg + 's')
        : '—';
      return `<tr>
        <td>${r.id}</td>
        <td>${fmtDate(r.started_at) || '—'}</td>
        <td>${r.from_date} / ${r.to_date}</td>
        <td>${badgeHtml(r.status)}</td>
        <td>${r.total_messages}</td>
        <td>${r.found_xml}</td>
        <td style="color:var(--green);font-weight:600">${r.new_invoices}</td>
        <td style="color:var(--yellow)">${r.duplicates}</td>
        <td>${r.out_of_range}</td>
        <td style="color:var(--red)">${r.errors}</td>
        <td>${dur}</td>
      </tr>`;
    }).join('');
  } catch(e) {
    $('hist-tbody').innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--red);padding:20px;">Error JS: ${escHtml(e.message)}</td></tr>`;
  }
}

$('btn-refresh-hist').addEventListener('click', () => { histLoaded = false; loadHistory(); histLoaded = true; });
</script>

</body>
</html>
