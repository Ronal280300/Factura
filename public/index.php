<?php
require_once __DIR__ . '/../app/bootstrap.php';
if (Auth::countUsers() === 0) { header('Location: setup.php'); exit; }
Auth::requireAuth('login.php');
$__user = Auth::user();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IVA Sync &mdash; Facturas Electrónicas CR</title>
  <style>
    /* ── Variables ─────────────────────────────────────────── */
    :root {
      --blue:       #2563eb;
      --blue-dark:  #1d4ed8;
      --blue-lt:    #eff6ff;
      --green:      #16a34a;
      --green-lt:   #f0fdf4;
      --yellow:     #d97706;
      --yellow-lt:  #fffbeb;
      --red:        #dc2626;
      --red-lt:     #fef2f2;
      --purple:     #7c3aed;
      --purple-lt:  #f5f3ff;
      --gray:       #64748b;
      --gray-lt:    #f8fafc;
      --border:     #e2e8f0;
      --border-dark:#cbd5e1;
      --text:       #0f172a;
      --text-muted: #64748b;
      --card-bg:    #ffffff;
      --shadow-sm:  0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
      --shadow:     0 4px 6px -1px rgba(0,0,0,.07), 0 2px 4px -2px rgba(0,0,0,.07);
      --shadow-lg:  0 10px 15px -3px rgba(0,0,0,.08), 0 4px 6px -4px rgba(0,0,0,.08);
      --radius:     10px;
      --radius-sm:  6px;
      --font:       system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
    }

    /* ── Reset ─────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: #f1f5f9; color: var(--text); font-size: 14px; min-height: 100vh; }
    button { cursor: pointer; font-family: inherit; }
    input, select  { font-family: inherit; }

    /* ── Header ────────────────────────────────────────────── */
    header {
      background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #3b82f6 100%);
      color: #fff;
      padding: 0 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 64px;
      box-shadow: 0 4px 12px rgba(37,99,235,.35);
    }
    .header-left { display: flex; align-items: center; gap: 14px; }
    .header-icon {
      width: 38px; height: 38px;
      background: rgba(255,255,255,.18);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
    }
    header h1  { font-size: 17px; font-weight: 700; letter-spacing: .2px; }
    header p   { font-size: 12px; opacity: .78; margin-top: 1px; }
    .header-badge {
      background: rgba(255,255,255,.2);
      border: 1px solid rgba(255,255,255,.3);
      border-radius: 20px;
      padding: 3px 12px;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .6px;
      text-transform: uppercase;
    }

    /* ── Layout ────────────────────────────────────────────── */
    .container { max-width: 1380px; margin: 0 auto; padding: 28px 24px; }

    /* ── Tabs ──────────────────────────────────────────────── */
    .tabs {
      display: flex;
      gap: 2px;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 5px;
      margin-bottom: 22px;
      width: fit-content;
      box-shadow: var(--shadow-sm);
    }
    .tab {
      background: none;
      border: none;
      padding: 9px 24px;
      border-radius: 7px;
      font-size: 14px;
      font-weight: 500;
      color: var(--text-muted);
      transition: background .15s, color .15s;
      display: flex; align-items: center; gap: 6px;
    }
    .tab:hover    { background: var(--gray-lt); color: var(--text); }
    .tab.active   { background: var(--blue); color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.3); }
    .tab-content  { display: none; }
    .tab-content.active { display: block; }

    /* ── Sub-tabs ──────────────────────────────────────────── */
    .sub-tabs {
      display: flex;
      gap: 0;
      border-bottom: 2px solid var(--border);
      margin-bottom: 20px;
    }
    .sub-tab {
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      padding: 10px 20px;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-muted);
      transition: color .15s, border-color .15s;
    }
    .sub-tab:hover  { color: var(--blue); }
    .sub-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
    .sub-content    { display: none; }
    .sub-content.active { display: block; }

    /* ── Card ──────────────────────────────────────────────── */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      padding: 22px 26px;
      margin-bottom: 18px;
    }
    .card-title {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: var(--text-muted);
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
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
      font-size: 11px;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    input[type="date"] {
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 9px 13px;
      font-size: 14px;
      color: var(--text);
      outline: none;
      transition: border-color .15s, box-shadow .15s;
      background: #fff;
    }
    input[type="date"]:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }

    /* ── Buttons ───────────────────────────────────────────── */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 20px;
      border-radius: var(--radius-sm);
      font-size: 13px;
      font-weight: 600;
      border: none;
      transition: all .15s;
      letter-spacing: .2px;
    }
    .btn-primary { background: var(--blue); color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,.25); }
    .btn-primary:hover { background: var(--blue-dark); box-shadow: 0 4px 8px rgba(37,99,235,.3); transform: translateY(-1px); }
    .btn-success { background: var(--green); color: #fff; box-shadow: 0 2px 4px rgba(22,163,74,.25); }
    .btn-success:hover { background: #15803d; transform: translateY(-1px); }
    .btn-outline {
      background: #fff;
      color: var(--blue);
      border: 1.5px solid var(--blue);
    }
    .btn-outline:hover { background: var(--blue-lt); }
    .btn-sm { padding: 5px 12px; font-size: 12px; }
    .btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

    /* ── Progress ──────────────────────────────────────────── */
    .progress-wrap { margin-top: 6px; }
    .progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      color: var(--text-muted);
      margin-bottom: 8px;
    }
    .progress-bar {
      height: 10px;
      background: #e2e8f0;
      border-radius: 99px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, var(--blue), #60a5fa);
      border-radius: 99px;
      transition: width .4s ease;
    }
    .progress-fill.done { background: linear-gradient(90deg, var(--green), #4ade80); }

    /* ── Stats Grid ────────────────────────────────────────── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 12px;
      margin-top: 4px;
    }
    .stat-card {
      background: var(--gray-lt);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
      text-align: center;
      transition: box-shadow .15s;
    }
    .stat-card:hover { box-shadow: var(--shadow); }
    .stat-value {
      font-size: 28px;
      font-weight: 800;
      line-height: 1;
      color: var(--text);
    }
    .stat-label {
      font-size: 11px;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-top: 6px;
    }
    .stat-card.green  { border-color: #86efac; background: var(--green-lt); }
    .stat-card.green  .stat-value { color: var(--green); }
    .stat-card.yellow { border-color: #fcd34d; background: var(--yellow-lt); }
    .stat-card.yellow .stat-value { color: var(--yellow); }
    .stat-card.red    { border-color: #fca5a5; background: var(--red-lt); }
    .stat-card.red    .stat-value { color: var(--red); }

    /* ── Badge ─────────────────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 10px;
      border-radius: 99px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .4px;
      text-transform: uppercase;
    }
    .badge-running { background: #dbeafe; color: #1d4ed8; }
    .badge-done    { background: #dcfce7; color: var(--green); }
    .badge-failed  { background: #fee2e2; color: var(--red); }
    .badge-pending { background: #f1f5f9; color: var(--gray); }

    /* ── Log Console ───────────────────────────────────────── */
    .log-console {
      background: #0f172a;
      color: #94a3b8;
      border-radius: var(--radius);
      padding: 14px 16px;
      height: 210px;
      overflow-y: auto;
      font-family: "Consolas", "Courier New", monospace;
      font-size: 12px;
      line-height: 1.7;
    }
    .log-line  { display: block; }
    .log-ok    { color: #4ade80; }
    .log-dup   { color: #fbbf24; }
    .log-err   { color: #f87171; }
    .log-skip  { color: #475569; }
    .log-info  { color: #38bdf8; }
    .log-ts    { color: #334155; margin-right: 8px; }

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
      background: #f8fafc;
      border-bottom: 2px solid var(--border);
      padding: 11px 13px;
      text-align: right;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--text-muted);
      white-space: nowrap;
      position: sticky;
      top: 0;
      z-index: 1;
    }
    thead th:first-child,
    thead th:nth-child(2) { text-align: left; }
    thead th.col-ctrl { text-align: center; background: #f0f7ff; color: var(--blue); }
    tbody tr { transition: background .1s; }
    tbody tr:nth-child(even) { background: #fafbfd; }
    tbody tr:hover { background: #eff6ff; }
    tbody tr.row-excluded { opacity: .45; text-decoration: line-through; background: #fef2f2 !important; }
    tbody td {
      padding: 10px 13px;
      border-bottom: 1px solid #f1f5f9;
      white-space: nowrap;
      text-align: right;
    }
    tbody td:first-child,
    tbody td:nth-child(2) { text-align: left; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
    tbody td.col-ctrl { text-align: center; }
    tfoot td {
      padding: 11px 13px;
      background: #eff6ff;
      font-weight: 700;
      border-top: 2px solid var(--blue);
      text-align: right;
      white-space: nowrap;
      color: var(--blue-dark);
    }
    tfoot td:first-child,
    tfoot td:nth-child(2) { text-align: left; color: var(--blue); font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
    tfoot td.col-ctrl { background: #f0f7ff; }
    tfoot tr.tr-proporcion td {
      background: #f8fafc;
      border-top: 1px dashed #bfdbfe;
      color: var(--gray);
      font-size: 11px;
      font-weight: 600;
    }
    tfoot tr.tr-proporcion td:first-child,
    tfoot tr.tr-proporcion td:nth-child(2) { color: var(--gray); }

    .col-zero { color: #cbd5e1; }

    /* ── Info button ───────────────────────────────────────── */
    .info-btn {
      background: none;
      border: none;
      color: var(--blue);
      font-size: 13px;
      cursor: pointer;
      padding: 0 2px;
      opacity: .65;
      transition: opacity .15s;
      vertical-align: middle;
      line-height: 1;
    }
    .info-btn:hover { opacity: 1; }

    /* ── Control columns ───────────────────────────────────── */
    .toggle-activa {
      display: inline-flex; align-items: center; gap: 5px;
      cursor: pointer; font-size: 12px; color: var(--text-muted);
    }
    .toggle-activa input[type="checkbox"] { accent-color: var(--blue); width: 15px; height: 15px; cursor: pointer; }
    .select-tipo {
      border: 1.5px solid var(--border);
      border-radius: 5px;
      padding: 4px 8px;
      font-size: 12px;
      color: var(--text);
      background: #fff;
      cursor: pointer;
      outline: none;
      transition: border-color .15s;
    }
    .select-tipo:focus { border-color: var(--blue); }
    .select-tipo.overridden { border-color: var(--purple); color: var(--purple); background: var(--purple-lt); font-weight: 600; }

    /* ── Resumen combinado ─────────────────────────────────── */
    .combinado-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      gap: 14px;
      margin-top: 8px;
    }
    .comb-card {
      background: var(--blue-lt);
      border: 1.5px solid #bfdbfe;
      border-radius: var(--radius);
      padding: 18px;
      transition: box-shadow .15s, transform .15s;
    }
    .comb-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
    .comb-card .comb-val { font-size: 20px; font-weight: 800; color: var(--blue); line-height: 1.2; }
    .comb-card .comb-lbl { font-size: 12px; font-weight: 600; color: var(--text); margin-top: 6px; }
    .comb-card .comb-desc { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
    .comb-card.green-card { background: var(--green-lt); border-color: #86efac; }
    .comb-card.green-card .comb-val { color: var(--green); }

    /* ── History Table ─────────────────────────────────────── */
    .hist-table thead th { text-align: left; }
    .hist-table tbody td { text-align: left; }

    /* ── Empty State ───────────────────────────────────────── */
    .empty {
      text-align: center;
      padding: 56px 20px;
      color: var(--text-muted);
    }
    .empty-icon { font-size: 44px; opacity: .25; margin-bottom: 12px; }
    .empty p { font-size: 14px; }

    /* ── Info bar ──────────────────────────────────────────── */
    .info-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }
    .info-bar span { font-size: 13px; color: var(--text-muted); }
    .info-bar strong { color: var(--text); }

    /* ── Chips ─────────────────────────────────────────────── */
    .chips-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
    .chip {
      background: var(--blue-lt);
      border: 1.5px solid #bfdbfe;
      border-radius: var(--radius);
      padding: 12px 16px;
      text-align: center;
      min-width: 120px;
      transition: box-shadow .15s;
    }
    .chip:hover { box-shadow: var(--shadow); }
    .chip .chip-val { font-size: 16px; font-weight: 700; color: var(--blue); }
    .chip .chip-lbl { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
    .chip.green-chip { background: var(--green-lt); border-color: #86efac; }
    .chip.green-chip .chip-val { color: var(--green); }

    /* ── Modal ─────────────────────────────────────────────── */
    .modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15,23,42,.55);
      backdrop-filter: blur(4px);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: fadeIn .15s ease;
    }
    .modal-overlay.hidden { display: none; }
    .modal-box {
      background: #fff;
      border-radius: 14px;
      padding: 28px;
      max-width: 640px;
      width: 94%;
      max-height: 82vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px rgba(0,0,0,.2);
      animation: slideUp .2s ease;
    }
    .modal-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--border);
    }
    .modal-title { font-size: 16px; font-weight: 700; color: var(--text); }
    .modal-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
    .modal-close {
      background: #f1f5f9; border: none; border-radius: 8px;
      width: 32px; height: 32px; font-size: 18px; color: var(--gray);
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: background .15s;
    }
    .modal-close:hover { background: #e2e8f0; }
    .modal-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .modal-table th { background: #f8fafc; padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); border-bottom: 2px solid var(--border); }
    .modal-table th:last-child { text-align: right; }
    .modal-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; }
    .modal-table td:last-child { text-align: right; font-weight: 600; color: var(--blue); }
    .modal-table tr.modal-total td { background: #eff6ff; font-weight: 700; border-top: 2px solid var(--blue); color: var(--blue-dark); }
    .modal-table tr.modal-total td:last-child { color: var(--blue-dark); }
    .modal-empty { text-align: center; padding: 32px; color: var(--text-muted); font-style: italic; }

    /* ── Toast ─────────────────────────────────────────────── */
    .toast-container {
      position: fixed; bottom: 24px; right: 24px;
      z-index: 2000;
      display: flex; flex-direction: column; gap: 10px;
      pointer-events: none;
    }
    .toast {
      background: #0f172a;
      color: #e2e8f0;
      padding: 12px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      box-shadow: 0 8px 24px rgba(0,0,0,.25);
      animation: slideIn .25s ease;
      display: flex; align-items: center; gap: 8px;
      max-width: 320px;
    }
    .toast.toast-error { background: #7f1d1d; }
    .toast.toast-success { background: #14532d; }

    /* ── Nota ──────────────────────────────────────────────── */
    .nota {
      font-size: 12px;
      background: var(--yellow-lt);
      border: 1px solid #fde68a;
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      color: #78350f;
      margin-top: 12px;
      line-height: 1.6;
    }

    /* ── Utility ───────────────────────────────────────────── */
    .hidden   { display: none !important; }
    .mt-4     { margin-top: 4px; }
    .mt-12    { margin-top: 12px; }
    .flex-gap { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .text-sm  { font-size: 12px; color: var(--text-muted); }

    /* ── Animations ────────────────────────────────────────── */
    @keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; transform: translateY(8px); } }
  </style>
</head>
<body>

<header>
  <div class="header-left">
    <div class="header-icon">&#x1F9FE;</div>
    <div>
      <h1>IVA Sync &mdash; Facturas Electrónicas</h1>
      <p>Sincronización y reporte de IVA &bull; Costa Rica</p>
    </div>
  </div>
  <span class="header-badge">v1.2</span>
</header>

<div class="container">

  <!-- Tabs principales -->
  <div class="tabs">
    <button class="tab active" data-tab="sync">&#x1F504; Sincronizar</button>
    <button class="tab"        data-tab="report">&#x1F4CA; Reporte</button>
    <button class="tab"        data-tab="history">&#x1F4CB; Historial</button>
  </div>

  <!-- ════════════════════════════════════════
       TAB 1: SINCRONIZAR
  ════════════════════════════════════════ -->
  <div id="tab-sync" class="tab-content active">

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
        <button class="btn btn-primary" id="btn-sync">&#x25B6; Sincronizar</button>
      </div>
      <p class="text-sm mt-4">
        El sistema buscará correos con un buffer de &plusmn;5 días y validará la fecha de emisión del XML.
      </p>
    </div>

    <div class="card hidden" id="sync-progress-card">
      <div class="card-title">
        Progreso de sincronización
        <span id="sync-badge" class="badge badge-pending">Pendiente</span>
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

    <div class="card hidden" id="sync-stats-card">
      <div class="card-title">Estadísticas</div>
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

    <div class="card hidden" id="sync-log-card">
      <div class="card-title">Log de operaciones</div>
      <div class="log-console" id="log-console"></div>
    </div>

  </div><!-- /tab-sync -->


  <!-- ════════════════════════════════════════
       TAB 2: REPORTE
  ════════════════════════════════════════ -->
  <div id="tab-report" class="tab-content">

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
        <button class="btn btn-primary" id="btn-report">&#x1F4CA; Generar reporte</button>
        <button class="btn btn-success hidden" id="btn-export">&#x1F4E5; Exportar Excel</button>
      </div>
      <p class="text-sm mt-4">
        Solo se muestran facturas cuya <strong>FechaEmision del XML</strong> esté dentro del rango.
        Las facturas se clasifican en Bienes o Servicios según la tasa de IVA y unidad de medida.
        Puedes <strong>excluir facturas</strong> o <strong>cambiar su categoría</strong> usando los controles de la tabla.
      </p>
    </div>

    <div class="card" id="report-empty">
      <div class="empty">
        <div class="empty-icon">&#x1F4C4;</div>
        <p>Selecciona un rango de fechas y presiona <strong>Generar reporte</strong>.</p>
      </div>
    </div>

    <div id="report-content" class="hidden">

      <!-- Sub-tabs -->
      <div class="sub-tabs">
        <button class="sub-tab active" data-sub="bienes">&#x1F6D2; Gastos Bienes</button>
        <button class="sub-tab"        data-sub="servicios">&#x1F4BC; Gastos Servicios</button>
        <button class="sub-tab"        data-sub="combinado">&#x1F4D0; Resumen Combinado</button>
      </div>

      <!-- ── SUB: GASTOS BIENES ───────────────────────────── -->
      <div id="sub-bienes" class="sub-content active">
        <div class="card">
          <div class="card-title">Gastos Bienes &mdash; IVA por tasa</div>
          <div class="info-bar">
            <span id="bienes-count">0 facturas</span>
          </div>
          <div class="table-wrap">
            <table id="bienes-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Emisor</th>
                  <th>Base 1%</th>
                  <th>IVA 1%</th>
                  <th>Base 2%</th>
                  <th>IVA 2%</th>
                  <th>Base 4%</th>
                  <th>IVA 4%</th>
                  <th>Base 13%</th>
                  <th>IVA 13%</th>
                  <th>No Sujeto</th>
                  <th>Total IVA</th>
                  <th class="col-ctrl">Activa</th>
                  <th class="col-ctrl">Categoría</th>
                </tr>
              </thead>
              <tbody id="bienes-tbody"></tbody>
              <tfoot id="bienes-tfoot"></tfoot>
            </table>
          </div>
          <div class="nota">
            <strong>Proporción:</strong> Base imponible = IVA pagado &divide; tasa.
            &nbsp;|&nbsp; <strong>Activa:</strong> desmarcar excluye la factura del reporte.
            &nbsp;|&nbsp; <strong>Categoría:</strong> cambiar mueve la factura entre pestañas.
          </div>
        </div>
      </div>

      <!-- ── SUB: GASTOS SERVICIOS ────────────────────────── -->
      <div id="sub-servicios" class="sub-content">
        <div class="card">
          <div class="card-title">Gastos Servicios &mdash; IVA por tasa</div>
          <div class="info-bar">
            <span id="servicios-count">0 facturas</span>
          </div>
          <div class="table-wrap">
            <table id="servicios-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Emisor</th>
                  <th>Base 1%</th>
                  <th>IVA 1%</th>
                  <th>Base 10%</th>
                  <th>IVA 10%</th>
                  <th>Base 13%</th>
                  <th>IVA 13%</th>
                  <th>No Sujeto</th>
                  <th>Total IVA</th>
                  <th class="col-ctrl">Activa</th>
                  <th class="col-ctrl">Categoría</th>
                </tr>
              </thead>
              <tbody id="servicios-tbody"></tbody>
              <tfoot id="servicios-tfoot"></tfoot>
            </table>
          </div>
          <div class="nota">
            <strong>Nota:</strong> IVA 10% aplica exclusivamente a servicios (restaurantes, hoteles).
            IVA 1% puede aparecer tanto en bienes como en servicios.
          </div>
        </div>
      </div>

      <!-- ── SUB: RESUMEN COMBINADO ───────────────────────── -->
      <div id="sub-combinado" class="sub-content">
        <div class="card">
          <div class="card-title">Resumen Combinado &mdash; Lógica de declaración</div>
          <p class="text-sm" style="margin-bottom:16px;">
            Combina bienes + servicios para calcular el total de impuestos del período,
            siguiendo la misma lógica del Excel contable.
          </p>
          <div class="combinado-grid" id="combinado-grid"></div>
          <div class="nota" style="margin-top:18px;">
            <strong>IVA 13% combinado</strong> = IVA 13% bienes + IVA 13% servicios.<br>
            <strong>Proporción 13% total</strong> = IVA 13% combinado &divide; 0.13 = base imponible que generó ese IVA.<br>
            <strong>Total IVA gastos</strong> = todos los IVAs de bienes + servicios (crédito fiscal potencial).
          </div>
        </div>
        <div class="card">
          <div class="card-title">Detalle Bienes</div>
          <div class="chips-row" id="bienes-chips"></div>
        </div>
        <div class="card">
          <div class="card-title">Detalle Servicios</div>
          <div class="chips-row" id="servicios-chips"></div>
        </div>
      </div>

    </div><!-- /report-content -->

  </div><!-- /tab-report -->


  <!-- ════════════════════════════════════════
       TAB 3: HISTORIAL
  ════════════════════════════════════════ -->
  <div id="tab-history" class="tab-content">

    <div class="card">
      <div class="card-title">
        Últimas 30 sincronizaciones
        <button class="btn btn-outline btn-sm" id="btn-refresh-hist">&#x21BA; Actualizar</button>
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
              <th>Duración</th>
            </tr>
          </thead>
          <tbody id="hist-tbody">
            <tr><td colspan="11" style="text-align:center;padding:28px;color:#999;">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /tab-history -->

</div><!-- /container -->

<!-- ── Modal de detalle ──────────────────────────────────── -->
<div class="modal-overlay hidden" id="info-modal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modal-title">Detalle</div>
        <div class="modal-subtitle" id="modal-subtitle"></div>
      </div>
      <button class="modal-close" id="modal-close-btn">&#x2715;</button>
    </div>
    <div id="modal-body"></div>
  </div>
</div>

<!-- ── Toast container ───────────────────────────────────── -->
<div class="toast-container" id="toast-container"></div>

<!-- SheetJS para exportar Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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
  return s.split(' ')[0];
}

function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function zeroFmt(v) {
  const n = parseFloat(v) || 0;
  return n === 0
    ? '<span class="col-zero">—</span>'
    : '&#x20A1;' + fmt(n);
}

function fmtNum(v) {
  const n = parseFloat(v) || 0;
  return n === 0 ? 0 : n;
}

async function post(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data || {})
  });
  const text = await r.text();
  try {
    return JSON.parse(text);
  } catch (_) {
    const preview = text.replace(/<[^>]+>/g,'').trim().slice(0,200) || 'Respuesta no-JSON';
    return { ok: false, error: 'Respuesta inesperada: ' + preview };
  }
}

function badgeHtml(status) {
  const map = { running:'badge-running', done:'badge-done', failed:'badge-failed', pending:'badge-pending', cancelled:'badge-pending' };
  return `<span class="badge ${map[status]||'badge-pending'}">${status}</span>`;
}

/* ── Toasts ─────────────────────────────────────────────── */
function showToast(msg, type = 'default') {
  const tc = $('toast-container');
  const t  = document.createElement('div');
  t.className = 'toast' + (type === 'error' ? ' toast-error' : type === 'success' ? ' toast-success' : '');
  const icon = type === 'error' ? '✗' : type === 'success' ? '✓' : 'ℹ';
  t.innerHTML = `<span>${icon}</span> ${escHtml(msg)}`;
  tc.appendChild(t);
  setTimeout(() => {
    t.style.animation = 'fadeOut .3s ease forwards';
    setTimeout(() => t.remove(), 300);
  }, 3200);
}

/* ── Modal ──────────────────────────────────────────────── */
function closeModal() { $('info-modal').classList.add('hidden'); }
$('modal-close-btn').addEventListener('click', closeModal);
$('info-modal').addEventListener('click', e => { if (e.target === $('info-modal')) closeModal(); });

function showInfoModal(tipo, colKey, colLabel, colUnit) {
  const rows = tipo === 'bienes' ? reportData.bienes.rows : reportData.servicios.rows;
  const contributing = rows.filter(r => parseFloat(r[colKey] || 0) > 0);
  const total = contributing.reduce((s, r) => s + parseFloat(r[colKey] || 0), 0);

  $('modal-title').textContent = colLabel;
  $('modal-subtitle').textContent = (tipo === 'bienes' ? 'Gastos Bienes' : 'Gastos Servicios') + ' · ' + contributing.length + ' factura(s) contribuyen a este total';

  if (contributing.length === 0) {
    $('modal-body').innerHTML = '<p class="modal-empty">No hay facturas con valor en esta columna.</p>';
  } else {
    let html = '<table class="modal-table">';
    html += '<thead><tr><th>Fecha</th><th>Emisor</th><th>Monto</th></tr></thead><tbody>';
    for (const r of contributing) {
      html += `<tr>
        <td>${fmtDate(r.fecha)}</td>
        <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;">${escHtml(r.emisor || '—')}</td>
        <td>&#x20A1;${fmt(r[colKey])}</td>
      </tr>`;
    }
    html += `</tbody><tfoot><tr class="modal-total">
      <td colspan="2"><strong>TOTAL ${escHtml(colLabel)}</strong></td>
      <td>&#x20A1;${fmt(total)}</td>
    </tr></tfoot></table>`;
    $('modal-body').innerHTML = html;
  }

  $('info-modal').classList.remove('hidden');
}

/* ── Generador de botón info ─────────────────────────────── */
function ib(tipo, colKey, colLabel) {
  return `<button class="info-btn" title="Ver detalle de ${escHtml(colLabel)}" onclick="showInfoModal('${tipo}','${colKey}','${escHtml(colLabel)}')">ⓘ</button>`;
}

/* ────────────────────────────────────────────
   TABS PRINCIPALES
───────────────────────────────────────────── */
let histLoaded = false;

document.querySelectorAll('.tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    if (btn.dataset.tab === 'history' && !histLoaded) { loadHistory(); histLoaded = true; }
  });
});

document.querySelectorAll('.sub-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.sub-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.sub-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sub-' + btn.dataset.sub).classList.add('active');
  });
});

/* ────────────────────────────────────────────
   TAB 1: SINCRONIZAR
───────────────────────────────────────────── */
const logEl = $('log-console');

function logLine(msg) {
  const ts  = new Date().toLocaleTimeString('es-CR');
  const cls = msg.startsWith('OK')   ? 'log-ok'
            : msg.startsWith('DUP')  ? 'log-dup'
            : msg.startsWith('ERR')  ? 'log-err'
            : msg.startsWith('SKIP') ? 'log-skip'
            : 'log-info';
  const line = document.createElement('span');
  line.className = `log-line ${cls}`;
  line.innerHTML = `<span class="log-ts">[${ts}]</span>${escHtml(msg)}`;
  logEl.prepend(line);
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
  if (!from || !to) { showToast('Selecciona el rango de fechas.', 'error'); return; }
  if (from > to)    { showToast('La fecha "Desde" no puede ser mayor que "Hasta".', 'error'); return; }

  const btn = $('btn-sync');
  btn.disabled = true; btn.textContent = 'Sincronizando...';

  ['sync-progress-card','sync-stats-card','sync-log-card'].forEach(id => $(id).classList.remove('hidden'));
  logEl.innerHTML = '';
  $('prog-fill').style.width = '0%';

  try {
    logLine('Conectando a Gmail y buscando correos...');
    const start = await post('../api/start_sync.php', { from, to });

    if (!start.ok) {
      logLine('ERR ' + (start.error || 'Error en start_sync'));
      btn.disabled = false; btn.textContent = '▶ Sincronizar';
      return;
    }

    if (start.zero_emails || start.total_messages === 0) {
      logLine('INFO No se encontraron correos en el rango ' + from + ' → ' + to + '.');
      logLine('INFO Recuerda que la búsqueda incluye buffer de ±5 días.');
      updateProgress(start.state);
      updateStats(start.state);
      btn.disabled = false; btn.textContent = '▶ Sincronizar';
      return;
    }

    logLine(`Sync #${start.sync_run_id} iniciado. ${start.total_messages} correo(s) en cola.`);
    updateProgress(start.state);
    updateStats(start.state);

    while (true) {
      const resp = await post('../api/process_next.php', { sync_run_id: start.sync_run_id });
      if (!resp.ok) { logLine('ERR ' + (resp.error || 'Error en process_next')); break; }
      updateProgress(resp.state);
      updateStats(resp.state);
      (resp.last_items || []).forEach(it => logLine(it));
      if (resp.state.status === 'done' || resp.state.status === 'failed') {
        const n = resp.state.new_invoices || 0;
        logLine(`-- Sincronización finalizada. ${n} factura(s) nueva(s) guardada(s). --`);
        histLoaded = false;
        showToast(`Sincronización completa: ${n} factura(s) nueva(s)`, 'success');
        break;
      }
    }
  } catch (e) {
    logLine('ERR (JS) ' + e.message);
  }

  btn.disabled = false; btn.textContent = '▶ Sincronizar';
});

/* ────────────────────────────────────────────
   TAB 2: REPORTE
───────────────────────────────────────────── */
let reportData  = null;
let reportRange = { from: '', to: '' };

$('btn-report').addEventListener('click', async () => {
  const from = $('r-from').value;
  const to   = $('r-to').value;
  if (!from || !to) { showToast('Selecciona el rango de fechas.', 'error'); return; }
  if (from > to)    { showToast('"Desde" no puede ser mayor que "Hasta".', 'error'); return; }

  const btn = $('btn-report');
  btn.disabled = true; btn.textContent = 'Generando...';

  try {
    const resp = await post('../api/report.php', { from, to });
    if (!resp.ok) { showToast('Error: ' + resp.error, 'error'); btn.disabled = false; btn.textContent = '📊 Generar reporte'; return; }

    reportData  = resp;
    reportRange = { from, to };
    renderReport(resp);
  } catch(e) {
    showToast('Error de conexión: ' + e.message, 'error');
  }

  btn.disabled = false; btn.textContent = '📊 Generar reporte';
});

async function reloadReport() {
  if (!reportRange.from) return;
  try {
    const resp = await post('../api/report.php', reportRange);
    if (resp.ok) { reportData = resp; renderReport(resp); }
    else showToast('Error al recargar: ' + resp.error, 'error');
  } catch(e) {
    showToast('Error de conexión', 'error');
  }
}

/* ── Toggle excluida ──────────────────────────────────────── */
async function toggleExcluida(invoiceId, exclude) {
  const resp = await post('../api/update_invoice.php', { id: invoiceId, field: 'excluida', value: exclude ? 1 : 0 });
  if (resp.ok) {
    showToast(exclude ? 'Factura excluida del reporte' : 'Factura incluida en el reporte', exclude ? 'default' : 'success');
    await reloadReport();
  } else {
    showToast('Error: ' + resp.error, 'error');
  }
}

/* ── Override tipo ───────────────────────────────────────── */
async function setOverrideTipo(invoiceId, tipo) {
  const resp = await post('../api/update_invoice.php', { id: invoiceId, field: 'override_tipo', value: tipo || null });
  if (resp.ok) {
    const msg = tipo === 'bien' ? 'Factura movida a Bienes'
              : tipo === 'servicio' ? 'Factura movida a Servicios'
              : 'Clasificación automática restaurada';
    showToast(msg, 'success');
    await reloadReport();
  } else {
    showToast('Error: ' + resp.error, 'error');
  }
}

/* ── Render report ────────────────────────────────────────── */
function renderReport(resp) {
  const { bienes, servicios, combinado } = resp;

  $('report-empty').classList.add('hidden');
  $('report-content').classList.remove('hidden');
  $('btn-export').classList.remove('hidden');

  renderBienes(bienes);
  renderServicios(servicios);
  renderCombinado(combinado, bienes.totals, servicios.totals);
}

/* ── Fila de control (Activa + Categoría) ─────────────────── */
function ctrlCells(r) {
  const overridden = r.override_tipo ? 'overridden' : '';
  return `
    <td class="col-ctrl">
      <label class="toggle-activa" title="${r.excluida ? 'Excluida — clic para incluir' : 'Activa — clic para excluir'}">
        <input type="checkbox" ${r.excluida ? '' : 'checked'} onchange="toggleExcluida(${r.id}, !this.checked)">
      </label>
    </td>
    <td class="col-ctrl">
      <select class="select-tipo ${overridden}" onchange="setOverrideTipo(${r.id}, this.value)" title="Categoría de esta factura">
        <option value=""         ${!r.override_tipo                    ? 'selected':''}>Auto</option>
        <option value="bien"     ${r.override_tipo === 'bien'          ? 'selected':''}>Bien</option>
        <option value="servicio" ${r.override_tipo === 'servicio'      ? 'selected':''}>Servicio</option>
      </select>
    </td>`;
}

/* ── Tabla Gastos Bienes ─────────────────────────────────── */
function renderBienes(data) {
  const { rows, totals } = data;
  $('bienes-count').innerHTML = `<strong>${rows.length}</strong> factura(s) con bienes`;

  const tbody = $('bienes-tbody');
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:36px;color:#999;">Sin gastos de bienes en este período.</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => `<tr class="${r.excluida ? 'row-excluded' : ''}">
      <td>${fmtDate(r.fecha)}</td>
      <td title="${escHtml(r.emisor)}">${escHtml(r.emisor || '—')}</td>
      <td>${zeroFmt(r.base_1)}</td>
      <td>${zeroFmt(r.iva_1)}</td>
      <td>${zeroFmt(r.base_2)}</td>
      <td>${zeroFmt(r.iva_2)}</td>
      <td>${zeroFmt(r.base_4)}</td>
      <td>${zeroFmt(r.iva_4)}</td>
      <td>${zeroFmt(r.base_13)}</td>
      <td>${zeroFmt(r.iva_13)}</td>
      <td>${zeroFmt(r.no_sujeto)}</td>
      <td><strong>&#x20A1;${fmt(r.iva_total)}</strong></td>
      ${ctrlCells(r)}
    </tr>`).join('');
  }

  // tfoot con totales correctos por columna + botones info
  const t = totals;
  $('bienes-tfoot').innerHTML = `
    <tr>
      <td>TOTAL</td><td></td>
      <td>&#x20A1;${fmt(t.base_1)} ${ib('bienes','base_1','Base 1% Bienes')}</td>
      <td>&#x20A1;${fmt(t.iva_1)} ${ib('bienes','iva_1','IVA 1% Bienes')}</td>
      <td>&#x20A1;${fmt(t.base_2)} ${ib('bienes','base_2','Base 2% Bienes')}</td>
      <td>&#x20A1;${fmt(t.iva_2)} ${ib('bienes','iva_2','IVA 2% Bienes')}</td>
      <td>&#x20A1;${fmt(t.base_4)} ${ib('bienes','base_4','Base 4% Bienes')}</td>
      <td>&#x20A1;${fmt(t.iva_4)} ${ib('bienes','iva_4','IVA 4% Bienes')}</td>
      <td>&#x20A1;${fmt(t.base_13)} ${ib('bienes','base_13','Base 13% Bienes')}</td>
      <td>&#x20A1;${fmt(t.iva_13)} ${ib('bienes','iva_13','IVA 13% Bienes')}</td>
      <td>&#x20A1;${fmt(t.no_sujeto)} ${ib('bienes','no_sujeto','No Sujeto Bienes')}</td>
      <td><strong>&#x20A1;${fmt(t.iva_total)}</strong> ${ib('bienes','iva_total','Total IVA Bienes')}</td>
      <td class="col-ctrl"></td><td class="col-ctrl"></td>
    </tr>
    <tr class="tr-proporcion">
      <td>PROPORCIÓN</td><td style="color:#94a3b8;font-size:11px;">(base imponible)</td>
      <td colspan="2">${t.proporcion_1  > 0 ? '&#x20A1;'+fmt(t.proporcion_1)  : '—'}</td>
      <td colspan="2">${t.proporcion_2  > 0 ? '&#x20A1;'+fmt(t.proporcion_2)  : '—'}</td>
      <td colspan="2">${t.proporcion_4  > 0 ? '&#x20A1;'+fmt(t.proporcion_4)  : '—'}</td>
      <td colspan="2">${t.proporcion_13 > 0 ? '&#x20A1;'+fmt(t.proporcion_13) : '—'}</td>
      <td>—</td><td>—</td>
      <td class="col-ctrl"></td><td class="col-ctrl"></td>
    </tr>`;
}

/* ── Tabla Gastos Servicios ──────────────────────────────── */
function renderServicios(data) {
  const { rows, totals } = data;
  $('servicios-count').innerHTML = `<strong>${rows.length}</strong> factura(s) con servicios`;

  const tbody = $('servicios-tbody');
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:36px;color:#999;">Sin gastos de servicios en este período.</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => `<tr class="${r.excluida ? 'row-excluded' : ''}">
      <td>${fmtDate(r.fecha)}</td>
      <td title="${escHtml(r.emisor)}">${escHtml(r.emisor || '—')}</td>
      <td>${zeroFmt(r.base_1)}</td>
      <td>${zeroFmt(r.iva_1)}</td>
      <td>${zeroFmt(r.base_10)}</td>
      <td>${zeroFmt(r.iva_10)}</td>
      <td>${zeroFmt(r.base_13)}</td>
      <td>${zeroFmt(r.iva_13)}</td>
      <td>${zeroFmt(r.no_sujeto)}</td>
      <td><strong>&#x20A1;${fmt(r.iva_total)}</strong></td>
      ${ctrlCells(r)}
    </tr>`).join('');
  }

  const t = totals;
  $('servicios-tfoot').innerHTML = `
    <tr>
      <td>TOTAL</td><td></td>
      <td>&#x20A1;${fmt(t.base_1)} ${ib('servicios','base_1','Base 1% Servicios')}</td>
      <td>&#x20A1;${fmt(t.iva_1)} ${ib('servicios','iva_1','IVA 1% Servicios')}</td>
      <td>&#x20A1;${fmt(t.base_10)} ${ib('servicios','base_10','Base 10% Servicios')}</td>
      <td>&#x20A1;${fmt(t.iva_10)} ${ib('servicios','iva_10','IVA 10% Servicios')}</td>
      <td>&#x20A1;${fmt(t.base_13)} ${ib('servicios','base_13','Base 13% Servicios')}</td>
      <td>&#x20A1;${fmt(t.iva_13)} ${ib('servicios','iva_13','IVA 13% Servicios')}</td>
      <td>&#x20A1;${fmt(t.no_sujeto)} ${ib('servicios','no_sujeto','No Sujeto Servicios')}</td>
      <td><strong>&#x20A1;${fmt(t.iva_total)}</strong> ${ib('servicios','iva_total','Total IVA Servicios')}</td>
      <td class="col-ctrl"></td><td class="col-ctrl"></td>
    </tr>
    <tr class="tr-proporcion">
      <td>PROPORCIÓN</td><td style="color:#94a3b8;font-size:11px;">(base imponible)</td>
      <td colspan="2">${t.proporcion_1  > 0 ? '&#x20A1;'+fmt(t.proporcion_1)  : '—'}</td>
      <td colspan="2">${t.proporcion_10 > 0 ? '&#x20A1;'+fmt(t.proporcion_10) : '—'}</td>
      <td colspan="2">${t.proporcion_13 > 0 ? '&#x20A1;'+fmt(t.proporcion_13) : '—'}</td>
      <td>—</td><td>—</td>
      <td class="col-ctrl"></td><td class="col-ctrl"></td>
    </tr>`;
}

/* ── Resumen Combinado ────────────────────────────────────── */
function renderCombinado(combinado, bTotals, sTotals) {
  $('combinado-grid').innerHTML = [
    { lbl:'IVA 13% Combinado',      val:combinado.iva_13_combinado,        desc:'Bienes 13% + Servicios 13%' },
    { lbl:'Proporción 13% Total',   val:combinado.proporcion_13_combinado, desc:'IVA 13% combinado ÷ 0.13' },
    { lbl:'Total IVA Gastos',       val:combinado.total_iva_gastos,        desc:'Todos los IVAs (crédito fiscal)', cls:'green-card' },
    { lbl:'Proporción Total Gastos',val:combinado.proporcion_total,        desc:'Base imponible total del período', cls:'green-card' },
  ].map(c => `
    <div class="comb-card ${c.cls||''}">
      <div class="comb-val">&#x20A1;${fmt(c.val)}</div>
      <div class="comb-lbl">${c.lbl}</div>
      <div class="comb-desc">${c.desc}</div>
    </div>`).join('');

  $('bienes-chips').innerHTML = [
    { lbl:'IVA 1% Bienes',    val:bTotals.iva_1   },
    { lbl:'IVA 2% Bienes',    val:bTotals.iva_2   },
    { lbl:'IVA 4% Bienes',    val:bTotals.iva_4   },
    { lbl:'IVA 13% Bienes',   val:bTotals.iva_13  },
    { lbl:'Total IVA Bienes', val:bTotals.iva_total, cls:'green-chip' },
  ].map(c => `<div class="chip ${c.cls||''}">
    <div class="chip-val">&#x20A1;${fmt(c.val)}</div>
    <div class="chip-lbl">${c.lbl}</div>
  </div>`).join('');

  $('servicios-chips').innerHTML = [
    { lbl:'IVA 1% Servicios',    val:sTotals.iva_1   },
    { lbl:'IVA 10% Servicios',   val:sTotals.iva_10  },
    { lbl:'IVA 13% Servicios',   val:sTotals.iva_13  },
    { lbl:'Total IVA Servicios', val:sTotals.iva_total, cls:'green-chip' },
  ].map(c => `<div class="chip ${c.cls||''}">
    <div class="chip-val">&#x20A1;${fmt(c.val)}</div>
    <div class="chip-lbl">${c.lbl}</div>
  </div>`).join('');
}

/* ── Exportar Excel (SheetJS) ────────────────────────────── */
$('btn-export').addEventListener('click', () => {
  if (!reportData || typeof XLSX === 'undefined') {
    showToast('SheetJS no disponible. Verifique conexión.', 'error');
    return;
  }

  const { bienes, servicios, combinado } = reportData;
  const bt = bienes.totals;
  const st = servicios.totals;
  const wb = XLSX.utils.book_new();

  // Estilo de número contable
  const numFmt = '#,##0.00';

  // ── Hoja Bienes ─────────────────────────────────────────
  const bData = [];
  bData.push(['GASTOS BIENES — IVA POR TASA', '', '', '', '', '', '', '', '', '', '', '']);
  bData.push([`Período: ${reportRange.from} al ${reportRange.to}`]);
  bData.push([]);
  bData.push(['Fecha','Emisor','Base 1%','IVA 1%','Base 2%','IVA 2%','Base 4%','IVA 4%','Base 13%','IVA 13%','No Sujeto','Total IVA']);
  for (const r of bienes.rows) {
    bData.push([r.fecha, r.emisor||'', +r.base_1,+r.iva_1,+r.base_2,+r.iva_2,+r.base_4,+r.iva_4,+r.base_13,+r.iva_13,+r.no_sujeto,+r.iva_total]);
  }
  bData.push(['TOTAL','',+bt.base_1,+bt.iva_1,+bt.base_2,+bt.iva_2,+bt.base_4,+bt.iva_4,+bt.base_13,+bt.iva_13,+bt.no_sujeto,+bt.iva_total]);
  bData.push(['PROPORCIÓN','',+bt.proporcion_1,'',+bt.proporcion_2,'',+bt.proporcion_4,'',+bt.proporcion_13,'','','']);

  const wsB = XLSX.utils.aoa_to_sheet(bData);
  wsB['!cols'] = [
    {wch:12},{wch:34},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14}
  ];
  // Formato numérico para celdas de datos (fila 5 en adelante, columnas C-L)
  const bStart = 5; // fila de encabezado de datos (0-indexed = 3, XLSX row = 4)
  XLSX.utils.book_append_sheet(wb, wsB, 'Gastos Bienes');

  // ── Hoja Servicios ───────────────────────────────────────
  const sData = [];
  sData.push(['GASTOS SERVICIOS — IVA POR TASA', '', '', '', '', '', '', '', '', '']);
  sData.push([`Período: ${reportRange.from} al ${reportRange.to}`]);
  sData.push([]);
  sData.push(['Fecha','Emisor','Base 1%','IVA 1%','Base 10%','IVA 10%','Base 13%','IVA 13%','No Sujeto','Total IVA']);
  for (const r of servicios.rows) {
    sData.push([r.fecha, r.emisor||'', +r.base_1,+r.iva_1,+r.base_10,+r.iva_10,+r.base_13,+r.iva_13,+r.no_sujeto,+r.iva_total]);
  }
  sData.push(['TOTAL','',+st.base_1,+st.iva_1,+st.base_10,+st.iva_10,+st.base_13,+st.iva_13,+st.no_sujeto,+st.iva_total]);
  sData.push(['PROPORCIÓN','',+st.proporcion_1,'',+st.proporcion_10,'',+st.proporcion_13,'','','']);

  const wsS = XLSX.utils.aoa_to_sheet(sData);
  wsS['!cols'] = [
    {wch:12},{wch:34},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14},{wch:14}
  ];
  XLSX.utils.book_append_sheet(wb, wsS, 'Gastos Servicios');

  // ── Hoja Resumen ─────────────────────────────────────────
  const cData = [];
  cData.push(['RESUMEN COMBINADO', '']);
  cData.push([`Período: ${reportRange.from} al ${reportRange.to}`]);
  cData.push([]);
  cData.push(['Concepto', 'Monto (₡)']);
  cData.push(['IVA 13% Combinado (Bienes + Servicios)', +combinado.iva_13_combinado]);
  cData.push(['Proporción 13% Total (IVA 13% ÷ 0.13)',  +combinado.proporcion_13_combinado]);
  cData.push(['Total IVA Gastos (todos los IVAs)',        +combinado.total_iva_gastos]);
  cData.push(['Proporción Total Gastos',                   +combinado.proporcion_total]);
  cData.push([]);
  cData.push(['DETALLE BIENES', '']);
  cData.push(['IVA 1% Bienes',    +bt.iva_1]);
  cData.push(['IVA 2% Bienes',    +bt.iva_2]);
  cData.push(['IVA 4% Bienes',    +bt.iva_4]);
  cData.push(['IVA 13% Bienes',   +bt.iva_13]);
  cData.push(['Total IVA Bienes', +bt.iva_total]);
  cData.push([]);
  cData.push(['DETALLE SERVICIOS', '']);
  cData.push(['IVA 1% Servicios',    +st.iva_1]);
  cData.push(['IVA 10% Servicios',   +st.iva_10]);
  cData.push(['IVA 13% Servicios',   +st.iva_13]);
  cData.push(['Total IVA Servicios', +st.iva_total]);

  const wsC = XLSX.utils.aoa_to_sheet(cData);
  wsC['!cols'] = [{wch:42},{wch:18}];
  XLSX.utils.book_append_sheet(wb, wsC, 'Resumen');

  XLSX.writeFile(wb, `reporte_iva_${reportRange.from}_${reportRange.to}.xlsx`);
  showToast('Archivo Excel generado correctamente', 'success');
});

/* ────────────────────────────────────────────
   TAB 3: HISTORIAL
───────────────────────────────────────────── */
async function loadHistory() {
  $('hist-tbody').innerHTML = '<tr><td colspan="11" style="text-align:center;padding:28px;color:#999;">Cargando...</td></tr>';
  try {
    const resp = await post('../api/history.php', {});
    if (!resp.ok) {
      $('hist-tbody').innerHTML = `<tr><td colspan="11" style="text-align:center;color:var(--red);padding:20px;">Error: ${escHtml(resp.error)}</td></tr>`;
      return;
    }
    const runs = resp.runs;
    if (!runs.length) {
      $('hist-tbody').innerHTML = '<tr><td colspan="11" style="text-align:center;padding:40px;color:#999;">No hay sincronizaciones aún.</td></tr>';
      return;
    }
    $('hist-tbody').innerHTML = runs.map(r => {
      const dur = r.duracion_seg !== null
        ? (r.duracion_seg >= 60 ? Math.floor(r.duracion_seg/60) + 'm ' + (r.duracion_seg%60) + 's' : r.duracion_seg + 's')
        : '—';
      return `<tr>
        <td>${r.id}</td>
        <td>${fmtDate(r.started_at)||'—'}</td>
        <td>${r.from_date} / ${r.to_date}</td>
        <td>${badgeHtml(r.status)}</td>
        <td>${r.total_messages}</td>
        <td>${r.found_xml}</td>
        <td style="color:var(--green);font-weight:700">${r.new_invoices}</td>
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
