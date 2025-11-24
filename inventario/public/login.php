<?php
require_once __DIR__ . '/../api/_bootstrap.php';
require_once __DIR__ . '/../config/db.php';

session_start();
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$errorMsg = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $emailValue = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    if ($email === '' || $password === '') {
        $errorMsg = 'Correo y contraseña son obligatorios.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT u.id_user, u.nombre, u.apellidos, u.correo, u.id_tp_perfil, u.pwd, t.descripcion_perfil
                FROM usuario u
                INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
                WHERE LOWER(u.correo) = :correo
            ");
            $stmt->execute([':correo' => $email]);
            $userRow = $stmt->fetch();

            if (!$userRow || (string)$userRow['pwd'] !== $password) {
                $errorMsg = 'Credenciales inválidas.';
            } else {
                $adminTipos = [1, 5]; // ADMINISTRADOR y TI
                $tipoId = (int)$userRow['id_tp_perfil'];
                $_SESSION['user'] = [
                    'id' => (int)$userRow['id_user'],
                    'name' => trim($userRow['nombre'] . ' ' . $userRow['apellidos']),
                    'email' => $userRow['correo'],
                    'tipo_id' => $tipoId,
                    'tipo_nombre' => $userRow['descripcion_perfil'] ?? 'SIN ROL',
                    'can_manage' => in_array($tipoId, $adminTipos, true)
                ];
                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $errorMsg = 'Error interno al iniciar sesión.';
        }
    }
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
      <p class="card-subtitle" id="auth-message">
        <?php echo $errorMsg ? htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') : 'Acceso para usuarios existentes.'; ?>
      </p>
      <form method="post">
        <label>
          Correo
          <input type="email" name="email" placeholder="correo@dominio.com" required autocomplete="email" value="<?php echo $emailValue; ?>">
        </label>
        <label>
          Contraseña
          <input type="password" name="password" placeholder="psw" required>
        </label>
        <div class="actions full">
          <button type="submit" class="btn" id="submit-btn">Entrar</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
