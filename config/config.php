<?php
require_once __DIR__ . '/../app/Env.php';
Env::load(__DIR__ . '/../.env');

return [
  'db' => [
    'host'    => Env::get('DB_HOST', '127.0.0.1'),
    'name'    => Env::get('DB_NAME', 'iva_sync'),
    'user'    => Env::get('DB_USER', 'root'),
    'pass'    => Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',
  ],

  'imap' => [
    'host'     => Env::get('IMAP_HOST', 'imap.gmail.com'),
    'port'     => Env::getInt('IMAP_PORT', 993),
    'flags'    => Env::get('IMAP_FLAGS', '/imap/ssl'),
    'username' => Env::get('IMAP_USERNAME', ''),
    'password' => Env::get('IMAP_PASSWORD', ''),
    'mailbox'  => Env::get('IMAP_MAILBOX', 'INBOX'),
  ],

  'search_buffer_days' => Env::getInt('SEARCH_BUFFER_DAYS', 5),
  'batch_size_default' => Env::getInt('BATCH_SIZE', 10),

  // ── Contribuyente ────────────────────────────────────────────────────────
  'receptor_cedulas' => Env::getArray('RECEPTOR_CEDULAS'),
  'strict_receptor'  => Env::getBool('STRICT_RECEPTOR', true),

  // ── Seguridad ────────────────────────────────────────────────────────────
  'auth' => [
    'enabled'     => Env::getBool('AUTH_ENABLED', true),
    'app_secret'  => Env::get('APP_SECRET', ''),
    'force_https' => Env::getBool('FORCE_HTTPS', true),
    'session_ttl' => Env::getInt('SESSION_TTL', 28800),  // 8 horas
  ],

  // ── Log ──────────────────────────────────────────────────────────────────
  'log' => [
    'level' => Env::get('LOG_LEVEL', 'info'),
    'path'  => Env::get('LOG_PATH', __DIR__ . '/../logs/app.log'),
  ],

  // ── Hacienda ─────────────────────────────────────────────────────────────
  'hacienda' => [
    'env'            => Env::get('HACIENDA_ENV', 'sandbox'),
    'idp_url'        => [
      'sandbox' => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/protocol/openid-connect/token',
      'prod'    => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/token',
    ],
    'api_url'        => [
      'sandbox' => 'https://api.comprobanteselectronicos.go.cr/recepcion-sandbox/v1',
      'prod'    => 'https://api.comprobanteselectronicos.go.cr/recepcion/v1',
    ],
    'client_id'      => [
      'sandbox' => 'api-stag',
      'prod'    => 'api-prod',
    ],
    'username'       => Env::get('HACIENDA_USERNAME', ''),
    'password'       => Env::get('HACIENDA_PASSWORD', ''),
    'cert_path'      => Env::get('HACIENDA_CERT_PATH', ''),
    'cert_password'  => Env::get('HACIENDA_CERT_PASSWORD', ''),

    'xades_policy_url'    => 'https://www.hacienda.go.cr/ATV/ComprobanteElectronico/docs/esquemas/2016/v4.3/Resolucion_Comprobantes_Electronicos_DGT-R-48-2016_4.3.pdf',
    'xades_policy_digest' => Env::get('XADES_POLICY_DIGEST', ''),

    'condicion_impuesto_default' => Env::get('CONDICION_IMPUESTO_DEFAULT', '01'),
    'auto_accept' => Env::getBool('HACIENDA_AUTO_ACCEPT', false),
  ],
];
