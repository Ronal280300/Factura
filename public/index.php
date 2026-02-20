<?php
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>IVA Sync</title>
  <style>
    body{font-family:Arial; margin:20px;}
    .row{display:flex; gap:10px; align-items:end;}
    input,button{padding:8px; font-size:14px;}
    .bar{height:16px; background:#eee; border-radius:8px; overflow:hidden; margin-top:10px;}
    .bar > div{height:16px; width:0%; background:#2e7d32;}
    .stats{margin-top:10px; display:flex; gap:16px; flex-wrap:wrap;}
    .log{margin-top:10px; padding:10px; border:1px solid #ddd; height:160px; overflow:auto; font-family:monospace; font-size:12px;}
  </style>
</head>
<body>
  <h2>Sincronizar facturas (XML)</h2>

  <div class="row">
    <div>
      <div>Desde</div>
      <input type="date" id="from">
    </div>
    <div>
      <div>Hasta</div>
      <input type="date" id="to">
    </div>
    <button id="btn">Sincronizar</button>
  </div>

  <div class="bar"><div id="bar"></div></div>

  <div class="stats" id="stats"></div>
  <div class="log" id="log"></div>

<script>
const logEl = document.getElementById('log');
const statsEl = document.getElementById('stats');
const barEl = document.getElementById('bar');

function log(msg){
  const d = new Date().toLocaleTimeString();
  logEl.innerHTML = `[${d}] ${msg}<br>` + logEl.innerHTML;
}

function renderStats(s){
  statsEl.innerHTML = `
    <div><b>Total emails:</b> ${s.total_messages}</div>
    <div><b>Procesados:</b> ${s.processed_messages}</div>
    <div><b>XML:</b> ${s.found_xml}</div>
    <div><b>Nuevas:</b> ${s.new_invoices}</div>
    <div><b>Duplicadas:</b> ${s.duplicates}</div>
    <div><b>Fuera rango:</b> ${s.out_of_range}</div>
    <div><b>Errores:</b> ${s.errors}</div>
    <div><b>Estado:</b> ${s.status}</div>
  `;
  const pct = s.total_messages > 0 ? Math.round((s.processed_messages / s.total_messages) * 100) : 0;
  barEl.style.width = pct + '%';
}

async function post(url, data){
  const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data || {})});
  return r.json();
}

document.getElementById('btn').addEventListener('click', async () => {
  const from = document.getElementById('from').value;
  const to = document.getElementById('to').value;
  if(!from || !to){ alert('Selecciona fechas'); return; }

  log('Creando sincronización...');
  const start = await post('../api/start_sync.php', {from, to});
  if(!start.ok){ log('Error: ' + start.error); return; }

  const syncId = start.sync_run_id;
  log(`Sync #${syncId} creado. Emails encontrados: ${start.total_messages}`);
  renderStats(start.state);

  // Loop por lotes
  while(true){
    const resp = await post('../api/process_next.php', {sync_run_id: syncId});
    if(!resp.ok){ log('Error: ' + resp.error); break; }

    renderStats(resp.state);
    (resp.last_items || []).forEach(it => log(it));

    if(resp.state.status === 'done' || resp.state.status === 'failed'){
      log('Sincronización finalizada.');
      break;
    }
  }
});
</script>
</body>
</html>