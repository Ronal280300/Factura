<?php

/**
 * Cargador de .env sin dependencias.
 *
 * Lee pares KEY=VALUE desde un archivo .env en la raiz del proyecto y los
 * publica en $_ENV. Soporta valores entre comillas y lineas de comentario.
 *
 * Uso:
 *   Env::load(__DIR__ . '/../.env');       // silencia si no existe
 *   $pass = Env::get('DB_PASS', '');       // con default
 *   $port = Env::getInt('IMAP_PORT', 993);
 *   $on   = Env::getBool('FORCE_HTTPS', true);
 */
class Env {

  private static bool $loaded = false;

  public static function load(string $path): void {
    if (self::$loaded || !is_file($path)) { self::$loaded = true; return; }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      $line = ltrim($line);
      if ($line === '' || $line[0] === '#') continue;
      if (!str_contains($line, '=')) continue;

      [$k, $v] = explode('=', $line, 2);
      $k = trim($k);
      $v = trim($v);

      // Soporta "valor con espacios" y 'idem'
      if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
        $v = substr($v, 1, -1);
      }

      $_ENV[$k] = $v;
      putenv("{$k}={$v}");
    }
    self::$loaded = true;
  }

  public static function get(string $key, $default = null) {
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
  }

  public static function getInt(string $key, int $default = 0): int {
    $v = self::get($key, null);
    return $v !== null ? (int)$v : $default;
  }

  public static function getBool(string $key, bool $default = false): bool {
    $v = self::get($key, null);
    if ($v === null) return $default;
    $v = strtolower((string)$v);
    return in_array($v, ['1','true','yes','on'], true);
  }

  public static function getArray(string $key, string $separator = ',', array $default = []): array {
    $v = self::get($key, null);
    if ($v === null) return $default;
    return array_values(array_filter(array_map('trim', explode($separator, $v))));
  }
}
