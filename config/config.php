<?php
return [
  'db' => [
    'host' => '127.0.0.1',
    'name' => 'iva_sync',
    'user' => 'root',
    'pass' => '', // en XAMPP normalmente vacío
    'charset' => 'utf8mb4',
  ],

  'imap' => [
    'host' => 'imap.gmail.com',   // si es Gmail
    'port' => 993,
    'flags' => '/imap/ssl',
    'username' => 'TU_CORREO_DE_FACTURAS@gmail.com',
    'password' => 'TU_APP_PASSWORD', // si Gmail con 2FA
    'mailbox'  => 'INBOX',
  ],

  // Para evitar "búsqueda histórica": buffer alrededor del rango solicitado (días)
  'search_buffer_days' => 5,

  // Tamaño del lote por request (para evitar timeouts)
  'batch_size_default' => 10,

  // ── Contribuyente (receptor) ──────────────────────────────────────────────
  // Cedula(s) del contribuyente. Se usan para descartar XMLs del buzon que
  // NO estan dirigidos a este contribuyente (p. ej. correos reenviados, spam
  // de facturas de otras empresas). Dejar vacio para desactivar el filtro.
  //
  // Acepta multiples cedulas (util para contadores que manejan varias empresas
  // o personas fisicas con actividad lucrativa).
  'receptor_cedulas' => [
    // '3101123456',
    // '112340567',
  ],

  // Si true: XMLs con receptor distinto se descartan y se contabilizan en
  // sync_runs.wrong_receptor. Si false: se guardan igualmente (util para
  // revisar manualmente, no recomendado en produccion).
  'strict_receptor' => true,
];
