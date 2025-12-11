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

function redirectEquipos(array $params = []): void
{
    $base = 'admin-equipos.php';
    header('Location: ' . $base . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    redirectEquipos(['eq_msg' => 'ID de equipo faltante']);
}

$tiposEquipo = $pdo->query("SELECT id_tp_equipo AS id_tipo_equipo, descripcion_tp_equipo AS nombre_tipo FROM tipo_equipo ORDER BY descripcion_tp_equipo")->fetchAll();
$estadosEquipo = $pdo->query("SELECT id_estado_equipo, descripcion_estado_equipo FROM estado_equipo ORDER BY descripcion_estado_equipo")->fetchAll();
$tiposActivo = $pdo->query("SELECT id_tipo_activo, descripcion_tp_activo FROM tipo_activo ORDER BY descripcion_tp_activo")->fetchAll();
$estadosActivo = $pdo->query("SELECT id_estado_activo, descripcion_estado_activo FROM estado_activo ORDER BY descripcion_estado_activo")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM equipo WHERE id_equipo = :id");
$stmt->execute([':id' => $id]);
$equipo = $stmt->fetch();
if (!$equipo) {
    redirectEquipos(['eq_msg' => 'Equipo no encontrado']);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_equipo_edit'])) {
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

    if ($tipoEquipo <= 0 || $estadoEquipo <= 0 || $modelo === '' || $marca === '' || $serie === '') {
        $message = 'Tipo, estado, modelo, marca y serie son obligatorios';
        // Mantener datos ingresados para que el usuario no los pierda
        $equipo['id_tp_equipo'] = $tipoEquipo;
        $equipo['id_tipo_activo'] = $tipoActivo;
        $equipo['id_estado_equipo'] = $estadoEquipo;
        $equipo['id_estado_activo'] = $estadoActivo;
        $equipo['modelo'] = $modelo;
        $equipo['marca'] = $marca;
        $equipo['procesador'] = $procesador;
        $equipo['hostname'] = $hostname;
        $equipo['memoria_ram'] = $ram;
        $equipo['almacenamiento'] = $disco;
        $equipo['num_serie'] = $serie;
        $equipo['precio'] = $precio;
        $equipo['iva'] = $iva;
        $equipo['tiempo_vida_util'] = $vidaUtil;
        $equipo['fecha_adquisicion'] = $fechaAdq;
        $equipo['num_factura'] = $numFactura;
    } else {
        $precioTotal = round($precio + $precio * ($iva / 100), 2);
        $upd = $pdo->prepare("
            UPDATE equipo SET
                marca = :marca,
                modelo = :modelo,
                procesador = :procesador,
                num_serie = :serie,
                memoria_ram = :ram,
                almacenamiento = :disco,
                hostname = :hostname,
                precio = :precio,
                iva = :iva,
                total = :total,
                tiempo_vida_util = :vida_util,
                fecha_adquisicion = :fecha_adq,
                num_factura = :num_factura,
                id_estado_activo = :estado_activo,
                id_estado_equipo = :estado_equipo,
                id_tp_equipo = :tipo_equipo,
                id_tipo_activo = :tipo_activo
            WHERE id_equipo = :id_equipo
        ");
        $upd->execute([
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':procesador' => $procesador,
            ':serie' => $serie,
            ':ram' => $ram,
            ':disco' => $disco,
            ':hostname' => $hostname,
            ':precio' => $precio,
            ':iva' => $iva,
            ':total' => $precioTotal,
            ':vida_util' => $vidaUtil,
            ':fecha_adq' => $fechaAdq,
            ':num_factura' => $numFactura === '' ? 'N/A' : $numFactura,
            ':estado_activo' => $estadoActivo > 0 ? $estadoActivo : $estadoEquipo,
            ':estado_equipo' => $estadoEquipo,
            ':tipo_equipo' => $tipoEquipo,
            ':tipo_activo' => $tipoActivo > 0 ? $tipoActivo : 1,
            ':id_equipo' => $id,
        ]);
        redirectEquipos(['eq_msg' => 'Equipo actualizado']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar equipo</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Edicion</div>
        <div>
          <h1>Editar equipo <?php echo htmlspecialchars($equipo['id_equipo'], ENT_QUOTES, 'UTF-8'); ?></h1>
          <p>Actualiza datos en caso de error de captura.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Datos del equipo</h2>
        <p class="card-subtitle <?php echo $message ? '' : 'muted'; ?>">
          <?php echo htmlspecialchars($message ?: 'Modifica los campos necesarios y guarda.', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <form method="post">
          <input type="hidden" name="form_equipo_edit" value="1">
          <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
            <label>
              ID Equipo
              <input type="text" name="id_equipo" value="<?php echo htmlspecialchars($equipo['id_equipo'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </label>
            <label>
              Tipo de equipo
              <select name="tipo_equipo" required>
                <?php foreach ($tiposEquipo as $t): ?>
                  <option value="<?php echo (int)$t['id_tipo_equipo']; ?>" <?php echo (int)$equipo['id_tp_equipo'] === (int)$t['id_tipo_equipo'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['nombre_tipo'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Tipo de activo
              <select name="tipo_activo" required>
                <?php foreach ($tiposActivo as $ta): ?>
                  <option value="<?php echo (int)$ta['id_tipo_activo']; ?>" <?php echo (int)$equipo['id_tipo_activo'] === (int)$ta['id_tipo_activo'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ta['descripcion_tp_activo'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Estado de equipo
              <select name="estado_equipo" required>
                <?php foreach ($estadosEquipo as $e): ?>
                  <option value="<?php echo (int)$e['id_estado_equipo']; ?>" <?php echo (int)$equipo['id_estado_equipo'] === (int)$e['id_estado_equipo'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['descripcion_estado_equipo'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Estado de activo
              <select name="estado_activo" required>
                <?php foreach ($estadosActivo as $ea): ?>
                  <option value="<?php echo (int)$ea['id_estado_activo']; ?>" <?php echo (int)$equipo['id_estado_activo'] === (int)$ea['id_estado_activo'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ea['descripcion_estado_activo'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Modelo
              <input type="text" name="modelo" required value="<?php echo htmlspecialchars($equipo['modelo'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Marca
              <input type="text" name="marca" required value="<?php echo htmlspecialchars($equipo['marca'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Procesador
              <input type="text" name="procesador" value="<?php echo htmlspecialchars($equipo['procesador'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Hostname
              <input type="text" name="hostname" value="<?php echo htmlspecialchars($equipo['hostname'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              RAM
              <input type="number" name="ram" min="0" step="1" placeholder="GB" value="<?php echo (int)$equipo['memoria_ram']; ?>">
            </label>
            <label>
              Disco
              <input type="number" name="disco" min="0" step="1" placeholder="GB" value="<?php echo (int)$equipo['almacenamiento']; ?>">
            </label>
            <label>
              Serie
              <input type="text" name="serie" required value="<?php echo htmlspecialchars($equipo['num_serie'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Precio
              <input type="number" step="0.01" name="precio" required value="<?php echo htmlspecialchars($equipo['precio'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              IVA (%)
              <input type="number" step="0.01" name="iva" required value="<?php echo htmlspecialchars($equipo['iva'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Vida util (anios)
              <input type="number" name="vida_util" value="<?php echo (int)$equipo['tiempo_vida_util']; ?>" min="1" step="1">
            </label>
            <label>
              Fecha de adquisicion
              <input type="date" name="fecha_adq" value="<?php echo htmlspecialchars(substr((string)$equipo['fecha_adquisicion'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Num. factura
              <input type="text" name="num_factura" value="<?php echo htmlspecialchars($equipo['num_factura'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
          </div>
          <div class="actions full" style="margin-top:12px;">
            <a class="btn-secondary" href="admin-equipos.php">Cancelar</a>
            <button type="submit" class="btn">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
