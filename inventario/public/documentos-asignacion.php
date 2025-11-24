<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$docs = $pdo->query("
    SELECT ar.id_asig_reasig, ar.fecha_asig_reasig, ar.documento_url,
           u.nombre + ' ' + u.apellidos AS usuario,
           e.modelo + ' (' + e.num_serie + ')' AS equipo,
           est.descripcion_est_asig_reasig AS estado
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documentos de asignación</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Documentos</div>
        <div>
          <h1>Asignaciones registradas</h1>
          <p>Descarga o visualiza el documento generado para cada asignación.</p>
        </div>
      </div>
    </section>

    <div class="card">
      <h2>Listado</h2>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Usuario</th>
              <th>Equipo</th>
              <th>Estado</th>
              <th>Documento</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$docs): ?>
              <tr><td colspan="6" class="empty">Sin registros</td></tr>
            <?php else: ?>
              <?php foreach ($docs as $d): ?>
                <tr>
                  <td><?php echo (int)$d['id_asig_reasig']; ?></td>
                  <td><?php echo htmlspecialchars(substr($d['fecha_asig_reasig'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($d['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($d['equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($d['estado'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if (!empty($d['documento_url'])): ?>
                      <a class="btn-secondary" href="<?php echo htmlspecialchars($d['documento_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ver</a>
                    <?php else: ?>
                      <span class="muted">Sin documento</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
