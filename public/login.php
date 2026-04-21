<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Si ya hay 0 usuarios → redirigir a setup
if (Auth::countUsers() === 0) {
  header('Location: setup.php'); exit;
}

// Si ya esta logueado → home
if (Auth::userId()) { header('Location: index.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = (string)($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
  $u = Auth::attemptLogin($email, $pass, $ip);
  if ($u) { header('Location: index.php'); exit; }
  $error = 'Credenciales invalidas';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — IVA Sync</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,Segoe UI,sans-serif;background:#f4f6f8;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .card{background:#fff;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.1);padding:32px;width:360px}
    h1{font-size:22px;margin-bottom:6px;color:#145299}
    p.sub{font-size:13px;color:#6c757d;margin-bottom:22px}
    label{display:block;margin:14px 0 6px;font-size:13px;color:#212529}
    input{width:100%;padding:10px;border:1px solid #dee2e6;border-radius:6px;font-size:14px}
    button{width:100%;margin-top:20px;padding:11px;border:0;background:#1a6fc4;color:#fff;border-radius:6px;font-size:15px;cursor:pointer}
    button:hover{background:#145299}
    .err{background:#fdecea;color:#c62828;padding:10px;border-radius:6px;margin-top:14px;font-size:13px}
  </style>
</head>
<body>
  <form class="card" method="post">
    <h1>IVA Sync</h1>
    <p class="sub">Factura Electronica CR v4.4</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Correo</label>
    <input type="email" name="email" required autofocus autocomplete="username">
    <label>Contrasena</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button type="submit">Ingresar</button>
  </form>
</body>
</html>
