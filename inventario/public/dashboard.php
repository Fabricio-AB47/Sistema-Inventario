<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];

require_once __DIR__ . '/../config/db.php';
$pdo = db();

$totalEquipos = (int)($pdo->query("SELECT COUNT(*) FROM equipo")->fetchColumn() ?: 0);
$totalUsuarios = (int)($pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn() ?: 0);

$porEstado = $pdo->query("
    SELECT es.descripcion_estado_equipo AS estado, COUNT(*) AS total
    FROM equipo e
    INNER JOIN estado_equipo es ON es.id_estado_equipo = e.id_estado_equipo
    GROUP BY es.descripcion_estado_equipo
    ORDER BY es.descripcion_estado_equipo
")->fetchAll();

$porTipo = $pdo->query("
    SELECT te.descripcion_tp_equipo AS tipo, COUNT(*) AS total
    FROM equipo e
    INNER JOIN tipo_equipo te ON te.id_tp_equipo = e.id_tp_equipo
    GROUP BY te.descripcion_tp_equipo
    ORDER BY te.descripcion_tp_equipo
")->fetchAll();

$movResumen = $pdo->query("
    SELECT COALESCE(est.descripcion_est_asig_reasig, 'Sin estado') AS tipo_movimiento, COUNT(*) AS total
    FROM asignacion_reasignacion ar
    LEFT JOIN estado_asignacion_reasignacion est ON est.id_estado_asig_reasig = ar.id_estado_asig_reasig
    GROUP BY est.descripcion_est_asig_reasig
")->fetchAll();

$movimientos = $pdo->query("
    SELECT TOP 10 ar.id_asig_reasig, est.descripcion_est_asig_reasig AS tipo_movimiento, ar.fecha_asig_reasig AS fecha_mov,
           u.nombre + ' ' + u.apellidos AS usuario,
           e.modelo + ' (' + e.num_serie + ')' AS equipo
    FROM asignacion_reasignacion ar
    INNER JOIN usuario u ON u.id_user = ar.id_user
    INNER JOIN equipo e ON e.id_equipo = ar.id_equipo
    LEFT JOIN estado_asignacion_reasignacion est ON est.id_estado_asig_reasig = ar.id_estado_asig_reasig
    ORDER BY ar.fecha_asig_reasig DESC, ar.id_asig_reasig DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Inventario</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Dashboard</div>
        <div>
          <h1>Resumen de equipos y movimientos</h1>
          <p>Conteos generados desde la base de datos.</p>
        </div>
      </div>
    </section>

    <div class="layout">
      <div class="card">
        <h2>Totales</h2>
        <div class="meta">
          <div class="badge">Equipos: <strong><?php echo $totalEquipos; ?></strong></div>
          <div class="badge">Usuarios: <strong><?php echo $totalUsuarios; ?></strong></div>
        </div>
        <h3 style="margin-top:12px;">Equipos por estado</h3>
        <table class="acta__table" style="margin-top:6px;">
          <thead><tr><th>Estado</th><th>Total</th></tr></thead>
          <tbody>
            <?php if (!$porEstado): ?>
              <tr><td colspan="2" class="empty">Sin datos</td></tr>
            <?php else: ?>
              <?php foreach ($porEstado as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)$row['total']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <h3 style="margin-top:12px;">Equipos por tipo</h3>
        <table class="acta__table" style="margin-top:6px;">
          <thead><tr><th>Tipo</th><th>Total</th></tr></thead>
          <tbody>
            <?php if (!$porTipo): ?>
              <tr><td colspan="2" class="empty">Sin datos</td></tr>
            <?php else: ?>
              <?php foreach ($porTipo as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)$row['total']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>Movimientos</h2>
        <h3 style="margin:8px 0 6px;">Resumen</h3>
        <table class="acta__table">
          <thead><tr><th>Tipo de movimiento</th><th>Total</th></tr></thead>
          <tbody>
            <?php if (!$movResumen): ?>
              <tr><td colspan="2" class="empty">Sin datos</td></tr>
            <?php else: ?>
              <?php foreach ($movResumen as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['tipo_movimiento'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)$row['total']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <h3 style="margin:12px 0 6px;">Últimos movimientos</h3>
        <div class="table-scroll">
          <table class="acta__table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Fecha</th>
                <th>Equipo</th>
                <th>Usuario</th>
                <th>Obs</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$movimientos): ?>
                <tr><td colspan="6" class="empty">Sin movimientos</td></tr>
              <?php else: ?>
                <?php foreach ($movimientos as $m): ?>
                  <tr>
                    <td><?php echo (int)$m['id_asig_reasig']; ?></td>
                    <td><?php echo htmlspecialchars($m['tipo_movimiento'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(substr($m['fecha_mov'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($m['equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($m['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($m['observaciones'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
