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

  // ── Integracion con Hacienda (API de Comprobantes Electronicos) ────────────
  // Requerido para enviar Mensaje Receptor (aceptacion / parcial / rechazo).
  // En sandbox usar api-stag / api.comprobanteselectronicos.go.cr/recepcion-sandbox
  // En produccion usar api-prod  / api.comprobanteselectronicos.go.cr/recepcion
  'hacienda' => [
    'env'            => 'sandbox',  // 'sandbox' | 'prod'
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
    'username'       => '',                        // correo de ATV/TRIBU-CR del contribuyente
    'password'       => '',                        // contrasena de ATV/TRIBU-CR
    'cert_path'      => '',                        // ruta absoluta al .p12 del BCCR
    'cert_password'  => '',                        // pin del .p12

    // Politica XAdES-EPES (URL de la resolucion en Hacienda)
    'xades_policy_url'    => 'https://www.hacienda.go.cr/ATV/ComprobanteElectronico/docs/esquemas/2016/v4.3/Resolucion_Comprobantes_Electronicos_DGT-R-48-2016_4.3.pdf',
    'xades_policy_digest' => '',                   // hash SHA-256 del PDF de la politica

    // Condicion del impuesto (uso del bien/servicio)
    //   01 = gasto corriente       (IVA 100% acreditable)
    //   02 = activo / proporcional (sujeto a proporcionalidad)
    //   03 = gasto corriente y capital
    //   04 = sin derecho a credito
    //   05 = proporcionalidad
    'condicion_impuesto_default' => '01',

    // Si true, al terminar de sincronizar se envia MensajeReceptor '1' (acepta)
    // automaticamente para todas las FE/FEE/FEC del contribuyente. NC/ND no
    // requieren mensaje receptor.
    'auto_accept' => false,
  ],
];
