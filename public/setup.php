<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Solo accesible si no hay usuarios. Al crear el primero, se bloquea.
if (Auth::countUsers() > 0) {
  header('Location: login.php'); exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = (string)($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $name  = (string)($_POST['full_name'] ?? '');
  try {
    $uid = Auth::createUser($email, $pass, 'admin', $name);
    Auth::audit($uid, 'setup.first_admin', $email, null);
    Logger::info('setup.first_admin', ['user_id' => $uid, 'email' => $email]);
    header('Location: login.php?created=1'); exit;
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configuracion inicial — IVA Sync</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,sans-serif;background:#f4f6f8;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .card{background:#fff;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.1);padding:32px;width:420px}
    h1{font-size:22px;margin-bottom:6px;color:#145299}
    p.sub{font-size:13px;color:#6c757d;margin-bottom:22px}
    label{display:block;margin:14px 0 6px;font-size:13px}
    input{width:100%;padding:10px;border:1px solid #dee2e6;border-radius:6px;font-size:14px}
    button{width:100%;margin-top:20px;padding:11px;border:0;background:#2e7d32;color:#fff;border-radius:6px;font-size:15px;cursor:pointer}
    button:hover{background:#1b5e20}
    .err{background:#fdecea;color:#c62828;padding:10px;border-radius:6px;margin-top:14px;font-size:13px}
    .hint{font-size:11px;color:#6c757d;margin-top:4px}
  </style>
</head>
<body>
  <form class="card" method="post">
    <h1>Primera instalacion</h1>
    <p class="sub">Crea el usuario administrador inicial. Esta pantalla se bloquea despues.</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Nombre completo</label>
    <input type="text" name="full_name" required>
    <label>Correo</label>
    <input type="email" name="email" required>
    <label>Contrasena</label>
    <input type="password" name="password" minlength="8" required>
    <div class="hint">Minimo 8 caracteres.</div>
    <button type="submit">Crear administrador</button>
  </form>
</body>
</html>
