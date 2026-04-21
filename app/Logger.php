<?php

/**
 * Logger minimal de JSON-lines (1 linea = 1 evento). Sin dependencias.
 *
 * Cada linea:
 *   {"ts":"2026-04-21T10:00:00+00:00","level":"info","event":"auth.login.ok","ctx":{...}}
 *
 * Niveles: debug < info < warn < error. Controlado por LOG_LEVEL de .env.
 */
class Logger {

  private const LEVELS = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];

  private static ?string $path = null;
  private static int $threshold = 1;   // info

  public static function configure(array $logCfg): void {
    self::$path      = $logCfg['path']  ?? null;
    self::$threshold = self::LEVELS[strtolower($logCfg['level'] ?? 'info')] ?? 1;
  }

  public static function debug(string $event, array $ctx = []): void { self::emit('debug', $event, $ctx); }
  public static function info (string $event, array $ctx = []): void { self::emit('info',  $event, $ctx); }
  public static function warn (string $event, array $ctx = []): void { self::emit('warn',  $event, $ctx); }
  public static function error(string $event, array $ctx = []): void { self::emit('error', $event, $ctx); }

  private static function emit(string $level, string $event, array $ctx): void {
    if (self::LEVELS[$level] < self::$threshold) return;
    if (!self::$path) return;

    $dir = dirname(self::$path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $line = json_encode([
      'ts'    => (new DateTime('now'))->format(DateTime::ATOM),
      'level' => $level,
      'event' => $event,
      'ctx'   => $ctx,
      'pid'   => getmypid(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    @file_put_contents(self::$path, $line, FILE_APPEND | LOCK_EX);
  }
}
