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

    /* ── Sub-tabs (dentro de Reporte) ──────────────────────── */
    .sub-tabs {
      display: flex;
      gap: 3px;
      border-bottom: 2px solid var(--border);
      margin-bottom: 18px;
    }
    .sub-tab {
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      padding: 8px 18px;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-sm);
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
      min-width: 800px;
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
    tfoot td:nth-child(2) { text-align: left; color: var(--blue); font-size: 11px; text-transform: uppercase; }
    /* Fila de proporcion en tfoot */
    tfoot tr.tr-proporcion td {
      background: #f0f7ff;
      border-top: 1px dashed #c5d8f8;
      color: var(--gray);
      font-size: 11px;
      font-weight: 600;
    }
    tfoot tr.tr-proporcion td:first-child,
    tfoot tr.tr-proporcion td:nth-child(2) { color: var(--gray); font-size: 11px; }

    .col-zero { color: #bbb; }

    /* ── Resumen combinado ─────────────────────────────────── */
    .combinado-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      margin-top: 8px;
    }
    .comb-card {
      background: #f0f7ff;
      border: 1px solid #c5d8f8;
      border-radius: var(--radius);
      padding: 14px 16px;
    }
    .comb-card .comb-val { font-size: 18px; font-weight: 700; color: var(--blue); }
    .comb-card .comb-lbl { font-size: 11px; color: var(--text-sm); margin-top: 3px; }
    .comb-card.green-card { background: var(--green-lt); border-color: #a5d6a7; }
    .comb-card.green-card .comb-val { color: var(--green); }

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

    /* ── Chips de resumen rápido ───────────────────────────── */
    .chips-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }
    .chip {
      background: #e8f0fe;
      border: 1px solid #c5d8f8;
      border-radius: 6px;
      padding: 8px 14px;
      text-align: center;
      min-width: 110px;
    }
    .chip .chip-val  { font-size: 15px; font-weight: 700; color: var(--blue); }
    .chip .chip-lbl  { font-size: 11px; color: var(--text-sm); margin-top: 2px; }
    .chip.green-chip { background: var(--green-lt); border-color: #a5d6a7; }
    .chip.green-chip .chip-val { color: var(--green); }

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
  <span class="header-badge">v1.1</span>
</header>

<div class="container">

  <!-- Tabs principales -->
  <div class="tabs">
    <button class="tab active" data-tab="sync">Sincronizar</button>
    <button class="tab"        data-tab="report">Reporte</button>
    <button class="tab"        data-tab="history">Historial</button>
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
        <button class="btn btn-primary" id="btn-sync">Sincronizar</button>
      </div>
      <p class="text-sm mt-4">
        El sistema buscara correos con un buffer de &plusmn;5 dias y validara la fecha de emision del XML.
      </p>
    </div>

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
        <button class="btn btn-outline hidden" id="btn-export">Exportar CSV</button>
      </div>
      <p class="text-sm mt-4">
        Solo se muestran facturas cuya <strong>FechaEmision del XML</strong> este dentro del rango indicado.
        Las facturas se clasifican automaticamente en Bienes o Servicios segun la tasa de IVA y unidad de medida.
      </p>
    </div>

    <!-- Empty state inicial -->
    <div class="card" id="report-empty">
      <div class="empty">
        <div class="empty-icon">[ ]</div>
        <p>Selecciona un rango de fechas y presiona <strong>Generar reporte</strong>.</p>
      </div>
    </div>

    <!-- Contenido del reporte (oculto hasta generar) -->
    <div id="report-content" class="hidden">

      <!-- Sub-tabs -->
      <div class="sub-tabs">
        <button class="sub-tab active" data-sub="bienes">Gastos Bienes</button>
        <button class="sub-tab"        data-sub="servicios">Gastos Servicios</button>
        <button class="sub-tab"        data-sub="combinado">Resumen Combinado</button>
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
                </tr>
              </thead>
              <tbody id="bienes-tbody"></tbody>
              <tfoot id="bienes-tfoot"></tfoot>
            </table>
          </div>
          <div class="nota">
            <strong>Proporcion:</strong> Base imponible = IVA pagado &divide; tasa.
            Ejemplo: si IVA 4% = &#x20A1;1,910 &rarr; Base = 1,910 / 0.04 = &#x20A1;47,750.
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
          <div class="card-title">Resumen Combinado &mdash; Logica de declaracion</div>

          <p class="text-sm" style="margin-bottom:14px;">
            Combina bienes + servicios para calcular el total de impuestos de gastos del periodo,
            siguiendo la misma logica del Excel contable.
          </p>

          <div class="combinado-grid" id="combinado-grid"></div>

          <div class="nota" style="margin-top:16px;">
            <strong>IVA 13% combinado</strong> = IVA 13% bienes + IVA 13% servicios &mdash;
            ambas categorias pueden tener 13%, por eso se suman.<br>
            <strong>Proporcion 13% total</strong> = IVA 13% combinado &divide; 0.13 = base imponible que genero ese IVA.<br>
            <strong>Total IVA gastos</strong> = todos los IVAs de bienes + todos los IVAs de servicios (credito fiscal potencial).
          </div>
        </div>

        <!-- Mini resumen bienes -->
        <div class="card">
          <div class="card-title">Detalle Bienes</div>
          <div class="chips-row" id="bienes-chips"></div>
        </div>

        <!-- Mini resumen servicios -->
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
    const preview = text.replace(/<[^>]+>/g, '').trim().slice(0, 200) || 'Respuesta no-JSON del servidor';
    return { ok: false, error: 'Respuesta inesperada del servidor: ' + preview };
  }
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
   TABS PRINCIPALES
───────────────────────────────────────────── */
let histLoaded = false;

document.querySelectorAll('.tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    if (btn.dataset.tab === 'history' && !histLoaded) {
      loadHistory();
      histLoaded = true;
    }
  });
});

/* ────────────────────────────────────────────
   SUB-TABS (dentro de Reporte)
───────────────────────────────────────────── */
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
  if (!from || !to) { alert('Selecciona el rango de fechas.'); return; }
  if (from > to)    { alert('La fecha "Desde" no puede ser mayor que "Hasta".'); return; }

  const btn = $('btn-sync');
  btn.disabled = true;
  btn.textContent = 'Sincronizando...';

  ['sync-progress-card','sync-stats-card','sync-log-card'].forEach(id => {
    $(id).classList.remove('hidden');
  });
  logEl.innerHTML = '';
  $('prog-fill').style.width = '0%';

  try {
    logLine('Conectando a Gmail y buscando correos...');
    const start = await post('../api/start_sync.php', { from, to });

    if (!start.ok) {
      logLine('ERR ' + (start.error || 'Error desconocido en start_sync'));
      btn.disabled = false;
      btn.textContent = 'Sincronizar';
      return;
    }

    if (start.zero_emails || start.total_messages === 0) {
      logLine('INFO No se encontraron correos en el rango ' + from + ' → ' + to + '.');
      logLine('INFO Recuerda que la búsqueda incluye buffer de ±5 días sobre el rango solicitado.');
      logLine('INFO Verifica que el correo configurado recibe facturas XML en ese periodo.');
      updateProgress(start.state);
      updateStats(start.state);
      btn.disabled = false;
      btn.textContent = 'Sincronizar';
      return;
    }

    logLine(`Sync #${start.sync_run_id} iniciado. ${start.total_messages} correo(s) en cola.`);
    updateProgress(start.state);
    updateStats(start.state);

    while (true) {
      const resp = await post('../api/process_next.php', { sync_run_id: start.sync_run_id });

      if (!resp.ok) {
        logLine('ERR ' + (resp.error || 'Error desconocido en process_next'));
        break;
      }

      updateProgress(resp.state);
      updateStats(resp.state);
      (resp.last_items || []).forEach(it => logLine(it));

      if (resp.state.status === 'done' || resp.state.status === 'failed') {
        const n = resp.state.new_invoices || 0;
        logLine(`-- Sincronizacion finalizada. ${n} factura(s) nueva(s) guardada(s). --`);
        histLoaded = false;
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
let reportData  = null;
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
  const { bienes, servicios, combinado, count } = resp;

  $('report-empty').classList.add('hidden');
  $('report-content').classList.remove('hidden');
  $('btn-export').classList.remove('hidden');

  renderBienes(bienes);
  renderServicios(servicios);
  renderCombinado(combinado, bienes.totals, servicios.totals);
}

/* ── Tabla Gastos Bienes ─────────────────────── */
function renderBienes(data) {
  const { rows, totals } = data;
  $('bienes-count').innerHTML = `<strong>${rows.length}</strong> factura(s) con bienes`;

  const tbody = $('bienes-tbody');
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:32px;color:#999;">Sin gastos de bienes en este periodo.</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => `<tr>
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
    </tr>`).join('');
  }

  $('bienes-tfoot').innerHTML = `
    <tr>
      <td>TOTAL IVA</td><td></td>
      <td>&#x20A1;${fmt(totals.base_1)}</td>
      <td>&#x20A1;${fmt(totals.iva_1)}</td>
      <td>&#x20A1;${fmt(totals.base_2)}</td>
      <td>&#x20A1;${fmt(totals.iva_2)}</td>
      <td>&#x20A1;${fmt(totals.base_4)}</td>
      <td>&#x20A1;${fmt(totals.iva_4)}</td>
      <td>&#x20A1;${fmt(totals.base_gravada)}</td>
      <td>&#x20A1;${fmt(totals.iva_13)}</td>
      <td>&#x20A1;${fmt(totals.no_sujeto)}</td>
      <td><strong>&#x20A1;${fmt(totals.iva_total)}</strong></td>
    </tr>
    <tr class="tr-proporcion">
      <td>PROPORCION</td><td>(base imponible)</td>
      <td colspan="2">${totals.proporcion_1 > 0 ? '&#x20A1;'+fmt(totals.proporcion_1) : '—'}</td>
      <td colspan="2">${totals.proporcion_2 > 0 ? '&#x20A1;'+fmt(totals.proporcion_2) : '—'}</td>
      <td colspan="2">${totals.proporcion_4 > 0 ? '&#x20A1;'+fmt(totals.proporcion_4) : '—'}</td>
      <td colspan="2">${totals.proporcion_13 > 0 ? '&#x20A1;'+fmt(totals.proporcion_13) : '—'}</td>
      <td>—</td><td>—</td>
    </tr>`;
}

/* ── Tabla Gastos Servicios ──────────────────── */
function renderServicios(data) {
  const { rows, totals } = data;
  $('servicios-count').innerHTML = `<strong>${rows.length}</strong> factura(s) con servicios`;

  const tbody = $('servicios-tbody');
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:32px;color:#999;">Sin gastos de servicios en este periodo.</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => `<tr>
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
    </tr>`).join('');
  }

  $('servicios-tfoot').innerHTML = `
    <tr>
      <td>TOTAL IVA</td><td></td>
      <td>&#x20A1;${fmt(totals.base_1)}</td>
      <td>&#x20A1;${fmt(totals.iva_1)}</td>
      <td>&#x20A1;${fmt(totals.base_gravada)}</td>
      <td>&#x20A1;${fmt(totals.iva_10)}</td>
      <td></td>
      <td>&#x20A1;${fmt(totals.iva_13)}</td>
      <td>&#x20A1;${fmt(totals.no_sujeto)}</td>
      <td><strong>&#x20A1;${fmt(totals.iva_total)}</strong></td>
    </tr>
    <tr class="tr-proporcion">
      <td>PROPORCION</td><td>(base imponible)</td>
      <td colspan="2">${totals.proporcion_1  > 0 ? '&#x20A1;'+fmt(totals.proporcion_1)  : '—'}</td>
      <td colspan="2">${totals.proporcion_10 > 0 ? '&#x20A1;'+fmt(totals.proporcion_10) : '—'}</td>
      <td colspan="2">${totals.proporcion_13 > 0 ? '&#x20A1;'+fmt(totals.proporcion_13) : '—'}</td>
      <td>—</td><td>—</td>
    </tr>`;
}

/* ── Resumen Combinado ───────────────────────── */
function renderCombinado(combinado, bTotals, sTotals) {
  $('combinado-grid').innerHTML = [
    { lbl: 'IVA 13% Combinado',       val: combinado.iva_13_combinado,        desc: 'Bienes 13% + Servicios 13%' },
    { lbl: 'Proporcion 13% Total',     val: combinado.proporcion_13_combinado, desc: 'IVA 13% combinado / 0.13' },
    { lbl: 'Total IVA Gastos',         val: combinado.total_iva_gastos,        desc: 'Todos los IVAs bienes + servicios', cls:'green-card' },
    { lbl: 'Proporcion Total Gastos',  val: combinado.proporcion_total,        desc: 'Base imponible total del periodo', cls:'green-card' },
  ].map(c => `
    <div class="comb-card ${c.cls || ''}">
      <div class="comb-val">&#x20A1;${fmt(c.val)}</div>
      <div class="comb-lbl"><strong>${c.lbl}</strong></div>
      <div class="comb-lbl" style="margin-top:4px;">${c.desc}</div>
    </div>
  `).join('');

  // Chips bienes
  $('bienes-chips').innerHTML = [
    { lbl:'IVA 1% Bienes',   val: bTotals.iva_1  },
    { lbl:'IVA 2% Bienes',   val: bTotals.iva_2  },
    { lbl:'IVA 4% Bienes',   val: bTotals.iva_4  },
    { lbl:'IVA 13% Bienes',  val: bTotals.iva_13 },
    { lbl:'Total IVA Bienes',val: bTotals.iva_total, cls:'green-chip' },
  ].map(c => `
    <div class="chip ${c.cls||''}">
      <div class="chip-val">&#x20A1;${fmt(c.val)}</div>
      <div class="chip-lbl">${c.lbl}</div>
    </div>
  `).join('');

  // Chips servicios
  $('servicios-chips').innerHTML = [
    { lbl:'IVA 1% Servicios',   val: sTotals.iva_1  },
    { lbl:'IVA 10% Servicios',  val: sTotals.iva_10 },
    { lbl:'IVA 13% Servicios',  val: sTotals.iva_13 },
    { lbl:'Total IVA Servicios',val: sTotals.iva_total, cls:'green-chip' },
  ].map(c => `
    <div class="chip ${c.cls||''}">
      <div class="chip-val">&#x20A1;${fmt(c.val)}</div>
      <div class="chip-lbl">${c.lbl}</div>
    </div>
  `).join('');
}

/* ── Exportar CSV ────────────────────────────── */
$('btn-export').addEventListener('click', () => {
  if (!reportData) return;
  const { bienes, servicios } = reportData;

  const esc = v => {
    if (v === null || v === undefined) return '';
    const s = String(v);
    return s.includes(',') || s.includes('"') || s.includes('\n')
      ? '"' + s.replace(/"/g,'""') + '"' : s;
  };

  const lines = [];

  // Bienes
  lines.push('=== GASTOS BIENES ===');
  lines.push(['Fecha','Emisor','Base 1%','IVA 1%','Base 2%','IVA 2%','Base 4%','IVA 4%','Base 13%','IVA 13%','No Sujeto','Total IVA'].join(','));
  for (const r of bienes.rows) {
    lines.push([r.fecha,esc(r.emisor),r.base_1,r.iva_1,r.base_2,r.iva_2,r.base_4,r.iva_4,r.base_13,r.iva_13,r.no_sujeto,r.iva_total].join(','));
  }
  const bt = bienes.totals;
  lines.push(['TOTAL','',bt.base_1,bt.iva_1,bt.base_2,bt.iva_2,bt.base_4,bt.iva_4,bt.base_gravada,bt.iva_13,bt.no_sujeto,bt.iva_total].join(','));
  lines.push(['PROPORCION','',bt.proporcion_1,'',bt.proporcion_2,'',bt.proporcion_4,'',bt.proporcion_13,'','',''].join(','));
  lines.push('');

  // Servicios
  lines.push('=== GASTOS SERVICIOS ===');
  lines.push(['Fecha','Emisor','Base 1%','IVA 1%','Base 10%','IVA 10%','Base 13%','IVA 13%','No Sujeto','Total IVA'].join(','));
  for (const r of servicios.rows) {
    lines.push([r.fecha,esc(r.emisor),r.base_1,r.iva_1,r.base_10,r.iva_10,r.base_13,r.iva_13,r.no_sujeto,r.iva_total].join(','));
  }
  const st = servicios.totals;
  lines.push(['TOTAL','',st.base_1,st.iva_1,st.base_gravada,st.iva_10,'',st.iva_13,st.no_sujeto,st.iva_total].join(','));
  lines.push(['PROPORCION','',st.proporcion_1,'',st.proporcion_10,'',st.proporcion_13,'','',''].join(','));
  lines.push('');

  // Combinado
  lines.push('=== RESUMEN COMBINADO ===');
  const c = reportData.combinado;
  lines.push(['IVA 13% Combinado',c.iva_13_combinado].join(','));
  lines.push(['Proporcion 13% Total',c.proporcion_13_combinado].join(','));
  lines.push(['Total IVA Gastos',c.total_iva_gastos].join(','));
  lines.push(['Proporcion Total',c.proporcion_total].join(','));

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
