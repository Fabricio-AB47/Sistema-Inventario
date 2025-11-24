<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];
require_once __DIR__ . '/../config/db.php';
$pdo = db();

// Usuarios con perfil "AUTORIDADES" (por descripción) o id 4 como fallback
$usuarios = $pdo->prepare("
    SELECT u.id_user AS id_usuario, u.nombre, u.apellidos, u.correo, t.descripcion_perfil AS rol
    FROM usuario u
    INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
    WHERE UPPER(t.descripcion_perfil) LIKE '%AUTORIDADES%' OR t.id_tp_perfil = 4
    ORDER BY u.nombre
");
$usuarios->execute();
$usuarios = $usuarios->fetchAll();

$equipos = $pdo->query("
    SELECT e.id_equipo, e.modelo, e.marca, e.num_serie AS serie, te.descripcion_tp_equipo AS tipo
    FROM equipo e
    INNER JOIN tipo_equipo te ON te.id_tp_equipo = e.id_tp_equipo
    ORDER BY e.id_equipo DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Asignación Autoridades</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Asignación</div>
        <div>
          <h1>Asignar equipos a autoridades</h1>
          <p>Selecciona equipo y autoridad, luego registra el movimiento.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Nueva asignación</h2>
        <p class="card-subtitle muted" id="asig-msg">Completa los campos y guarda.</p>
        <form id="asig-form">
          <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap:14px;">
            <label>
              Equipo
              <select id="eq">
                <option value="">Seleccione equipo</option>
                <?php foreach ($equipos as $eq): ?>
                  <option value="<?php echo htmlspecialchars($eq['id_equipo'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($eq['modelo'] . ' - ' . $eq['serie'] . ' (' . $eq['tipo'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Autoridad
              <select id="usr">
                <option value="">Seleccione autoridad</option>
                <?php foreach ($usuarios as $u): ?>
                  <option value="<?php echo (int)$u['id_usuario']; ?>">
                    <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellidos'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Observaciones
              <input type="text" id="obs" placeholder="Opcional">
            </label>
          </div>
          <div class="actions full" style="margin-top:12px;">
            <button type="reset" class="btn-secondary">Limpiar</button>
            <button type="submit" class="btn">Guardar asignación</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const form = document.getElementById('asig-form');
      const msg = document.getElementById('asig-msg');
      const eq = document.getElementById('eq');
      const usr = document.getElementById('usr');
      const obs = document.getElementById('obs');

      function showMessage(text, tone = 'muted') {
        msg.textContent = text;
        msg.className = `card-subtitle ${tone}`;
      }

      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (!eq.value || !usr.value) {
          showMessage('Equipo y autoridad son obligatorios', 'danger');
          return;
        }
        try {
          const res = await fetch('../api/movimientos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id_equipo: eq.value,
              id_usuario: parseInt(usr.value, 10),
              observaciones: obs.value.trim()
            })
          });
          if (!res.ok) throw new Error('No se pudo registrar');
          showMessage('Asignación guardada', 'success');
          form.reset();
        } catch (error) {
          showMessage(error.message, 'danger');
        }
      });
    })();
  </script>
</body>
</html>
