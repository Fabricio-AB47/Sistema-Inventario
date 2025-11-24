<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso | Inventario</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <section class="hero">
      <div class="hero-header">
        <div class="pill">Acceso seguro</div>
        <div>
          <h1>Inventario de equipos</h1>
          <p>Inicia sesión para administrar equipos y asignaciones.</p>
        </div>
      </div>
    </section>

    <div class="card" style="max-width:420px; margin:24px auto;">
      <h2 id="form-title">Iniciar sesión</h2>
      <p class="card-subtitle" id="auth-message">Acceso para usuarios existentes.</p>
      <form id="auth-form">
        <label>
          Correo
          <input type="email" id="email" placeholder="correo@dominio.com" required autocomplete="email">
        </label>
        <label>
          Contraseña
          <input type="password" id="password" placeholder="psw" required>
        </label>
        <div class="actions full">
          <button type="submit" class="btn" id="submit-btn">Entrar</button>
        </div>
      </form>
    </div>
  </div>
  <script src="assets/js/auth.js"></script>
</body>
</html>
