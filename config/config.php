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

  // Para evitar “búsqueda histórica”: buffer alrededor del rango solicitado (días)
  'search_buffer_days' => 5,

  // Tamaño del lote por request (para evitar timeouts)
  'batch_size_default' => 10,
];