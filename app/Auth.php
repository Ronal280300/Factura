<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Logger.php';

/**
 * Autenticacion por sesion con password_hash (bcrypt).
 *
 * Uso:
 *   Auth::bootstrap($cfg);            // arranca sesion + enforce HTTPS
 *   Auth::requireAuth();              // llama al tope de cada endpoint
 *   $u = Auth::user();                // ['id','email','role',...]
 *
 * Rate-limit basico: bloquea al 5to intento fallido por 10 min.
 */
class Auth {

  private const MAX_FAILED = 5;
  private const LOCKOUT_SEC = 600;

  /** Arranca sesion y aplica politica HTTPS. Llamar una vez por request. */
  public static function bootstrap(array $cfg): void {
    $authCfg = $cfg['auth'] ?? [];

    // Forzar HTTPS en produccion
    if (!empty($authCfg['force_https']) && self::isProductionRequest() && !self::isHttps()) {
      $host = $_SERVER['HTTP_HOST'] ?? '';
      $uri  = $_SERVER['REQUEST_URI'] ?? '/';
      header("Location: https://{$host}{$uri}", true, 301);
      exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => self::isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_name('iva_sync');
      session_start();
    }

    // Expira sesion inactiva
    $ttl = (int)($authCfg['session_ttl'] ?? 28800);
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $ttl) {
      session_unset();
      session_destroy();
      session_start();
    }
    $_SESSION['last_activity'] = time();
  }

  /** Valida email+pass y loguea. Devuelve fila user o null. */
  public static function attemptLogin(string $email, string $password, string $ip): ?array {
    $pdo = Db::pdo();
    $st  = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([strtolower(trim($email))]);
    $u = $st->fetch();

    if (!$u || !$u['active']) {
      Logger::warn('auth.login.failed', ['email' => $email, 'reason' => 'unknown_or_inactive', 'ip' => $ip]);
      return null;
    }

    // Lockout
    if ((int)$u['failed_attempts'] >= self::MAX_FAILED) {
      $since = $u['last_login_at'] ? strtotime($u['last_login_at']) : 0;
      if (time() - $since < self::LOCKOUT_SEC) {
        Logger::warn('auth.login.locked', ['email' => $email, 'ip' => $ip]);
        return null;
      }
    }

    if (!password_verify($password, $u['password_hash'])) {
      $pdo->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?")
          ->execute([$u['id']]);
      Logger::warn('auth.login.failed', ['email' => $email, 'reason' => 'bad_password', 'ip' => $ip]);
      return null;
    }

    // Re-hash si el cost ha subido
    if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT)) {
      $new = password_hash($password, PASSWORD_BCRYPT);
      $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new, $u['id']]);
    }

    $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ?, failed_attempts = 0 WHERE id = ?")
        ->execute([$ip, $u['id']]);

    $_SESSION['user_id']   = (int)$u['id'];
    $_SESSION['user_role'] = $u['role'];
    $_SESSION['user_email']= $u['email'];
    session_regenerate_id(true);

    self::audit($u['id'], 'login', null, ['ip' => $ip]);
    Logger::info('auth.login.ok', ['user_id' => $u['id'], 'email' => $email]);

    return $u;
  }

  public static function logout(): void {
    if (!empty($_SESSION['user_id'])) {
      self::audit((int)$_SESSION['user_id'], 'logout', null, null);
      Logger::info('auth.logout', ['user_id' => $_SESSION['user_id']]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
  }

  /** Gate para endpoints HTML. Redirige a /public/login.php si no autenticado. */
  public static function requireAuth(string $loginUrl = '/login.php', array $requiredRoles = []): void {
    $cfg = require __DIR__ . '/../config/config.php';
    if (empty($cfg['auth']['enabled'])) return;  // auth deshabilitado

    if (empty($_SESSION['user_id'])) {
      // Para APIs JSON, devolver 401
      if (self::wantsJson()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'No autenticado']);
        exit;
      }
      header("Location: {$loginUrl}");
      exit;
    }

    if ($requiredRoles && !in_array($_SESSION['user_role'], $requiredRoles, true)) {
      if (self::wantsJson()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Permiso insuficiente']);
        exit;
      }
      http_response_code(403);
      echo 'Permiso insuficiente';
      exit;
    }
  }

  public static function user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
      'id'    => (int)$_SESSION['user_id'],
      'email' => $_SESSION['user_email'] ?? '',
      'role'  => $_SESSION['user_role']  ?? '',
    ];
  }

  public static function userId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
  }

  public static function createUser(string $email, string $password, string $role, ?string $fullName = null): int {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Email invalido');
    if (strlen($password) < 8) throw new InvalidArgumentException('La contrasena debe tener al menos 8 caracteres');
    if (!in_array($role, ['admin','accountant','viewer'], true)) throw new InvalidArgumentException('Rol invalido');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    Db::pdo()->prepare("INSERT INTO users (email, password_hash, role, full_name) VALUES (?,?,?,?)")
             ->execute([$email, $hash, $role, $fullName]);
    return (int)Db::pdo()->lastInsertId();
  }

  public static function countUsers(): int {
    return (int)Db::pdo()->query("SELECT COUNT(*) FROM users")->fetchColumn();
  }

  public static function audit(?int $userId, string $action, ?string $target, $payload): void {
    try {
      Db::pdo()->prepare("INSERT INTO audit_log (user_id, ip, action, target, payload) VALUES (?,?,?,?,?)")
               ->execute([
                 $userId,
                 $_SERVER['REMOTE_ADDR'] ?? null,
                 $action,
                 $target,
                 $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
               ]);
    } catch (Throwable $e) {
      Logger::error('audit.fail', ['error' => $e->getMessage()]);
    }
  }

  // ── helpers ─────────────────────────────────────────────────────────────
  public static function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
  }

  private static function isProductionRequest(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return !preg_match('/^(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])/', $host);
  }

  private static function wantsJson(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($accept, 'application/json') || str_contains($uri, '/api/');
  }
}
