<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$countAutoridades = (int)$pdo->query("
    SELECT COUNT(*) FROM usuario u
    INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
    WHERE UPPER(t.descripcion_perfil) LIKE '%AUTORIDADES%' OR t.id_tp_perfil = 4
")->fetchColumn();

$asignadas = $pdo->query("
    SELECT COUNT(*) FROM asignacion_reasignacion ar
    INNER JOIN usuario u ON u.id_user = ar.id_user
    INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
    WHERE UPPER(t.descripcion_perfil) LIKE '%AUTORIDADES%' OR t.id_tp_perfil = 4
")->fetchColumn();

$ultimas = $pdo->query("
    SELECT TOP 10 ar.id_asig_reasig, ar.fecha_asig_reasig,
           u.nombre + ' ' + u.apellidos AS autoridad,
           e.modelo + ' (' + e.num_serie + ')' AS equipo
    FROM asignacion_reasignacion ar
    INNER JOIN usuario u ON u.id_user = ar.id_user
    INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
    INNER JOIN equipo e ON e.id_equipo = ar.id_equipo
    WHERE UPPER(t.descripcion_perfil) LIKE '%AUTORIDADES%' OR t.id_tp_perfil = 4
    ORDER BY ar.fecha_asig_reasig DESC, ar.id_asig_reasig DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Autoridades</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Dashboard</div>
        <div>
          <h1>Asignaciones a autoridades</h1>
          <p>Resumen de equipos asignados a perfiles de autoridades.</p>
        </div>
      </div>
    </section>

    <div class="layout">
      <div class="card">
        <h2>Totales</h2>
        <div class="meta">
          <div class="badge">Autoridades: <strong><?php echo $countAutoridades; ?></strong></div>
          <div class="badge">Asignaciones: <strong><?php echo (int)$asignadas; ?></strong></div>
        </div>
      </div>

      <div class="card">
        <h2>Últimas asignaciones</h2>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Autoridad</th>
                <th>Equipo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$ultimas): ?>
                <tr><td colspan="4" class="empty">Sin registros</td></tr>
              <?php else: ?>
                <?php foreach ($ultimas as $m): ?>
                  <tr>
                    <td><?php echo (int)$m['id_asig_reasig']; ?></td>
                    <td><?php echo htmlspecialchars(substr($m['fecha_asig_reasig'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($m['autoridad'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($m['equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
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
