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
$messageMueble = '';

function redirectMuebles(array $params = []): void
{
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_mueble'])) {
    $tipoMueble = (int)($_POST['tipo_mueble'] ?? 0);
    $tipoActivo = (int)($_POST['tipo_activo'] ?? 0);
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
    $dimensiones = trim($_POST['dimensiones'] ?? '');
    $origen = trim($_POST['origen'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $iva = (float)($_POST['iva'] ?? 0);
    $vidaUtil = (int)($_POST['vida_util'] ?? 3);
    $fechaAdq = $_POST['fecha_adq'] ?? date('Y-m-d');
    $numFactura = trim($_POST['num_factura'] ?? 'N/A');
    $idMueble = trim($_POST['id_mueble'] ?? '') ?: uniqid('MB-');

    if ($tipoMueble <= 0 || $tipoActivo <= 0 || $marca === '' || $modelo === '') {
        $messageMueble = 'Tipo de mueble, tipo de activo, marca y modelo son obligatorios';
    } else {
        $total = round($precio + $precio * ($iva / 100), 2);
        $ins = $pdo->prepare("
            INSERT INTO muebles_enseres (
                id_mueble_enseres, id_tp_mueble, fecha_adquision, precio, iva, total_muebles_enseres,
                tiempo_vida_util, num_factura, id_tipo_activo, Cantidad, Dimensiones, OrigenBien, Proveedor,
                marca, modelo, descripcion
            ) VALUES (
                :id_mueble, :id_tp_mueble, :fecha_adq, :precio, :iva, :total,
                :vida_util, :num_factura, :tipo_activo, :cantidad, :dimensiones, :origen, :proveedor,
                :marca, :modelo, :descripcion
            )
        ");
        $ins->execute([
            ':id_mueble' => $idMueble,
            ':id_tp_mueble' => $tipoMueble,
            ':fecha_adq' => $fechaAdq,
            ':precio' => $precio,
            ':iva' => $iva,
            ':total' => $total,
            ':vida_util' => $vidaUtil,
            ':num_factura' => $numFactura === '' ? 'N/A' : $numFactura,
            ':tipo_activo' => $tipoActivo,
            ':cantidad' => $cantidad,
            ':dimensiones' => $dimensiones,
            ':origen' => $origen,
            ':proveedor' => $proveedor,
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':descripcion' => $descripcion
        ]);
        redirectMuebles(['mb_msg' => 'Mueble/Enser ingresado']);
    }
}

$tiposMueble = $pdo->query("SELECT id_tp_mueble, descripcion_tp_mueble FROM tipo_mueble ORDER BY descripcion_tp_mueble")->fetchAll();
$tiposActivo = $pdo->query("SELECT id_tipo_activo, descripcion_tp_activo FROM tipo_activo ORDER BY descripcion_tp_activo")->fetchAll();
$muebles = $pdo->query("
    SELECT m.id_mueble_enseres, m.marca, m.modelo, m.descripcion, m.Cantidad AS cantidad, m.Dimensiones AS dimensiones,
           m.OrigenBien AS origen, m.Proveedor AS proveedor, m.total_muebles_enseres AS total,
           m.fecha_adquision, m.tiempo_vida_util, tm.descripcion_tp_mueble AS tipo_mueble,
           ta.descripcion_tp_activo AS tipo_activo
    FROM muebles_enseres m
    INNER JOIN tipo_mueble tm ON tm.id_tp_mueble = m.id_tp_mueble
    INNER JOIN tipo_activo ta ON ta.id_tipo_activo = m.id_tipo_activo
    ORDER BY m.id_mueble_enseres DESC
")->fetchAll();

if (isset($_GET['mb_msg'])) {
    $messageMueble = $_GET['mb_msg'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración | Muebles y enseres</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Panel administrador</div>
        <div>
          <h1>Ingreso de muebles y enseres</h1>
          <p>Registra mobiliario con su tipo, proveedor y vida útil.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Nuevo mueble/enser</h2>
        <p class="card-subtitle <?php echo $messageMueble ? '' : 'muted'; ?>">
          <?php echo htmlspecialchars($messageMueble ?: 'Completa los datos requeridos.', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <form method="post">
          <input type="hidden" name="form_mueble" value="1">
          <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
            <label>
              ID Mueble/Enser
              <input type="text" name="id_mueble" placeholder="Se autogenera si se deja vacío">
            </label>
            <label>
              Tipo de mueble
              <select name="tipo_mueble" required>
                <?php foreach ($tiposMueble as $tm): ?>
                  <option value="<?php echo (int)$tm['id_tp_mueble']; ?>"><?php echo htmlspecialchars($tm['descripcion_tp_mueble'], ENT_QUOTES, 'UTF-8'); ?></option>
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
              Marca
              <input type="text" name="marca" required>
            </label>
            <label>
              Modelo
              <input type="text" name="modelo" required>
            </label>
            <label>
              Descripción
              <textarea name="descripcion" placeholder="Detalle del bien" style="min-height:72px;"></textarea>
            </label>
            <label>
              Cantidad
              <input type="number" name="cantidad" min="1" step="1" value="1">
            </label>
            <label>
              Dimensiones
              <input type="text" name="dimensiones" placeholder="Alto x Ancho x Profundidad">
            </label>
            <label>
              Origen del bien
              <input type="text" name="origen" placeholder="Compra / Donación">
            </label>
            <label>
              Proveedor
              <input type="text" name="proveedor" placeholder="Empresa proveedora">
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
            <button type="submit" class="btn">Guardar mueble/enser</button>
          </div>
        </form>

        <div class="table-scroll" style="margin-top:14px;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Origen</th>
                <th>Total</th>
                <th>Fecha adq</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$muebles): ?>
                <tr><td colspan="9" class="empty">Sin muebles registrados</td></tr>
              <?php else: ?>
                <?php foreach ($muebles as $mb): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($mb['id_mueble_enseres'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($mb['tipo_mueble'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($mb['marca'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($mb['modelo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="muted"><?php echo htmlspecialchars($mb['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int)($mb['cantidad'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($mb['origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo number_format((float)($mb['total'] ?? 0), 2); ?></td>
                    <td><?php echo htmlspecialchars(substr((string)$mb['fecha_adquision'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
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
