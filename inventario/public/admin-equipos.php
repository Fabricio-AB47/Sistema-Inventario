<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];
if (empty($user['can_manage'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
$pdo = db();
$messageEquipo = '';

function redirectWith($params) {
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . '?' . http_build_query($params));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_equipo'])) {
    $tipoEquipo = (int)($_POST['tipo_equipo'] ?? 0);
    $tipoActivo = (int)($_POST['tipo_activo'] ?? 0);
    $estadoEquipo = (int)($_POST['estado_equipo'] ?? 0);
    $estadoActivo = (int)($_POST['estado_activo'] ?? 0);
    $modelo = trim($_POST['modelo'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $ram = (int)($_POST['ram'] ?? 0);
    $disco = (int)($_POST['disco'] ?? 0);
    $serie = trim($_POST['serie'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $iva = (float)($_POST['iva'] ?? 0);
    $vidaUtil = (int)($_POST['vida_util'] ?? 36);
    $fechaAdq = $_POST['fecha_adq'] ?? date('Y-m-d');
    $numFactura = trim($_POST['num_factura'] ?? 'N/A');
    $idEquipo = trim($_POST['id_equipo'] ?? '') ?: uniqid('EQ-');

    if ($tipoEquipo <= 0 || $estadoEquipo <= 0 || $modelo === '' || $marca === '' || $serie === '') {
        $messageEquipo = 'Tipo, estado, modelo, marca y serie son obligatorios';
    } else {
        $precioTotal = round($precio + $precio * ($iva / 100), 2);
        $ins = $pdo->prepare("
            INSERT INTO equipo (
                id_equipo, marca, modelo, num_serie, memoria_ram, almacenamiento, hostname, procesador,
                precio, iva, total, tiempo_vida_util, fecha_adquisicion, num_factura,
                id_estado_activo, id_estado_equipo, id_tp_equipo, id_tipo_activo
            ) VALUES (
                :id_equipo, :marca, :modelo, :serie, :ram, :disco, :hostname, :procesador,
                :precio, :iva, :total, :vida_util, :fecha_adq, :num_factura,
                :estado_activo, :estado_equipo, :tipo_equipo, :tipo_activo
            )
        ");
        $ins->execute([
            ':id_equipo' => $idEquipo,
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':serie' => $serie,
            ':ram' => $ram,
            ':disco' => $disco,
            ':hostname' => $hostname,
            ':procesador' => $procesador,
            ':precio' => $precio,
            ':iva' => $iva,
            ':total' => $precioTotal,
            ':vida_util' => $vidaUtil,
            ':fecha_adq' => $fechaAdq,
            ':num_factura' => $numFactura === '' ? 'N/A' : $numFactura,
            ':estado_activo' => $estadoActivo > 0 ? $estadoActivo : $estadoEquipo,
            ':estado_equipo' => $estadoEquipo,
            ':tipo_equipo' => $tipoEquipo,
            ':tipo_activo' => $tipoActivo > 0 ? $tipoActivo : 1
        ]);
        redirectWith(['eq_msg' => 'Equipo creado']);
    }
}

$tiposEquipo = $pdo->query("SELECT id_tp_equipo AS id_tipo_equipo, descripcion_tp_equipo AS nombre_tipo FROM tipo_equipo ORDER BY descripcion_tp_equipo")->fetchAll();
$estadosEquipo = $pdo->query("SELECT id_estado_equipo, descripcion_estado_equipo FROM estado_equipo ORDER BY descripcion_estado_equipo")->fetchAll();
$tiposActivo = $pdo->query("SELECT id_tipo_activo, descripcion_tp_activo FROM tipo_activo ORDER BY descripcion_tp_activo")->fetchAll();
$estadosActivo = $pdo->query("SELECT id_estado_activo, descripcion_estado_activo FROM estado_activo ORDER BY descripcion_estado_activo")->fetchAll();
$equipos = $pdo->query("
    SELECT e.id_equipo, e.modelo, e.marca, e.num_serie AS serie, e.hostname, e.memoria_ram, e.almacenamiento, e.procesador,
           e.precio, e.iva, e.total, e.fecha_adquisicion, e.num_factura,
           te.descripcion_tp_equipo AS tipo_equipo,
           es.descripcion_estado_equipo AS estado_equipo,
           ea.descripcion_estado_activo AS estado_activo,
           ta.descripcion_tp_activo AS tipo_activo
    FROM equipo e
    INNER JOIN tipo_equipo te ON te.id_tp_equipo = e.id_tp_equipo
    INNER JOIN estado_equipo es ON es.id_estado_equipo = e.id_estado_equipo
    INNER JOIN estado_activo ea ON ea.id_estado_activo = e.id_estado_activo
    INNER JOIN tipo_activo ta ON ta.id_tipo_activo = e.id_tipo_activo
    ORDER BY e.id_equipo DESC
")->fetchAll();

if (isset($_GET['eq_msg'])) {
    $messageEquipo = $_GET['eq_msg'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración | Equipos</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Panel administrador</div>
        <div>
          <h1>Ingreso de equipos</h1>
          <p>Solo roles Administrador y TI pueden crear equipos.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Nuevo equipo</h2>
        <p class="card-subtitle <?php echo $messageEquipo ? '' : 'muted'; ?>">
          <?php echo htmlspecialchars($messageEquipo ?: 'Selecciona tipo/estado y llena los datos.', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <form method="post">
          <input type="hidden" name="form_equipo" value="1">
          <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
            <label>
              ID Equipo
              <input type="text" name="id_equipo" placeholder="Se autogenera si lo dejas vacío">
            </label>
            <label>
              Tipo de equipo
              <select name="tipo_equipo" required>
                <?php foreach ($tiposEquipo as $t): ?>
                  <option value="<?php echo (int)$t['id_tipo_equipo']; ?>"><?php echo htmlspecialchars($t['nombre_tipo'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Tipo de activo
              <select name="tipo_activo" required>
                <?php foreach ($tiposActivo as $ta): ?>
                  <option value="<?php echo (int)$ta['id_tipo_activo']; ?>"><?php echo htmlspecialchars($ta['descripcion_tp_activo'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Estado de equipo
              <select name="estado_equipo" required>
                <?php foreach ($estadosEquipo as $e): ?>
                  <option value="<?php echo (int)$e['id_estado_equipo']; ?>"><?php echo htmlspecialchars($e['descripcion_estado_equipo'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Estado de activo
              <select name="estado_activo" required>
                <?php foreach ($estadosActivo as $ea): ?>
                  <option value="<?php echo (int)$ea['id_estado_activo']; ?>"><?php echo htmlspecialchars($ea['descripcion_estado_activo'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Modelo
              <input type="text" name="modelo" required>
            </label>
            <label>
              Marca
              <input type="text" name="marca" required>
            </label>
            <label>
              Procesador
              <input type="text" name="procesador" placeholder="CPU / generación">
            </label>
            <label>
              Hostname
              <input type="text" name="hostname" placeholder="Nombre en red">
            </label>
            <label>
              RAM
              <input type="number" name="ram" min="0" step="1" placeholder="GB">
            </label>
            <label>
              Disco
              <input type="number" name="disco" min="0" step="1" placeholder="GB">
            </label>
            <label>
              Serie
              <input type="text" name="serie" required>
            </label>
            <label>
              Precio
              <input type="number" step="0.01" name="precio" required>
            </label>
            <label>
              IVA (%)
              <input type="number" step="0.01" name="iva" value="15" required>
            </label>
            <label>
              Vida útil (años)
              <input type="number" name="vida_util" value="3" min="1" step="1">
            </label>
            <label>
              Fecha de adquisición
              <input type="date" name="fecha_adq" value="<?php echo date('Y-m-d'); ?>">
            </label>
            <label>
              Nº factura
              <input type="text" name="num_factura" placeholder="N/A">
            </label>
          </div>
          <div class="actions full" style="margin-top:12px;">
            <button type="reset" class="btn-secondary">Limpiar</button>
            <button type="submit" class="btn">Guardar equipo</button>
          </div>
        </form>

        <div class="table-scroll" style="margin-top:14px;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Modelo</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Estado activo</th>
                <th>Serie</th>
                <th>Procesador</th>
                <th>Precio</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$equipos): ?>
                <tr><td colspan="9" class="empty">Sin equipos</td></tr>
              <?php else: ?>
                <?php foreach ($equipos as $eq): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($eq['id_equipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($eq['modelo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($eq['tipo_equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($eq['estado_equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($eq['estado_activo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($eq['serie'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($eq['procesador'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo number_format((float)($eq['total'] ?? 0), 2); ?></td>
                    <td>
                      <a class="btn-secondary" href="equipo-editar.php?id=<?php echo urlencode($eq['id_equipo']); ?>">Editar</a>
                    </td>
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
