<?php

/**
 * Bootstrap comun. Incluir al inicio de todo endpoint HTTP.
 *
 * Efectos:
 *  1. Carga .env en $_ENV
 *  2. Carga config.php y lo expone en $GLOBALS['cfg']
 *  3. Configura Logger
 *  4. Arranca Auth (sesion + HTTPS)
 *
 * Tras el bootstrap, llamar Auth::requireAuth() (con rol opcional) en
 * endpoints protegidos. Devuelve el array de configuracion.
 */

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Auth.php';

Env::load(__DIR__ . '/../.env');

$GLOBALS['cfg'] = require __DIR__ . '/../config/config.php';

Logger::configure($GLOBALS['cfg']['log'] ?? []);
Auth::bootstrap($GLOBALS['cfg']);

return $GLOBALS['cfg'];
